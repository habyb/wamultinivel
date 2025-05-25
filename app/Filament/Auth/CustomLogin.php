<?php

namespace App\Filament\Auth;

use Filament\Pages\Auth\Login;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class CustomLogin extends Login
{
    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getLoginFormComponent(),
                        $this->getPasswordFormComponent(),
                        $this->getRememberFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getLoginFormComponent(): Component
    {
        return TextInput::make('login')
            ->label('Email or WhatsApp')
            ->helperText(__('ie: user@domain.com or 219998887777'))
            ->placeholder(__('Enter your Email or Whatsapp with DDD'))
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login_type = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'remoteJid';

        if ($login_type == 'remoteJid') {
            $number = $data['login'];

            $user = User::select('*')
                ->where('remoteJid', $number . '@s.whatsapp.net')
                ->Orwhere('remoteJid', '55' . $number . '@s.whatsapp.net')
                ->Orwhere('remoteJid', '55' . remove_third_digit($number) . '@s.whatsapp.net')
                ->first();

            if ($user) {
                $data['login'] = $user->remoteJid;
            } else {
                $data['login'] = '@';
            }

            // dd($data['login']);
        }

        return [
            $login_type => $data['login'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.login' => __('filament-panels::pages/auth/login.messages.failed'),
        ]);
    }
}
