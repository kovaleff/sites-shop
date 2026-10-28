<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Enums\ListingStatus;
use App\Models\Site;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class SiteListLayout extends Table
{
    /**
     * @var string
     */
    public $target = 'sites';

    /**
     * @return TD[]
     */
    public function columns(): array
    {
        return [
            TD::make('id', 'ID')
                ->sort()
                ->width('80px')
                ->render(fn (Site $site) => $site->id),

            TD::make('title', 'Название')
                ->sort()
                ->cantHide()
                ->filter(TD::FILTER_TEXT)
                ->render(fn (Site $site) => Link::make($site->title)
                    ->route('platform.marketplace.sites.edit', $site)),

            TD::make('domain', 'Домен')
                ->sort()
                ->cantHide()
                ->filter(TD::FILTER_TEXT)
                ->render(fn (Site $site) => $site->domain),

            TD::make('status', 'Статус')
                ->filter(TD::FILTER_SELECT, collect(ListingStatus::cases())
                    ->mapWithKeys(fn (ListingStatus $status) => [$status->value => $status->label()])
                    ->all())
                ->render(fn (Site $site) => $site->status->label()),

            TD::make('price', 'Цена')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_NUMBER_RANGE)
                ->render(fn (Site $site) => number_format((float) $site->price, 2, '.', ' ')),

            TD::make('monthly_revenue', 'Доход')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_NUMBER_RANGE)
                ->render(fn (Site $site) => number_format((float) $site->monthly_revenue, 2, '.', ' ')),

            TD::make('verified', 'Подтверждён')
                ->sort()
                ->filter(TD::FILTER_SELECT, ['1' => 'Да', '0' => 'Нет'])
                ->render(fn (Site $site) => $site->verified ? 'Да' : 'Нет'),

            TD::make('paid_until', 'Оплачено до')
                ->sort()
                ->render(fn (Site $site) => $site->paid_until?->format('d.m.Y') ?? '—'),

            TD::make('owner', 'Владелец')
                ->render(fn (Site $site) => $site->owner?->name ?? '—'),

            TD::make('created_at', 'Создано')
                ->sort()
                ->alignRight()
                ->filter(TD::FILTER_DATE_RANGE)
                ->render(fn (Site $site) => $site->created_at?->format('d.m.Y H:i')),
        ];
    }
}
