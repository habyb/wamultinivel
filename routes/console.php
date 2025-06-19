<?php

use Carbon\Carbon;
use App\Models\SentMessage;
use Illuminate\Foundation\Inspiring;
use App\Jobs\SendScheduledMessagesJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Console\ClosureCommand;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:assign-embaixador-role-to-users')->everyMinute();

Artisan::command('send:scheduled-messages', function () {
    dispatch(new SendScheduledMessagesJob());
})->describe('Envia mensagens agendadas ou imediatas');

Schedule::command('send:scheduled-messages')
    ->everyMinute()
    ->skip(function () {
        return SentMessage::where('status', 'pending')
            ->where(function ($query) {
                $query->whereNull('sent_at')
                    ->orWhere('sent_at', '<=', Carbon::now());
            })
            ->doesntExist();
    });

Schedule::command('app:prune-livewire-temp')->everyMinute();

Schedule::command('app:update-network-count')->everyMinute();
