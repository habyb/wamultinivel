<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class MyNetwork extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-globe-americas';
    protected static string $view = 'filament.pages.my-network';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'My Network');
    }

    public function getTitle(): string
    {
        return __(key: 'My Network');
    }

    /**
     * Get the table query.
     */
    protected function getTableQuery(): Builder
    {
        return auth()->user()->networkGuestsQuery();
    }

    /**
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('code')->label('Invitation ID'),
            Tables\Columns\TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            Tables\Columns\TextColumn::make('remoteJid')
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                })
                ->label('WhatsApp')
                ->searchable(),
            Tables\Columns\TextColumn::make('roles.name')
                ->sortable()
                ->searchable()
                ->badge()
                ->separator(', '),
            TextColumn::make('first_level_guests_count')
                ->label('Number of guests')
                ->counts('firstLevelGuests')
                ->badge()
                ->sortable()
                ->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),
            Tables\Columns\TextColumn::make('referrerGuest.name')
                ->label('Invited by')
                ->formatStateUsing(function ($state, $record) {
                    if (!$state) {
                        return '—';
                    }

                    $nomeLimitado = Str::limit($state, 10, '...');
                    return "{$nomeLimitado} ({$record->invitation_code})";
                })
                ->tooltip(
                    fn($state, $record) =>
                    $state ? "{$record->referrerGuest->name} ({$record->invitation_code})" : null
                ),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
