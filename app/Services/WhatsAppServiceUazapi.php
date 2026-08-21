<?php

namespace App\Services;

use App\Contracts\WhatsAppServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Setting;

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
     * Busca na tabela Settings (via Filament) utilizando cache para performance.
     * Faz fallback para as variáveis de ambiente originais se o registro não existir.
     */
    protected function token(): string
    {
        return Cache::rememberForever('uazapi_token', function () {
            $setting = Setting::where('name', 'whatsapp_token')->first();
            if ($setting && isset($setting->payload['value'])) {
                return $setting->payload['value'];
            }
            return config('services.uazapi.token');
        });
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
        // Simula o tempo de digitação humano: 1 segundo base + 30ms por caractere (máximo de 10 segundos)
        $delay = min(1000 + (mb_strlen($text) * 30), 10000);

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/text', [
                'number'       => $phone,
                'text'         => $text,
                'linkPreview'  => false,
                'delay'        => $delay,
                'readchat'     => true,
                'readmessages' => true,
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
     * Sends a message with interactive buttons via /send/menu (type: button).
     *
     * @param array $buttons Associative array ['button_id' => 'Button Title']
     */
    public function sendInteractiveButtons(string $phone, string $bodyText, array $buttons)
    {
        $choices = [];
        foreach ($buttons as $id => $title) {
            $choices[] = $title . '|' . $id;
        }

        $delay = min(1000 + (mb_strlen($bodyText) * 30), 10000);

        $response = Http::withHeaders($this->headers())
            ->post($this->baseUrl() . '/send/menu', [
                'number'       => $phone,
                'type'         => 'button',
                'text'         => $bodyText,
                'choices'      => $choices,
                'linkPreview'  => false,
                'delay'        => $delay,
                'readchat'     => true,
                'readmessages' => true,
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
     * Sends a list message (interactive dropdown) via /send/menu (type: list).
     */
    public function sendListMessage(
        string $phone,
        string $bodyText,
        string $buttonText,
        array $sections,
        ?string $headerText = null
    ) {
        $choices = [];
        foreach ($sections as $section) {
            if (isset($section['title'])) {
                $choices[] = '[' . $section['title'] . ']';
            }
            if (isset($section['rows'])) {
                foreach ($section['rows'] as $row) {
                    $id = $row['id'] ?? '';
                    $title = $row['title'] ?? '';
                    $desc = $row['description'] ?? '';
                    
                    // Format: "texto|id|descrição"
                    $item = $title;
                    if ($id || $desc) {
                        $item .= '|' . $id;
                    }
                    if ($desc) {
                        $item .= '|' . $desc;
                    }
                    $choices[] = $item;
                }
            }
        }

        $delay = min(1000 + (mb_strlen($bodyText) * 30), 10000);

        $payload = [
            'number'       => $phone,
            'type'         => 'list',
            'text'         => $bodyText,
            'listButton'   => $buttonText,
            'choices'      => $choices,
            'linkPreview'  => false,
            'delay'        => $delay,
            'readchat'     => true,
            'readmessages' => true,
        ];

        if ($headerText) {
            $payload['footerText'] = $headerText; // UAZAPI doesn't have headerText, mapped to footerText if needed, or omit.
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
