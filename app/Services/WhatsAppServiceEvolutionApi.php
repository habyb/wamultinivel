<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppServiceEvolutionApi
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
            'apikey' => config('services.evolution.token'),
            'Accept' => 'application/json',
        ])
            ->post(config('services.evolution.url') . '/message/sendText/' . config('services.evolution.instance'), [
                'number' => $phone,
                'text' => $text,
            ]);
    }

    /**
     * Sends image, video or document
     *
     * @param string $number
     * @param string $mediatype
     * @param string $mimetype
     * @param string $caption
     * @param string $media
     * @param string $fileName
     */
    public function sendMediaUrl(string $number, string $mediatype, string $mimetype, string $caption, string $media, string $fileName)
    {
        $response = Http::withHeaders([
            'apikey' => config('services.evolution.token'),
            'Accept' => 'application/json',
        ])
            ->post(config('services.evolution.url') . '/message/sendMedia/' . config('services.evolution.instance'), [
                'number' => $number,
                'mediatype' => $mediatype,
                'mimetype' => $mimetype,
                'caption' => $caption,
                'media' => $media,
                'fileName' => $fileName,
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
            'apikey' => config('services.evolution.token'),
            'Accept' => 'application/json',
        ])
            ->post(config('services.evolution.url') . '/message/sendText/' . config('services.evolution.instance'), [
                'number' => $phone,
                'text' => $text,
            ]);

        if ($response->successful() && isset($response['key']['remoteJid'])) {
            return $response['key']['remoteJid'];
        }

        return null;
    }
}
