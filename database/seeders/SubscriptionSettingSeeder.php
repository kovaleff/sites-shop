<?php

namespace Database\Seeders;

use App\Models\SubscriptionSetting;
use Illuminate\Database\Seeder;

/**
 * Создаёт singleton-настройки тарифа с безопасными значениями по умолчанию:
 * цена не задана и тариф выключен, пока администратор не настроит его.
 */
class SubscriptionSettingSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionSetting::query()->updateOrCreate(
            ['id' => 1],
            SubscriptionSetting::defaults(),
        );
    }
}
