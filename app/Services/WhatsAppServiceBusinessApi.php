<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class WhatsAppServiceBusinessApi
{
    /**
     * Obtém o access token da config.
     * Prioriza services.business.access_token e faz fallback para services.facebook.token
     */
    protected function accessToken(): ?string
    {
        return config('services.business.access_token');
    }

    /**
     * Cliente HTTP resiliente + token em header E querystring.
     */
    protected function http(): PendingRequest
    {
        $token = $this->accessToken();

        $request = Http::acceptJson()
            ->connectTimeout(10)
            ->timeout(20)
            ->withOptions([
                'version' => 1.1,            // força HTTP/1.1
                'force_ip_resolve' => 'v4',  // força IPv4
                'verify' => true,
            ]);

        // Header Authorization (se tiver token)
        if (! empty($token)) {
            $request = $request->withToken($token);
        }

        // Também injeta access_token na query por robustez
        // (usa withOptions para cobrir todas as chamadas subsequentes)
        $existingQuery = Arr::get($request->getOptions(), 'query', []);
        $request = $request->withOptions([
            'query' => array_merge($existingQuery, [
                'access_token' => $token,
            ]),
        ]);

        return $request;
    }

    /**
     * Monta o endpoint /{waba_id}/message_templates.
     */
    protected function templatesEndpoint(): string
    {
        $version = config('services.business.version');
        $phoneAccount  = config('services.business.phone_account_id');

        return sprintf(config('services.business.url') . '/%s/%s/message_templates', $version, $phoneAccount);
    }

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
     * Retorna um preview textual do template (HEADER/BODY/FOOTER + botões).
     * Cache por 10 min (chave por nome + idioma).
     */
    public function getTemplatePreview(string $name, ?string $language = null): string
    {
        $lang = $language ?: 'any';
        $cacheKey = "wa:tpl:preview:{$name}:{$lang}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($name, $language) {
            // Busca somente o template escolhido, com components
            $endpoint = $this->templatesEndpoint();
            $resp = $this->http()
                ->retry(5, 300, throw: false)
                ->get($endpoint, [
                    'name'   => $name, // filtra por nome
                    'fields' => 'name,language,status,category,components,languages',
                    'limit'  => 50,
                ]);

            if (! $resp->successful()) {
                logger()->warning('WA template preview fetch failed', [
                    'status' => $resp->status(),
                    'body'   => $resp->body(),
                ]);
                return 'Não foi possível carregar a pré-visualização agora.';
            }

            $items = $resp->json('data', []);
            if (empty($items)) {
                return 'Template não encontrado.';
            }

            // escolhe o item pelo idioma (se informado) ou o primeiro
            $tpl = collect($items)->first(function ($t) use ($language) {
                return $language ? (($t['language'] ?? null) === $language) : true;
            }) ?? $items[0];

            return $this->buildPreviewFromComponents($tpl['components'] ?? []);
        });
    }

    /**
     * Monta uma string legível a partir dos components do template.
     */
    protected function buildPreviewFromComponents(array $components): string
    {
        $header = $body = $footer = null;
        $buttons = [];

        foreach ($components as $c) {
            $type = strtoupper($c['type'] ?? '');
            if ($type === 'HEADER') {
                $format = strtoupper($c['format'] ?? 'TEXT');
                if ($format === 'TEXT') {
                    $header = $c['text'] ?? ($c['example']['header_text'][0] ?? null);
                } else {
                    $header = "[HEADER: {$format}]";
                }
            } elseif ($type === 'BODY') {
                // usa exemplo se existir, senão texto com {{1}}, {{2}} etc.
                $body = $c['example']['body_text'][0] ?? ($c['text'] ?? null);
            } elseif ($type === 'FOOTER') {
                $footer = $c['text'] ?? null;
            } elseif ($type === 'BUTTONS') {
                foreach ($c['buttons'] ?? [] as $b) {
                    $txt = $b['text'] ?? strtoupper($b['type'] ?? 'BUTTON');
                    // anexa amostra de URL se houver
                    if (($b['type'] ?? null) === 'URL' && !empty($b['url'])) {
                        $txt .= " → {$b['url']}";
                    }
                    $buttons[] = $txt;
                }
            }
        }

        $lines = [];
        if ($header) $lines[] = (string) $header;
        if ($body)   $lines[] = (string) $body;
        if ($footer) $lines[] = '— ' . (string) $footer;
        if ($buttons) $lines[] = 'Botões: ' . implode(' | ', $buttons);

        return trim(implode("\n\n", array_filter($lines)));
    }

    /**
     * Get templates on WhatsApp Business API
     */
    public function getTemplate(): array
    {
        return Cache::remember('wa:templates', now()->addMinutes(10), function () {
            $endpoint = $this->templatesEndpoint();

            $response = $this->http()
                ->retry(5, 300, throw: false)
                ->get($endpoint, [
                    'fields' => 'name,status,category,languages',
                    'limit'  => 200,
                ]);

            if ($response->successful()) {
                $data = $response->json('data', []);
                $opts = [];

                foreach ($data as $t) {
                    $name = $t['name'] ?? null;
                    if (! $name) continue;

                    $label = $name;
                    if (!empty($t['status'])) {
                        $label .= ' (' . Str::of($t['status'])->upper() . ')';
                    }
                    $opts[$name] = $label;
                }

                Cache::put('wa:templates:last_ok', $opts, now()->addDay());
                return $opts;
            }

            logger()->warning('WA templates fetch failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

            return Cache::get('wa:templates:last_ok', []);
        });
    }

    /**
     * Força a atualização do cache dos templates.
     * - apaga o cache atual
     * - repopula chamando getTemplate()
     * - persiste o “último bom” para fallback
     */
    public function refreshTemplatesCache(): array
    {
        Cache::forget('wa:templates');

        $opts = $this->getTemplate(); // getTemplate() já faz a chamada ao Graph e cacheia

        if (! empty($opts)) {
            Cache::put('wa:templates:last_ok', $opts, now()->addDay());
        }

        return $opts;
    }

    /**
     * Alias por segurança (caso em algum lugar tenha sido escrito no singular).
     */
    public function refreshTemplateCache(): array
    {
        return $this->refreshTemplatesCache();
    }
}
