<?php

namespace App\Console\Commands;

use App\Jobs\SendScheduledMessagesJob;
use App\Models\SentMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SendScheduledMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:scheduled-messages {--batch=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envia mensagens agendadas (ou imediatas) de acordo com sent_at <= now()';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $batch = (int) $this->option('batch');
        $now   = Carbon::now()->startOfMinute();

        $pending = SentMessage::query()
            ->where('status', 'pending')
            ->where('sent_at', '<=', $now)
            ->count();

        if ($pending === 0) {
            $this->info("Nenhuma mensagem pendente até {$now->toDateTimeString()}.");
            return self::SUCCESS;
        }

        dispatch(new SendScheduledMessagesJob($batch));
        $this->info("Job despachado para {$pending} mensagens (batch={$batch}).");

        return self::SUCCESS;
    }
}
