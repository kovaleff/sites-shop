<?php

namespace App\Models;

use App\Enums\NotificationType;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Orchid\Filters\Types\Like;
use Orchid\Filters\Types\Where;
use Orchid\Filters\Types\WhereDateStartEnd;
use Orchid\Platform\Models\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    /**
     * Домены технических адресов, которые выдаются аккаунтам без реального email:
     * письма на них гарантированно не доставимы.
     *
     * @var array<int, string>
     */
    private const TECHNICAL_EMAIL_DOMAINS = ['@vk.users', '@ya.users'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'permissions',
        'avatar',
        'bio',
        'city',
        'vk_id',
        'ya_id',
        'vk_last_login_at',
        'ya_last_login_at',
    ];

    /**
     * The attributes for which you can use filters in url.
     *
     * @var array<string, class-string>
     */
    protected $allowedFilters = [
        'id' => Where::class,
        'name' => Like::class,
        'email' => Like::class,
        'vk_id' => Where::class,
        'ya_id' => Where::class,
        'updated_at' => WhereDateStartEnd::class,
        'created_at' => WhereDateStartEnd::class,
    ];

    /**
     * The attributes for which can use sort in url.
     *
     * @var array<int, string>
     */
    protected $allowedSorts = [
        'id',
        'name',
        'email',
        'vk_id',
        'ya_id',
        'vk_last_login_at',
        'ya_last_login_at',
        'updated_at',
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
            'password' => 'hashed',
            'notification_settings' => 'array',
            'vk_last_login_at' => 'datetime',
            'ya_last_login_at' => 'datetime',
        ];
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function conversationsAsBuyer(): HasMany
    {
        return $this->hasMany(Conversation::class, 'buyer_id');
    }

    public function conversationsAsSeller(): HasMany
    {
        return $this->hasMany(Conversation::class, 'seller_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function subscriptionPayments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Непрочитанные сообщения по всем диалогам пользователя.
     */
    public function unreadMessagesCount(): int
    {
        return Message::query()
            ->whereIn('conversation_id', Conversation::query()->forParticipant($this)->select('id'))
            ->where('sender_id', '!=', $this->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Настройки уведомлений с учётом значений по умолчанию.
     *
     * @return array<string, bool>
     */
    public function notificationSettings(): array
    {
        return array_replace(
            NotificationType::defaults(),
            $this->notification_settings ?? [],
        );
    }

    public function wantsNotification(NotificationType $type): bool
    {
        return (bool) ($this->notificationSettings()[$type->value] ?? true);
    }

    /**
     * Обновить переданные типы, остальные оставить без изменений.
     *
     * @param  array<string, bool>  $settings
     */
    public function updateNotificationSettings(array $settings): void
    {
        $current = $this->notificationSettings();

        foreach (NotificationType::cases() as $type) {
            if (array_key_exists($type->value, $settings)) {
                $current[$type->value] = (bool) $settings[$type->value];
            }
        }

        if ($current === $this->notificationSettings()) {
            return;
        }

        $this->notification_settings = $current;
        $this->save();
    }

    public function disableNotification(NotificationType $type): void
    {
        $this->updateNotificationSettings([$type->value => false]);
    }

    /**
     * У аккаунтов из VK/Яндекса без реального email адрес технический:
     * отправлять на него письма нельзя.
     */
    public function hasDeliverableEmail(): bool
    {
        $email = Str::lower(trim((string) $this->email));

        if ($email === '') {
            return false;
        }

        return ! Str::endsWith($email, self::TECHNICAL_EMAIL_DOMAINS);
    }
}
