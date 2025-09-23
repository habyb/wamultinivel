<?php
// app/Filament/Widgets/TopNetworkGuests.php

namespace App\Filament\Widgets;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopNetworkGuests extends BaseWidget
{
    protected static ?string $heading = 'Top Convidados da Rede';
    protected static ?int $sort = 2;

    /** Default page size and options for TableWidget */
    protected int|string|array $defaultTableRecordsPerPage = 10;
    protected array $tableRecordsPerPageSelectOptions = [10, 25, 50];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                auth()->user()
                    ->networkGuestsQuery()
                    ->withCount([
                        // Alias used for sorting:
                        'firstLevelGuests as network_total_count',
                    ])
                    // IMPORTANT: Drop any prior ORDER BY from scopes so defaultSort wins.
                    ->reorder()
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nome')
                    ->searchable(),

                TextColumn::make('network_total_count')
                    ->label('Convidados')
                    ->badge()
                    ->alignment('right')
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state <= 5   => 'success',
                        $state <= 20  => 'warning',
                        default       => 'info',
                    }),
            ])
            // Open already sorted by Total (Rede) DESC:
            ->defaultSort('network_total_count', 'desc')
            // Avoid cross-widget state collisions:
            ->queryStringIdentifier('top_network_guests')
            ->paginated([10, 25, 50]);
    }
}
