<?php

declare(strict_types=1);

namespace Tests\Feature\Orchid;

use App\Models\Category;
use App\Models\Site;
use Illuminate\Support\Str;

class CategoryManagementTest extends OrchidTestCase
{
    public function test_admin_can_create_a_category(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.categories.create', ['save']), [
            'category' => ['name' => 'Интернет-магазины'],
        ])->assertRedirect();

        $category = Category::query()->where('name', 'Интернет-магазины')->firstOrFail();

        $this->assertSame(Str::slug('Интернет-магазины'), $category->slug);
    }

    public function test_renaming_a_category_keeps_its_slug(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create(['name' => 'Старое название']);
        $slug = $category->slug;

        $this->post(route('platform.marketplace.categories.edit', [$category, 'save']), [
            'category' => ['name' => 'Новое название'],
        ])->assertRedirect();

        $category->refresh();

        $this->assertSame('Новое название', $category->name);
        $this->assertSame($slug, $category->slug);
    }

    public function test_duplicate_category_name_is_rejected(): void
    {
        $this->actingAs($this->admin());

        Category::factory()->create(['name' => 'Занятое название']);
        $other = Category::factory()->create(['name' => 'Другое название']);

        $this->post(route('platform.marketplace.categories.create', ['save']), [
            'category' => ['name' => 'Занятое название'],
        ])->assertSessionHasErrors('category.name');

        $this->post(route('platform.marketplace.categories.edit', [$other, 'save']), [
            'category' => ['name' => 'Занятое название'],
        ])->assertSessionHasErrors('category.name');

        $this->assertSame('Другое название', $other->refresh()->name);
        $this->assertSame(1, Category::query()->where('name', 'Занятое название')->count());
    }

    public function test_category_used_by_a_site_cannot_be_removed(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create();
        Site::factory()->create()->categories()->attach($category);

        $this->post(route('platform.marketplace.categories.edit', [$category, 'remove']))
            ->assertRedirect();

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_unused_category_can_be_removed_from_the_card_and_the_list(): void
    {
        $this->actingAs($this->admin());

        $fromCard = Category::factory()->create();
        $fromList = Category::factory()->create();

        $this->post(route('platform.marketplace.categories.edit', [$fromCard, 'remove']))
            ->assertRedirect();

        $this->post(route('platform.marketplace.categories', [
            'method' => 'remove',
            'id' => $fromList->id,
        ]))->assertRedirect();

        $this->assertDatabaseMissing('categories', ['id' => $fromCard->id]);
        $this->assertDatabaseMissing('categories', ['id' => $fromList->id]);
    }

    public function test_category_forms_render_the_readonly_slug(): void
    {
        $this->actingAs($this->admin());

        $category = Category::factory()->create(['name' => 'Формовая тематика']);

        $this->get(route('platform.marketplace.categories.create'))
            ->assertOk()
            ->assertSee('Новая тематика');

        $this->get(route('platform.marketplace.categories.edit', $category))
            ->assertOk()
            ->assertSee('Формовая тематика')
            ->assertSee($category->slug);
    }

    public function test_slug_cannot_be_changed_through_the_form(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('platform.marketplace.categories.create', ['save']), [
            'category' => [
                'name' => 'Своя тематика',
                'slug' => 'custom-slug',
            ],
        ])->assertRedirect();

        $category = Category::query()->where('name', 'Своя тематика')->firstOrFail();

        $this->assertNotSame('custom-slug', $category->slug);
        $this->assertSame(Str::slug('Своя тематика'), $category->slug);
    }
}
