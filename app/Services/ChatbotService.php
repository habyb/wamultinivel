<?php

namespace App\Services;

use App\Jobs\SendWhatsappFreeTextJob;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Contracts\WhatsAppServiceInterface;

class ChatbotService
{
    protected $whatsapp;
    protected $statePrefix = 'chatbot:state:';

    // Hash bcrypt pré-computado para a string 'password'
    protected static string $dummyPasswordHash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';

    public function __construct(WhatsAppServiceInterface $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function processMessage(array $contact, array $message)
    {
        $waId = $contact['wa_id'];
        $profileName = $contact['profile']['name'] ?? 'Amigo(a)';
        
        $type = $message['type'] ?? '';
        $text = '';

        if ($type === 'text') {
            $body = $message['text']['body'] ?? '';
            $text = is_string($body) ? $body : '';
        } elseif ($type === 'interactive') {
            $btn = $message['interactive']['button_reply']['id'] ?? 
                   $message['interactive']['button_reply']['title'] ?? 
                   $message['interactive']['list_reply']['title'] ?? 
                   $message['interactive']['list_reply']['id'] ?? '';
            $text = is_string($btn) ? $btn : '';
        } elseif ($type === 'button') {
            $btn = $message['button']['text'] ?? 
                   $message['button']['payload'] ?? '';
            $text = is_string($btn) ? $btn : '';
        }

        // Se o tipo for algo diferente (áudio, imagem, doc) ou o texto extraído for nulo/vazio e for arquivo
        $mediaTypes = ['image', 'audio', 'video', 'document', 'sticker', 'location', 'imagemessage', 'audiomessage', 'videomessage', 'documentmessage', 'stickermessage', 'locationmessage', 'vcard', 'contactmessage'];
        if (in_array(strtolower($type), $mediaTypes) || (empty($text) && !in_array($type, ['text', 'interactive', 'button']))) {
            return $this->sendReply($waId, "Ainda não consigo entender áudios, imagens ou outros tipos de arquivos. Por favor, digite em texto.");
        };

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
                    $msg = "\n\nMuito obrigado por sua resposta. Sua participação é muito importante para nós e nos ajuda a melhorar cada vez mais.";

                    return $this->sendReply($waId, "Olá *{$user->name}*!$msg");
                }

                $code = $user->code ?: '';

                $alreadyRegisteredMessages = [
                    "{$user->name}, você já faz parte do nosso time vencedor! 🚀\n\nUtilize a mensagem abaixo para facilitar o compartilhamento com seus amigos! 👇",
                    "Olá, {$user->name}! Vi sua mensagem, mas fique tranquilo(a): o seu cadastro já está confirmado e você já faz parte do nosso time vencedor! 🚀\n\nDá uma olhada na mensagem aqui embaixo! É só copiar e colar no grupo da galera. 👇",
                    "Tudo certo por aqui, {$user->name}! Você já concluiu essa etapa de inscrição e está oficialmente no nosso time vencedor! 🚀\n\nPreguiça de digitar? Tranquilo! Copie a mensagem abaixo e mande para os seus amigos. 😜",
                    "Opa, {$user->name}! Não precisa se cadastrar de novo, sua vaga no nosso time vencedor já está super garantida! 🚀\n\nBora compartilhar? Copie o textinho abaixo e espalhe a novidade! ✨",
                    "Que bom te ver novamente, {$user->name}! Só para te avisar que o seu registro já está pronto e você já é do nosso time vencedor! 🚀\n\nCopie a mensagem abaixo e mande para os seus contatos! 😉",
                    "Ei, {$user->name}! O sistema já reconheceu o seu número por aqui. Você já completou a inscrição no nosso time vencedor! 🚀\n\nMensagem pronta para enviar aos amigos: é só copiar e encaminhar! 📲",
                    "Pode relaxar, {$user->name}! Seus dados já estão com a gente e você já integra o nosso time vencedor! 🚀\n\nQuer avisar seus amigos? Use o texto abaixo, já deixamos tudo pronto pra você! 👇",
                    "Cadastro detectado com sucesso, {$user->name}! Você já finalizou esse processo e já faz parte do nosso time vencedor! 🚀\n\nPra facilitar sua vida, preparamos essa mensagem. É só copiar e mandar pro pessoal! 🙌",
                    "Boas notícias, {$user->name}: não falta mais nada do seu lado! Você já está 100% cadastrado(a) no nosso time vencedor! 🚀\n\nCompartilhe essa ideia com seus amigos utilizando a mensagem sugerida abaixo. 🤝",
                    "Tudo pronto com o seu perfil, {$user->name}! Você já passou por essa fase e já é membro do nosso time vencedor! 🚀\n\nNão deixe seus amigos de fora! Copie o texto abaixo e envie pra eles agora mesmo. 🏃‍♂️💨",
                ];
                $msg1 = $alreadyRegisteredMessages[array_rand($alreadyRegisteredMessages)];

                $inviteMessages = [
                    "✉️ *Convite especial!* Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor. Para participar, é só clicar no link abaixo e responder 2 perguntas rápidas.",
                    "🌟 *Convite Exclusivo!* Gostaria muito que você fizesse parte do Time André Corrêa. Somos um grupo focado em trabalho sério e em construir um amanhã melhor. Para entrar, basta acessar o link abaixo e responder a duas perguntinhas.",
                    "📩 *Você recebeu um convite!* Venha integrar o Time André Corrêa, uma equipe dedicada ao trabalho de verdade por um futuro promissor. É super fácil participar: clique no link a seguir e responda 2 perguntas bem rápidas.",
                    "🎯 *Um convite para você!* Quero te chamar para o Time André Corrêa. Aqui, o nosso foco é o comprometimento para garantir um futuro melhor para todos. Para confirmar sua participação, clique no link abaixo e responda a 2 perguntas.",
                    "✨ *Olhe esse convite especial!* Estou te chamando para o Time André Corrêa. Somos uma equipe que valoriza o esforço e a construção de dias melhores. Faça parte clicando no link abaixo e respondendo a duas questões curtas.",
                    "✉️ *Convite importante!* Faça parte da nossa rede no Time André Corrêa. Juntos, acreditamos que o trabalho sério constrói um futuro de verdade. Para se juntar a nós, acesse o link abaixo e responda apenas 2 perguntas.",
                    "🤝 *Chegou um convite para você!* Venha caminhar com o Time André Corrêa, um grupo que aposta no trabalho duro para transformar o amanhã. É muito simples participar: clique no link e complete 2 respostas rápidas.",
                    "Olá! Tenho um *convite super especial* para você. Venha somar forças com o Time André Corrêa! Acreditamos no poder da dedicação para melhorar o nosso futuro. Topa? É só clicar no link abaixo e preencher 2 questões rápidas.",
                    "🚀 *Convite imperdível!* Junte-se ao Time André Corrêa e faça parte de uma equipe comprometida com resultados e um futuro muito melhor. Para começar, toque no link abaixo e responda a 2 perguntas super rápidas.",
                    "💙 *Um convite especial passando por aqui!* Quero te convidar para o Time André Corrêa, uma equipe que acredita de verdade que o trabalho sério muda o futuro. Clique no link abaixo, responda 2 perguntas rapidinho e venha com a gente!",
                ];
                
                $msg2 = $inviteMessages[array_rand($inviteMessages)] . "\n\n" .
                        "https://convite.andrecorrea.com.br/{$code}\n\n" .
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
        $messages = [
            "Olá. {$greeting}! Seja Bem-vindo(a) ao time do Dep. André Corrêa.\n\nPercebi que este é o nosso primeiro contato e para continuarmos basta tocar no botão abaixo. Ao continuar, você aceita receber nossos informativos. 📩👇",
            "Olá, {$greeting}! Que bom ter você no time do Dep. André Corrêa! Como é a nossa primeira conversa, clique no botão abaixo para prosseguir e confirmar que deseja receber nossas novidades. 🤝👇",
            "{$greeting}! Seja muito bem-vindo(a) à equipe do Deputado André Corrêa. Vi aqui que é o nosso primeiro contato. Para iniciar e aceitar nossos comunicados, é só tocar no botão a seguir. 📲👇",
            "Oi! {$greeting}! Bem-vindo(a) ao grupo de apoio do Dep. André Corrêa. Para darmos o primeiro passo e você concordar em receber nossas mensagens, basta clicar no botão abaixo. ✨👇",
            "Saudações! {$greeting}! É um prazer ter você no time do Dep. André Corrêa. Como ainda não nos falamos antes, toque no botão abaixo para continuar e aceitar nossos informativos. 🚀👇",
            "Olá! {$greeting}! Você agora faz parte da rede do Dep. André Corrêa, seja bem-vindo(a)! Para liberar nosso papo e confirmar o recebimento de atualizações, clique no botão aqui embaixo. 📩👇",
            "{$greeting}! Que alegria receber você no time do Dep. André Corrêa! Sendo esta a nossa primeira troca de mensagens, toque no botão abaixo para avançar e aceitar nossa comunicação. ✅👇",
            "Tudo bem? {$greeting}! Bem-vindo(a) à nossa equipe do Deputado André Corrêa. Para começar oficialmente e permitir o envio das nossas notícias, você só precisa clicar no botão abaixo. 📰👇",
            "Oi, {$greeting}! Seja muito bem-vindo(a) ao projeto do Dep. André Corrêa! Para continuarmos nosso primeiro contato e você autorizar nossos informativos, toque no botão a seguir. 🎯👇",
            "Olá! {$greeting}! É ótimo ter você com o Dep. André Corrêa. Como estamos começando a conversar agora, clique no botão abaixo para confirmar sua entrada e aceitar receber nossos alertas. 🔔👇",
        ];
        $msg = $messages[array_rand($messages)];
        
        return $this->whatsapp->sendInteractiveButtons($waId, $msg, [
            'confirm_yes' => 'Continuar',
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

                $alreadyRegisteredMessages = [
                    "{$user->name}, *você já está cadastrado* e faz parte do nosso time vencedor! 🚀\n\nUtilize a mensagem abaixo para facilitar o compartilhamento com seus amigos! 👇",
                    "Olá, {$user->name}! Vi sua mensagem, mas fique tranquilo(a): o seu cadastro já está confirmado e você já faz parte do nosso time vencedor! 🚀\n\nDá uma olhada na mensagem aqui embaixo! É só copiar e colar no grupo da galera. 🚀",
                    "Tudo certo por aqui, {$user->name}! Você já concluiu essa etapa de inscrição e está oficialmente no nosso time vencedor! 🚀\n\nPreguiça de digitar? Tranquilo! Copie a mensagem abaixo e mande para os seus amigos. 😜",
                    "Opa, {$user->name}! Não precisa se cadastrar de novo, sua vaga no nosso time vencedor já está super garantida! 🚀\n\nBora compartilhar? Copie o textinho abaixo e espalhe a novidade! ✨",
                    "Que bom te ver novamente, {$user->name}! Só para te avisar que o seu registro já está pronto e você já é do nosso time vencedor! 🚀\n\nCopie a mensagem abaixo e mande para os seus contatos! 😉",
                    "Ei, {$user->name}! O sistema já reconheceu o seu número por aqui. Você já completou a inscrição no nosso time vencedor! 🚀\n\nMensagem pronta para enviar aos amigos: é só copiar e encaminhar! 📲",
                    "Pode relaxar, {$user->name}! Seus dados já estão com a gente e você já integra o nosso time vencedor! 🚀\n\nQuer avisar seus amigos? Use o texto abaixo, já deixamos tudo pronto pra você! 👇",
                    "Cadastro detectado com sucesso, {$user->name}! Você já finalizou esse processo e já faz parte do nosso time vencedor! 🚀\n\nPra facilitar sua vida, preparamos essa mensagem. É só copiar e mandar pro pessoal! 🙌",
                    "Boas notícias, {$user->name}: não falta mais nada do seu lado! Você já está 100% cadastrado(a) no nosso time vencedor! 🚀\n\nCompartilhe essa ideia com seus amigos utilizando a mensagem sugerida abaixo. 🤝",
                    "Tudo pronto com o seu perfil, {$user->name}! Você já passou por essa fase e já é membro do nosso time vencedor! 🚀\n\nNão deixe seus amigos de fora! Copie o texto abaixo e envie pra eles agora mesmo. 🏃‍♂️💨",
                ];
                $msg1 = $alreadyRegisteredMessages[array_rand($alreadyRegisteredMessages)];

                $inviteMessages = [
                    "✉️ *Convite especial!* Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor. Para participar, é só clicar no link abaixo e responder 2 perguntas rápidas.",
                    "🌟 *Convite Exclusivo!* Gostaria muito que você fizesse parte do Time André Corrêa. Somos um grupo focado em trabalho sério e em construir um amanhã melhor. Para entrar, basta acessar o link abaixo e responder a duas perguntinhas.",
                    "📩 *Você recebeu um convite!* Venha integrar o Time André Corrêa, uma equipe dedicada ao trabalho de verdade por um futuro promissor. É super fácil participar: clique no link a seguir e responda 2 perguntas bem rápidas.",
                    "🎯 *Um convite para você!* Quero te chamar para o Time André Corrêa. Aqui, o nosso foco é o comprometimento para garantir um futuro melhor para todos. Para confirmar sua participação, clique no link abaixo e responda a 2 perguntas.",
                    "✨ *Olhe esse convite especial!* Estou te chamando para o Time André Corrêa. Somos uma equipe que valoriza o esforço e a construção de dias melhores. Faça parte clicando no link abaixo e respondendo a duas questões curtas.",
                    "✉️ *Convite importante!* Faça parte da nossa rede no Time André Corrêa. Juntos, acreditamos que o trabalho sério constrói um futuro de verdade. Para se juntar a nós, acesse o link abaixo e responda apenas 2 perguntas.",
                    "🤝 *Chegou um convite para você!* Venha caminhar com o Time André Corrêa, um grupo que aposta no trabalho duro para transformar o amanhã. É muito simples participar: clique no link e complete 2 respostas rápidas.",
                    "Olá! Tenho um *convite super especial* para você. Venha somar forças com o Time André Corrêa! Acreditamos no poder da dedicação para melhorar o nosso futuro. Topa? É só clicar no link abaixo e preencher 2 questões rápidas.",
                    "🚀 *Convite imperdível!* Junte-se ao Time André Corrêa e faça parte de uma equipe comprometida com resultados e um futuro muito melhor. Para começar, toque no link abaixo e responda a 2 perguntas super rápidas.",
                    "💙 *Um convite especial passando por aqui!* Quero te convidar para o Time André Corrêa, uma equipe que acredita de verdade que o trabalho sério muda o futuro. Clique no link abaixo, responda 2 perguntas rapidinho e venha com a gente!",
                ];
                
                $msg2 = $inviteMessages[array_rand($inviteMessages)] . "\n\n" .
                        "https://convite.andrecorrea.com.br/{$code}\n\n" .
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
                        'password' => self::$dummyPasswordHash,
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

            default:
                $this->clearStep($waId);
                return $this->sendReply($waId, "Ops, algo deu errado. Por favor, tente novamente.");
        }
    }

    protected function processAwaitingRegistrationConfirmation($waId, User $user, $text)
    {
        $textLower = strtolower(trim($text));
        $textClean = preg_replace('/[!.?~,;]/', '', $textLower);

        if (in_array($textClean, ['confirm_yes', 'continuar', 'sim', 'yes', 'ok', 'aceito', 'quero', 'sim, quero receber'])) {
            $this->setStep($waId, 'AWAITING_NAME');
            $user->update(['is_question_name' => true]);
            $namePrompts = [
                "Perfeito! Para começarmos, qual o seu nome completo?",
                "Legal! Me diga seu nome e sobrenome, por favor.",
                "Tudo certo! Pode me informar seu nome completo para o cadastro?",
                "Excelente! Para dar andamento ao seu cadastro, digite seu nome e sobrenome.",
                "Maravilha! Vamos iniciar: por favor, digite o seu nome completo aqui.",
                "Ótimo! O primeiro passo é simples: qual é o seu nome e sobrenome?",
                "Muito bom! Para eu registrar você no nosso time, me envie seu nome completo.",
                "Show! Vamos começar o seu cadastro. Pode digitar seu nome e sobrenome, por favor?",
                "Tudo pronto por aqui! Para iniciar nossa conversa, qual é o seu nome completo?",
                "Que legal ter você com a gente! Escreva seu nome e sobrenome para eu te cadastrar.",
            ];
            $this->sendReply($waId, $namePrompts[array_rand($namePrompts)]);
        } else {
            $this->sendReply($waId, "⚠️ Resposta não permitida. Por favor, clique no botão *Continuar* para prosseguir.");
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
        $cityPrompts = [
            "Legal {$finalName}, agora por favor digite o nome da sua *Cidade*.",
            "Perfeito, {$finalName}! Para continuarmos, me diga de qual *Cidade* você é.",
            "Ótimo, {$finalName}. Qual é o nome da sua *Cidade*?",
            "Tudo certo, {$finalName}! Agora eu só preciso saber a sua *Cidade*.",
            "Muito bom, {$finalName}! Digite o nome da sua *Cidade* para avançarmos.",
            "Anotado, {$finalName}! Em qual *Cidade* você mora atualmente?",
            "Excelente, {$finalName}! Agora, por favor, me informe a sua *Cidade*.",
            "Maravilha, {$finalName}. Para finalizarmos essa etapa, digite a sua *Cidade*.",
            "Show de bola, {$finalName}! Me escreve aqui o nome da sua *Cidade*.",
            "Beleza, {$finalName}! Qual é a *Cidade* onde você mora?",
        ];
        $this->sendReply($waId, $cityPrompts[array_rand($cityPrompts)]);
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

        $code = $user->code ?: strtoupper(Str::random(10));

        $user->update([
            'city' => $finalCity,
            'is_add_city' => true,
            'is_add_date_of_birth' => true,
            'code' => $code,
        ]);
        $this->clearStep($waId);

        $completionMessages = [
            "{$user->name}, agora você faz parte do nosso time vencedor! 🚀\n\nUtilize a mensagem abaixo para facilitar o compartilhamento com seus amigos!",
            "Oficializado, {$user->name}! Você já está no nosso time vencedor! 🚀\n\nUse o texto logo abaixo para convidar seus amigos com facilidade.",
            "Parabéns, {$user->name}! Você acaba de entrar para o nosso time vencedor! 🚀\n\nCompartilhe a mensagem abaixo com seus amigos para facilitar o convite.",
            "Que incrível, {$user->name}! Agora você integra o nosso time vencedor! 🚀\n\nPara chamar seus amigos, é só usar a mensagem prontinha aqui embaixo.",
            "{$user->name}, seja muito bem-vindo ao nosso time vencedor! 🚀\n\nFacilite o convite para seus amigos encaminhando a mensagem a seguir.",
            "Prontinho, {$user->name}! Sua vaga no nosso time vencedor está garantida! 🚀\n\nBasta copiar ou encaminhar a mensagem abaixo para os seus contatos.",
            "Show, {$user->name}! Você entrou de vez para o nosso time vencedor! 🚀\n\nPara te ajudar a trazer mais pessoas, preparamos a mensagem abaixo.",
            "Tudo certo, {$user->name}! É uma honra ter você no nosso time vencedor! 🚀\n\nAproveite o texto abaixo para compartilhar facilmente com seus amigos.",
            "Feito, {$user->name}! Você já é parte oficial do nosso time vencedor! 🚀\n\nUse a mensagem a seguir para espalhar essa novidade por aí.",
            "Boas-vindas oficiais, {$user->name}! Agora você está no nosso time vencedor! 🚀\n\nEncaminhe a mensagem abaixo para seus amigos e facilite o compartilhamento.",
        ];
        $msg1 = $completionMessages[array_rand($completionMessages)];

        $inviteMessages = [
            "✉️ *Convite especial!* Quero te convidar para fazer parte do Time André Corrêa, uma equipe que acredita no trabalho sério e na construção de um futuro melhor. Para participar, é só clicar no link abaixo e responder 2 perguntas rápidas.",
            "🌟 *Convite Exclusivo!* Gostaria muito que você fizesse parte do Time André Corrêa. Somos um grupo focado em trabalho sério e em construir um amanhã melhor. Para entrar, basta acessar o link abaixo e responder a duas perguntinhas.",
            "📩 *Você recebeu um convite!* Venha integrar o Time André Corrêa, uma equipe dedicada ao trabalho de verdade por um futuro promissor. É super fácil participar: clique no link a seguir e responda 2 perguntas bem rápidas.",
            "🎯 *Um convite para você!* Quero te chamar para o Time André Corrêa. Aqui, o nosso foco é o comprometimento para garantir um futuro melhor para todos. Para confirmar sua participação, clique no link abaixo e responda a 2 perguntas.",
            "✨ *Olhe esse convite especial!* Estou te chamando para o Time André Corrêa. Somos uma equipe que valoriza o esforço e a construção de dias melhores. Faça parte clicando no link abaixo e respondendo a duas questões curtas.",
            "✉️ *Convite importante!* Faça parte da nossa rede no Time André Corrêa. Juntos, acreditamos que o trabalho sério constrói um futuro de verdade. Para se juntar a nós, acesse o link abaixo e responda apenas 2 perguntas.",
            "🤝 *Chegou um convite para você!* Venha caminhar com o Time André Corrêa, um grupo que aposta no trabalho duro para transformar o amanhã. É muito simples participar: clique no link e complete 2 respostas rápidas.",
            "Olá! Tenho um *convite super especial* para você. Venha somar forças com o Time André Corrêa! Acreditamos no poder da dedicação para melhorar o nosso futuro. Topa? É só clicar no link abaixo e preencher 2 questões rápidas.",
            "🚀 *Convite imperdível!* Junte-se ao Time André Corrêa e faça parte de uma equipe comprometida com resultados e um futuro muito melhor. Para começar, toque no link abaixo e responda a 2 perguntas super rápidas.",
            "💙 *Um convite especial passando por aqui!* Quero te convidar para o Time André Corrêa, uma equipe que acredita de verdade que o trabalho sério muda o futuro. Clique no link abaixo, responda 2 perguntas rapidinho e venha com a gente!",
        ];
        $msg2 = $inviteMessages[array_rand($inviteMessages)] . "\n\n" .
                "https://convite.andrecorrea.com.br/{$code}\n\n" .
                "Contamos com você nessa caminhada!";

        $response = $this->sendReply($waId, $msg1);

        SendWhatsappFreeTextJob::dispatch($waId, $msg2)->delay(now()->addSeconds(3));

        return $response;
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
