<?php

declare(strict_types=1);

namespace App\Orchid;

use Orchid\Platform\Dashboard;
use Orchid\Platform\ItemPermission;
use Orchid\Platform\OrchidServiceProvider;
use Orchid\Screen\Actions\Menu;

class PlatformProvider extends OrchidServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(Dashboard $dashboard): void
    {
        parent::boot($dashboard);

        // ...
    }

    /**
     * Register the application menu.
     *
     * @return Menu[]
     */
    public function menu(): array
    {
        return [
            Menu::make('Пользователи')
                ->icon('bs.people')
                ->route('platform.systems.users')
                ->permission('platform.systems.users')
                ->title('Биржа'),

            Menu::make('Тематики')
                ->icon('bs.tags')
                ->route('platform.marketplace.categories')
                ->permission('platform.marketplace.categories'),

            Menu::make('Объявления')
                ->icon('bs.globe')
                ->route('platform.marketplace.sites')
                ->permission('platform.marketplace.sites'),

            Menu::make('Диалоги')
                ->icon('bs.chat-dots')
                ->route('platform.marketplace.conversations')
                ->permission('platform.marketplace.conversations'),

            Menu::make('Сообщения')
                ->icon('bs.envelope')
                ->route('platform.marketplace.messages')
                ->permission('platform.marketplace.messages'),

            Menu::make('Платежи')
                ->icon('bs.credit-card')
                ->route('platform.marketplace.payments')
                ->permission('platform.marketplace.payments')
                ->divider(),

            Menu::make(__('Roles'))
                ->icon('bs.shield')
                ->route('platform.systems.roles')
                ->permission('platform.systems.roles')
                ->title(__('Access Controls'))
                ->divider(),
        ];
    }

    /**
     * Register permissions for the application.
     *
     * @return ItemPermission[]
     */
    public function permissions(): array
    {
        return [
            ItemPermission::group(__('System'))
                ->addPermission('platform.systems.roles', __('Roles'))
                ->addPermission('platform.systems.users', __('Users')),

            ItemPermission::group('Биржа')
                ->addPermission('platform.marketplace.categories', 'Тематики')
                ->addPermission('platform.marketplace.sites', 'Объявления')
                ->addPermission('platform.marketplace.conversations', 'Диалоги')
                ->addPermission('platform.marketplace.messages', 'Сообщения')
                ->addPermission('platform.marketplace.payments', 'Платежи'),
        ];
    }
}
