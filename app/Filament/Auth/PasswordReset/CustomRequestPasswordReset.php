<?php

namespace App\Filament\Auth\PasswordReset;

use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Password;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\CanResetPassword;
use Filament\Notifications\Auth\ResetPassword as ResetPasswordNotification;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use App\Models\User;
use App\Services\WhatsAppServiceBusinessApi;

class CustomRequestPasswordReset extends RequestPasswordReset
{
    public function form(Form $form): Form
    {
        return $form;
    }

    /**
     * @return array<int | string, string | Form>
     */
    protected function getForms(): array
    {
        return [
            'form' => $this->form(
                $this->makeForm()
                    ->schema([
                        $this->getEmailFormComponent(),
                    ])
                    ->statePath('data'),
            ),
        ];
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label(__('Email or WhatsApp'))
            ->helperText(__('ie: user@domain.com or 219998887777'))
            ->placeholder(__('Enter your Email or Whatsapp with DDD'))
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    protected function getRequestFormAction(): Action
    {
        return Action::make('request')
            ->label(__('Send'))
            ->submit('request');
    }

    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return;
        }

        $data = $this->form->getState();

        $emailOrWhatsapp = $data['email'];

        $login_type = filter_var($emailOrWhatsapp, FILTER_VALIDATE_EMAIL) ? 'email' : 'remoteJid';

        if ($login_type == 'email') {
            $status = Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
                $data,
                function (CanResetPassword $user, string $token): void {
                    if (! method_exists($user, 'notify')) {
                        $userClass = $user::class;

                        throw new Exception("Model [{$userClass}] does not have a [notify()] method.");
                    }

                    $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                    $notification->url = Filament::getResetPasswordUrl($token, $user);

                    $user->notify($notification);
                },
            );

            if ($status !== Password::RESET_LINK_SENT) {
                Notification::make()
                    ->title(__($status))
                    ->danger()
                    ->send();

                return;
            }

            Notification::make()
                ->title(__($status))
                ->success()
                ->send();

            $this->form->fill();
        } elseif ($login_type == 'remoteJid' && is_numeric($emailOrWhatsapp)) {

            $remoteJid = $emailOrWhatsapp . '@s.whatsapp.net';

            $user = User::select('*')
                ->where('remoteJid', $remoteJid)
                ->Orwhere('remoteJid', '55' . $remoteJid)
                ->Orwhere('remoteJid', '55' . remove_third_digit($remoteJid))
                ->first();

            if ($user) {
                // Send message via WhatsApp with email and password
                $password = generate_custom_alphanumeric_password(8, true, true, true, true);
                $number = fix_whatsapp_number(preg_replace('/\D/', '', $user->remoteJid));

                $user->forceFill([
                    'password' => bcrypt($password),
                ])->saveQuietly();

                app(WhatsAppServiceBusinessApi::class)->sendText(
                    phone: $number,
                    template: 'senha',
                    language: 'pt_BR',
                    params: [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $password]
                            ],
                        ],
                        [
                            'type' => 'button',
                            'sub_type' => 'url',
                            'index' => 0,
                            'parameters' => [
                                ['type' => 'text', 'text' => $password]
                            ]
                        ]
                    ]
                );

                app(WhatsAppServiceBusinessApi::class)->sendText(
                    phone: $number,
                    template: 'solicitacao_obrigado',
                    language: 'pt_BR',
                    params: [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', "parameter_name" => "name", 'text' => $user->name]
                            ],
                        ]
                    ]
                );

                Notification::make()
                    ->title(__('We sent your new password by WhatsApp'))
                    ->success()
                    ->send();
            } else {
                Notification::make()
                    ->title(__('We did not find a user with this WhatsApp number'))
                    ->danger()
                    ->send();
            }
        } else {
            Notification::make()
                ->title(__('Fill in correctly your Email or WhatsApp number'))
                ->danger()
                ->send();
        }
    }
}
