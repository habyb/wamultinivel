<?php

namespace App\Jobs;

use App\Services\ChatbotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatbotMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public array $contact;
    public array $message;

    /**
     * Create a new job instance.
     */
    public function __construct(array $contact, array $message)
    {
        $this->contact = $contact;
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(ChatbotService $chatbot): void
    {
        $chatbot->processMessage($this->contact, $this->message);
    }
}
