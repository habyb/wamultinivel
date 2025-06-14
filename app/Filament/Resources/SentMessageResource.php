<?php

namespace App\Filament\Resources;

use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Get;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\SentMessage;
use Filament\Resources\Resource;
use Filament\Forms\Components\Grid;
use Illuminate\Validation\Rules\File;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Forms\Components\Placeholder;
use App\Filament\Resources\SentMessageResource\Pages;
use TangoDevIt\FilamentEmojiPicker\EmojiPickerAction;
use App\Filament\Resources\SentMessageResource\RelationManagers;
use Carbon\Carbon;

class SentMessageResource extends Resource
{
    protected static ?string $model = SentMessage::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left';
    protected static ?string $slug = 'send-messages';

    public static function getModelLabel(): string
    {
        return __(key: 'Send messages');
    }

    public static function getNavigationGroup(): string
    {
        return __('Messages');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title')
                    ->label('Title')
                    ->helperText('Esse título NÃO será visível para o contato no WhatsApp. Este campo é utilizado apenas para identificação.')
                    ->required()
                    ->minLength(5)
                    ->maxLength(255)
                    ->columnSpan('full'),

                Forms\Components\Select::make('cities')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('neighborhoods')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('genders')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('age_groups')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('concerns_01')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('concerns_02')
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
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    }),

                Forms\Components\Select::make('type')
                    ->columnSpan(1)
                    ->label('Message type')
                    ->options([
                        'text' => __('Text message'),
                        'image' => __('Image with description'),
                        'document' => __('Document with description'),
                        'video' => __('Video with description'),
                        'audio' => __('Audio'),
                    ])
                    ->reactive()
                    ->required(),

                // image, document, video, audio
                Grid::make(1)
                    ->schema([
                        Grid::make()
                            ->schema([
                                // image
                                Forms\Components\FileUpload::make('path')
                                    ->label('Imagem')
                                    ->acceptedFileTypes(['image/jpeg', 'image/png'])
                                    ->disk('public')
                                    ->directory('messages')
                                    ->preserveFilenames()
                                    ->deleteUploadedFileUsing(function (string $file) {
                                        Storage::disk('public')->delete($file);
                                    })
                                    ->visible(fn(callable $get) => $get('type') === 'image')
                                    ->required(fn(callable $get) => $get('type') === 'image'),

                                // document
                                Forms\Components\FileUpload::make('path')
                                    ->label('Documento')
                                    ->acceptedFileTypes([
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.ms-powerpoint',
                                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                        'application/pdf',
                                    ])
                                    ->disk('public')
                                    ->directory('messages')
                                    ->preserveFilenames()
                                    ->deleteUploadedFileUsing(function (string $file) {
                                        Storage::disk('public')->delete($file);
                                    })
                                    ->visible(fn(callable $get) => $get('type') === 'document')
                                    ->required(fn(callable $get) => $get('type') === 'document'),

                                // video
                                Forms\Components\FileUpload::make('path')
                                    ->label('Arquivo de Vídeo')
                                    ->acceptedFileTypes(['video/mp4'])
                                    ->disk('public')
                                    ->directory('messages')
                                    ->preserveFilenames()
                                    ->deleteUploadedFileUsing(function (string $file) {
                                        Storage::disk('public')->delete($file);
                                    })
                                    ->visible(fn(callable $get) => $get('type') === 'video')
                                    ->required(fn(callable $get) => $get('type') === 'video'),

                                // audio
                                Forms\Components\FileUpload::make('path')
                                    ->label('Arquivo de Áudio')
                                    ->acceptedFileTypes(['audio/mpeg'])
                                    ->disk('public')
                                    ->directory('messages')
                                    ->preserveFilenames()
                                    ->deleteUploadedFileUsing(function (string $file) {
                                        Storage::disk('public')->delete($file);
                                    })
                                    ->maxSize(10240) // 10 MB
                                    ->visible(fn(callable $get) => $get('type') === 'audio')
                                    ->required(fn(callable $get) => $get('type') === 'audio'),
                            ])
                            ->columnSpan(1),
                    ]),

                // description (text, image, document, video)
                Grid::make(1)
                    ->schema([
                        Grid::make()
                            ->schema([
                                Forms\Components\Textarea::make('description')
                                    ->columnSpan(1)
                                    ->required()
                                    ->label('Description')
                                    ->minLength(5)
                                    ->maxLength(5000)
                                    ->extraAttributes(['id' => 'data.description'])
                                    ->hintAction(
                                        EmojiPickerAction::make('emoji-description')
                                            ->icon('heroicon-o-face-smile')
                                            ->label(__('Choose an emoji'))
                                    )
                                    ->visible(fn(Get $get) => in_array($get('type'), ['text', 'image', 'document', 'video'])),
                            ])
                            ->columnSpan(1),
                    ]),

                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Agendar envio para')
                    ->helperText('Selecione data e hora do envio. Pode ser deixado em branco.')
                    ->suffixIcon('heroicon-m-calendar')
                    ->seconds(false)
                    ->native(false)
                    ->nullable()
                    ->displayFormat('d/m/Y H:i')
                    ->rule(function () {
                        return function ($attribute, $value, $fail) {
                            if ($value && Carbon::parse($value)->lt(now()->addMinutes(2))) {
                                $fail('A data e hora devem ser pelo menos 2 minutos no futuro.');
                            }
                        };
                    }),

                Forms\Components\TextInput::make('contacts_count_preview')
                    ->label('Contacts found')
                    ->disabled()
                    ->dehydrated(false)
                    ->afterStateHydrated(function ($state, callable $get, callable $set) {
                        $ageGroups = $get('age_groups');

                        $count = \App\Models\User::query()
                            ->when($get('cities'), fn($q) => $q->whereIn('city', $get('cities')))
                            ->when($get('neighborhoods'), fn($q) => $q->whereIn('neighborhood', $get('neighborhoods')))
                            ->when($get('genders'), fn($q) => $q->whereIn('gender', $get('genders')))
                            ->when($get('concerns_01'), fn($q) => $q->whereIn('concern_01', $get('concerns_01')))
                            ->when($get('concerns_02'), fn($q) => $q->whereIn('concern_02', $get('concerns_02')))
                            ->get()
                            ->filter(function ($user) use ($ageGroups) {
                                // check age group
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

                                    return false; // age is not within any track
                                }

                                return true; // no age filter, keep user
                            })
                            ->count();

                        $set('contacts_count_preview', "{$count} contatos");
                    })
            ]);
    }

    public static function rules(): array
    {
        return [
            'type' => ['required', 'in:text,image,document,video,audio'],
            'description' => ['nullable', 'string'],
            'path' => [
                'nullable',
                function (File $file) {
                    return $file->when(fn($input) => in_array($input['type'], ['image', 'document', 'video', 'audio']), function ($rule, $value, $fail) use (&$input) {
                        $type = $input['type'] ?? null;

                        match ($type) {
                            'image' => $rule->mimes(['jpg', 'jpeg', 'png']),
                            'document' => $rule->mimes(['document', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']),
                            'video' => $rule->mimes(['mp4']),
                            'audio' => $rule->mimes(['mp3']),
                            default => null,
                        };
                    });
                },
            ],
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime(format: 'd/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
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
