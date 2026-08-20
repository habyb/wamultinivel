<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendWhatsappFreeTextJob;

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

    public function __construct()
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

                    // Mensagem de Parabéns
                    $parabensMessages = [
                        "🥳 Parabéns {$user->name}!\n\nAgora você faz parte do nosso time de Embaixadores!\n\nPara acompanhar o crescimento da sua rede de convidados, acesse o link abaixo.\n\nhttps://convite.andrecorrea.com.br",
                        "🎉 Uhuu, {$user->name}! Você acaba de se tornar um de nossos Embaixadores!\n\nAcompanhe de perto a sua rede de convidados pelo link abaixo:\n\nhttps://convite.andrecorrea.com.br",
                        "🎊 Excelente notícia, {$user->name}! Agora você integra o seleto time de Embaixadores!\n\nVeja como a sua rede está crescendo acessando este link:\n\nhttps://convite.andrecorrea.com.br",
                        "🌟 Parabéns, {$user->name}! Você subiu de nível e virou um Embaixador do nosso time!\n\nPara monitorar o crescimento dos seus convidados, clique aqui:\n\nhttps://convite.andrecorrea.com.br",
                        "🚀 Boas-vindas ao nível Embaixador, {$user->name}!\n\nAcompanhe toda a evolução da sua rede de convidados acessando o seu painel no link abaixo:\n\nhttps://convite.andrecorrea.com.br",
                        "👏 Show de bola, {$user->name}! Você alcançou o status de Embaixador na nossa equipe!\n\nFique de olho na sua rede acessando este link:\n\nhttps://convite.andrecorrea.com.br",
                        "🏆 Parabéns pela conquista, {$user->name}! Você é oficialmente um dos nossos Embaixadores!\n\nVocê pode ver todos os seus indicados clicando no link abaixo:\n\nhttps://convite.andrecorrea.com.br",
                        "✨ Muito bem, {$user->name}! Que orgulho ter você no time de Embaixadores!\n\nMonitore o sucesso dos seus convites direto no link a seguir:\n\nhttps://convite.andrecorrea.com.br",
                        "🥳 Parabéns, {$user->name}! Agora você é um Embaixador oficial do nosso projeto!\n\nAcesse o link abaixo para acompanhar todos que entraram pelo seu convite:\n\nhttps://convite.andrecorrea.com.br",
                        "🎉 Sensacional, {$user->name}! Bem-vindo(a) ao grupo de Embaixadores!\n\nPara ver o tamanho da sua rede de convidados, é só acessar o link abaixo:\n\nhttps://convite.andrecorrea.com.br",
                    ];
                    $msgParabens = $parabensMessages[array_rand($parabensMessages)];

                    // Mensagem de Introdução da Senha
                    $msgIntroSenha = "Para acessar o seu painel, utilize o seu número de WhatsApp e a senha provisória abaixo:";
                    
                    // Apenas a senha para facilitar copiar/colar
                    $msgApenasSenha = $password;

                    // Dispatch congratulations immediately via queue
                    // Essa mensagem é grande e terá um delay interno no Uazapi de aprox 7 a 10 segundos
                    SendWhatsappFreeTextJob::dispatch($number, $msgParabens);

                    // Dispatch password intro with a 12-second delay via queue para garantir que chegue DEPOIS da mensagem de parabéns
                    SendWhatsappFreeTextJob::dispatch($number, $msgIntroSenha)->delay(now()->addSeconds(12));

                    // Dispatch exact password with a 17-second delay via queue
                    SendWhatsappFreeTextJob::dispatch($number, $msgApenasSenha)->delay(now()->addSeconds(17));
                }
            }
        });
    }
}
