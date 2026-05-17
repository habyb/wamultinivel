<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class ChatbotService
{
    protected $whatsapp;
    protected $redisPrefix = 'chatbot:state:';

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
                    $message['interactive']['button_reply']['title'] ?? '';
        }

        Log::info("Processing message from $waId: $text");

        // 1. Verificar se é uma mensagem de cadastro com ID
        if (preg_match('/ID:\s*([A-Z0-9]+)/i', $text, $matches)) {
            $invitationCode = $matches[1];
            return $this->handleInitialRegistration($waId, $profileName, $invitationCode);
        }

        // 2. Verificar estado no Redis
        $state = Redis::get($this->redisPrefix . $waId);

        if (!$state) {
            // Se não tem estado e não é comando de cadastro, poderíamos ignorar ou enviar menu inicial
            return null;
        }

        return $this->handleStateAction($waId, $state, $text);
    }

    protected function handleInitialRegistration($waId, $name, $invitationCode)
    {
        $user = User::where('remoteJid', $waId)->first();

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
                'invitation_code' => $invitationCode,
                'code' => strtoupper(Str::random(10)),
            ]);
        } else {
            // Atualizar código de convite se ainda não estiver completo
            $user->update(['invitation_code' => $invitationCode]);
        }

        // Iniciar fluxo de onboarding
        $this->setStep($waId, 'AWAITING_REGISTRATION_CONFIRMATION');
        
        $msg = "Olá. Boa tarde! Seja Bem-vindo(a) ao time do Dep. André Corrêa.\n\n" .
               "Que ótimo ter você aqui! 🎉\n" .
               "Percebi que este é o nosso primeiro contato, e para continuarmos essa conversa, preciso da sua autorização para enviar informativos e novidades da nossa equipe. 📩\n\n" .
               "Basta tocar no botão abaixo para confirmar. 👇";
        
        $this->whatsapp->sendInteractiveButtons($waId, $msg, [
            'confirm_yes' => 'Sim, quero receber',
            'confirm_no' => 'Talvez depois',
        ]);
    }

    protected function handleStateAction($waId, $state, $text)
    {
        // Lógica para cada passo do cadastro será implementada aqui
        Log::info("Handling state $state for $waId with text: $text");

        $textLower = strtolower(trim($text));
        
        switch ($state) {
            case 'AWAITING_REGISTRATION_CONFIRMATION':
                if (Str::contains($textLower, ['sim', 'quero', 'ok', 'confirm_yes'])) {
                    $this->setStep($waId, 'AWAITING_NAME');
                    $this->sendReply($waId, "Legal! Vamos começar. Digite seu *Nome e Sobrenome*.");
                } else {
                    $this->sendReply($waId, "Sem problemas. Se mudar de ideia, é só enviar a mensagem de cadastro novamente.");
                    $this->clearStep($waId);
                }
                break;
            
            // Outros passos serão adicionados conforme o fluxo
        }
    }

    protected function setStep($waId, $step)
    {
        Redis::setex($this->redisPrefix . $waId, 3600 * 24, $step); // 24h de expiração
    }

    protected function clearStep($waId)
    {
        Redis::del($this->redisPrefix . $waId);
    }

    protected function sendReply($waId, $text)
    {
        Log::info("Sending reply to $waId: $text");
        return $this->whatsapp->sendFreeText($waId, $text);
    }
}
