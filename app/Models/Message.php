<?php

namespace App\Models;

use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Orchid\Filters\Filterable;
use Orchid\Filters\Types\WhereDateStartEnd;

#[Fillable(['body'])]
class Message extends Model
{
    use Filterable;

    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * @var array<int, string>
     */
    protected $allowedSorts = [
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
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Message $message): void {
            $message->conversation()->update([
                'last_message_at' => $message->created_at,
            ]);
        });
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Чужие непрочитанные сообщения пользователя.
     */
    #[Scope]
    protected function unreadFor(Builder $query, User $user): void
    {
        $query->where('sender_id', '!=', $user->id)
            ->whereNull('read_at');
    }

    public function isSentBy(User $user): bool
    {
        return $this->sender_id === $user->id;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->read_at = now();
        $this->save();
    }
}
