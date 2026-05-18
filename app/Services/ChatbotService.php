<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChatbotService
{
    protected $whatsapp;
    protected $statePrefix = 'chatbot:state:';

    public function __construct(WhatsAppServiceBusinessApi $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function processMessage(array $contact, array $message)
    {
        $waId = $contact['wa_id'];
        $profileName = $contact['profile']['name'] ?? 'Amigo(a)';
        
        $text = '';
        if (($message['type'] ?? '') === 'text') {
            $text = $message['text']['body'] ?? '';
        } elseif (($message['type'] ?? '') === 'interactive') {
            $text = $message['interactive']['button_reply']['id'] ?? 
                    $message['interactive']['button_reply']['title'] ?? 
                    $message['interactive']['list_reply']['title'] ?? 
                    $message['interactive']['list_reply']['id'] ?? '';
        }

        // Log::info("Processing message from $waId: $text");

        // 1. Verificar se é uma mensagem de cadastro com ID
        if (preg_match('/ID:\s*([A-Z0-9]+)/i', $text, $matches)) {
            $invitationCode = $matches[1];
            return $this->handleInitialRegistration($waId, $profileName, $invitationCode);
        }

        // 2. Verificar estado no Cache
        $state = Cache::get($this->statePrefix . $waId);

        if (!$state) {
            $user = User::where('remoteJid', fix_whatsapp_number($waId))->first();
            
            // Caso 1: Usuário já completou o cadastro
            if ($user && $user->is_add_date_of_birth) {
                $text = trim($text);
                if (in_array($text, ['SIM', 'NÃO'])) {
                    $msg = "\n\nMuito obrigado por sua resposta. Sua participação é muito importante para nós e nos ajuda a melhorar cada vez mais.\n\n" .
                        "Acompanhe nosso trabalho nessas áreas através de minhas redes sociais:\n\n" .
                        "*📘 Facebook:* https://www.facebook.com/depandrecorrea1\n" .
                        "*📸 Instagram:* https://instagram.com/depandrecorrea\n" .
                        "*🌐 Site:* https://www.andrecorrea.com.br/";

                    return $this->sendReply($waId, "Olá *{$user->name}*!$msg");
                }

                $inviteLink = config('app.url') . '/' . ($user->code ?: '');

                $msg = "você já faz parte do nosso time vencedor! Segue o seu link de convite. Compartilhe! 🔗✨\n\n" .
                    "*Seu link de convite:*\n" . 
                    "{$inviteLink}\n\n" .
                    "Acompanhe nosso trabalho através de minhas redes sociais:\n\n" .
                    "*📘 Facebook:* https://www.facebook.com/depandrecorrea1\n" .
                    "*📸 Instagram:* https://instagram.com/depandrecorrea\n" .
                    "*🌐 Site:* https://www.andrecorrea.com.br/";

                return $this->sendReply($waId, "{$user->name}, $msg");
            }
            
            // Caso 2: Usuário não existe (Tentativa de contato sem ID de convite)
            if (!$user) {
                return $this->sendReply($waId, "❌ Ops, ocorreu um erro. Por favor, envie a mensagem de cadastro com ID de convite válido.");
            }

            // Caso 3: Usuário existe mas não completou o cadastro
            // Reinicia o fluxo de boas-vindas
            return $this->sendInitialWelcome($waId);
        }

        return $this->handleStateAction($waId, $state, $text);
    }

    protected function sendInitialWelcome($waId)
    {
        $this->setStep($waId, 'AWAITING_REGISTRATION_CONFIRMATION');
        
        $greeting = get_greeting();
        $msg = "Olá. {$greeting}! Seja Bem-vindo(a) ao time do Dep. André Corrêa.\n\n" .
               "Que ótimo ter você aqui! 🎉\n" .
               "Percebi que este é o nosso primeiro contato, e para continuarmos essa conversa, preciso da sua autorização para enviar informativos e novidades da nossa equipe. 📩\n\n" .
               "Basta tocar no botão abaixo para confirmar. 👇";
        
        return $this->whatsapp->sendInteractiveButtons($waId, $msg, [
            'confirm_yes' => 'Sim, quero receber',
            'confirm_no' => 'Talvez depois',
        ]);
    }

    protected function handleInitialRegistration($waId, $name, $invitationCode)
    {
        // Validar se o código de convite existe
        $referrer = User::where('code', $invitationCode)->first();
        if (!$referrer) {
            return $this->sendReply($waId, "❌ Por favor, envie a mensagem de cadastro com ID de convite válido.");
        }

        $user = User::where('remoteJid', fix_whatsapp_number($waId))->first();

        if ($user && $user->is_add_date_of_birth) {
            // Cenário A: Usuário Completo
            $inviteLink = config('app.url') . '/' . ($user->code ?: '');
            $msg = "você faz parte do nosso time vencedor! Segue o seu link de convite. Compartilhe! 🔗✨\n\n" .
            "*Seu link de convite:*\n" . 
            "{$inviteLink}\n\n" .
            "Acompanhe nosso trabalho através de minhas redes sociais:\n\n" .
            "*📘 Facebook:* https://www.facebook.com/depandrecorrea1\n" .
            "*📸 Instagram:* https://instagram.com/depandrecorrea\n" .
            "*🌐 Site:* https://www.andrecorrea.com.br/";

            $this->sendReply($waId, "{$user->name}, $msg");
            return;
        }

        if (!$user) {
            // Criar novo usuário
            $user = User::create([
                'name' => $name,
                'email' => $waId . '@s.whatsapp.net',
                'password' => bcrypt(Str::random(16)),
                'remoteJid' => $waId,
                'is_remote_jid' => true,
                'invitation_code' => $invitationCode,
                'code' => strtoupper(Str::random(10)),
            ]);
        } else {
            // Atualizar código de convite se ainda não estiver completo
            $user->update([
                'invitation_code' => $invitationCode,
                'is_remote_jid' => true,
            ]);
        }

        // Iniciar fluxo de onboarding
        return $this->sendInitialWelcome($waId);
    }

    protected function handleStateAction($waId, $state, $text)
    {
        // Log::info("Handling state $state for $waId with text: $text");

        $textLower = strtolower(trim($text));
        $user = User::where('remoteJid', fix_whatsapp_number($waId))->first();

        if (!$user) {
            return $this->sendReply($waId, "Ops, ocorreu um erro. Por favor, envie a mensagem de cadastro novamente.");
        }

        switch ($state) {
            case 'AWAITING_REGISTRATION_CONFIRMATION':
                if (Str::contains($textLower, ['sim', 'quero', 'ok', 'confirm_yes'])) {
                    $this->setStep($waId, 'AWAITING_NAME');
                    $user->update(['is_question_name' => true]);
                    $this->sendReply($waId, "Legal! Vamos começar. Digite seu *Nome e Sobrenome*.");
                } else {
                    $msg = "Sem problemas! 😊\n" .
                        "Quando estiver pronto(a), estarei por aqui para continuar nossa conversa.\n" .
                        "Fique à vontade para me chamar quando quiser saber mais ou receber novidades. 😉";

                    $this->sendReply($waId, $msg);
                    $this->clearStep($waId);
                }
                break;

            case 'AWAITING_NAME':
                $user->update([
                    'name' => $text,
                    'is_add_name' => true,
                    'is_question_city' => true
                ]);
                $this->setStep($waId, 'AWAITING_CITY');
                $this->sendReply($waId, "Legal {$text}, agora por favor digite o nome da sua *Cidade*.");
                break;

            case 'AWAITING_CITY':
                $user->update([
                    'city' => $text,
                    'is_add_city' => true
                ]);

                if (Str::contains(strtolower($text), ['rio de janeiro', 'rj'])) {
                    $this->setStep($waId, 'AWAITING_NEIGHBORHOOD');
                    $user->update(['is_question_neighborhood' => true]);
                    $this->sendReply($waId, "Vimos que você é do Rio de Janeiro! Por favor, digite seu *Bairro*.");
                } else {
                    $this->askConcern01($waId, $user, $text);
                }
                break;

            case 'AWAITING_NEIGHBORHOOD':
                $user->update([
                    'neighborhood' => $text,
                    'is_add_neighborhood' => true
                ]);
                $this->askConcern01($waId, $user, $user->city);
                break;

            case 'AWAITING_CONCERN_01':
                $concerns = collect($this->getConcernsList()[0]['rows'])->pluck('title')->toArray();
                if (!in_array($text, $concerns)) {
                    $this->sendReply($waId, "🚫 Resposta não permitida. Selecione uma resposta da lista.");
                    return $this->askConcern01($waId, $user, $user->city);
                }

                $user->update([
                    'concern_01' => $text,
                    'is_add_concern_01' => true,
                    'is_question_concern_02' => true
                ]);
                $this->setStep($waId, 'AWAITING_CONCERN_02');
                $this->whatsapp->sendListMessage(
                    $waId, 
                    "Escolha uma das opções na lista.", 
                    "Selecione", 
                    $this->getConcernsList(),
                    "Segunda preocupação"
                );
                break;

            case 'AWAITING_CONCERN_02':
                $concerns = collect($this->getConcernsList()[0]['rows'])->pluck('title')->toArray();
                if (!in_array($text, $concerns)) {
                    $this->sendReply($waId, "🚫 Resposta não permitida. Selecione uma resposta da lista.");
                    return $this->whatsapp->sendListMessage(
                        $waId, 
                        "Escolha uma das opções na lista.", 
                        "Selecione", 
                        $this->getConcernsList(),
                        "Segunda preocupação"
                    );
                }

                $user->update([
                    'concern_02' => $text,
                    'is_add_concern_02' => true,
                    'is_question_gender' => true
                ]);
                $this->setStep($waId, 'AWAITING_GENDER');
                $this->whatsapp->sendListMessage($waId, "Escolha uma das opções na lista.", "Selecione", [
                    [
                        'title' => 'Gênero',
                        'rows' => [
                            ['id' => 'Masculino', 'title' => 'Masculino', 'description' => 'Pessoas do gênero masculino'],
                            ['id' => 'Feminino', 'title' => 'Feminino', 'description' => 'Pessoas do gênero feminino'],
                            ['id' => 'Outro', 'title' => 'Não informar', 'description' => 'Prefiro não informar'],
                        ]
                    ]
                ], "Selecione o seu Gênero");
                break;

            case 'AWAITING_GENDER':
                $genders = ['Masculino', 'Feminino', 'Não informar'];
                if (!in_array($text, $genders)) {
                    $this->sendReply($waId, "🚫 Resposta não permitida. Selecione uma resposta da lista.");
                    return $this->whatsapp->sendListMessage($waId, "Escolha uma das opções na lista.", "Selecione", [
                        [
                            'title' => 'Gênero',
                            'rows' => [
                                ['id' => 'Masculino', 'title' => 'Masculino', 'description' => 'Pessoas do gênero masculino'],
                                ['id' => 'Feminino', 'title' => 'Feminino', 'description' => 'Pessoas do gênero feminino'],
                                ['id' => 'Outro', 'title' => 'Não informar', 'description' => 'Prefiro não informar'],
                            ]
                        ]
                    ], "Selecione o seu Gênero");
                }

                $user->update([
                    'gender' => $text,
                    'is_add_gender' => true,
                    'is_question_date_of_birth' => true
                ]);
                $this->setStep($waId, 'AWAITING_DATE_OF_BIRTH');
                $this->sendReply($waId, "Digite sua *Data de Nascimento*.\nDigite apenas os números.\nEx: *01011980*");
                break;

            case 'AWAITING_DATE_OF_BIRTH':
                $dateInput = preg_replace('/\D/', '', $text);
                $formattedDate = null;

                if (strlen($dateInput) === 6) {
                    $day = substr($dateInput, 0, 2);
                    $month = substr($dateInput, 2, 2);
                    $year = substr($dateInput, 4, 2);
                    
                    // Lógica para ano: se > 26 (ano atual 2026), assume 19XX, senão 20XX
                    $year = (int)$year > 26 ? "19$year" : "20$year";
                    $dateInput = $day . $month . $year;
                }

                if (strlen($dateInput) === 8) {
                    $day = substr($dateInput, 0, 2);
                    $month = substr($dateInput, 2, 2);
                    $year = substr($dateInput, 4, 4);
                    
                    if (checkdate((int)$month, (int)$day, (int)$year)) {
                        $formattedDate = "$day/$month/$year";
                    }
                }

                if (!$formattedDate) {
                    return $this->sendReply($waId, "🚫 A data informada é inválida.\nPor favor revise e tente novamente.");
                }

                $code = $user->code ?: strtoupper(Str::random(10));

                $user->update([
                    'date_of_birth' => $formattedDate,
                    'is_add_date_of_birth' => true,
                    'code' => $code,
                ]);
                $this->clearStep($waId);
                
                $inviteLink = config('app.url') . '/' . $code;
                $msg = "você faz parte do nosso time vencedor! Segue o seu link de convite. Compartilhe! 🔗✨\n\n" .
                "*Seu link de convite:*\n" . 
                "{$inviteLink}\n\n" .
                "Acompanhe nosso trabalho através de minhas redes sociais:\n\n" .
                "*📘 Facebook:* https://www.facebook.com/depandrecorrea1\n" .
                "*📸 Instagram:* https://instagram.com/depandrecorrea\n" .
                "*🌐 Site:* https://www.andrecorrea.com.br/";
                
                $this->sendReply($waId, "{$user->name}, $msg");
                break;
        }
    }

    protected function askConcern01($waId, $user, $city)
    {
        $this->setStep($waId, 'AWAITING_CONCERN_01');
        $user->update(['is_question_concern_01' => true]);
        
        $this->whatsapp->sendListMessage(
            $waId, 
            "Escolha uma das opções na lista.", 
            "Selecione", 
            $this->getConcernsList(),
            "Principal preocupação"
        );
    }

    protected function getConcernsList()
    {
        return [
            [
                'title' => 'Preocupações',
                'rows' => [
                    ['id' => 'Asfalto ruim', 'title' => 'Asfalto ruim', 'description' => 'Ruas esburacadas e de difícil acesso.'],
                    ['id' => 'Cultura e Lazer', 'title' => 'Cultura e Lazer', 'description' => 'Falta de espaços culturais e recreativos.'],
                    ['id' => 'Falta de água', 'title' => 'Falta de água', 'description' => 'Escassez ou interrupção no abastecimento.'],
                    ['id' => 'Falta de creches', 'title' => 'Falta de creches', 'description' => 'Poucas vagas para crianças pequenas.'],
                    ['id' => 'Falta de emprego', 'title' => 'Falta de emprego', 'description' => 'Escassez de oportunidades de trabalho.'],
                    ['id' => 'Iluminação e segurança', 'title' => 'Iluminação e segurança', 'description' => 'Ruas escuras e alto índice de crimes.'],
                    ['id' => 'Qualidade na educação', 'title' => 'Qualidade na educação', 'description' => 'Ensino deficiente e precário.'],
                    ['id' => 'Saneamento básico', 'title' => 'Saneamento básico', 'description' => 'Falta de esgoto e água tratada.'],
                    ['id' => 'Saúde precária', 'title' => 'Saúde precária', 'description' => 'Falta de médicos e estrutura hospitalar.'],
                    ['id' => 'Transporte insuficiente', 'title' => 'Transporte insuficiente', 'description' => 'Poucos ônibus e lotação diária.'],
                ]
            ]
        ];
    }

    protected function setStep($waId, $step)
    {
        Cache::put($this->statePrefix . $waId, $step, now()->addDay());
    }

    protected function clearStep($waId)
    {
        Cache::forget($this->statePrefix . $waId);
    }

    protected function sendReply($waId, $text)
    {
        // Log::info("Sending reply to $waId: $text");
        return $this->whatsapp->sendFreeText($waId, $text);
    }
}
