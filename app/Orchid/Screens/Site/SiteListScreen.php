<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Site;

use App\Models\Site;
use App\Orchid\Layouts\Site\SiteFiltersLayout;
use App\Orchid\Layouts\Site\SiteListLayout;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;

class SiteListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'sites' => Site::query()
                ->with(['owner', 'categories'])
                ->withCount(['conversations', 'subscriptionPayments'])
                ->filters(SiteFiltersLayout::class)
                ->defaultSort('created_at', 'desc')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Объявления';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Все объявления биржи с коммерческими показателями и статусом оплаты.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.sites',
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|Layout[]
     */
    public function layout(): iterable
    {
        return [
            SiteFiltersLayout::class,
            SiteListLayout::class,
        ];
    }
}
