<?php
// app/Filament/Pages/NetworkRanking.php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class NetworkRanking extends Page implements HasTable
{
    use InteractsWithTable;

    /** Navigation */
    protected static ?string $navigationIcon  = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.network-ranking';

    /** Week ranges (Mon–Sun) dynamically computed from "today" */
    protected ?CarbonImmutable $currWeekStart = null;
    protected ?CarbonImmutable $currWeekEnd   = null;
    protected ?CarbonImmutable $prevWeekStart = null;
    protected ?CarbonImmutable $prevWeekEnd   = null;

    /** In-memory cache to avoid duplicate queries per row */
    protected array $networkWeeklyCache = [];

    /** Paging defaults */
    protected int|string|array $defaultTableRecordsPerPage = 10;
    protected array $tableRecordsPerPageSelectOptions = [10, 25, 50, 100];

    /**
     * Lazy init of week ranges to survive Livewire rehydrations.
     *
     * SECURITY (EN): Immutable dates + app timezone ensure deterministic ranges.
     */
    protected function initWeekRanges(): void
    {
        if ($this->currWeekStart !== null) {
            return;
        }

        $now = CarbonImmutable::now(config('app.timezone'));

        // Last completed Sunday (<= now)
        $lastSunday = $now->endOfWeek(CarbonImmutable::SUNDAY);
        if ($lastSunday->greaterThan($now)) {
            $lastSunday = $lastSunday->subWeek();
        }

        $this->currWeekEnd   = $lastSunday->endOfDay();
        $this->currWeekStart = $this->currWeekEnd->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();

        $this->prevWeekEnd   = $this->currWeekStart->subDay()->endOfDay();
        $this->prevWeekStart = $this->prevWeekEnd->startOfWeek(CarbonImmutable::MONDAY)->startOfDay();
    }

    public static function getNavigationLabel(): string
    {
        return 'Ranking Rede';
    }

    public function getTitle(): string
    {
        return 'Ranking Rede';
    }

    /** Helper "DD/MM a DD/MM" */
    protected function rangeLabel(CarbonImmutable $start, CarbonImmutable $end): string
    {
        return $start->format('d/m') . ' a ' . $end->format('d/m');
    }

    /**
     * Count weekly NETWORK registrations for a given root user (all levels).
     *
     * FIX (EN): We no longer hardcode the FK name. Instead, we read the FK used
     * by the `firstLevelGuests()` relation on the User model, so it matches your
     * actual schema (e.g., postgres column may not be `referrer_guest_id`).
     *
     * PERFORMANCE (EN):
     * - One recursive CTE per row; results cached for the request lifecycle.
     * - Uses query grammar wrapping to be safe on Postgres/MySQL quoting.
     *
     * SECURITY (EN):
     * - Only values are bound as parameters; identifiers are strictly derived
     *   from the Eloquent relation and wrapped by the grammar to prevent
     *   injection.
     */
    protected function countWeeklyNetwork(int $rootUserId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $cacheKey = $rootUserId . '|' . $start->toDateString() . '|' . $end->toDateString();

        if (array_key_exists($cacheKey, $this->networkWeeklyCache)) {
            return $this->networkWeeklyCache[$cacheKey];
        }

        // Discover table & FK from the existing relation to avoid guessing names.
        $userModel   = new User();
        $table       = $userModel->getTable();
        $relation    = $userModel->firstLevelGuests(); // must exist on User model
        $qualifiedFk = $relation->getQualifiedForeignKeyName(); // e.g. "users.referrer_id" or "guests.parent_id"

        // Extract plain column name from "table.column".
        $fkParts = explode('.', $qualifiedFk);
        $fkCol   = end($fkParts);

        // Properly wrap identifiers for the current connection/grammar (Postgres-safe).
        $grammar        = DB::getQueryGrammar();
        $wrappedTable   = $grammar->wrap($table);      // -> e.g. "users"
        $wrappedFkCol   = $grammar->wrap($fkCol);      // -> e.g. "referrer_id"
        $wrappedCreated = $grammar->wrap('created_at'); // -> e.g. "created_at"

        // Build the recursive CTE using the discovered FK.
        $sql = <<<SQL
            WITH RECURSIVE subtree AS (
                SELECT u.id
                FROM {$wrappedTable} u
                WHERE u.{$wrappedFkCol} = :root_id

                UNION ALL

                SELECT c.id
                FROM {$wrappedTable} c
                INNER JOIN subtree s ON c.{$wrappedFkCol} = s.id
            )
            SELECT COUNT(*) AS c
            FROM {$wrappedTable} x
            WHERE x.id IN (SELECT id FROM subtree)
              AND x.{$wrappedCreated} BETWEEN :start_at AND :end_at
        SQL;

        $row = DB::selectOne($sql, [
            'root_id'  => $rootUserId,
            'start_at' => $start->toDateTimeString(),
            'end_at'   => $end->toDateTimeString(),
        ]);

        $count = (int) (($row->c ?? 0));

        $this->networkWeeklyCache[$cacheKey] = $count;

        return $count;
    }

    public function table(Table $table): Table
    {
        $this->initWeekRanges();

        $currRange = $this->rangeLabel($this->currWeekStart, $this->currWeekEnd);
        $prevRange = $this->rangeLabel($this->prevWeekStart, $this->prevWeekEnd);

        return $table
            ->query(
                auth()->user()
                    ->completedRegistrationsQuery()
                    ->withCount([
                        // Direct members registered INSIDE each week window (not cumulative).
                        'firstLevelGuests as members_w1' => fn ($q) =>
                            $q->whereBetween('created_at', [
                                $this->currWeekStart->toDateTimeString(),
                                $this->currWeekEnd->toDateTimeString(),
                            ]),
                        'firstLevelGuests as members_w0' => fn ($q) =>
                            $q->whereBetween('created_at', [
                                $this->prevWeekStart->toDateTimeString(),
                                $this->prevWeekEnd->toDateTimeString(),
                            ]),
                    ])
                    // Only show users who had >= 1 direct member in the current week
                    ->whereHas('firstLevelGuests', fn ($q) =>
                        $q->whereBetween('created_at', [
                            $this->currWeekStart->toDateTimeString(),
                            $this->currWeekEnd->toDateTimeString(),
                        ])
                    )
            )
            ->columns([
                TextColumn::make('posicao')
                    ->label('Colocação')
                    ->rowIndex(isFromZero: false)
                    ->formatStateUsing(fn ($state) => "{$state}º")
                    ->alignment('left'),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                ColumnGroup::make($currRange, [
                    // REDE (all levels) registered in the CURRENT week
                    TextColumn::make('network_w1')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countWeeklyNetwork((int) $record->id, $this->currWeekStart, $this->currWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    // DIRECT MEMBERS registered in the CURRENT week
                    TextColumn::make('members_w1')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                ColumnGroup::make($prevRange, [
                    // REDE (all levels) registered in the PREVIOUS week
                    TextColumn::make('network_w0')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countWeeklyNetwork((int) $record->id, $this->prevWeekStart, $this->prevWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    // DIRECT MEMBERS registered in the PREVIOUS week
                    TextColumn::make('members_w0')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                // Growth column (based on REDE weekly counts)
                TextColumn::make('growth_pct')
                    ->label('Crescimento (?)')
                    ->extraHeaderAttributes([
                        'title' => 'Percentual de Crescimento em Relação à Semana Anterior (REDE)',
                        'class' => 'whitespace-nowrap',
                    ])
                    ->state(function ($record): string {
                        $w1 = $this->countWeeklyNetwork((int) $record->id, $this->currWeekStart, $this->currWeekEnd);
                        $w0 = $this->countWeeklyNetwork((int) $record->id, $this->prevWeekStart, $this->prevWeekEnd);

                        if ($w0 === 0) {
                            return $w1 > 0 ? '∞%' : '0%';
                        }

                        $pct = (($w1 - $w0) / $w0) * 100;
                        return number_format($pct, 0, ',', '.') . '%';
                    })
                    ->badge()
                    ->alignment('right')
                    ->color(function ($record): string {
                        $w1 = $this->countWeeklyNetwork((int) $record->id, $this->currWeekStart, $this->currWeekEnd);
                        $w0 = $this->countWeeklyNetwork((int) $record->id, $this->prevWeekStart, $this->prevWeekEnd);

                        if ($w0 === 0 && $w1 > 0) {
                            return 'warning';
                        }

                        $delta = $w1 - $w0;
                        return $delta < 0 ? 'danger' : ($delta === 0 ? 'gray' : 'success');
                    }),
            ])
            // Sorting by computed (per-row) values is disabled to keep the query efficient.
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /** Labels used in the Blade header */
    public function getCurrentRangeLabel(): string
    {
        $this->initWeekRanges();
        return $this->rangeLabel($this->currWeekStart, $this->currWeekEnd);
    }

    public function getPreviousRangeLabel(): string
    {
        $this->initWeekRanges();
        return $this->rangeLabel($this->prevWeekStart, $this->prevWeekEnd);
    }

    /** Access restriction */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin']);
    }
}
