<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\SendWhatsappFreeTextJob;

class SendWhatsappFreeTextCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-whatsapp-free-text {waId} {text}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches a SendWhatsappFreeTextJob to send a free text message via WhatsApp.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $waId = $this->argument('waId');
        $text = $this->argument('text');

        $this->info("Dispatching SendWhatsappFreeTextJob for waId: {$waId}");
        
        SendWhatsappFreeTextJob::dispatch($waId, $text);

        $this->info("Job dispatched successfully!");

        return 0;
    }
}
