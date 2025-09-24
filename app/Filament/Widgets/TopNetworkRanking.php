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

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [10, 25, 50];
    }

    protected function getDefaultTableRecordsPerPage(): int|string|array
    {
        return 10;
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return $user->firstLevelGuests()
            ->getQuery()
            ->withCount(['firstLevelGuests as network_guests_count'])
            ->orderByDesc('network_guests_count')
            ->orderBy('id'); // tie-break para ranking estável
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('position')
                ->label('Posição')
                ->rowIndex()
                ->alignment('left')
                ->badge()
                ->color('gray'),

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

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }
}
