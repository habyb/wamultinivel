<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Auth;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Str;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Grid;

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
                            ->content(fn($record) => $record?->code ?? '—')
                            ->visible(fn(string $operation): bool => $operation !== 'create'),
                        Placeholder::make('convidados_diretos_count')
                            ->label('Number of guests')
                            ->content(fn($record) => $record?->convidados_diretos_count ?? 0)
                            ->visible(fn(string $operation): bool => $operation !== 'create'),
                        Placeholder::make('convidador.name')
                            ->label('Invited by')
                            ->content(
                                fn($record) => $record?->convidador
                                    ? "{$record->convidador->name} ({$record->invitation_code})"
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
                                ? preg_replace('/\D/', '', $record->remoteJid)
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
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->separator(', '),
                TextColumn::make('convidados_diretos_count')
                    ->label('Number of guests')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match (true) {
                        $state == 0 => 'gray',
                        $state <= 5 => 'success',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('convidador.name')
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
                        $state ? "{$record->convidador->name} ({$record->invitation_code})" : null
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

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Superadmin
        if ($user->hasRole('Superadmin')) {
            return parent::getEloquentQuery()->with(['convidador'])->withCount('convidadosDiretos');
        }

        // Admin
        if ($user->hasRole('Admin')) {
            return parent::getEloquentQuery()
                ->whereHas(
                    'roles',
                    fn(Builder $query) =>
                    $query->whereIn('name', ['Embaixador', 'Membro'])
                )
                ->with(['convidador'])
                ->withCount('convidadosDiretos');
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return parent::getEloquentQuery()
                ->where('invitation_code', $user->code)
                ->with(['convidador'])
                ->withCount('convidadosDiretos');
        }

        // fallback
        return parent::getEloquentQuery()->whereRaw('0 = 1');
    }
}
