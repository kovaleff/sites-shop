<?php

namespace App\Models;

use App\Enums\SavedSearchFrequency;
use App\Services\Catalog\CatalogFilter;
use Database\Factories\SavedSearchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'filters', 'frequency', 'last_notified_at'])]
class SavedSearch extends Model
{
    /** @use HasFactory<SavedSearchFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'frequency' => 'daily',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'frequency' => SavedSearchFrequency::class,
            'last_notified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Пора ли отправлять очередное письмо по этому фильтру.
     */
    public function isDue(): bool
    {
        $lastSentAt = $this->last_notified_at ?? $this->created_at ?? now();

        return $lastSentAt->lessThanOrEqualTo(now()->subDays($this->frequency->intervalDays()));
    }

    /**
     * Публичные лоты, подходящие под сохранённые фильтры, кроме лотов самого пользователя.
     *
     * @return Builder<Site>
     */
    public function matchingSites(): Builder
    {
        $filters = app(CatalogFilter::class)->normalize($this->filters ?? []);

        $query = Site::query()
            ->publiclyVisible()
            ->where('user_id', '!=', $this->user_id);

        app(CatalogFilter::class)->apply($query, $filters);

        return $query;
    }

    /**
     * Относительный путь каталога с теми же фильтрами — для SPA-ссылок.
     */
    public function catalogPath(): string
    {
        $query = app(CatalogFilter::class)->toQueryString($this->filters ?? []);

        return '/'.($query === '' ? '' : '?'.$query);
    }

    /**
     * Абсолютная ссылка на каталог с теми же фильтрами — для писем.
     */
    public function catalogUrl(): string
    {
        return url($this->catalogPath());
    }
}
