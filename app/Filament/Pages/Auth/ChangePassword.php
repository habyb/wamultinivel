<?php

namespace App\Filament\Pages\Auth;

use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Filament\Notifications\Notification;
use Illuminate\Validation\Rules\Password;

class ChangePassword extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-key';
    protected static string $view = 'filament.pages.auth.change-password';
    protected static ?string $navigationLabel = 'Alterar Senha';

    public function getTitle(): string
    {
        return __(key: 'Change Password');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Settings');
    }

    public $current_password;
    public $password;
    public $password_confirmation;

    protected function getFormSchema(): array
    {
        return [
            TextInput::make('current_password')
                ->label('Senha Atual')
                ->password()
                ->required(),

            TextInput::make('password')
                ->label('Nova Senha')
                ->password()
                ->required()
                ->rules([
                    'confirmed',
                    Password::min(8)
                        ->mixedCase()
                        ->letters()
                        ->numbers(),
                ]),

            TextInput::make('password_confirmation')
                ->label('Confirmar Nova Senha')
                ->password()
                ->required()
                ->same('password'),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        validator($data, [
            'current_password' => ['required'],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)
                    ->mixedCase()
                    ->letters()
                    ->numbers(),
            ],
        ])->validate();

        $user = Auth::user();

        // Valida a senha atual
        if (!Hash::check($this->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'A senha atual está incorreta.',
            ]);
        }

        // Atualiza para nova senha
        $user->forceFill([
            'password' => Hash::make($this->password),
        ])->save();

        Notification::make()
            ->title('Senha alterada com sucesso!')
            ->success()
            ->send();

        $this->reset(['current_password', 'password', 'password_confirmation']);
    }
}
