<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Console\ClosureCommand;
use App\Jobs\SendScheduledMessagesJob;

Artisan::command('inspire', function () {
    /** @var ClosureCommand $this */
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:assign-embaixador-role-to-users')->everyMinute();

Artisan::command('send:scheduled-messages', function () {
    dispatch(new SendScheduledMessagesJob());
})->describe('Envia mensagens agendadas ou imediatas');

Schedule::command('send:scheduled-messages')->everyMinute();

Schedule::command('app:prune-livewire-temp')->everyMinute();

Schedule::command('app:update-network-count')->everyMinute();
