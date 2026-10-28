<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_exactly_31_unique_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $categories = Category::query()->get();

        $this->assertCount(31, $categories);
        $this->assertSame(31, $categories->unique('name')->count());
        $this->assertSame(31, $categories->unique('slug')->count());
    }

    public function test_every_slug_is_generated_from_the_name(): void
    {
        $this->seed(CategorySeeder::class);

        Category::query()->each(function (Category $category): void {
            $this->assertSame(Str::slug($category->name), $category->slug);
        });
    }

    public function test_duplicate_category_is_merged(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertDatabaseHas('categories', ['name' => 'Здоровье и медицина']);
        $this->assertDatabaseMissing('categories', ['name' => 'Медицина и здоровье']);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(31, Category::query()->count());
    }
}
