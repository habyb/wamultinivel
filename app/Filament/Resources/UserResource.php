<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;
use Filament\Tables\Actions\DeleteBulkAction;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function getModelLabel(): string
    {
        return __(key: 'User');
    }

    protected function getActions(): array
    {
        return [
            Impersonate::make()->record($this->getRecord())
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->schema([
                        Placeholder::make('code')
                            ->label('Invitation ID')
                            ->view('filament.forms.components.invitation-code')
                            ->visible(fn(string $operation): bool => $operation !== 'create'),
                        Placeholder::make('first_level_guests_count')
                            ->label('Number of guests')
                            ->content(fn($record) => $record?->first_level_guests_count ?? 0)
                            ->visible(fn(string $operation): bool => $operation !== 'create'),
                        Placeholder::make('referrerGuest.name')
                            ->label('Invited by')
                            ->content(
                                fn($record) => $record?->referrerGuest
                                    ? "{$record->referrerGuest->name} ({$record->invitation_code})"
                                    : '—'
                            )
                            ->visible(fn(string $operation): bool => $operation !== 'create'),
                    ])->columns(3),
                Grid::make(2)->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),
                    Placeholder::make('remoteJid')
                        ->label('WhatsApp')
                        ->content(
                            fn($record) => $record?->remoteJid
                                ? format_phone_number(fix_whatsapp_number($record->remoteJid))
                                : '—'
                        )
                        ->visible(fn(string $operation): bool => $operation !== 'create'),
                ])->columns(3),
                Grid::make(2)->schema([
                    Forms\Components\TextInput::make('password')
                        ->password()
                        ->required()
                        ->required(fn(string $operation): bool => $operation === 'create')
                        ->dehydrated(fn(?string $state) => filled($state))
                        ->confirmed()
                        ->maxLength(255),
                    Forms\Components\TextInput::make('password_confirmation')
                        ->password()
                        ->requiredWith(statePaths: 'password')
                        ->dehydrated(condition: false),
                    Select::make('roles')
                        ->multiple()
                        ->relationship(
                            name: 'roles',
                            titleAttribute: 'name',
                            modifyQueryUsing: fn(Builder $query) =>
                            Auth::user()?->hasRole('Superadmin')
                                ? $query // Superadmin sees all roles
                                : $query->where('name', '!=', 'Superadmin')
                        )
                        ->preload()
                        ->columnSpanFull(),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->label('Invitation ID'),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('remoteJid')
                    ->label('WhatsApp')
                    ->formatStateUsing(function (string $state): string {
                        return format_phone_number(fix_whatsapp_number($state));
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->separator(', '),
                TextColumn::make('first_level_guests_count')
                    ->label('Number of guests')
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
            ])
            ->filters([
                //
            ])
            ->actions([
                Impersonate::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * @return array
     */
    public static function getBulkActions(): array
    {
        return [
            DeleteBulkAction::make()->visible(fn() => Auth::user()->can('delete', User::class)),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Superadmin
        if ($user->hasRole('Superadmin')) {
            return parent::getEloquentQuery()
                ->with(['referrerGuest'])
                ->withCount('firstLevelGuests');
        }

        // Admin
        if ($user->hasRole('Admin')) {
            return parent::getEloquentQuery()
                ->whereHas(
                    'roles',
                    fn(Builder $query) =>
                    $query->whereIn('name', ['Admin', 'Embaixador', 'Membro'])
                )
                ->with(['referrerGuest'])
                ->where('is_add_email', true)
                ->withCount('firstLevelGuests');
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return parent::getEloquentQuery()
                ->where('invitation_code', $user->code)
                ->with(['referrerGuest'])
                ->where('is_add_email', true)
                ->withCount('firstLevelGuests');
        }

        // fallback
        return parent::getEloquentQuery()->whereRaw('0 = 1')->where('is_add_email', true);
    }
}
