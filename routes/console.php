<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('events:expire-holds')->everyMinute();

Artisan::command('meetings:close-agendas', function () {
    $workflow = app(\App\Services\MeetingWorkflow::class);
    \App\Models\Meeting::whereNull('agenda_closed_at')->where('planned_start_at', '<=', now()->addDay())->each(fn ($meeting) => $workflow->syncDeadline($meeting));
})->purpose('Close and publish meeting agendas 24 hours before the scheduled start');
Schedule::command('meetings:close-agendas')->everyMinute();
