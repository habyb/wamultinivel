<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SentMessageResource\Pages;
use App\Filament\Resources\SentMessageResource\RelationManagers;
use App\Models\SentMessage;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use App\Models\User;
use Filament\Forms\Get;
use Illuminate\Validation\Rules\File;
use TangoDevIt\FilamentEmojiPicker\EmojiPickerAction;

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
                    ->helperText('Esse título será visível para o contato no WhatsApp.')
                    ->required()
                    ->minLength(5)
                    ->maxLength(255)
                    ->columnSpan('full'),

                Forms\Components\Select::make('cities')
                    ->label('Cities')
                    ->helperText('Selecione uma ou mais cidades para destino.')
                    ->multiple()
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
                    ->searchable(),

                Forms\Components\Select::make('neighborhoods')
                    ->label('Neighborhoods')
                    ->helperText('Selecione um ou mais bairros para destino.')
                    ->multiple()
                    ->options(function () {
                        return User::select('neighborhood')
                            ->distinct()
                            ->orderBy('neighborhood')
                            ->pluck('neighborhood', 'neighborhood')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable(),

                Forms\Components\Select::make('genders')
                    ->label('Genders')
                    ->helperText('Selecione um ou mais gêneros para destino.')
                    ->multiple()
                    ->options(function () {
                        return User::select('gender')
                            ->distinct()
                            ->orderBy('gender')
                            ->pluck('gender', 'gender')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable(),

                Forms\Components\Select::make('age_groups')
                    ->label('Age groups')
                    ->helperText('Selecione uma ou mais faixas etárias.')
                    ->multiple()
                    ->native(false)
                    ->dehydrated(true)
                    ->options([
                        '16-30' => '16-30',
                        '31-40' => '31-40',
                        '41-50' => '41-50',
                        '51-60' => '51-60',
                        '60+'   => '60+',
                    ])
                    ->searchable(),

                Forms\Components\Select::make('concerns_01')
                    ->label('Main concerns')
                    ->helperText('Selecione um ou mais preocupações principais para destino.')
                    ->multiple()
                    ->options(function () {
                        return User::select('concern_01')
                            ->distinct()
                            ->orderBy('concern_01')
                            ->pluck('concern_01', 'concern_01')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable(),

                Forms\Components\Select::make('concerns_02')
                    ->label('Secondary concerns')
                    ->helperText('Selecione um ou mais preocupações secundárias para destino.')
                    ->multiple()
                    ->options(function () {
                        return User::select('concern_02')
                            ->distinct()
                            ->orderBy('concern_02')
                            ->pluck('concern_02', 'concern_02')
                            ->filter()
                            ->toArray();
                    })
                    ->searchable(),

                Forms\Components\Select::make('type')
                    ->label('Message type')
                    ->options([
                        'text' => __('Text message'),
                        'image' => __('Image with description'),
                        'doc' => __('Document with description'),
                        'video' => __('Video with description'),
                        'audio' => __('Audio'),
                    ])
                    ->live()
                    ->required(),

                Forms\Components\Group::make()
                    ->columnSpanFull()
                    ->schema([
                        // File upload (image, doc, video, audio)
                        Forms\Components\FileUpload::make('path')
                            ->required()
                            ->label('File')
                            ->visible(fn(Get $get) => in_array($get('type'), ['image', 'doc', 'video', 'audio']))
                            ->disk('public')
                            ->directory('messages')
                            ->preserveFilenames()
                            ->maxFiles(1)
                            ->acceptedFileTypes(function (Get $get) {
                                return match ($get('type')) {
                                    'image' => ['image/jpeg', 'image/png', 'image/jpg'],
                                    'doc' => [
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.ms-powerpoint',
                                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                    ],
                                    'video' => ['video/mp4'],
                                    'audio' => ['audio/mpeg'], // mp3
                                    default => [],
                                };
                            }),

                        // Description (text, image, doc, video)
                        Forms\Components\Textarea::make('description')
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
                            ->visible(fn(Get $get) => in_array($get('type'), ['text', 'image', 'doc', 'video'])),
                    ]),

                Forms\Components\DateTimePicker::make('sent_at')
                    ->label('Agendar envio para')
                    ->helperText('Selecione data e hora do envio. Pode ser deixado em branco.')
                    ->suffixIcon('heroicon-m-calendar')
                    ->seconds(false)
                    ->native(false)
                    ->nullable(),
            ]);
    }

    public static function rules(): array
    {
        return [
            'type' => ['required', 'in:text,image,doc,video,audio'],
            'description' => ['nullable', 'string'],
            'path' => [
                'nullable',
                function (File $file) {
                    return $file->when(fn($input) => in_array($input['type'], ['image', 'doc', 'video', 'audio']), function ($rule, $value, $fail) use (&$input) {
                        $type = $input['type'] ?? null;

                        match ($type) {
                            'image' => $rule->mimes(['jpg', 'jpeg', 'png']),
                            'doc' => $rule->mimes(['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx']),
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
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'primary' => 'pending',
                        'success' => 'sent',
                        'danger' => 'failed',
                    ])
                    ->sortable(),
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
