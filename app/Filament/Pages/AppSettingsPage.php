<?php

namespace App\Filament\Pages;

use App\Settings\AppSettings;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Concerns\InteractsWithNotifications;
use Filament\Forms\Form;
use Filament\Notifications\Notification;

class AppSettingsPage extends Page implements HasForms
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Configurações';
    protected static string $view = 'filament.pages.app-settings-page';

    public ?array $data = [];

    public function mount(): void
    {
        /** @var AppSettings $settings */
        $settings = app(AppSettings::class);

        $this->form->fill([
            'whatsapp_number' => $settings->whatsapp_number,
            'support_email' => $settings->support_email,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('whatsapp_number')
                    ->label('Número do WhatsApp')
                    ->required(),

                Forms\Components\TextInput::make('support_email')
                    ->label('E-mail de Suporte')
                    ->email()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        app(AppSettings::class)->save($this->form->getState());

        Notification::make()
            ->title('Configurações salvas com sucesso!')
            ->success()
            ->send();
    }
}
