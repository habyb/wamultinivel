<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessChatbotMessageJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatProcessController extends Controller
{
    /**
     * Process incoming message from Webhook.
     * Auto-detects whether payload is from WABA (Meta) or uazapi.
     */
    public function process(Request $request)
    {
        $payload = $request->all();

        // Auto-detectar provider pelo formato do payload
        if (isset($payload['EventType']) && isset($payload['message'])) {
            return $this->processUazapi($payload);
        }

        // WABA (Meta) format
        return $this->processWaba($payload);
    }

    /**
     * Process WABA (Meta) webhook payload.
     */
    protected function processWaba(array $payload)
    {
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) return response('No entry', 400);

        $change = $entry['changes'][0]['value'] ?? null;
        if (!$change) return response('No change value', 400);

        $contact = $change['contacts'][0] ?? null;
        $message = $change['messages'][0] ?? null;

        if ($contact && $message) {
            ProcessChatbotMessageJob::dispatch($contact, $message);
        }

        return response('OK', 200);
    }

    /**
     * Process uazapi webhook payload.
     */
    protected function processUazapi(array $payload)
    {
        $msg = $payload['message'] ?? [];
        $chat = $payload['chat'] ?? [];

        // Ignorar mensagens enviadas pelo próprio bot (evita loops)
        if ($msg['fromMe'] ?? false) {
            return response('IGNORED', 200);
        }

        // Ignorar eventos que não são mensagens
        if (($payload['EventType'] ?? '') !== 'messages') {
            return response('NOT_MESSAGE', 200);
        }

        // Extrair número puro (remove @s.whatsapp.net)
        $waId = str_replace('@s.whatsapp.net', '', $chat['wa_chatid'] ?? '');
        if (empty($waId)) {
            Log::warning('[ChatProcessController] uazapi payload sem wa_chatid', $payload);
            return response('NO_CHATID', 400);
        }

        $profileName = $chat['name'] ?? $msg['senderName'] ?? 'Amigo(a)';

        // Normalizar para o formato esperado pelo ProcessChatbotMessageJob
        $contact = [
            'wa_id'   => $waId,
            'profile' => ['name' => $profileName],
        ];

        // Determinar o tipo e conteúdo da mensagem
        $msgType = strtolower($msg['messageType'] ?? $msg['type'] ?? 'text');
        $buttonOrListId = $msg['buttonOrListid'] ?? '';

        if (! empty($buttonOrListId)) {
            // Resposta a botão ou lista interativa
            $buttonText = $msg['text'] ?? $msg['content'] ?? '';
            $message = [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button_reply',
                    'button_reply' => [
                        'id'    => $buttonOrListId,
                        'title' => $buttonText,
                    ],
                    'list_reply' => [
                        'id'    => $buttonOrListId,
                        'title' => $buttonText,
                    ],
                ],
            ];
        } elseif ($msgType === 'conversation' || $msgType === 'text' || $msgType === 'extendedtextmessage') {
            // Mensagem de texto simples
            $message = [
                'type' => 'text',
                'text' => ['body' => $msg['text'] ?? $msg['content'] ?? ''],
            ];
        } else {
            // Outros tipos (imagem, áudio, etc.) — tratar como texto genérico
            $message = [
                'type' => 'text',
                'text' => ['body' => $msg['text'] ?? $msg['content'] ?? ''],
            ];
        }

        ProcessChatbotMessageJob::dispatch($contact, $message);

        return response('EVENT_RECEIVED', 200);
    }
}
