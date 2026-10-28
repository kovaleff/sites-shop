<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Category;

use App\Models\Category;
use App\Orchid\Layouts\Category\CategoryListLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Layout;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Toast;

class CategoryListScreen extends Screen
{
    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(): iterable
    {
        return [
            'categories' => Category::query()
                ->withCount('sites')
                ->defaultSort('name')
                ->paginate(),
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        return 'Тематики';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Справочник тематик объявлений биржи.';
    }

    public function permission(): ?iterable
    {
        return [
            'platform.marketplace.categories',
        ];
    }

    /**
     * The screen's action buttons.
     *
     * @return Action[]
     */
    public function commandBar(): iterable
    {
        return [
            Link::make('Создать')
                ->icon('bs.plus-circle')
                ->route('platform.marketplace.categories.create'),
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
            CategoryListLayout::class,
        ];
    }

    /**
     * Remove the category when it is not used by any site.
     */
    public function remove(Request $request): RedirectResponse
    {
        $category = Category::query()->findOrFail($request->integer('id'));

        if ($category->sites()->exists()) {
            Toast::error('Нельзя удалить тематику, к которой привязаны объявления.');

            return redirect()->route('platform.marketplace.categories');
        }

        $category->delete();

        Toast::info('Тематика удалена.');

        return redirect()->route('platform.marketplace.categories');
    }
}
