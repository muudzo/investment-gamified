<?php

use App\Models\IdempotencyKey;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Laravel 12 loads scheduling from this file. The legacy app/Console/Kernel.php
| is NOT loaded by the framework, which is why these previously never ran.
*/

// Retention: purge audit rows past the retention window.
// Audit content is immutable (UPDATE is blocked at both app and DB level), but
// lifecycle deletion of old rows is permitted for retention — see the
// relax_portfolio_audit_delete_trigger migration.
Schedule::command('audit:clean')->dailyAt('02:00');

// Remove Sanctum tokens that expired more than a day ago.
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Prune replayed idempotency keys once they fall outside the replay/TTL window.
Schedule::call(function (): void {
    $ttlHours = (int) config('idempotency.ttl_hours', 24);
    IdempotencyKey::where('created_at', '<', now()->subHours($ttlHours))->delete();
})->hourly()->name('prune-idempotency-keys')->withoutOverlapping();
