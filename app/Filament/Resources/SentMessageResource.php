<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\SentMessage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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
use Filament\Forms\Components\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DateTimePicker;
use App\Services\WhatsAppServiceBusinessApi;
use Filament\Forms\Get;
use Filament\Forms\Set;
use App\Filament\Resources\SentMessageResource\Pages;
use TangoDevIt\FilamentEmojiPicker\EmojiPickerAction;

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

    public static function getModelLabel(): string
    {
        return __(key: 'Messages');
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
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('contacts_count_preview', '0 contatos');
                            // Questionary
                            $set('cities', []);
                            $set('neighborhoods', []);
                            $set('genders', []);
                            $set('age_groups', []);
                            $set('concerns_01', []);
                            $set('concerns_02', []);
                            // Ambassadors
                            $set('all_ambassadors', false);
                            $set('selected_city', null);
                            $set('ambassadors', []);
                            $set('include_ambassador_network', false);
                            // Contacts
                            $set('contacts', []);
                            $set('include_network', false);
                        })
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
                            Grid::make(12)
                                ->schema([
                                    Toggle::make('all_ambassadors')
                                        ->label('Selecionar todos os Embaixadores')
                                        ->reactive()
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            if ($state === true) {
                                                $query = User::query()
                                                    ->whereHas('firstLevelGuestsNetwork')
                                                    ->where('is_add_date_of_birth', true);

                                                // Se houver cidade selecionada, filtra por ela
                                                $selectedCity = $get('selected_city');
                                                if (filled($selectedCity)) {
                                                    $query->where('city', $selectedCity);
                                                }

                                                $count = $query->count();

                                                $set('contacts_count_preview', "{$count} contatos");
                                                $set('include_ambassador_network', false);
                                                $set('ambassadors', []);
                                                $set('selected_city', null);
                                            } else {
                                                $set('contacts_count_preview', '0 contatos');
                                            }
                                        })
                                        ->default(false)
                                        ->columnSpan(4),

                                    Select::make('selected_city')
                                        ->label('Cities')
                                        ->options(
                                            fn() => User::query()
                                                ->whereNotNull('city')
                                                ->where('is_add_date_of_birth', true)
                                                ->distinct()
                                                ->orderBy('city')
                                                ->pluck('city', 'city')
                                                ->toArray()
                                        )
                                        ->placeholder(__('Select a city'))
                                        ->searchable()
                                        ->native(false)
                                        ->preload()
                                        ->live(debounce: 0)
                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                            // Só recalcula quando "todos os embaixadores" estiver ativo
                                            if ($get('all_ambassadors') === true) {
                                                $query = User::query()
                                                    ->whereHas('firstLevelGuestsNetwork')
                                                    ->where('is_add_date_of_birth', true);

                                                // Se limpou a cidade, volta a contar todos; se selecionou, filtra
                                                if (filled($state)) {
                                                    $query->where('city', $state);
                                                }

                                                $count = $query->count();
                                                $set('contacts_count_preview', "{$count} contatos");
                                            }
                                        })
                                        ->visible(fn(callable $get) => $get('all_ambassadors') === true)
                                        ->columnSpan(3),
                                ]),

                            Select::make('ambassadors')
                                ->label('Ambassadors')
                                ->helperText('Selecione um ou mais Embaixadores para destino.')
                                ->required(fn(callable $get) => $get('all_ambassadors') !== true)
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
                                ->dehydrated(fn(callable $get) => $get('all_ambassadors') === false)
                                ->disabled(fn(callable $get) => $get('all_ambassadors') === true)
                                ->default(false)
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
                                    'image' => __('Image'),
                                    'video' => __('Video'),
                                ])
                                ->reactive()
                                ->required()
                                ->columnSpan(6),

                            Grid::make(12)->schema([
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
                                    ->maxSize(5120)
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
                                    ->maxSize(16384)
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

                                Textarea::make('description')
                                    ->required()
                                    ->label('Info')
                                    ->placeholder('Conteúdo que substitui {{info}} no Modelo de mensagem.')
                                    ->minLength(5)
                                    ->maxLength(5000)
                                    ->extraAttributes(['id' => 'data.description'])
                                    ->hintAction(
                                        EmojiPickerAction::make('emoji-description')
                                            ->icon('heroicon-o-face-smile')
                                            ->label(__('Choose an emoji'))
                                    )
                                    ->visible(fn(Get $get) => in_array($get('type'), ['image', 'video']))
                                    ->rows(10)
                                    ->columnSpan(6),
                            ]),

                        ]),

                    Grid::make(12)
                        ->schema([
                            Select::make('template_name')
                                ->label('Template')
                                // Apenas cache — nada de chamada remota no render
                                ->options(fn(WhatsAppServiceBusinessApi $svc) => $svc->getTemplate(false))
                                ->searchable()
                                ->preload()
                                ->reactive()

                                // Reidrata ao abrir a tela (edição)
                                ->afterStateHydrated(function ($state, Set $set, Get $get, WhatsAppServiceBusinessApi $svc) {
                                    if (blank($state)) {
                                        return;
                                    }

                                    $info = $svc->templateInfo($state, $get('template_language'));
                                    if ($info) {
                                        $set('template_id', $info['id'] ?? null);
                                        $set('template_language', $info['language'] ?? null);

                                        $components = $info['components'] ?? null;
                                        $set('template_components', is_null($components)
                                            ? null
                                            : (is_string($components) ? $components : json_encode($components, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));

                                        $set('template_preview', $svc->getTemplatePreview($state, $info['language'] ?? null));
                                    }
                                })

                                // Ao selecionar um modelo, popula os Hiddens e o preview
                                ->afterStateUpdated(function ($state, Set $set, Get $get, WhatsAppServiceBusinessApi $svc) {
                                    if (blank($state)) {
                                        // limpa tudo ao des-selecionar
                                        $set('template_preview', null);
                                        $set('template_id', null);
                                        $set('template_language', null);
                                        $set('template_components', null);
                                        return;
                                    }

                                    $preferredLang = $get('template_language'); // pode ser null
                                    $info = $svc->templateInfo($state, $preferredLang);

                                    // Preenche os Hiddens (id / language / components)
                                    $set('template_id', $info['id'] ?? null);
                                    $set('template_language', $info['language'] ?? null);

                                    $components = $info['components'] ?? null;
                                    $set('template_components', is_null($components)
                                        ? null
                                        : (is_string($components) ? $components : $components));

                                    // Atualiza o preview com o idioma resolvido
                                    $langForPreview = $info['language'] ?? $preferredLang;
                                    $set('template_preview', $svc->getTemplatePreview($state, $langForPreview));
                                })

                                ->placeholder('Selecione um modelo')
                                ->hint('Sincronizados a cada 10 min. Clique no ícone “Atualizar” para renovar.')
                                ->suffixAction(
                                    Action::make('refreshTemplates')
                                        ->label('Atualizar')
                                        ->icon('heroicon-m-arrow-path')
                                        ->action(function (WhatsAppServiceBusinessApi $svc, callable $set) {
                                            // limpa seleção atual e força refresh do cache
                                            $set('template_name', null);
                                            $set('template_preview', null);
                                            $set('template_id', null);
                                            $set('template_language', null);
                                            $set('template_components', null);

                                            $opts = $svc->refreshTemplatesCache();

                                            Notification::make()
                                                ->title(empty($opts) ? 'Não foi possível atualizar agora.' : 'Modelos atualizados!')
                                                ->body(empty($opts)
                                                    ? 'Tente novamente em alguns segundos; vou continuar usando o último cache válido.'
                                                    : 'A lista foi recarregada. Abra o select para ver os novos modelos.')
                                                ->success(!empty($opts))
                                                ->warning(empty($opts))
                                                ->send();
                                        })
                                )
                                // opcional: se quiser exibir só os aprovados e que iniciam por "image_" ou "video_"
                                ->options(function (WhatsAppServiceBusinessApi $svc) {
                                    return collect($svc->getTemplate())
                                        ->filter(function ($label, $name) {
                                            return Str::contains($label, '(APPROVED)')
                                                && Str::startsWith(Str::lower($name), [
                                                    'imagem_sem_',
                                                    'imagem_com_',
                                                    'video_sem_',
                                                    'video_com_'
                                                ]);
                                        })
                                        ->mapWithKeys(fn($label, $name) => [$name => $name])
                                        ->all();
                                })
                                ->required()
                                ->columnSpan(6),

                            Textarea::make('template_preview')
                                ->label('Preview of the template')
                                ->hint(__('Select a template first'))
                                ->disabled()
                                ->dehydrated(false)
                                ->afterStateHydrated(function (Set $set, Get $get, WhatsAppServiceBusinessApi $svc) {
                                    $name = $get('template_name');
                                    if ($name) {
                                        $set('template_preview', $svc->getTemplatePreview($name, $get('template_language')));
                                    }
                                })
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
                TextColumn::make('sent_ok_at')
                    ->label(__('Sent at'))
                    ->dateTime(format: 'd/m/Y H:i:s')
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
