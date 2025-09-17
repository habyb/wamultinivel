<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use App\Models\User;

class TotalEmbaixadores extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';
    protected static string $view = 'filament.pages.total-embaixadores';
    protected static ?int $navigationSort = 5;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'Total Embaixadores');
    }

    public function getTitle(): string
    {
        return __(key: 'Total Embaixadores');
    }

    /**
     * Get the table query.
     */
    protected function getTableQuery(): Builder
    {
        return User::embaixadoresQuery()
            ->withCount([
                'firstLevelGuests as first_level_guests_count',
            ])
            ->reorder()
            ->orderByDesc('first_level_guests_count');
    }

    /**
     * Ordenação padrão ao abrir a página.
     */
    protected function getTableDefaultSortColumn(): ?string
    {
        return 'first_level_guests_count';
    }

    protected function getTableDefaultSortDirection(): ?string
    {
        return 'desc';
    }

    /**
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('code')->label('Invitation ID'),
            TextColumn::make('name')
                ->label('Name')
                ->sortable()
                ->searchable(),
            TextColumn::make('remoteJid')
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                })
                ->label('WhatsApp')
                ->searchable(),
            TextColumn::make('first_level_guests_count')
                ->label('Number of guests')
                ->badge()
                ->alignment('right')
                ->color(fn (string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),
            TextColumn::make('referrerGuest.name')
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
            TextColumn::make('created_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }
}
