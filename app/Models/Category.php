<?php

namespace App\Models;

use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;

#[Fillable(['name'])]
#[RouteKey('slug')]
class Category extends Model
{
    use Filterable;

    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'name' => Like::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'name',
        'created_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            $category->slug ??= Str::slug($category->name);
        });
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class);
    }
}
