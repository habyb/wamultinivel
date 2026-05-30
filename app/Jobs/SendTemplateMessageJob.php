<?php

namespace App\Jobs;

use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTemplateMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $phone;
    public string $template;
    public string $language;
    public array $params;

    /**
     * Create a new job instance.
     */
    public function __construct(string $phone, string $template, string $language, array $params)
    {
        $this->phone = $phone;
        $this->template = $template;
        $this->language = $language;
        $this->params = $params;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(WhatsAppServiceBusinessApi::class)->sendText(
            phone: $this->phone,
            template: $this->template,
            language: $this->language,
            params: $this->params
        );
    }
}
