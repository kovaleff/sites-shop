<?php

declare(strict_types=1);

namespace App\Orchid\Layouts\User;

use App\Models\Site;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layouts\Table;
use Orchid\Screen\TD;

class UserSitesTable extends Table
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
                ->render(fn (Site $site) => Link::make((string) $site->id)
                    ->route('platform.marketplace.sites.edit', $site)),

            TD::make('title', 'Название')
                ->render(fn (Site $site) => $site->title),

            TD::make('domain', 'Домен')
                ->render(fn (Site $site) => $site->domain),

            TD::make('status', 'Статус')
                ->render(fn (Site $site) => $site->status->label()),

            TD::make('price', 'Цена')
                ->alignRight()
                ->render(fn (Site $site) => number_format((float) $site->price, 2, '.', ' ')),

            TD::make('created_at', 'Создано')
                ->alignRight()
                ->render(fn (Site $site) => $site->created_at?->format('d.m.Y H:i')),
        ];
    }
}
