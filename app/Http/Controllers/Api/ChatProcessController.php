<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatbotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ChatProcessController extends Controller
{
    protected $chatbot;

    public function __construct(ChatbotService $chatbot)
    {
        $this->chatbot = $chatbot;
    }

    /**
     * Process incoming message from Webhook
     */
    public function process(Request $request)
    {
        $payload = $request->all();

        // Extrair dados básicos do payload da Meta
        $entry = $payload['entry'][0] ?? null;
        if (!$entry) return response('No entry', 400);

        $change = $entry['changes'][0]['value'] ?? null;
        if (!$change) return response('No change value', 400);

        $contact = $change['contacts'][0] ?? null;
        $message = $change['messages'][0] ?? null;

        if ($contact && $message && ($message['type'] ?? '') === 'text') {
            $this->chatbot->processMessage($contact, $message);
        }

        return response('OK', 200);
    }
}
