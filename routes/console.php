<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Напоминания об истекающей подписке: за 7 и 1 день, а также в день окончания.
Schedule::command('sites:notify-expiring')->dailyAt('09:00');
