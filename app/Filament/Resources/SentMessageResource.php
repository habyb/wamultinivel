<?php

namespace App\Filament\Resources;

use Carbon\Carbon;
use Filament\Forms;
use App\Models\User;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Models\SentMessage;
use Filament\Resources\Resource;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use App\Services\WhatsAppServiceBusinessApi;
use App\Filament\Resources\SentMessageResource\Pages;

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

    protected static function getApprovedTemplates(): array
    {
        return collect(app(WhatsAppServiceBusinessApi::class)->getTemplate())
            ->where('status', 'APPROVED')
            ->pluck('name', 'name')
            ->toArray();
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
                    ->required(),

                Textarea::make('template_preview')
                    ->label('Preview of the template')
                    ->hint(__('Select a template first'))
                    ->disabled()
                    ->rows(10)
                    ->columnSpan(1)
                    ->reactive(),

                Forms\Components\DateTimePicker::make('sent_at')
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
                    }),

                Hidden::make('template_id')
                    ->dehydrated(true)
                    ->default(null),

                Hidden::make('template_language')
                    ->dehydrated(true)
                    ->default(null),

                Hidden::make('template_components')
                    ->dehydrated(true)
                    ->default(null),


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
                            ->where('is_add_date_of_birth', true)
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sent_at')
                    ->label('Sent at')
                    ->dateTime(format: 'd/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Created at')
                    ->dateTime()
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
