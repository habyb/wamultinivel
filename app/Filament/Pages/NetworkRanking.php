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

    protected static ?string $navigationIcon  = 'heroicon-o-arrow-trending-up';
    protected static ?string $navigationGroup = 'Relatórios';
    protected static ?int    $navigationSort  = 2;
    protected static string  $view            = 'filament.pages.network-ranking';

    /** Dynamic week ranges (Mon–Sun) */
    protected ?CarbonImmutable $currWeekStart = null;
    protected ?CarbonImmutable $currWeekEnd   = null;
    protected ?CarbonImmutable $prevWeekStart = null;
    protected ?CarbonImmutable $prevWeekEnd   = null;

    /** Request-lifetime cache for per-user weekly network counts */
    protected array $networkWeeklyCache = [];

    protected int|string|array $defaultTableRecordsPerPage = 10;
    protected array $tableRecordsPerPageSelectOptions = [10, 25, 50, 100];

    /**
     * Lazy-init ranges so Livewire rehydrations (e.g., per-page change) are safe.
     * SECURITY (EN): Uses app timezone and immutable instances.
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
     * Count weekly NETWORK (all levels) for a given root user between [$start, $end].
     *
     * FIX (EN):
     * - Detects FK and owner key from the existing `firstLevelGuests()` relation on User,
     *   so it works whether your schema uses `referrer_id`, `inviter_code`, etc.
     * - Joins using child's FK to parent's OWNER KEY (not always the numeric `id`).
     * - Seeds the recursion with the ROOT owner's key value to avoid type mismatch
     *   (e.g., varchar codes vs bigint ids) on PostgreSQL.
     *
     * PERFORMANCE (EN):
     * - Uses a recursive CTE once per user per range, cached in-memory for the request.
     */
    protected function countWeeklyNetwork(int $rootUserId, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $cacheKey = $rootUserId . '|' . $start->toDateString() . '|' . $end->toDateString();
        if (isset($this->networkWeeklyCache[$cacheKey])) {
            return $this->networkWeeklyCache[$cacheKey];
        }

        $user       = new User();
        $table      = $user->getTable();
        $relation   = $user->firstLevelGuests();            // HasMany relation (child -> parent)
        $fkCol      = $relation->getForeignKeyName();       // child column referencing parent owner key
        $ownerKey   = $relation->getLocalKeyName();         // parent owner key column

        // Owner key value for the ROOT user (can be id, code, etc.)
        $rootOwnerVal = DB::table($table)->where('id', $rootUserId)->value($ownerKey);

        // Wrap identifiers for current grammar (Postgres-safe).
        $grammar         = DB::getQueryGrammar();
        $wrappedTable    = $grammar->wrap($table);
        $wrappedFkCol    = $grammar->wrap($fkCol);
        $wrappedOwnerKey = $grammar->wrap($ownerKey);
        $wrappedCreated  = $grammar->wrap('created_at');

        // Recursive adjacency via <child.$fkCol = parent.$ownerKey>
        $sql = <<<SQL
            WITH RECURSIVE subtree AS (
                SELECT u.id, u.{$wrappedOwnerKey} AS owner_key
                FROM {$wrappedTable} u
                WHERE u.{$wrappedFkCol} = :root_owner_val

                UNION ALL

                SELECT c.id, c.{$wrappedOwnerKey} AS owner_key
                FROM {$wrappedTable} c
                INNER JOIN subtree s ON c.{$wrappedFkCol} = s.owner_key
            )
            SELECT COUNT(*) AS c
            FROM {$wrappedTable} x
            WHERE x.id IN (SELECT id FROM subtree)
              AND x.{$wrappedCreated} BETWEEN :start_at AND :end_at
        SQL;

        $row = DB::selectOne($sql, [
            'root_owner_val' => $rootOwnerVal,
            'start_at'       => $start->toDateTimeString(),
            'end_at'         => $end->toDateTimeString(),
        ]);

        $count = (int) ($row->c ?? 0);
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
                    // Only show users who had >= 1 direct member in the CURRENT week
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
                    // REDE (all levels) registered in CURRENT week
                    TextColumn::make('network_w1')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countWeeklyNetwork((int) $record->id, $this->currWeekStart, $this->currWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    // DIRECT members registered in CURRENT week
                    TextColumn::make('members_w1')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                ColumnGroup::make($prevRange, [
                    // REDE (all levels) registered in PREVIOUS week
                    TextColumn::make('network_w0')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countWeeklyNetwork((int) $record->id, $this->prevWeekStart, $this->prevWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    // DIRECT members registered in PREVIOUS week
                    TextColumn::make('members_w0')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                // Growth based on REDE weekly counts
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
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

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

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['Superadmin']);
    }
}
