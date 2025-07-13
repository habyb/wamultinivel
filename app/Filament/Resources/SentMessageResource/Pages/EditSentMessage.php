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
            ->hidden(fn() => $this->record->status === 'sent');
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->hidden(fn() => $this->record->status === 'sent');
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
                                ->columnSpan(6)
                                ->disabled(),

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
                        ->columnSpan(4)
                        ->disabled(),
                ]),
        ]);
    }
}
