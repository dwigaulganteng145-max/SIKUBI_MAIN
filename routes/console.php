<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;
use App\Models\DuplicateTransaction;
use Carbon\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Auto-clean resolved duplicate transactions older than 30 days
Schedule::call(function () {
    DuplicateTransaction::whereIn('status', ['IMPORTED', 'DISMISSED'])
        ->where('updated_at', '<', Carbon::now()->subDays(30))
        ->delete();
})->daily();
