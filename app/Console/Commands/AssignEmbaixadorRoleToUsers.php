<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppService;

class AssignEmbaixadorRoleToUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:assign-embaixador-role-to-users';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assigns the Embaixador role to users who have one or more guests';

    public function __construct(protected WhatsAppService $whatsAppService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Assigns the Membro role to users without any role
        DB::transaction(function () {
            $usersDoesntHaveRoles = User::doesntHave('roles')->get();

            foreach ($usersDoesntHaveRoles as $user) {
                $user->assignRole('Membro');

                $user->forceFill([
                    'email' => strtolower($user->email),
                ])->saveQuietly();
            }
        });

        // Assigns the Embaixador role to users with convidado direto
        DB::transaction(function () {
            // Fetch all users with role Membro
            $users = User::role('Membro')->get();

            foreach ($users as $user) {
                if ($user->firstLevelGuests()->where('is_add_email', true)->exists()) {
                    // Remove current role Membro
                    $user->removeRole('Membro');

                    // Assign new role Embaixador
                    $user->assignRole('Embaixador');

                    // Send message via WhatsApp with email and password
                    $password = generate_custom_alphanumeric_password(8, true, true, true, true);
                    $number = fix_whatsapp_number(preg_replace('/\D/', '', $user->remoteJid));
                    $text = "🥳 Parabéns *$user->name*!\n";
                    $text .= "Agora você faz parte do nosso time de Embaixadores!\n";
                    $text .= "Para acompanhar o crescimento da sua rede de convidados, acesse o link abaixo e insira seus dados para login.\n\n";
                    $text .= "https://convite.andrecorrea.com.br\n";
                    $text .= "*WhatsApp:* $number\n";
                    $text .= "*Senha:* $password";

                    $user->forceFill([
                        'password' => bcrypt($password),
                    ])->saveQuietly();

                    $this->whatsAppService->sendText($number, $text);
                }
            }
        });
    }
}
