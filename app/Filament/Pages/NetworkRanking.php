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

    /** Week ranges (Mon–Sun) */
    protected ?CarbonImmutable $currWeekStart = null;
    protected ?CarbonImmutable $currWeekEnd   = null;
    protected ?CarbonImmutable $prevWeekStart = null;
    protected ?CarbonImmutable $prevWeekEnd   = null;

    /** Request-lifetime cache (per user per cutoff) for cumulative network size */
    protected array $networkUntilCache = [];

    protected int|string|array $defaultTableRecordsPerPage = 10;
    protected array $tableRecordsPerPageSelectOptions = [10, 25, 50, 100];

    /**
     * Lazy-init ranges so Livewire rehydrations are safe.
     *
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
     * Count cumulative NETWORK (all levels) up to a cutoff ($until) for a given root user.
     *
     * WHAT IT DOES (EN):
     * - Builds the entire subtree (all levels) using the real FK/owner key from
     *   User::firstLevelGuests() to match your schema types (id vs code).
     * - Counts ONLY nodes whose created_at <= $until (cumulative).
     *
     * PERFORMANCE (EN):
     * - One recursive CTE per (user, cutoff) and memoized in-memory.
     */
    protected function countNetworkUntil(int $rootUserId, CarbonImmutable $until): int
    {
        $cacheKey = $rootUserId . '|<=|' . $until->toDateTimeString();
        if (isset($this->networkUntilCache[$cacheKey])) {
            return $this->networkUntilCache[$cacheKey];
        }

        $user       = new User();
        $table      = $user->getTable();
        $relation   = $user->firstLevelGuests();     // HasMany
        $fkCol      = $relation->getForeignKeyName();   // child column -> parent owner
        $ownerKey   = $relation->getLocalKeyName();     // parent owner key (may be id or code)

        // Owner key value for ROOT user (keeps types consistent on PostgreSQL)
        $rootOwnerVal = DB::table($table)->where('id', $rootUserId)->value($ownerKey);

        // Wrap identifiers safely
        $grammar         = DB::getQueryGrammar();
        $wrappedTable    = $grammar->wrap($table);
        $wrappedFkCol    = $grammar->wrap($fkCol);
        $wrappedOwnerKey = $grammar->wrap($ownerKey);
        $wrappedCreated  = $grammar->wrap('created_at');

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
              AND x.{$wrappedCreated} <= :until_at
        SQL;

        $row = DB::selectOne($sql, [
            'root_owner_val' => $rootOwnerVal,
            'until_at'       => $until->toDateTimeString(),
        ]);

        $count = (int) ($row->c ?? 0);
        $this->networkUntilCache[$cacheKey] = $count;

        return $count;
    }

    public function table(Table $table): Table
    {
        $this->initWeekRanges();

        $currRange = $this->rangeLabel($this->currWeekStart, $this->currWeekEnd); // ex.: "08/07 a 14/07"
        $prevRange = $this->rangeLabel($this->prevWeekStart, $this->prevWeekEnd); // ex.: "01/07 a 07/07"

        return $table
            ->query(
                auth()->user()
                    ->completedRegistrationsQuery()
                    ->withCount([
                        // DIRECT members cumulative up to each week's END (<=)
                        'firstLevelGuests as members_w1' => fn ($q) =>
                            $q->where('created_at', '<=', $this->currWeekEnd),
                        'firstLevelGuests as members_w0' => fn ($q) =>
                            $q->where('created_at', '<=', $this->prevWeekEnd),
                    ])
                    // Show only who has at least 1 direct member up to CURRENT week end
                    ->whereHas('firstLevelGuests', fn ($q) =>
                        $q->where('created_at', '<=', $this->currWeekEnd)
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

                // GROUP: Current week (cumulative totals up to Sun)
                ColumnGroup::make($currRange, [
                    TextColumn::make('network_w1')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countNetworkUntil((int) $record->id, $this->currWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    TextColumn::make('members_w1')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                // GROUP: Previous week (cumulative totals up to Sun)
                ColumnGroup::make($prevRange, [
                    TextColumn::make('network_w0')
                        ->label('Rede')
                        ->state(fn ($record): int =>
                            $this->countNetworkUntil((int) $record->id, $this->prevWeekEnd)
                        )
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 50 ? 'primary' : 'success'))
                        ->alignment('right'),

                    TextColumn::make('members_w0')
                        ->label('Membros')
                        ->numeric(thousandsSeparator: '.', decimalPlaces: 0)
                        ->badge()
                        ->color(fn (int $state): string => $state === 0 ? 'gray' : ($state <= 25 ? 'primary' : 'success'))
                        ->alignment('right'),
                ]),

                // Growth % based on cumulative "Rede" (current vs previous week end)
                TextColumn::make('growth_pct')
                    ->label('Crescimento (?)')
                    ->extraHeaderAttributes([
                        'title' => 'Percentual de Crescimento em Relação à Semana Anterior (REDE)',
                        'class' => 'whitespace-nowrap',
                    ])
                    ->state(function ($record): string {
                        $nw1 = $this->countNetworkUntil((int) $record->id, $this->currWeekEnd);
                        $nw0 = $this->countNetworkUntil((int) $record->id, $this->prevWeekEnd);

                        // SAFETY (EN): Avoid divide-by-zero; show infinity symbol for growth from zero.
                        if ($nw0 === 0) {
                            return $nw1 > 0 ? '∞%' : '0%';
                        }

                        $pct = (($nw1 - $nw0) / $nw0) * 100;
                        return number_format($pct, 0, ',', '.') . '%';
                    })
                    ->badge()
                    ->alignment('right')
                    ->color(function ($record): string {
                        $nw1 = $this->countNetworkUntil((int) $record->id, $this->currWeekEnd);
                        $nw0 = $this->countNetworkUntil((int) $record->id, $this->prevWeekEnd);

                        if ($nw0 === 0 && $nw1 > 0) {
                            return 'warning';
                        }

                        $delta = $nw1 - $nw0;
                        return $delta < 0 ? 'danger' : ($delta === 0 ? 'gray' : 'success');
                    }),
            ])
            // Sorting by computed (per-row) values is intentionally disabled (SQL efficiency).
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
