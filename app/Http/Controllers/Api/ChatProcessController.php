<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessChatbotMessageJob;
use Illuminate\Http\Request;

class ChatProcessController extends Controller
{
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

        if ($contact && $message) {
            ProcessChatbotMessageJob::dispatch($contact, $message);
        }

        return response('OK', 200);
    }
}
