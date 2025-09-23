<?php
// app/Filament/Widgets/TopNetworkRanking.php

namespace App\Filament\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TopNetworkRanking extends BaseWidget
{
    protected static ?string $heading = 'Top Cadastros Diretos';
    protected static ?int $sort = 1;

    /** @var int|string|array Default per-page size */
    protected int|string|array $defaultTableRecordsPerPage = 10;

    /** @var array Page-size options */
    protected array $tableRecordsPerPageSelectOptions = [10, 25, 50];

    /**
     * Build the query.
     *
     * SECURITY & PERFORMANCE:
     * - Uses server-side pagination & ordering.
     * - Only exposes required fields.
     */
    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return $user
            ->firstLevelGuests()
            ->getQuery()
            ->withCount(['firstLevelGuests as network_guests_count'])
            ->orderByDesc('network_guests_count');
    }

    /**
     * Define the table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('name')
                ->label('Nome')
                ->searchable(),

            TextColumn::make('network_guests_count')
                ->label('Convidados')
                ->badge()
                ->alignment('right')
                ->color(fn (int $state): string => match (true) {
                    $state === 0 => 'gray',
                    $state <= 5   => 'success',
                    $state <= 20  => 'warning',
                    default       => 'info',
                }),
        ];
    }

    /**
     * Keep pagination enabled.
     */
    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
