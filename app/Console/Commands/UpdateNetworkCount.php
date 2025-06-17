<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateNetworkCount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-network-count';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update total_network_count on users table';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        User::all()->each->updateNetworkCount();
        $this->info('Contagem de rede atualizada.');
    }
}
