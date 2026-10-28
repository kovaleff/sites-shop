<?php

namespace App\Models;

use Database\Factories\ConversationFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\WhereDateStartEnd;

#[Fillable(['site_id', 'buyer_id', 'seller_id'])]
class Conversation extends Model
{
    use Filterable;

    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'last_message_at' => WhereDateStartEnd::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'last_message_at',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public static function startFor(Site $site, User $buyer): self
    {
        if ($site->isOwnedBy($buyer)) {
            throw new DomainException('The owner cannot start a conversation about their own site.');
        }

        if (! $site->isPubliclyVisible()) {
            throw new DomainException('A conversation can only start for a publicly visible site.');
        }

        return static::query()->firstOrCreate(
            [
                'site_id' => $site->id,
                'buyer_id' => $buyer->id,
            ],
            [
                'seller_id' => $site->user_id,
            ],
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isParticipant(User $user): bool
    {
        return $user->id === $this->buyer_id || $user->id === $this->seller_id;
    }

    public function isReadOnly(): bool
    {
        return ! $this->site->isPubliclyVisible();
    }

    public function canBeRepliedToBy(User $user): bool
    {
        return $this->isParticipant($user) && ! $this->isReadOnly();
    }

    public function postMessage(User $sender, string $body): Message
    {
        if (! $this->canBeRepliedToBy($sender)) {
            throw new DomainException('The user cannot post to this conversation.');
        }

        return DB::transaction(fn (): Message => $this->messages()->forceCreate([
            'sender_id' => $sender->id,
            'body' => $body,
        ]));
    }

    public function unreadCountFor(User $user): int
    {
        if (! $this->isParticipant($user)) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->count();
    }

    public function markReadFor(User $user): int
    {
        if (! $this->isParticipant($user)) {
            return 0;
        }

        return $this->messages()
            ->where('sender_id', '!=', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
