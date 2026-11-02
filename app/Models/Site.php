<?php

namespace App\Models;

use App\Enums\ListingStatus;
use App\Enums\ModerationStatus;
use App\Events\SiteModerationChanged;
use App\Http\Controllers\SitemapController;
use App\Services\Seo\ListingImageGenerator;
use Carbon\CarbonImmutable;
use Database\Factories\SiteFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Filters\Types\WhereIn;
use Orchid\Filters\Types\WhereMaxMin;

#[Fillable(['title', 'domain', 'description', 'price', 'monthly_revenue', 'has_trademark'])]
class Site extends Model
{
    use Filterable;

    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    public const CATALOG_PER_PAGE = 25;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'domain' => Like::class,
        'status' => WhereIn::class,
        'moderation_status' => WhereIn::class,
        'price' => WhereMaxMin::class,
        'monthly_revenue' => WhereMaxMin::class,
        'verified' => WhereIn::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'id',
        'title',
        'domain',
        'price',
        'monthly_revenue',
        'verified',
        'paid_until',
        'created_at',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'moderation_status' => 'pending',
        'has_trademark' => false,
        'price' => 0,
        'monthly_revenue' => 0,
        'verified' => false,
    ];

    protected static function booted(): void
    {
        static::creating(function (Site $site): void {
            $site->verification_filename ??= Str::lower(Str::random(32)).'.txt';
        });

        static::saving(function (Site $site): void {
            $site->domain_zone = $site->zoneFromDomain($site->domain);
        });

        // Лот изменился: сбрасываем карту сайта и кэш картинки превью.
        static::saved(function (Site $site): void {
            SitemapController::flush();
            app(ListingImageGenerator::class)->forget($site);
        });

        static::deleted(function (): void {
            SitemapController::flush();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'moderation_status' => ModerationStatus::class,
            'price' => 'decimal:2',
            'monthly_revenue' => 'decimal:2',
            'has_trademark' => 'boolean',
            'verified' => 'boolean',
            'paid_until' => 'date',
            'sold_at' => 'datetime',
            'hidden_at' => 'datetime',
            'moderated_at' => 'datetime',
            'verification_checked_at' => 'datetime',
        ];
    }

    /**
     * Normalize the site domain before storing it.
     */
    protected function domain(): Attribute
    {
        return Attribute::make(
            set: function (?string $value): ?string {
                return self::normalizeDomain($value);
            },
        );
    }

    /**
     * Приведение домена к сравнимому виду: нижний регистр, без www. и слэша.
     */
    public static function normalizeDomain(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $domain = Str::lower(trim($value));
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;

        return rtrim($domain, '/');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * Все сообщения по объявлению (через диалоги) — для счётчика непрочитанных.
     */
    public function messages(): HasManyThrough
    {
        return $this->hasManyThrough(Message::class, Conversation::class);
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(SiteReport::class);
    }

    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('status', ListingStatus::Active)
            ->where('moderation_status', ModerationStatus::Approved)
            ->whereNotNull('paid_until')
            ->whereDate('paid_until', '>=', today()->toDateString());
    }

    #[Scope]
    protected function ownedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    #[Scope]
    protected function ofCategories(Builder $query, array $categoryIds): void
    {
        if ($categoryIds === []) {
            return;
        }

        $query->whereHas('categories', function (Builder $query) use ($categoryIds): void {
            $query->whereIn('categories.id', $categoryIds);
        });
    }

    #[Scope]
    protected function priceBetween(Builder $query, ?float $min, ?float $max): void
    {
        $query->when($min !== null, fn (Builder $query) => $query->where('price', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('price', '<=', $max));
    }

    #[Scope]
    protected function revenueBetween(Builder $query, ?float $min, ?float $max): void
    {
        $query->when($min !== null, fn (Builder $query) => $query->where('monthly_revenue', '>=', $min))
            ->when($max !== null, fn (Builder $query) => $query->where('monthly_revenue', '<=', $max));
    }

    #[Scope]
    protected function domainContains(Builder $query, ?string $term): void
    {
        $term = Str::lower(trim((string) $term));

        if ($term === '') {
            return;
        }

        $query->where('domain', 'like', '%'.$term.'%');
    }

    #[Scope]
    protected function newestFirst(Builder $query): void
    {
        $query->latest('created_at')->latest('id');
    }

    #[Scope]
    protected function visibleInCabinet(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    #[Scope]
    protected function hiddenFromCabinet(Builder $query): void
    {
        $query->whereNotNull('hidden_at');
    }

    public function isPubliclyVisible(): bool
    {
        return $this->status === ListingStatus::Active
            && $this->moderation_status === ModerationStatus::Approved
            && $this->paid_until !== null
            && ! $this->paid_until->lt(today());
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * Транслитерированный слаг названия для человекопонятного URL лота.
     */
    public function slug(): string
    {
        $slug = Str::slug((string) $this->title);

        return $slug === '' ? 'site' : $slug;
    }

    /**
     * Канонический путь страницы лота: /site/{id}-{slug}.
     */
    public function canonicalPath(): string
    {
        return '/site/'.$this->getKey().'-'.$this->slug();
    }

    /**
     * Срок окупаемости в месяцах; null, если доход не указан.
     */
    public function paybackMonths(): ?int
    {
        $revenue = (float) $this->monthly_revenue;

        if ($revenue <= 0) {
            return null;
        }

        return (int) ceil((float) $this->price / $revenue);
    }

    /**
     * Пометка «требует продления»: подписка истекает в течение 7 дней или уже истекла.
     */
    public function needsRenewal(): bool
    {
        return $this->paid_until === null
            || $this->paid_until->lessThanOrEqualTo(today()->addDays(7));
    }

    /**
     * Снять с публикации объявление с истёкшей подпиской и записать причину
     * для кабинета продавца. Возвращает true, если статус действительно изменился.
     */
    public function unpublishForExpiredSubscription(): bool
    {
        if ($this->status !== ListingStatus::Active) {
            return false;
        }

        if ($this->paid_until === null || ! $this->paid_until->lt(today())) {
            return false;
        }

        $this->status = ListingStatus::Inactive;
        $this->unpublish_reason = sprintf(
            'Подписка истекла %s. Объявление снято с публикации — продлите подписку и включите его снова.',
            $this->paid_until->format('d.m.Y'),
        );
        $this->save();

        return true;
    }

    /**
     * Скрыть проданное объявление из списка кабинета (данные сохраняются).
     */
    public function hideFromCabinet(): void
    {
        if ($this->status !== ListingStatus::Sold) {
            throw new DomainException('Only sold sites can be hidden from the cabinet.');
        }

        if ($this->hidden_at !== null) {
            return;
        }

        $this->hidden_at = now();
        $this->save();
    }

    /**
     * Вернуть скрытое объявление в список кабинета.
     */
    public function restoreFromCabinet(): void
    {
        if ($this->hidden_at === null) {
            return;
        }

        $this->hidden_at = null;
        $this->save();
    }

    public function acceptsPayments(): bool
    {
        return in_array($this->status, [ListingStatus::Active, ListingStatus::Inactive], true);
    }

    public function changeStatus(ListingStatus $status): void
    {
        if ($this->status === $status) {
            return;
        }

        if (! $this->status->canTransitionTo($status)) {
            throw new DomainException(
                "Cannot change site status from {$this->status->value} to {$status->value}."
            );
        }

        $this->status = $status;
        $this->unpublish_reason = null;

        if ($status === ListingStatus::Sold) {
            $this->sold_at = now();
        }

        $this->save();
    }

    public function markVerified(bool $verified = true): void
    {
        if ($this->verified === $verified) {
            return;
        }

        $this->verified = $verified;
        $this->save();
    }

    /**
     * Автоподтверждение владения доменом: включает `verified` и, если объявление
     * ещё ждёт проверки, автоматически одобряет его.
     */
    public function markDomainVerified(): void
    {
        $this->verified = true;
        $this->verification_checked_at = now();
        $this->verification_error = null;

        if ($this->moderation_status === ModerationStatus::Pending) {
            $this->moderation_status = ModerationStatus::Approved;
            $this->moderation_note = null;
            $this->moderated_at = now();
        }

        $this->save();
    }

    public function markDomainVerificationFailed(string $reason): void
    {
        $this->verified = false;
        $this->verification_checked_at = now();
        $this->verification_error = Str::limit($reason, 250, '');
        $this->save();
    }

    /**
     * Ручное решение модератора.
     */
    public function setModeration(ModerationStatus $status, ?string $note = null): void
    {
        if ($this->moderation_status === $status && $this->moderation_note === $note) {
            return;
        }

        $this->moderation_status = $status;
        $this->moderation_note = $note;
        $this->moderated_at = now();
        $this->save();

        SiteModerationChanged::dispatch($this, $status, $note);
    }

    /**
     * Домен изменился — подтверждение владения нужно пройти заново.
     */
    public function resetDomainVerification(): void
    {
        $this->verified = false;
        $this->verification_checked_at = null;
        $this->verification_error = null;

        if ($this->moderation_status === ModerationStatus::Approved) {
            $this->moderation_status = ModerationStatus::Pending;
            $this->moderated_at = null;
        }

        $this->save();
    }

    public function extendSubscription(int $months): void
    {
        if ($months < 1) {
            throw new DomainException('The subscription period must be at least one month.');
        }

        $base = $this->paid_until !== null && $this->paid_until->greaterThan(today())
            ? $this->paid_until
            : today();

        $this->paid_until = CarbonImmutable::parse($base)->addMonthsNoOverflow($months);
        $this->unpublish_reason = null;
        $this->save();
    }

    /**
     * Доменная зона (последняя метка домена), используется фильтром каталога.
     */
    private function zoneFromDomain(?string $domain): ?string
    {
        if ($domain === null) {
            return null;
        }

        $domain = Str::lower(trim($domain));

        if (! str_contains($domain, '.')) {
            return null;
        }

        $zone = Str::afterLast($domain, '.');

        return $zone === '' ? null : $zone;
    }
}
