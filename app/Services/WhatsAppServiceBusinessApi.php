<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppServiceBusinessApi
{
    /**
     * Sends message
     *
     * @param string $phone
     * @param string $template
     * @param string $language
     * @param string $text
     */
    public function sendText(string $phone, string $template, string $language, array $params)
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.business.access_token'),
            'Accept' => 'application/json',
            'Content-Type'  => 'application/json',
        ])->post(config('services.business.url') . '/' . config('services.business.version') . '/' . config('services.business.phone_number_id') . '/messages', [
            'messaging_product' => 'whatsapp',
            'to'                => $phone,
            'type'              => 'template',
            'template'          => [
                'name'     => $template,
                'language' => ['code' => $language],
                'components' => $params,
            ],
        ]);
        return $response->json();
    }

    /**
     * Get templates on WhatsApp Business API
     */
    public function getTemplate(): array
    {
        $baseUrl = config('services.business.url') . '/'
            . config('services.business.version') . '/'
            . config('services.business.phone_account_id')
            . '/message_templates';

        $token = config('services.business.access_token');
        $all = [];
        $next = $baseUrl;
        $tries = 0;

        while ($next && $tries < 10) {
            $response = Http::withToken($token)->get($next);
            $res = $response->json();

            if (empty($res['data']) || !is_array($res['data'])) {
                break;
            }

            $all = array_merge($all, $res['data']);

            // Checa se existe paginação válida
            if (isset($res['paging']['cursors']['after'])) {
                $after = $res['paging']['cursors']['after'];
                $next = $baseUrl . '?after=' . urlencode($after);
            } else {
                $next = null;
            }

            $tries++;
        }

        return $all;
    }
}
