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


Schedule::command('send:scheduled-messages')
    ->everyMinute()
    ->skip(function () {
        $now = Carbon::now()->startOfMinute();

        return SentMessage::query()
            ->where('status', 'pending')
            ->where('sent_at', '<=', $now)
            ->doesntExist();
    });

Schedule::command('app:assign-embaixador-role-to-users')->everyMinute();

Schedule::command('app:prune-livewire-temp')->everyMinute();

Schedule::command('app:update-network-count')->everyMinute();

Schedule::command('app:fix-city-users')->everyFiveMinutes();

Schedule::command('app:fix-neighborhood-users')->everyMinute();
