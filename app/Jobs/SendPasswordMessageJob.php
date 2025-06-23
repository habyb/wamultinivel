<?php

namespace App\Jobs;

use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendPasswordMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $number;
    public string $password;

    public function __construct(string $number, string $password)
    {
        $this->number = $number;
        $this->password = $password;
    }

    public function handle(): void
    {
        app(WhatsAppServiceBusinessApi::class)->sendText(
            phone: $this->number,
            template: 'senha',
            language: 'pt_BR',
            params: [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $this->password]
                    ],
                ],
                [
                    'type' => 'button',
                    'sub_type' => 'url',
                    'index' => 0,
                    'parameters' => [
                        ['type' => 'text', 'text' => $this->password]
                    ]
                ]
            ]
        );
    }
}
