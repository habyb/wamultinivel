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
    protected $signature = 'app:send-whatsapp-free-text {waId?} {text?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatches a SendWhatsappFreeTextJob to send a free text message via WhatsApp, or processes the queue if run without arguments.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $waId = $this->argument('waId');
        $text = $this->argument('text');

        if (!$waId || !$text) {
            $this->info("No arguments provided. Processing queued jobs...");
            $this->call('queue:work', [
                '--stop-when-empty' => true,
            ]);
            return 0;
        }

        $this->info("Dispatching SendWhatsappFreeTextJob for waId: {$waId}");
        
        SendWhatsappFreeTextJob::dispatch($waId, $text);

        $this->info("Job dispatched successfully!");

        return 0;
    }
}
