<?php

declare(strict_types=1);

namespace App\Orchid\Screens\Category;

use App\Models\Category;
use App\Orchid\Layouts\Category\CategoryEditLayout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Orchid\Screen\Action;
use Orchid\Screen\Actions\Button;
use Orchid\Screen\Actions\Link;
use Orchid\Screen\Screen;
use Orchid\Support\Facades\Layout;
use Orchid\Support\Facades\Toast;

class CategoryEditScreen extends Screen
{
    /**
     * @var Category|null
     */
    public $category;

    /**
     * Fetch data to be displayed on the screen.
     *
     * @return array
     */
    public function query(?Category $category = null): iterable
    {
        $category ??= new Category;

        return [
            'category' => [
                'name' => $category->name,
                'slug' => $category->slug,
                'sites_count' => $category->exists ? $category->sites()->count() : 0,
            ],
        ];
    }

    /**
     * The name of the screen displayed in the header.
     */
    public function name(): ?string
    {
        $category = request()->route('category');

        return $category instanceof Category
            ? 'Тематика: '.$category->name
            : 'Новая тематика';
    }

    /**
     * Display header description.
     */
    public function description(): ?string
    {
        return 'Название тематики объявлений. Публичный идентификатор не изменяется.';
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
        $category = request()->route('category');

        return [
            Link::make('Назад к списку')
                ->icon('bs.arrow-left')
                ->route('platform.marketplace.categories'),

            Button::make('Удалить')
                ->icon('bs.trash3')
                ->confirm('Тематика будет удалена безвозвратно.')
                ->method('remove')
                ->canSee($category instanceof Category),

            Button::make('Сохранить')
                ->icon('bs.check-circle')
                ->method('save'),
        ];
    }

    /**
     * The screen's layout elements.
     *
     * @return string[]|\Orchid\Screen\Layout[]
     */
    public function layout(): iterable
    {
        return [
            Layout::block(CategoryEditLayout::class)
                ->title('Тематика')
                ->description('Название отображается в фильтрах и карточках объявлений.'),
        ];
    }

    /**
     * Create or update the category.
     */
    public function save(Request $request, ?Category $category = null): RedirectResponse
    {
        $category = $category?->exists ? $category : new Category;

        $request->validate([
            'category.name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Category::class, 'name')->ignore($category->getKey()),
            ],
        ]);

        $category->fill([
            'name' => $request->input('category.name'),
        ])->save();

        Toast::info('Тематика сохранена.');

        return redirect()->route('platform.marketplace.categories');
    }

    /**
     * Remove the category when it is not used by any site.
     */
    public function remove(Category $category): RedirectResponse
    {
        if ($category->sites()->exists()) {
            Toast::error('Нельзя удалить тематику, к которой привязаны объявления.');

            return redirect()->route('platform.marketplace.categories');
        }

        $category->delete();

        Toast::info('Тематика удалена.');

        return redirect()->route('platform.marketplace.categories');
    }
}
