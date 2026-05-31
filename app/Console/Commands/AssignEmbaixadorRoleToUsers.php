<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Services\WhatsAppServiceBusinessApi;
use App\Jobs\SendTemplateMessageJob;

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

    public function __construct(protected WhatsAppServiceBusinessApi $whatsAppService)
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
                if ($user->firstLevelGuests()->where('is_add_date_of_birth', true)->exists()) {
                    // Remove current role Membro
                    $user->removeRole('Membro');

                    // Assign new role Embaixador
                    $user->assignRole('Embaixador');

                    // Send message via WhatsApp with email and password
                    $password = generate_custom_alphanumeric_password(8, true, true, true, true);
                    $number = fix_whatsapp_number($user->remoteJid);

                    $user->forceFill([
                        'password' => bcrypt($password),
                    ])->saveQuietly();

                    // Dispatch congratulations template immediately via queue
                    SendTemplateMessageJob::dispatch(
                        $number,
                        'parabens',
                        'pt_BR',
                        [
                            [
                                'type' => 'body',
                                'parameters' => [
                                    ['type' => 'text', 'text' => $user->name]
                                ],
                            ]
                        ]
                    );

                    // Wait 5 seconds before sending the password template
                    sleep(5);

                    // Dispatch password template with a 25-second delay via queue
                    SendTemplateMessageJob::dispatch(
                        $number,
                        'senha',
                        'pt_BR',
                        [
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
                    )->delay(now()->addSeconds(25));
                }
            }
        });
    }
}
