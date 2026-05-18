<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    /**
     * Handle the WhatsApp Webhook verification (GET).
     */
    public function verify(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        $verifyToken = env('WHATSAPP_VERIFY_TOKEN', 'minha_chave_secreta_123');

        if ($mode && $token) {
            if ($mode === 'subscribe' && $token === $verifyToken) {
                return response($challenge, 200);
            }
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle the WhatsApp Webhook notifications (POST).
     */
    public function handle(Request $request)
    {
        /*
        * Para desenvolvimento, é útil logar o payload completo para entender a estrutura dos dados recebidos.
         * No ambiente de produção, considere usar um canal de log separado ou uma ferramenta de monitoramento.
         *
         * Exemplo de log detalhado:
        $payload = $request->all();

        // Log para análise
        Log::channel('single')->info('WhatsApp Webhook Received:', $payload);
        
        Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/whatsapp_webhook.log'),
        ])->info(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        */

        // Delegar para o ChatProcessController (Cérebro)
        // Poderia ser um Job assíncrono aqui para resposta imediata ao Facebook
        app(ChatProcessController::class)->process($request);

        return response('EVENT_RECEIVED', 200);
    }
}
