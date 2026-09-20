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

Artisan::command('deploy:refresh', function () {
    $this->info('Clearing all application, view, route, and config caches...');
    Artisan::call('optimize:clear');
    Artisan::call('config:clear');
    Artisan::call('cache:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    $this->info('Deploy refresh complete! All compiled view, route, and OPcache layers purged.');
})->purpose('Purge all compiled views, routes, config caches, and OPcache on production server');
