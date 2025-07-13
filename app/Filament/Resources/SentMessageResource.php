<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use App\Services\WhatsAppServiceBusinessApi;
use Filament\Forms\Components\DateTimePicker;
use App\Filament\Resources\SentMessageResource\Pages;

class SentMessageResource extends Resource
{
    protected static ?string $model = SentMessage::class;

    public static function getNavigationLabel(): string
    {
        return __(key: 'Messages');
    }

    protected static ?string $slug = 'send-messages';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-queue-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Send messages');
    }

    protected static function getApprovedTemplates(): array
    {
        return collect(app(WhatsAppServiceBusinessApi::class)->getTemplate())
            ->where('status', 'APPROVED')
            ->pluck('name', 'name')
            ->toArray();
    }

    protected static function countFilteredContacts(callable $get): int
    {
        $ageGroups = $get('age_groups');

        return User::query()
            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
            ->where('is_add_date_of_birth', true)
            ->get()
            ->filter(function ($user) use ($ageGroups) {
                if (!empty($ageGroups)) {
                    $birth = $user->getParsedDateOfBirth();

                    if (!$birth) {
                        return false;
                    }

                    $age = $birth->age;

                    foreach ($ageGroups as $group) {
                        if (preg_match('/^(\d{2})-(\d{2})$/', $group, $m)) {
                            $min = (int) $m[1];
                            $max = (int) $m[2];

                            if ($age >= $min && $age <= $max) {
                                return true;
                            }
                        }
                    }

                    return false;
                }

                return true;
            })
            ->count();
    }

    public static function getFormSchema(): array
    {
        return [
            Grid::make(12)
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->helperText('Esse título NÃO será visível para o contato no WhatsApp. Este campo é utilizado apenas para identificação.')
                        ->required()
                        ->minLength(5)
                        ->maxLength(255)
                        ->columnSpan(8),
                ]),

            Grid::make(12)
                ->schema([
                    Select::make('filter')
                        ->label('Filter')
                        ->helperText('Selecione um filtro.')
                        ->required()
                        ->reactive()
                        ->live()
                        ->native(false)
                        ->dehydrated(true)
                        ->options([
                            null => 'Selecione',
                            'questionary' => __('Questionary'),
                            'ambassadors' => __('Ambassadors'),
                            'contacts' => __('Contacts'),
                        ])
                        ->searchable()
                        ->columnSpan(3),
                ])
                ->id('filter'),

            // Questionary Section - START
            Section::make(__('Questionary'))
                ->visible(fn(callable $get) => $get('filter') === 'questionary')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Select::make('cities')
                                ->label('Cities')
                                ->helperText('Selecione uma ou mais cidades para destino.')
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->native(false)
                                ->dehydrated(true)
                                ->options(function () {
                                    return User::select('city')
                                        ->distinct()
                                        ->orderBy('city')
                                        ->pluck('city', 'city')
                                        ->filter()
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),

                            Select::make('neighborhoods')
                                ->label('Neighborhoods')
                                ->helperText('Selecione um ou mais bairros para destino.')
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->options(function () {
                                    return User::select('neighborhood')
                                        ->distinct()
                                        ->orderBy('neighborhood')
                                        ->pluck('neighborhood', 'neighborhood')
                                        ->filter()
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),

                            Select::make('genders')
                                ->label('Genders')
                                ->helperText('Selecione um ou mais gêneros para destino.')
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->options(function () {
                                    return User::select('gender')
                                        ->distinct()
                                        ->orderBy('gender')
                                        ->pluck('gender', 'gender')
                                        ->filter()
                                        ->toArray();
                                })
                                ->searchable()
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),

                            Select::make('age_groups')
                                ->label('Age groups')
                                ->helperText('Selecione uma ou mais faixas etárias.')
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->native(false)
                                ->dehydrated(true)
                                ->options([
                                    '16-30' => '16-30',
                                    '31-40' => '31-40',
                                    '41-50' => '41-50',
                                    '51-60' => '51-60',
                                    '60+'   => '60+',
                                ])
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),

                            Select::make('concerns_01')
                                ->label('Main concerns')
                                ->helperText(__('Select one or more main concerns for destination.'))
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->options(function () {
                                    return User::select('concern_01')
                                        ->distinct()
                                        ->orderBy('concern_01')
                                        ->pluck('concern_01', 'concern_01')
                                        ->filter()
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),

                            Select::make('concerns_02')
                                ->label('Secondary concerns')
                                ->helperText(__('Select one or more secondary concerns for destination.'))
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->options(function () {
                                    return User::select('concern_02')
                                        ->distinct()
                                        ->orderBy('concern_02')
                                        ->pluck('concern_02', 'concern_02')
                                        ->filter()
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $count = self::countFilteredContacts($get);
                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(6),
                        ]),
                ])
                ->id('questionary'),
            // Questionary Section - END

            // Ambassadors Section - START
            Section::make(__('Ambassadors'))
                ->visible(fn(callable $get) => $get('filter') === 'ambassadors')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Toggle::make('all_ambassadors')
                                ->label('Selecionar todos os Embaixadores')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $set) {
                                    if ($state === true) {
                                        $count = User::whereHas('firstLevelGuestsNetwork')->where('is_add_date_of_birth', true)->count();
                                        $set('contacts_count_preview', "{$count} contatos");
                                        $set('include_ambassador_network', false);
                                        $set('ambassadors', []);
                                    } else {
                                        $set('contacts_count_preview', '0 contatos');
                                    }
                                })
                                ->columnSpan(12),

                            Select::make('ambassadors')
                                ->label('Ambassadors')
                                ->helperText('Selecione um ou mais Embaixadores para destino.')
                                ->required()
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->native(false)
                                ->dehydrated(true)
                                ->options(function () {
                                    return User::select('id', 'name', 'code')
                                        ->whereHas('firstLevelGuestsNetwork')
                                        ->where('is_add_date_of_birth', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->code})"])
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $contacts = Arr::wrap($get('ambassadors'));
                                    $includeNetwork = $get('include_ambassador_network');

                                    $ids = collect($contacts);

                                    if ($includeNetwork) {
                                        foreach ($contacts as $contactId) {
                                            $user = User::find($contactId);
                                            if ($user) {
                                                $networkIds = $user->getRecursiveNetWork($user);
                                                $ids = $ids->merge($networkIds);
                                            }
                                        }
                                    }

                                    $ids = $ids->unique(); // garantir IDs únicos

                                    $count = User::whereIn('id', $ids)
                                        ->where('is_add_date_of_birth', true)
                                        ->count();

                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->disabled(fn(callable $get) => $get('all_ambassadors') === true)
                                ->columnSpan(12),

                            Toggle::make('include_ambassador_network')
                                ->label('Enviar também para toda a rede de contatos selecionados.')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $contactIds = collect($get('ambassadors') ?? []);

                                    if ($contactIds->isEmpty()) {
                                        $set('contacts_count_preview', "0 contatos");
                                        return;
                                    }

                                    if ($state === true) {
                                        $ids = $contactIds->values();

                                        foreach ($contactIds as $contactId) {
                                            $user = User::find($contactId);
                                            if ($user) {
                                                $networkIds = $user->getRecursiveNetWork($user);
                                                $ids = $ids->merge($networkIds);
                                            }
                                        }

                                        $count = User::query()
                                            ->whereIn('id', $ids->unique())
                                            ->where('is_add_date_of_birth', true)
                                            ->count();

                                        $set('contacts_count_preview', "{$count} contatos");
                                    } else {
                                        $count = User::query()
                                            ->whereIn('id', $contactIds)
                                            ->where('is_add_date_of_birth', true)
                                            ->count();

                                        $set('contacts_count_preview', "{$count} contatos");
                                    }
                                })
                                ->disabled(fn(callable $get) => $get('all_ambassadors') === true)
                                ->columnSpan(12),
                        ]),
                ])
                ->id('ambassadors'),
            // Ambassadors Section - END

            // Contacts Section - START
            Section::make(__('Contacts'))
                ->visible(fn(callable $get) => $get('filter') === 'contacts')
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Select::make('contacts')
                                ->label('Contacts')
                                ->helperText('Selecione um ou mais Contatos para destino.')
                                ->required()
                                ->multiple()
                                ->reactive()
                                ->live()
                                ->native(false)
                                ->dehydrated(true)
                                ->options(function () {
                                    return User::select('id', 'name', 'code')
                                        ->where('is_add_date_of_birth', true)
                                        ->orderBy('name')
                                        ->get()
                                        ->mapWithKeys(fn($user) => [$user->id => "{$user->name} ({$user->code})"])
                                        ->toArray();
                                })
                                ->searchable()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $contacts = Arr::wrap($get('contacts'));
                                    $includeNetwork = $get('include_network');

                                    $ids = collect($contacts);

                                    if ($includeNetwork) {
                                        foreach ($contacts as $contactId) {
                                            $user = User::find($contactId);
                                            if ($user) {
                                                $networkIds = $user->getRecursiveNetWork($user);
                                                $ids = $ids->merge($networkIds);
                                            }
                                        }
                                    }

                                    $ids = $ids->unique(); // garantir IDs únicos

                                    $count = User::whereIn('id', $ids)
                                        ->where('is_add_date_of_birth', true)
                                        ->count();

                                    $set('contacts_count_preview', "{$count} contatos");
                                })
                                ->columnSpan(12),

                            Toggle::make('include_network')
                                ->label('Enviar também para toda a rede de contatos selecionados.')
                                ->reactive()
                                ->afterStateUpdated(function ($state, callable $get, callable $set) {
                                    $contactIds = collect($get('contacts') ?? []);

                                    if ($contactIds->isEmpty()) {
                                        $set('contacts_count_preview', "0 contatos");
                                        return;
                                    }

                                    if ($state === true) {
                                        $ids = $contactIds->values();

                                        foreach ($contactIds as $contactId) {
                                            $user = User::find($contactId);
                                            if ($user) {
                                                $networkIds = $user->getRecursiveNetWork($user);
                                                $ids = $ids->merge($networkIds);
                                            }
                                        }

                                        $count = User::query()
                                            ->whereIn('id', $ids->unique())
                                            ->where('is_add_date_of_birth', true)
                                            ->count();

                                        $set('contacts_count_preview', "{$count} contatos");
                                    } else {
                                        $count = User::query()
                                            ->whereIn('id', $contactIds)
                                            ->where('is_add_date_of_birth', true)
                                            ->count();

                                        $set('contacts_count_preview', "{$count} contatos");
                                    }
                                })
                                ->columnSpan(12),

                        ]),
                ])
                ->id('contacts'),
            // Contacts Section - END

            // Message Section - START
            Section::make(__('Message'))
                ->schema([
                    Grid::make(12)
                        ->schema([
                            Select::make('type')
                                ->columnSpan(1)
                                ->label('Message type')
                                ->options([
                                    'text' => __('Text message'),
                                    'image' => __('Image with description'),
                                    'video' => __('Video with description'),
                                ])
                                ->reactive()
                                ->required()
                                ->columnSpan(6),

                            // image
                            FileUpload::make('path')
                                ->label('Imagem')
                                ->acceptedFileTypes(['image/jpeg', 'image/png'])
                                ->disk('public')
                                ->directory('messages')
                                ->preserveFilenames()
                                ->deleteUploadedFileUsing(function (string $file) {
                                    Storage::disk('public')->delete($file);
                                })
                                ->visible(fn(callable $get) => $get('type') === 'image')
                                ->required(fn(callable $get) => $get('type') === 'image')
                                ->columnSpan(6)
                                ->maxSize(5120) // 5 MB
                                ->saveUploadedFileUsing(function (\Illuminate\Http\UploadedFile $file, $record) {
                                    try {
                                        $path = $file->storePubliclyAs('messages', $file->hashName(), 'public');

                                        return $path;
                                    } catch (\Throwable $e) {
                                        Log::error('Erro no upload de mídia', [
                                            'erro' => $e->getMessage(),
                                            'arquivo' => $file->getClientOriginalName(),
                                            'tamanho' => $file->getSize(),
                                        ]);
                                        throw $e; // rethrow para que Filament trate corretamente
                                    }
                                })
                                ->helperText('Tamanho máximo permitido: 5 MB'),

                            // video
                            FileUpload::make('path')
                                ->label('Arquivo de Vídeo')
                                ->acceptedFileTypes(['video/mp4'])
                                ->disk('public')
                                ->directory('messages')
                                ->preserveFilenames()
                                ->deleteUploadedFileUsing(function (string $file) {
                                    Storage::disk('public')->delete($file);
                                })
                                ->visible(fn(callable $get) => $get('type') === 'video')
                                ->required(fn(callable $get) => $get('type') === 'video')
                                ->columnSpan(6)
                                ->maxSize(16384) // 16 MB
                                ->saveUploadedFileUsing(function (\Illuminate\Http\UploadedFile $file, $record) {
                                    try {
                                        $path = $file->storePubliclyAs('messages', $file->hashName(), 'public');

                                        return $path;
                                    } catch (\Throwable $e) {
                                        Log::error('Erro no upload de mídia', [
                                            'erro' => $e->getMessage(),
                                            'arquivo' => $file->getClientOriginalName(),
                                            'tamanho' => $file->getSize(),
                                        ]);
                                        throw $e; // rethrow para que Filament trate corretamente
                                    }
                                })
                                ->helperText('Tamanho máximo permitido: 16 MB'),
                        ]),

                    Grid::make(12)
                        ->schema([
                            Select::make('template_name')
                                ->label('Template')
                                ->searchable()
                                ->reactive() // torna o campo reativo
                                ->options(
                                    fn() => collect(app(WhatsAppServiceBusinessApi::class)->getTemplate())
                                        ->where('status', 'APPROVED')
                                        ->pluck('name', 'name')
                                        ->toArray()
                                )
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $template = collect(app(WhatsAppServiceBusinessApi::class)->getTemplate())
                                        ->firstWhere('name', $state);

                                    $header = collect($template['components'] ?? [])->firstWhere('type', 'HEADER')['text'] ?? '';
                                    $body = collect($template['components'] ?? [])->firstWhere('type', 'BODY')['text'] ?? '';
                                    $footer = collect($template['components'] ?? [])->firstWhere('type', 'FOOTER')['text'] ?? '';

                                    $preview = "{$header}\n\n{$body}\n\n_{$footer}_";

                                    $set('template_preview', $preview);

                                    if ($template) {
                                        $set('template_id', $template['id'] ?? null);
                                        $set('template_language', $template['language'] ?? null);
                                        $set('template_components', $template['components'] ?? null);
                                    }
                                })
                                ->afterStateHydrated(function ($state, callable $set) {
                                    if (! $state) return;

                                    $template = collect(app(\App\Services\WhatsAppServiceBusinessApi::class)->getTemplate())
                                        ->firstWhere('name', $state);

                                    if (! $template) {
                                        $set('template_preview', 'Template não encontrado.');
                                        return;
                                    }

                                    $header = collect($template['components'])->firstWhere('type', 'HEADER')['text'] ?? '';
                                    $body   = collect($template['components'])->firstWhere('type', 'BODY')['text'] ?? '';
                                    $footer = collect($template['components'])->firstWhere('type', 'FOOTER')['text'] ?? '';

                                    $preview = <<<TEXT
                                                {$header}

                                                {$body}

                                                {$footer}
                                                TEXT;

                                    $set('template_preview', $preview);
                                })
                                ->required()
                                ->columnSpan(6),

                            Textarea::make('template_preview')
                                ->label('Preview of the template')
                                ->hint(__('Select a template first'))
                                ->disabled()
                                ->rows(10)
                                ->columnSpan(1)
                                ->reactive()
                                ->columnSpan(6),
                        ]),
                ])
                ->id('message'),
            // Message Section - END

            Grid::make(12)
                ->schema([
                    DateTimePicker::make('sent_at')
                        ->label(__('Schedule'))
                        ->helperText(__('Select shipping date and time at least 2 minutes in the future.'))
                        ->suffixIcon('heroicon-m-calendar')
                        ->seconds(false)
                        ->native(false)
                        ->nullable()
                        ->required()
                        ->displayFormat('d/m/Y H:i')
                        ->rule(function () {
                            return function ($attribute, $value, $fail) {
                                if ($value && Carbon::parse($value)->lt(now()->addMinutes(2))) {
                                    $fail(__('The date and time should be at least 2 minutes in the future.'));
                                }
                            };
                        })
                        ->columnSpan(4),
                ]),

            Grid::make(12)
                ->schema([
                    TextInput::make('contacts_count_preview')
                        ->label('Contacts found')
                        ->disabled()
                        ->dehydrated(false)
                        ->afterStateHydrated(function ($state, callable $get, callable $set) {
                            $count = self::countFilteredContacts($get);
                            $set('contacts_count_preview', "{$count} contatos");
                        })
                        ->columnSpan(3),
                ]),

            Hidden::make('template_id')
                ->dehydrated(true)
                ->default(null),

            Hidden::make('template_language')
                ->dehydrated(true)
                ->default(null),

            Hidden::make('template_components')
                ->dehydrated(true)
                ->default(null),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::getFormSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sent_at')
                    ->label(__('Scheduled at'))
                    ->dateTime(format: 'd/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime(format: 'd/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('title')->sortable()->searchable(),
                TextColumn::make('contacts_count')
                    ->label('Contacts')
                    ->badge()
                    ->sortable()
                    ->color(fn(string $state): string => match (true) {
                        $state == 0 => 'gray',
                        $state <= 5 => 'success',
                        default => 'warning',
                    }),
                TextColumn::make('status')
                    ->label('Status')
                    ->sortable()
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            'pending' => __('Pending'),
                            'sent' => __('Sent'),
                            'failed' => __('Failed'),
                            default => ucfirst($state),
                        };
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('View')) // Novo texto
                    ->icon('heroicon-o-eye') // Novo ícone (ex: ícone de lápis)
                    ->color('warning'), // opcional: muda a cor do botão
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
            'index' => Pages\ListSentMessages::route('/'),
            'create' => Pages\CreateSentMessage::route('/create'),
            'edit' => Pages\EditSentMessage::route('/{record}/edit'),
        ];
    }
}
