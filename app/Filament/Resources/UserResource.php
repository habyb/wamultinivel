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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Get;
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
                    Forms\Components\TextInput::make('city')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true),
                    Forms\Components\TextInput::make('neighborhood')
                        ->maxLength(255),
                ])->columns(2),
                Grid::make(2)->schema([
                    Select::make('concern_01')
                        ->label('Main concern')
                        ->options([
                            'Asfalto ruim' => 'Asfalto ruim',
                            'Cultura e Lazer' => 'Cultura e Lazer',
                            'Falta de água' => 'Falta de água',
                            'Falta de creches' => 'Falta de creches',
                            'Falta de emprego' => 'Falta de emprego',
                            'Iluminação e segurança' => 'Iluminação e segurança',
                            'Qualidade na educação' => 'Qualidade na educação',
                            'Saneamento básico' => 'Saneamento básico',
                            'Saúde precária' => 'Saúde precária',
                            'Transporte insuficiente' => 'Transporte insuficiente',
                        ]),
                    DatePicker::make('date_of_birth')
                        ->label('Date of Birth')
                        ->native(false)
                        ->extraInputAttributes(['readonly' => 'readonly'])
                        ->displayFormat('d/m/Y')
                        ->format('d/m/Y'),
                ])->columns(2),
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime(format: 'd/m/Y H:i:s')
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime(format: 'd/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('code')->label('Invitation ID'),
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('remoteJid')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['Superadmin', 'Admin']))
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
                Tables\Columns\TextColumn::make('city')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('whatsapp')
                    ->label('')
                    ->tooltip('WhatsApp')
                    ->icon(fn () => new \Illuminate\Support\HtmlString('<svg style="width: 1.25rem; height: 1.25rem;" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51a12.8 12.8 0 0 0-.57-.01c-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413Z"/></svg>'))
                    ->color('success')
                    ->url(function ($record) {
                        if (!$record?->remoteJid) {
                            return null;
                        }

                        static $textSetting = null;
                        if ($textSetting === null) {
                            $setting = \App\Models\Setting::where('name', 'whatsapp_link_text')->first();
                            $textSetting = $setting ? data_get($setting->payload, 'value', '') : '';
                        }

                        $url = 'https://wa.me/' . fix_whatsapp_number($record->remoteJid);
                        
                        if (!empty($textSetting)) {
                            $message = str_replace('{NAME}', $record->name, $textSetting);
                            $url .= '?text=' . urlencode($message);
                        }

                        return $url;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn ($record) => auth()->user()?->hasAnyRole(['Superadmin', 'Admin', 'Embaixador']) && filled($record?->remoteJid)),
                Impersonate::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ])->visible(fn() => Auth::user()?->hasRole('Superadmin')),
            ])
            ->paginated([10, 25, 50, 100]);
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
            DeleteBulkAction::make()->visible(fn() => Auth::user()?->hasRole('Superadmin')),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();

        // Superadmin
        if ($user->hasRole('Superadmin')) {
            return parent::getEloquentQuery()
                ->with(['roles', 'referrerGuest'])
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
                ->with(['roles', 'referrerGuest'])
                ->where('is_add_date_of_birth', true)
                ->withCount('firstLevelGuests');
        }

        // Embaixador or Membro
        if ($user->hasRole('Embaixador') || $user->hasRole('Membro')) {
            return parent::getEloquentQuery()
                ->where('invitation_code', $user->code)
                ->with(['roles', 'referrerGuest'])
                ->where('is_add_date_of_birth', true)
                ->withCount('firstLevelGuests');
        }

        // fallback
        return parent::getEloquentQuery()->whereRaw('0 = 1')->where('is_add_date_of_birth', true);
    }
}
