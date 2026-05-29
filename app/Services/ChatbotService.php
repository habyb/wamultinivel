<?php

namespace App\Services;

use App\Jobs\SendWhatsappFreeTextJob;
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
        } elseif (($message['type'] ?? '') === 'button') {
            $text = $message['button']['text'] ?? 
                    $message['button']['payload'] ?? '';
        }

        // Log::info("Processing message from $waId: $text");

        // 1. Comando de reset/reiniciar (Escape)
        if (Str::upper(trim($text)) === 'REINICIAR') {
            $this->clearStep($waId);
            return $this->sendReply($waId, "🔄 Tudo bem! O fluxo foi reiniciado. Para começar novamente, clique no link de convite ou envie a mensagem inicial de cadastro.");
        }

        // 2. Verificar se é uma mensagem de cadastro com ID
        if (preg_match('/ID:\s*([A-Z0-9]+)/i', $text, $matches)) {
            $invitationCode = $matches[1];
            return $this->handleInitialRegistration($waId, $profileName, $invitationCode);
        }

        // 2. Verificar estado no Cache
        $state = Cache::get($this->statePrefix . $waId);

        if (!$state) {
            $user = User::where('remoteJid', $waId)->first();
            
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

                $code = $user->code ?: '';

                $msg1 = "{$user->name}, você já faz parte do nosso time vencedor! 🚀\n\n" .
                        "Utilize a mensagem abaixo para facilitar o compartilhamento com seus amigos!";

                $msg2 = "Convite especial!\n\n" .
                        "Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor.\n\n" .
                        "Para participar, é só clicar no link abaixo e responder 5 perguntas rápidas.\n\n" .
                        "https://convite.andrecorrea.com.br/{$code}\n\n" .
                        "Também estou enviando os links das redes sociais do deputado para você conhecer melhor seu trabalho.\n\n" .
                        "📘 Facebook: https://www.facebook.com/depandrecorrea1\n" .
                        "📸 Instagram: https://instagram.com/depandrecorrea\n" .
                        "🌐 Site: https://www.andrecorrea.com.br/\n\n" .
                        "Contamos com você nessa caminhada!";

                $response = $this->sendReply($waId, $msg1);

                SendWhatsappFreeTextJob::dispatch($waId, $msg2)->delay(now()->addSeconds(3));

                return $response;
            }
            
            // Caso 2: Usuário não existe (Tentativa de contato sem ID de convite)
            if (!$user) {
                return $this->sendReply($waId, "⚠️ Ops, ocorreu um erro. Por favor, envie a mensagem de cadastro com ID de convite válido.");
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
        // Lock atômico por até 10 segundos, aguardando até 5 segundos para obter o lock
        $lock = Cache::lock('chatbot:register:' . $waId, 10);

        try {
            $lock->block(5);

            // Validar se o código de convite existe
            $referrer = User::where('code', $invitationCode)->first();
            if (!$referrer) {
                return $this->sendReply($waId, "⚠️ Por favor, envie a mensagem de cadastro com ID de convite válido.");
            }

            $user = User::where('remoteJid', $waId)->first();

            if ($user && $user->is_add_date_of_birth) {
                // Cenário A: Usuário Completo
                $code = $user->code ?: '';

                $msg1 = "{$user->name}, *você já está cadastrado* e faz parte do nosso time vencedor! 🚀\n\n" .
                        "Utilize a mensagem abaixo para facilitar o compartilhamento com seus amigos!";

                $msg2 = "Convite especial!\n\n" .
                        "Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor.\n\n" .
                        "Para participar, é só clicar no link abaixo e responder 5 perguntas rápidas.\n\n" .
                        "https://convite.andrecorrea.com.br/{$code}\n\n" .
                        "Também estou enviando os links das redes sociais do deputado para você conhecer melhor seu trabalho.\n\n" .
                        "📘 Facebook: https://www.facebook.com/depandrecorrea1\n" .
                        "📸 Instagram: https://instagram.com/depandrecorrea\n" .
                        "🌐 Site: https://www.andrecorrea.com.br/\n\n" .
                        "Contamos com você nessa caminhada!";

                $this->sendReply($waId, $msg1);

                SendWhatsappFreeTextJob::dispatch($waId, $msg2)->delay(now()->addSeconds(3));

                return;
            }

            if (!$user) {
                try {
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
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Caso ocorra exceção de concorrência, busca o usuário existente
                    $user = User::where('remoteJid', $waId)->first();
                    if (!$user) {
                        $user = User::where('email', $waId . '@s.whatsapp.net')->first();
                    }

                    if ($user) {
                        $user->update([
                            'invitation_code' => $invitationCode,
                            'is_remote_jid' => true,
                        ]);
                    } else {
                        throw $e;
                    }
                }
            } else {
                // Atualizar código de convite se ainda não estiver completo
                $user->update([
                    'invitation_code' => $invitationCode,
                    'is_remote_jid' => true,
                ]);
            }

            // Iniciar fluxo de onboarding
            return $this->sendInitialWelcome($waId);

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning("Lock timeout ao tentar registrar o usuário com waId: " . $waId);
            return $this->sendReply($waId, "⚠️ Processando sua mensagem, por favor aguarde um momento.");
        } finally {
            $lock->release();
        }
    }

    protected function handleStateAction($waId, $state, $text)
    {
        $user = User::where('remoteJid', $waId)->first();

        if (!$user) {
            return $this->sendReply($waId, "Ops, ocorreu um erro. Por favor, envie a mensagem de cadastro novamente.");
        }

        switch ($state) {
            case 'AWAITING_REGISTRATION_CONFIRMATION':
                return $this->processAwaitingRegistrationConfirmation($waId, $user, $text);

            case 'AWAITING_NAME':
                return $this->processAwaitingName($waId, $user, $text);

            case 'AWAITING_CITY':
                return $this->processAwaitingCity($waId, $user, $text);

            case 'AWAITING_NEIGHBORHOOD':
                return $this->processAwaitingNeighborhood($waId, $user, $text);

            case 'AWAITING_CONCERN_01':
                return $this->processAwaitingConcern01($waId, $user, $text);

            case 'AWAITING_CONCERN_02':
                return $this->processAwaitingConcern02($waId, $user, $text);

            case 'AWAITING_GENDER':
                return $this->processAwaitingGender($waId, $user, $text);

            case 'AWAITING_DATE_OF_BIRTH':
                return $this->processAwaitingDateOfBirth($waId, $user, $text);

            default:
                $this->clearStep($waId);
                return $this->sendReply($waId, "Ops, algo deu errado. Por favor, tente novamente.");
        }
    }

    protected function processAwaitingRegistrationConfirmation($waId, User $user, $text)
    {
        $textLower = strtolower(trim($text));

        if (in_array($textLower, ['confirm_yes', 'sim, quero receber'])) {
            $this->setStep($waId, 'AWAITING_NAME');
            $user->update(['is_question_name' => true]);
            $this->sendReply($waId, "Legal! Vamos começar. Digite seu *Nome e Sobrenome*.");
        } elseif (in_array($textLower, ['confirm_no', 'talvez depois'])) {
            $msg = "Sem problemas! 😊\n" .
                "Quando estiver pronto(a), estarei por aqui para continuar nossa conversa.\n" .
                "Fique à vontade para me chamar quando quiser saber mais ou receber novidades. 😉";

            $this->sendReply($waId, $msg);
            $this->clearStep($waId);
        } else {
            $this->sendReply($waId, "⚠️ Resposta não permitida. Selecione uma resposta dos botões.");
        }
    }

    protected function processAwaitingName($waId, User $user, $text)
    {
        $cleanedName = preg_replace('/\s+/', ' ', trim($text));
        
        // 1. Validar apenas letras e espaços
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/u', $cleanedName)) {
            return $this->sendReply($waId, "⚠️ O nome deve conter apenas letras. Por favor, tente novamente.");
        }

        // 2. Validar pelo menos duas palavras e comprimento mínimo
        $parts = explode(' ', $cleanedName);
        if (count($parts) < 2 || mb_strlen($cleanedName) < 5) {
            return $this->sendReply($waId, "⚠️ Por favor, digite seu nome *completo* (Nome e Sobrenome).");
        }

        // 3. Limite máximo
        if (mb_strlen($cleanedName) > 100) {
            return $this->sendReply($waId, "⚠️ O nome digitado é muito longo. Por favor, tente abreviar um pouco.");
        }

        // 4. Normalizar para Title Case (mantendo preposições em lowercase)
        $prepositions = ['da', 'de', 'do', 'das', 'dos', 'e'];
        $normalizedParts = array_map(function ($part, $index) use ($prepositions) {
            $partLower = mb_strtolower($part);
            if ($index > 0 && in_array($partLower, $prepositions)) {
                return $partLower;
            }
            return mb_convert_case($partLower, MB_CASE_TITLE, "UTF-8");
        }, $parts, array_keys($parts));
        
        $finalName = implode(' ', $normalizedParts);

        $user->update([
            'name' => $finalName,
            'is_add_name' => true,
            'is_question_city' => true
        ]);
        $this->setStep($waId, 'AWAITING_CITY');
        $this->sendReply($waId, "Legal {$finalName}, agora por favor digite o nome da sua *Cidade*.");
    }

    protected function processAwaitingCity($waId, User $user, $text)
    {
        // 1. Limpeza inicial e remoção de sufixos de estado (ex: Valença/RJ, Valença - RJ, Valença RJ)
        $cleanedCity = preg_replace('/\s+/', ' ', trim($text));
        // Regex para remover separadores e siglas de estado no final (2 letras maiúsculas ou minúsculas precedidas por espaço, traço, barra ou vírgula)
        $cleanedCity = preg_replace('/[\/\-\,\s]+[a-zA-Z]{2}$/', '', $cleanedCity);
        $cleanedCity = trim($cleanedCity);

        // 2. Validar apenas letras e espaços
        if (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/u', $cleanedCity)) {
            return $this->sendReply($waId, "⚠️ O nome da cidade deve conter apenas letras. Por favor, tente novamente.");
        }

        // 3. Validação de tamanho
        if (mb_strlen($cleanedCity) < 3 || mb_strlen($cleanedCity) > 50) {
            return $this->sendReply($waId, "⚠️ O nome da cidade parece inválido. Por favor, digite o nome completo da sua cidade.");
        }

        // 4. Normalizar para Title Case (mantendo preposições em lowercase)
        $parts = explode(' ', mb_strtolower($cleanedCity));
        $prepositions = ['da', 'de', 'do', 'das', 'dos', 'e'];
        $normalizedParts = array_map(function ($part, $index) use ($prepositions) {
            if ($index > 0 && in_array($part, $prepositions)) {
                return $part;
            }
            return mb_convert_case($part, MB_CASE_TITLE, "UTF-8");
        }, $parts, array_keys($parts));
        
        $finalCity = implode(' ', $normalizedParts);

        $user->update([
            'city' => $finalCity,
            'is_add_city' => true
        ]);

        if ($finalCity === 'Rio de Janeiro') {
            $this->setStep($waId, 'AWAITING_NEIGHBORHOOD');
            $user->update(['is_question_neighborhood' => true]);
            $this->sendReply($waId, "Vimos que você é do Rio de Janeiro! Por favor, digite seu *Bairro*.");
        } else {
            $this->askConcern01($waId, $user, $finalCity);
        }
    }

    protected function processAwaitingNeighborhood($waId, User $user, $text)
    {
        $cleanedNeighborhood = preg_replace('/\s+/', ' ', trim($text));

        // 1. Validar letras, números, espaços e hifens
        if (!preg_match('/^[a-zA-ZÀ-ÿ0-9\s\-]+$/u', $cleanedNeighborhood)) {
            return $this->sendReply($waId, "⚠️ O nome do bairro contém caracteres inválidos. Por favor, digite apenas letras e números.");
        }

        // 2. Validação de tamanho
        if (mb_strlen($cleanedNeighborhood) < 2 || mb_strlen($cleanedNeighborhood) > 50) {
            return $this->sendReply($waId, "⚠️ O nome do bairro parece inválido. Por favor, verifique e tente novamente.");
        }

        // 3. Normalizar para Title Case (mantendo preposições em lowercase)
        $parts = explode(' ', mb_strtolower($cleanedNeighborhood));
        $prepositions = ['da', 'de', 'do', 'das', 'dos', 'e'];
        $normalizedParts = array_map(function ($part, $index) use ($prepositions) {
            if ($index > 0 && in_array($part, $prepositions)) {
                return $part;
            }
            return mb_convert_case($part, MB_CASE_TITLE, "UTF-8");
        }, $parts, array_keys($parts));
        
        $finalNeighborhood = implode(' ', $normalizedParts);

        $user->update([
            'neighborhood' => $finalNeighborhood,
            'is_add_neighborhood' => true
        ]);
        $this->askConcern01($waId, $user, $user->city);
    }

    protected function processAwaitingConcern01($waId, User $user, $text)
    {
        $concerns = collect($this->getConcernsList()[0]['rows'])->pluck('title')->toArray();
        if (!in_array($text, $concerns)) {
            $this->sendReply($waId, "⚠️ Resposta não permitida. Selecione uma resposta da lista.");
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
    }

    protected function processAwaitingConcern02($waId, User $user, $text)
    {
        $concerns = collect($this->getConcernsList()[0]['rows'])->pluck('title')->toArray();
        if (!in_array($text, $concerns)) {
            $this->sendReply($waId, "⚠️ Resposta não permitida. Selecione uma resposta da lista.");
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
    }

    protected function processAwaitingGender($waId, User $user, $text)
    {
        $genders = ['Masculino', 'Feminino', 'Não informar'];
        if (!in_array($text, $genders)) {
            $this->sendReply($waId, "⚠️ Resposta não permitida. Selecione uma resposta da lista.");
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
    }

    protected function processAwaitingDateOfBirth($waId, User $user, $text)
    {
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
            return $this->sendReply($waId, "⚠️ A data informada é inválida.\nPor favor revise e tente novamente.");
        }

        $code = $user->code ?: strtoupper(Str::random(10));

        $user->update([
            'date_of_birth' => $formattedDate,
            'is_add_date_of_birth' => true,
            'code' => $code,
        ]);
        $this->clearStep($waId);
        
        $msg1 = "{$user->name}, agora você faz parte do nosso time vencedor! 🚀\n\n" .
                "Utilize a mensagem abaixo para facilitar o compartilhamento com seus amigos!";
                
        $msg2 = "Convite especial!\n\n" .
                "Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor.\n\n" .
                "Para participar, é só clicar no link abaixo e responder 5 perguntas rápidas.\n\n" .
                "https://convite.andrecorrea.com.br/{$code}\n\n" .
                "Também estou enviando os links das redes sociais do deputado para você conhecer melhor seu trabalho.\n\n" .
                "📘 Facebook: https://www.facebook.com/depandrecorrea1\n" .
                "📸 Instagram: https://instagram.com/depandrecorrea\n" .
                "🌐 Site: https://www.andrecorrea.com.br/\n\n" .
                "Contamos com você nessa caminhada!";
        
        $response = $this->sendReply($waId, $msg1);
        
        SendWhatsappFreeTextJob::dispatch($waId, $msg2)->delay(now()->addSeconds(3));
        
        return $response;
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
        Cache::put($this->statePrefix . $waId, $step, now()->addDays(7));
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
