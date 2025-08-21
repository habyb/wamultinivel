<?php

namespace App\Filament\Auth\PasswordReset;

use Filament\Pages\Auth\PasswordReset\RequestPasswordReset;
use Filament\Forms\Form;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Facades\Password;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordMail;
use Illuminate\Support\Facades\Notification as MailNotification; // facade p/ enviar sem fila
use Filament\Notifications\Notification; // mantém os toasts do Filament
use Filament\Actions\Action;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Exception;
use App\Models\User;
use App\Services\WhatsAppServiceBusinessApi;
use App\Jobs\SendPasswordMessageJob;

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
            // Garante um broker válido (cai no defaults se o Panel não definir)
            $brokerName = Filament::getAuthPasswordBroker() ?: config('auth.defaults.passwords', 'users');

            $status = Password::broker($brokerName)->sendResetLink(
                ['email' => $data['email']], // passe apenas o campo esperado
                function (CanResetPassword $user, string $token): void {
                    if (! method_exists($user, 'notify')) {
                        $userClass = $user::class;
                        throw new \Exception("Model [{$userClass}] does not have a [notify()] method.");
                    }

                    // Faz a notificação usar a URL do Filament para reset
                    ResetPasswordMail::createUrlUsing(
                        fn($notifiable, string $tok) =>
                        Filament::getResetPasswordUrl($tok, $notifiable)
                    );

                    // Cria a notificação nativa do Laravel
                    $notification = new ResetPasswordMail($token);

                    // Envia **agora** (síncrono), mesmo que a notificação implemente ShouldQueue
                    MailNotification::sendNow($user, $notification);
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
            return;
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

                dispatch(new SendPasswordMessageJob($number, $password))->delay(now()->addSeconds(3));

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
