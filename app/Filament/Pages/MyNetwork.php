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
    protected static ?int $navigationSort = 4;

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
     *
     * Aqui garantimos os agregados necessários para ordenação:
     * - first_level_guests_count: total de convidados de 1º nível (withCount)
     * - role_name: menor nome de role associado (withMin) para permitir ORDER BY
     */
    protected function getTableQuery(): Builder
    {
        return auth()->user()
            ->networkGuestsQuery()
            ->withCount([
                // ajuste o nome da relação se for diferente de firstLevelGuests
                'firstLevelGuests as first_level_guests_count',
            ])
            ->withMin(
                // relação do Spatie HasRoles
                'roles as role_name',
                'name',
            )
            ->reorder();
    }

    /**
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

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

            /**
             * Para ordenação usamos o alias "role_name" vindo do withMin.
             * Se quiser continuar exibindo múltiplos papéis, mantenha esta coluna;
             * apenas note que ela NÃO será ordenável — quem ordena é a role_name.
             */
            TextColumn::make('roles.name')
                ->label('Funções')
                ->badge()
                ->sortable()
                ->separator(', '),

            TextColumn::make('first_level_guests_count')
                ->label('Number of guests')
                ->badge()
                ->sortable()
                ->alignment('right')
                ->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),

            TextColumn::make('referrerGuest.name')
                ->label('Invited by')
                ->sortable()
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
        ];
    }
}
