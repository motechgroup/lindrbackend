<?php

use App\Services\CallService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('calls:reconcile', function () {
    $count = app(CallService::class)->reconcileStaleSessions();
    $this->info("Reconciled {$count} stale call session(s).");
})->purpose('Reconcile abandoned or hanging call sessions')->everyMinute();
