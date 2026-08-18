<?php

namespace App\Jobs;

use App\Contracts\WhatsAppServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsappFreeTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $waId;
    public string $text;

    /**
     * Create a new job instance.
     */
    public function __construct(string $waId, string $text)
    {
        $this->waId = $waId;
        $this->text = $text;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        app(WhatsAppServiceInterface::class)->sendFreeText($this->waId, $this->text);
    }
}
