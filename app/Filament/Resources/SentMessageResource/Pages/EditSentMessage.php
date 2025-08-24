<?php

namespace App\Filament\Resources\SentMessageResource\Pages;

use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\SentMessageResource;
use Filament\Actions\Action;
use App\Filament\Resources\SentMessageResource\Widgets\SentMessagesLogTable;
use Filament\Forms\Form;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\WhatsAppServiceBusinessApi;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Get;
use Filament\Forms\Set;

class EditSentMessage extends EditRecord
{
    protected static string $resource = SentMessageResource::class;

    /**
     * Customize the head title.
     *
     * @return string
     */
    public function getHeading(): string
    {
        return __('Messages');
    }

    /**
     * Custom breadcrumb trail for this page.
     *
     * @return array<string, string|null>
     */
    public function getBreadcrumbs(): array
    {
        return [
            route('filament.admin.resources.send-messages.index') => __('Messages'),
            null => __('View'),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()
            ->hidden(true);
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->hidden(true);
    }

    protected function getFooterWidgets(): array
    {
        return [
            SentMessagesLogTable::class,
        ];
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Grid::make(12)
                ->schema([
                    TextInput::make('title')
                        ->label('Title')
                        ->helperText('Esse título NÃO será visível para o contato no WhatsApp. Este campo é utilizado apenas para identificação.')
                        ->required()
                        ->minLength(5)
                        ->maxLength(255)
                        ->columnSpan(8)
                        ->disabled(),
                ]),

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
                                ->columnSpan(6)
                                ->disabled(),

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
                                    ->helperText('Tamanho máximo permitido: 5 MB')
                                    ->disabled(),

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
                                    ->helperText('Tamanho máximo permitido: 16 MB')
                                    ->disabled(),

                                Textarea::make('description')
                                    ->required()
                                    ->label('Info')
                                    ->hint('Conteúdo que substitui {{info}} no Modelo de mensagem.')
                                    ->minLength(5)
                                    ->maxLength(5000)
                                    ->extraAttributes(['id' => 'data.description'])
                                    ->visible(fn(Get $get) => in_array($get('type'), ['image', 'video']))
                                    ->rows(10)
                                    ->columnSpan(6)
                                    ->disabled(),
                            ]),
                        ]),

                    Grid::make(12)
                        ->schema([
                            Select::make('template_name')
                                ->label('Template')
                                // ⚠️ Apenas cache — nada de chamada remota no render
                                ->options(fn(WhatsAppServiceBusinessApi $svc) => $svc->getTemplate(false))
                                ->searchable()
                                ->preload()
                                ->reactive()
                                ->afterStateUpdated(function ($state, Set $set, Get $get, WhatsAppServiceBusinessApi $svc) {
                                    if (blank($state)) {
                                        $set('template_preview', null);
                                        return;
                                    }
                                    $lang = $get('template_language'); // se tiver esse campo; senão null
                                    $set('template_preview', $svc->getTemplatePreview($state, $lang));
                                })
                                ->placeholder('Selecione um modelo')
                                // opcional: se quiser exibir só os aprovados
                                ->options(function (WhatsAppServiceBusinessApi $svc) {
                                    return collect($svc->getTemplate())
                                        ->filter(fn($label) => str_contains($label, '(APPROVED)'))
                                        ->mapWithKeys(fn($label, $name) => [$name => $name])
                                        ->all();
                                })
                                ->required()
                                ->disabled()
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
                        ->columnSpan(4)
                        ->disabled(),

                    DateTimePicker::make('sent_ok_at')
                        ->label(__('Sent at'))
                        ->suffixIcon('heroicon-m-calendar')
                        ->seconds(false)
                        ->native(false)
                        ->nullable()
                        ->required()
                        ->displayFormat('d/m/Y H:i:s')
                        ->columnSpan(4)
                        ->disabled(),
                ]),
        ]);
    }
}
