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
use Filament\Tables\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
     * Detect the "completed registration" boolean flag used by UsersRegistrationCompleted.
     *
     * SAFETY (EN):
     * - We try common column names; if none exists, we do not filter (fallback).
     */
    protected function detectCompletedColumn(string $table): ?string
    {
        foreach (['is_add_date_of_birth', 'is_completed', 'registration_completed'] as $col) {
            if (Schema::hasColumn($table, $col)) {
                return $col;
            }
        }

        return null;
    }

    /**
     * Count cumulative NETWORK (all levels) up to a cutoff ($until) for a given root user,
     * filtering ONLY completed registrations to match "Cadastros Completos".
     *
     * PERFORMANCE (EN):
     * - One recursive CTE per (user, cutoff) memoized in-memory.
     */
    protected function countNetworkUntil(int $rootUserId, CarbonImmutable $until): int
    {
        $cacheKey = $rootUserId . '|<=|' . $until->toDateTimeString();
        if (isset($this->networkUntilCache[$cacheKey])) {
            return $this->networkUntilCache[$cacheKey];
        }

        $user       = new User();
        $table      = $user->getTable();
        $relation   = $user->firstLevelGuests();         // HasMany
        $fkCol      = $relation->getForeignKeyName();    // child column -> parent owner
        $ownerKey   = $relation->getLocalKeyName();      // parent owner key (may be id or code)

        $completedCol = $this->detectCompletedColumn($table);

        // Owner key value for ROOT user (keeps types consistent on PostgreSQL)
        $rootOwnerVal = DB::table($table)->where('id', $rootUserId)->value($ownerKey);

        // Wrap identifiers safely
        $grammar          = DB::getQueryGrammar();
        $wrappedTable     = $grammar->wrap($table);
        $wrappedFkCol     = $grammar->wrap($fkCol);
        $wrappedOwnerKey  = $grammar->wrap($ownerKey);
        $wrappedCreated   = $grammar->wrap('created_at');
        $wrappedCompleted = $completedCol ? $grammar->wrap($completedCol) : null;

        $completedFilter = $wrappedCompleted ? " AND x.{$wrappedCompleted} = true" : '';

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
              {$completedFilter}
        SQL;

        $row = DB::selectOne($sql, [
            'root_owner_val' => $rootOwnerVal,
            'until_at'       => $until->toDateTimeString(),
        ]);

        $count = (int) ($row->c ?? 0);
        $this->networkUntilCache[$cacheKey] = $count;

        return $count;
    }

    /**
     * ORDER BY cumulative network up to current week end, DESC.
     *
     * IMPORTANT (EN):
     * - Uses the same "completed registration" filter to keep ordering consistent
     *   with the visible numbers and the "Cadastros Completos" page.
     */
    protected function orderByNetworkCurrentWeekDesc(Builder $query): Builder
    {
        $user       = new User();
        $table      = $user->getTable();
        $relation   = $user->firstLevelGuests();
        $fkCol      = $relation->getForeignKeyName();
        $ownerKey   = $relation->getLocalKeyName();

        $completedCol = $this->detectCompletedColumn($table);

        $grammar          = DB::getQueryGrammar();
        $wrappedTable     = $grammar->wrap($table);
        $wrappedFkCol     = $grammar->wrap($fkCol);
        $wrappedOwnerKey  = $grammar->wrap($ownerKey);
        $wrappedCreated   = $grammar->wrap('created_at');
        $wrappedCompleted = $completedCol ? $grammar->wrap($completedCol) : null;

        $completedFilter = $wrappedCompleted ? " AND x.{$wrappedCompleted} = true" : '';

        $expr = <<<SQL
            (
                WITH RECURSIVE subtree AS (
                    SELECT u.id, u.{$wrappedOwnerKey} AS owner_key
                    FROM {$wrappedTable} u
                    WHERE u.{$wrappedFkCol} = {$wrappedTable}.{$wrappedOwnerKey}

                    UNION ALL

                    SELECT c.id, c.{$wrappedOwnerKey} AS owner_key
                    FROM {$wrappedTable} c
                    INNER JOIN subtree s ON c.{$wrappedFkCol} = s.owner_key
                )
                SELECT COUNT(*)
                FROM {$wrappedTable} x
                WHERE x.id IN (SELECT id FROM subtree)
                  AND x.{$wrappedCreated} <= ?
                  {$completedFilter}
            )
        SQL;

        return $query->orderByRaw("{$expr} DESC", [$this->currWeekEnd->toDateTimeString()]);
    }

    public function table(Table $table): Table
    {
        $this->initWeekRanges();

        $currRange = $this->rangeLabel($this->currWeekStart, $this->currWeekEnd); // ex.: "08/07 a 14/07"
        $prevRange = $this->rangeLabel($this->prevWeekStart, $this->prevWeekEnd); // ex.: "01/07 a 07/07"

        return $table
            ->query(
                tap(
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
                        ),
                    /** @return Builder $q */
                    fn (Builder $q) => $this->orderByNetworkCurrentWeekDesc($q),
                )
            )
            ->columns([
                TextColumn::make('posicao')
                    ->label('Colocação')
                    ->rowIndex(isFromZero: false)
                    ->formatStateUsing(fn ($state) => "{$state}º")
                    ->alignment('left'),

                TextColumn::make('name')
                    ->label('Nome'),

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

                        if ($nw0 === 0) {
                            return $nw1 > 0 ? '∞%' : '0%';
                        }

                        $pct = (($nw1 - $nw0) / $nw0) * 100;
                        return number_format($pct, 2, ',', '.') . '%';
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
            // Header action: Export all as CSV (full dataset, same ordering & numbers)
            ->headerActions([
                Action::make('export_all_csv')
                    ->label('Exportar Todos')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () use ($currRange, $prevRange) {
                        // EN: Build the SAME base query used by the table, including ordering.
                        $builder = tap(
                            auth()->user()
                                ->completedRegistrationsQuery()
                                ->withCount([
                                    'firstLevelGuests as members_w1' => fn ($q) =>
                                        $q->where('created_at', '<=', $this->currWeekEnd),
                                    'firstLevelGuests as members_w0' => fn ($q) =>
                                        $q->where('created_at', '<=', $this->prevWeekEnd),
                                ])
                                ->whereHas('firstLevelGuests', fn ($q) =>
                                    $q->where('created_at', '<=', $this->currWeekEnd)
                                ),
                            fn (Builder $q) => $this->orderByNetworkCurrentWeekDesc($q),
                        );

                        $filename = 'ranking_rede_' . now()->format('Ymd_His') . '.csv';

                        return response()->streamDownload(function () use ($builder, $currRange, $prevRange) {
                            // SECURITY (EN): Stream to avoid memory pressure for large datasets.
                            $handle = fopen('php://output', 'w');

                            // Header row — mirrors table columns
                            fputcsv($handle, [
                                'Colocação',
                                'Nome',
                                "{$currRange} Rede",
                                "{$currRange} Membros",
                                "{$prevRange} Rede",
                                "{$prevRange} Membros",
                                'Crescimento (%)',
                            ]);

                            $pos = 1;

                            foreach ($builder->cursor() as $record) {
                                /** @var \App\Models\User $record */
                                $nw1 = $this->countNetworkUntil((int) $record->id, $this->currWeekEnd);
                                $nw0 = $this->countNetworkUntil((int) $record->id, $this->prevWeekEnd);
                                $m1  = (int) ($record->members_w1 ?? 0);
                                $m0  = (int) ($record->members_w0 ?? 0);

                                if ($nw0 === 0) {
                                    $growth = $nw1 > 0 ? '∞%' : '0%';
                                } else {
                                    $pct    = (($nw1 - $nw0) / $nw0) * 100;
                                    $growth = number_format($pct, 2, ',', '.') . '%';
                                }

                                fputcsv($handle, [
                                    $pos,
                                    $record->name,
                                    $nw1,
                                    $m1,
                                    $nw0,
                                    $m0,
                                    $growth,
                                ]);

                                $pos++;
                            }

                            fclose($handle);
                        }, $filename);
                    }),
            ])
            // SQL sorting is applied via orderByNetworkCurrentWeekDesc()
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
