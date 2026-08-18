<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppServiceUazapi implements WhatsAppServiceInterface
{
    /**
     * Base URL da instância uazapi.
     */
    protected function baseUrl(): string
    {
        return rtrim(config('services.uazapi.url'), '/');
    }

    /**
     * Token de autenticação da uazapi.
     */
    protected function token(): string
    {
        return config('services.uazapi.token');
    }

    /**
     * Headers padrão para todas as requisições.
     */
    protected function headers(): array
    {
        return [
            'token'        => $this->token(),
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    /**
     * Sends a free text message.
     */
    public function sendFreeText(string $phone, string $text)
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/text', [
                'number' => $phone,
                'text'   => $text,
            ]);

        if (! $response->successful()) {
            Log::error('[WhatsAppServiceUazapi] Falha ao enviar texto', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }

    /**
     * Sends a message with interactive buttons via /send/menu (menuType: button).
     *
     * @param array $buttons Associative array ['button_id' => 'Button Title']
     */
    public function sendInteractiveButtons(string $phone, string $bodyText, array $buttons)
    {
        $buttonObjects = [];
        foreach ($buttons as $id => $title) {
            $buttonObjects[] = [
                'id'   => (string) $id,
                'text' => $title,
            ];
        }

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/menu', [
                'number'   => $phone,
                'menuType' => 'button',
                'text'     => $bodyText,
                'buttons'  => $buttonObjects,
            ]);

        if (! $response->successful()) {
            Log::error('[WhatsAppServiceUazapi] Falha ao enviar botões', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }

    /**
     * Sends a list message (interactive dropdown) via /send/menu (menuType: list).
     */
    public function sendListMessage(
        string $phone,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null
    ) {
        $payload = [
            'number'     => $phone,
            'menuType'   => 'list',
            'text'       => $bodyText,
            'buttonText' => $buttonText,
            'sections'   => $sections,
        ];

        if ($headerText) {
            $payload['title'] = $headerText;
        }

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/menu', $payload);

        if (! $response->successful()) {
            Log::error('[WhatsAppServiceUazapi] Falha ao enviar lista', [
                'phone'  => $phone,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }

    /**
     * Sends image, video or document via /send/media.
     */
    public function sendMediaUrl(string $number, string $mediatype, string $url, string $caption = '', string $fileName = '')
    {
        $payload = [
            'number' => $number,
            'type'   => $mediatype,
            'url'    => $url,
        ];

        if (! empty($caption)) {
            $payload['caption'] = $caption;
        }

        if (! empty($fileName)) {
            $payload['filename'] = $fileName;
        }

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/media', $payload);

        if (! $response->successful()) {
            Log::error('[WhatsAppServiceUazapi] Falha ao enviar mídia', [
                'number' => $number,
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        return $response->json();
    }
}
