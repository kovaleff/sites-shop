<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Жизненный цикл подписки: снять истёкшие объявления и напомнить о продлении
// за 7 и 1 день, а также в день окончания.
Schedule::command('sites:unpublish-expired')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('sites:notify-expiring')->dailyAt('09:00')->withoutOverlapping();

// Еженедельная перепроверка доменов опубликованных объявлений.
Schedule::command('sites:recheck-domains')->weeklyOn(1, '03:00')->withoutOverlapping();

// Карта сайта и утренняя сводка жалоб модераторам.
Schedule::command('sitemap:generate')->dailyAt('04:00')->withoutOverlapping();
Schedule::command('reports:digest')->dailyAt('09:00')->withoutOverlapping();

// Дайджест новых лотов по сохранённым фильтрам покупателей.
Schedule::command('saved-searches:notify')->dailyAt('10:00')->withoutOverlapping();
