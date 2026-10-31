<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\Site;

use App\Models\Site;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class ModerationQueueLayout extends Table
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
            TD::make('title', 'Объявление')
                ->cantHide()
                ->render(fn (Site $site) => Link::make($site->title)
                    ->route('platform.marketplace.sites.edit', $site)),

            TD::make('domain', 'Домен')
                ->render(fn (Site $site) => $site->domain),

            TD::make('owner', 'Продавец')
                ->render(fn (Site $site) => $site->owner?->name ?? '—'),

            TD::make('verification', 'Проверка домена')
                ->render(fn (Site $site) => $site->verification_error
                    ?? ($site->verified ? 'Владение подтверждено' : 'Не проверялся')),

            TD::make('paid_until', 'Оплачено до')
                ->render(fn (Site $site) => $site->paid_until?->format('d.m.Y') ?? '—'),

            TD::make('created_at', 'Создано')
                ->sort()
                ->render(fn (Site $site) => $site->created_at?->format('d.m.Y H:i')),

            TD::make('actions', 'Решение')
                ->alignRight()
                ->render(fn (Site $site) => Button::make('Одобрить')
                    ->icon('bs.check-circle')
                    ->method('approve')
                    ->parameters(['site' => $site->id])
                    ->class('btn btn-link text-success')),

            TD::make('reject', '')
                ->alignRight()
                ->render(fn (Site $site) => Button::make('Отклонить')
                    ->icon('bs.x-circle')
                    ->method('reject')
                    ->parameters(['site' => $site->id])
                    ->class('btn btn-link text-danger')),
        ];
    }
}
