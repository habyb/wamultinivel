<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    /**
     * Sends message
     *
     * @param string $phone
     * @param string $text
     */
    public function sendText(string $phone, string $text)
    {
        $response = Http::withHeaders([
            'apikey' => config('services.evulution.token'),
            'Accept' => 'application/json',
        ])
            ->post(config('services.evulution.url') . '/message/sendText/' . config('services.evulution.instance'), [
                'number' => $phone,
                'text' => $text,
            ]);
    }

    /**
     * Returns remote jid
     *
     * @param string $phone
     * @param string $text
     * @return string|null Returns remote jid (552192010169@s.whatsapp.net) or null
     */
    public function getRemoteJid(string $phone, string $text): ?string
    {
        $response = Http::withHeaders([
            'apikey' => config('services.evulution.token'),
            'Accept' => 'application/json',
        ])
            ->post(config('services.evulution.url') . '/message/sendText/' . config('services.evulution.instance'), [
                'number' => $phone,
                'text' => $text,
            ]);

        if ($response->successful() && isset($response['key']['remoteJid'])) {
            return $response['key']['remoteJid'];
        }

        return null;
    }
}
