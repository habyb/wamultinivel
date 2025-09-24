<?php
// app/Filament/Pages/NetworkRanking.php

namespace App\Filament\Pages;

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
                        // Direct members (cumulative up to each week end)
                        'firstLevelGuests as members_w1' => fn ($q) =>
                            $q->whereDate('created_at', '<=', $this->currWeekEnd->toDateString()),
                        'firstLevelGuests as members_w0' => fn ($q) =>
                            $q->whereDate('created_at', '<=', $this->prevWeekEnd->toDateString()),
                    ])
                    // NOTE (EN): Using snapshot column for "network" until a recursive relation exists.
                    ->addSelect([
                        DB::raw('users.total_network_count as network_w1'),
                        DB::raw('users.total_network_count as network_w0'),
                    ])
                    // Only users with >= 1 direct member up to current week end
                    ->whereHas('firstLevelGuests', fn ($q) =>
                        $q->whereDate('created_at', '<=', $this->currWeekEnd->toDateString())
                    )
                    ->orderByDesc('network_w1')
                    ->orderByDesc('members_w1')
            )
            ->columns([
                // — First, the "free" columns (no group). Above them the extra header row stays empty.
                TextColumn::make('posicao')
                    ->label('Colocação')
                    ->rowIndex(isFromZero: false)
                    ->formatStateUsing(fn ($state) => "{$state}º")
                    ->alignment('left'),

                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                // — Create a NEW HEADER ROW using ColumnGroup for each week range.
                ColumnGroup::make($currRange, [
                    TextColumn::make('network_w1')
                        ->label('Rede')
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

                ColumnGroup::make($prevRange, [
                    TextColumn::make('network_w0')
                        ->label('Rede')
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

                // You may keep this outside of a group or create another ColumnGroup if desired.
                // Coluna "Crescimento" com tooltip no cabeçalho
                TextColumn::make('growth_pct')
                    // Simple approach: add "(?)" and a native tooltip on the header cell.
                    // UX (EN): Hover the word "Crescimento" (or anywhere in the header cell) to see the tooltip.
                    ->label('Crescimento (?)')
                    ->extraHeaderAttributes([
                        'title' => 'Percentual de Crescimento em Relação à Semana Anterior',
                        'class' => 'whitespace-nowrap',
                    ])
                    ->state(function ($record): string {
                        /** @var int $w1 */
                        $w1 = (int) ($record->network_w1 ?? 0);
                        /** @var int $w0 */
                        $w0 = (int) ($record->network_w0 ?? 0);

                        // SAFETY (EN): Robust to zero baseline.
                        if ($w0 === 0) {
                            return $w1 > 0 ? '∞%' : '0%';
                        }

                        $pct = (($w1 - $w0) / $w0) * 100;
                        return number_format($pct, 0, ',', '.') . '%';
                    })
                    ->badge()
                    ->alignment('right')
                    ->color(function ($record): string {
                        $w1 = (int) ($record->network_w1 ?? 0);
                        $w0 = (int) ($record->network_w0 ?? 0);

                        if ($w0 === 0 && $w1 > 0) {
                            return 'warning';
                        }

                        $delta = $w1 - $w0;
                        return $delta < 0 ? 'danger' : ($delta === 0 ? 'gray' : 'success');
                    }),

            ])
            ->defaultSort('network_w1', 'desc')
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
