<?php

declare(strict_types=1);

namespace App\Orchid\Screens\SubscriptionSetting;

use App\Enums\ListingStatus;
use App\Models\SubscriptionSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Fields\Input;
use Orchid\Screen\Fields\Select;
use Orchid\Screen\Fields\Switcher;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class SubscriptionSettingScreen extends Screen
{
    /**
     * @return array<string, mixed>
     */
    public function query(): iterable
    {
        $settings = SubscriptionSetting::current();

        return [
            'settings' => [
                'base_price' => number_format((float) $settings->base_price, 2, '.', ''),
                'periods' => $settings->normalizedPeriods(),
                'discount_3' => $settings->discountFor(3),
                'discount_6' => $settings->discountFor(6),
                'discount_12' => $settings->discountFor(12),
                'target_status' => $settings->targetListingStatus()->value,
                'qr_ttl_minutes' => $settings->qr_ttl_minutes,
                'is_active' => $settings->is_active,
            ],
        ];
    }

    public function name(): ?string
    {
        return 'Настройки подписки';
    }

    public function description(): ?string
    {
        return 'Цена, сроки, скидки и целевой статус после оплаты. '
            .'Изменения применяются только к новым счетам.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.subscription-settings',
        ];
    }

    /**
     * @return array<int, Button>
     */
    public function commandBar(): iterable
    {
        return [
            Button::make('Сохранить')
                ->icon('bs.check2-circle')
                ->method('save'),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public function layout(): iterable
    {
        return [
            Layout::rows([
                Input::make('settings.base_price')
                    ->title('Базовая цена за домен в месяц, ₽')
                    ->type('number')
                    ->step(0.01)
                    ->min(0.01)
                    ->required()
                    ->help('Базовая цена может содержать копейки; итог счёта округляется до целого рубля.'),

                Select::make('settings.periods')
                    ->title('Доступные сроки, мес.')
                    ->multiple()
                    ->options([
                        1 => '1 месяц',
                        3 => '3 месяца',
                        6 => '6 месяцев',
                        12 => '12 месяцев',
                    ])
                    ->help('Срок 1 месяц обязателен.'),

                Input::make('settings.discount_3')
                    ->title('Скидка для 3 месяцев, %')
                    ->type('number')
                    ->min(0)
                    ->max(99),

                Input::make('settings.discount_6')
                    ->title('Скидка для 6 месяцев, %')
                    ->type('number')
                    ->min(0)
                    ->max(99),

                Input::make('settings.discount_12')
                    ->title('Скидка для 12 месяцев, %')
                    ->type('number')
                    ->min(0)
                    ->max(99),

                Select::make('settings.target_status')
                    ->title('Целевой статус после оплаты')
                    ->options([
                        ListingStatus::Active->value => ListingStatus::Active->label(),
                        ListingStatus::Inactive->value => ListingStatus::Inactive->label(),
                    ])
                    ->required(),

                Input::make('settings.qr_ttl_minutes')
                    ->title('Срок действия QR-кода, минут')
                    ->type('number')
                    ->min(1)
                    ->max(60)
                    ->required(),

                Switcher::make('settings.is_active')
                    ->title('Тариф активен')
                    ->sendTrueOrFalse()
                    ->help('Выключенный тариф блокирует новые счета; уже выставленные счета остаются оплачиваемыми.'),
            ]),
        ];
    }

    public function save(Request $request): void
    {
        $data = $request->validate([
            'settings.base_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'settings.periods' => ['required', 'array', 'min:1'],
            'settings.periods.*' => ['integer', 'min:1', 'max:24'],
            'settings.discount_3' => ['nullable', 'integer', 'min:0', 'max:99'],
            'settings.discount_6' => ['nullable', 'integer', 'min:0', 'max:99'],
            'settings.discount_12' => ['nullable', 'integer', 'min:0', 'max:99'],
            'settings.target_status' => ['required', Rule::in([
                ListingStatus::Active->value,
                ListingStatus::Inactive->value,
            ])],
            'settings.qr_ttl_minutes' => ['required', 'integer', 'min:1', 'max:60'],
            'settings.is_active' => ['nullable', 'boolean'],
        ]);

        $payload = $data['settings'];
        $periods = collect($payload['periods'] ?? [])
            ->map(fn ($months): int => (int) $months)
            ->unique()
            ->sort()
            ->values()
            ->all();

        if (! in_array(1, $periods, true)) {
            Toast::error('Срок 1 месяц обязателен.');

            return;
        }

        $discounts = [];

        foreach ([3, 6, 12] as $months) {
            if (! in_array($months, $periods, true)) {
                continue;
            }

            $percent = (int) ($payload["discount_{$months}"] ?? 0);

            if ($percent > 0) {
                $discounts[$months] = $percent;
            }
        }

        $basePrice = (float) $payload['base_price'];

        foreach ($periods as $months) {
            $discount = $discounts[$months] ?? 0;
            $total = (int) round($basePrice * $months * (100 - $discount) / 100, 0, PHP_ROUND_HALF_UP);

            if ($total <= 0) {
                Toast::error('Итоговая сумма должна быть больше нуля. Проверьте цену и скидку.');

                return;
            }
        }

        SubscriptionSetting::query()->updateOrCreate(
            ['id' => 1],
            [
                'base_price' => $payload['base_price'],
                'periods' => $periods,
                'discounts' => $discounts,
                'target_status' => $payload['target_status'],
                'qr_ttl_minutes' => (int) $payload['qr_ttl_minutes'],
                'is_active' => (bool) ($payload['is_active'] ?? false),
            ],
        );

        Cache::forget(SubscriptionSetting::CACHE_KEY);

        Toast::info('Настройки сохранены.');
    }
}
