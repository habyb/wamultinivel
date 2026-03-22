<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\WhatsAppServiceBusinessApi;
use Illuminate\Support\Facades\Cache;

class WhatsAppClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:whatsapp-clear-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears and refreshes the WhatsApp templates cache.';

    /**
     * Execute the console command.
     */
    public function handle(WhatsAppServiceBusinessApi $whatsAppService)
    {
        $this->info('Clearing WhatsApp-related cache keys...');

        // Chama o método de atualização do serviço, que lida com a lógica de cache
        $this->info('Requesting template list refresh from Meta API...');
        $refreshedTemplates = $whatsAppService->refreshTemplatesCache();

        if (empty($refreshedTemplates)) {
            $this->warn('The refreshed template list is empty. There might be an issue connecting to the API or no templates were found.');
        } else {
            $this->info('WhatsApp templates cache has been refreshed successfully.');
            $this->comment('Found templates: ' . implode(', ', array_keys($refreshedTemplates)));
        }

        $this->info('Also running standard application cache clear.');
        $this->call('cache:clear');

        $this->info('Cache clearing process finished.');

        return 0;
    }
}
