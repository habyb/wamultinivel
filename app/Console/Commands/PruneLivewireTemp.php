<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;

class PruneLivewireTemp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:prune-livewire-temp';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dir = storage_path('app/private/livewire-tmp');

        collect(glob($dir . '/*'))
            ->filter(fn($file) => (int) Carbon::createFromTimestamp(filemtime($file))->diffInMinutes(now()) > 60)
            ->each(fn($file) => unlink($file));

        $this->info('Arquivos Livewire temporários limpos.');
    }
}
