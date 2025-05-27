<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class DirectRegistrations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static string $view = 'filament.pages.direct-registrations';

    protected static ?string $navigationGroup = 'Relatórios';

    protected static ?string $title = 'Cadastros Diretos';

    /**
     * Get the table query.
     */
    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return $user->firstLevelGuests()->getQuery();
    }

    /**
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
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
            TextColumn::make('first_level_guests_count')
                ->label('Número de convidados')
                ->counts('firstLevelGuests'), // Conta os registros da relação 'guests'
        ];
    }
}
