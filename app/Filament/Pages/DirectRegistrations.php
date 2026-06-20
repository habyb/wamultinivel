<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Filament\Tables\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;
use App\Models\User;
use Filament\Tables\Actions\BulkAction;
use Carbon\Carbon;

class DirectRegistrations extends Page implements HasTable
{
    use InteractsWithTable {
        table as traitTable;
    }

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';
    protected static string $view = 'filament.pages.direct-registrations';
    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): string
    {
        return __(key: 'Reports');
    }

    public static function getNavigationLabel(): string
    {
        return __(key: 'Direct Registrations');
    }

    public function getTitle(): string
    {
        return __(key: 'Direct Registrations');
    }

    /**
     * Get the table query.
     */
    protected function getTableQuery(): Builder
    {
        $user = Auth::user();

        return $user->firstLevelGuests()
            ->getQuery()
            ->with(['roles'])
            ->withCount('firstLevelGuests')
            ->orderByDesc('first_level_guests_count');
    }

    /**
     * Define table columns.
     */
    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('created_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime(format: 'd/m/Y H:i:s')
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('code')->label('Invitation ID'),
            TextColumn::make('name')
                ->label('Name')
                ->searchable(),
            TextColumn::make('remoteJid')
                ->visible(fn () => auth()->user()?->hasAnyRole(['Superadmin', 'Admin']))
                ->formatStateUsing(function (string $state): string {
                    return format_phone_number(fix_whatsapp_number($state));
                })
                ->label('WhatsApp')
                ->searchable(),
            TextColumn::make('roles.name')
                ->searchable()
                ->badge()
                ->separator(', '),
            TextColumn::make('first_level_guests_count')
                ->label('Number of guests')
                ->badge()
                ->alignment('right')
                ->color(fn(string $state): string => match (true) {
                    $state == 0 => 'gray',
                    $state <= 5 => 'success',
                    default => 'warning',
                }),
            TextColumn::make('invitation_code')
                ->label('Invited by')
                ->formatStateUsing(function ($state) {
                    $currentUser = Auth::user();
                    if (!$currentUser) {
                        return '—';
                    }

                    $nomeLimitado = Str::limit($currentUser->name, 10, '...');
                    return "{$nomeLimitado} ({$state})";
                })
                ->tooltip(
                    fn($state) =>
                    Auth::user() ? Auth::user()->name . " ({$state})" : null
                ),
        ];
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $this->traitTable($table)
            ->paginated([10, 25, 50, 100]);
    }
}
