# Data Model: Оплата подписки через СБП Robokassa

**Feature**: `006-robokassa-sbp-payment` | **Date**: 2026-11-04

Модель данных расширяет существующие `sites`, `users` и `subscription_payments`. Все суммы в БД — `decimal(10,2)`, в API — целые рубли; даты — ISO 8601 в API, `date`/`timestamp` в БД. Новые поля старых платежей nullable, чтобы существующая история не ломалась.

---

## 1. Обзор сущностей

| Сущность | Таблица | Назначение |
|---|---|---|
| Тариф подписки | `subscription_settings` | Singleton-настройки: цена, сроки, скидки, целевой статус, TTL QR, активность |
| Попытка оплаты | `subscription_payments` | Счёт за подписку конкретного объявления со снимком условий |
| Решение администратора | `subscription_payment_reviews` | Аудит разбора платежа «Требует проверки» |
| Объявление | `sites` | Владелец, статус, `paid_until`, `public_domain`, `unpublish_reason` |
| Пользователь | `users` | Плательщик; email аккаунта — значение по умолчанию для чека |

Подтверждение провайдера не хранится отдельной сущностью: значимые поля попадают в `subscription_payments.provider_payload`, а сырые параметры не логируются.

---

## 2. `subscription_settings`

Singleton-строка (`id = 1`). Модель `App\Models\SubscriptionSetting`.

| Поле | Тип | Null | Default | Правило |
|---|---|---|---|---|
| `id` | bigint unsigned PK | нет | auto | Всегда `1` |
| `base_price` | decimal(10,2) unsigned | нет | `0.00` | Базовая цена за один домен в месяц; ≥ 0; для выставления счёта должна быть > 0 |
| `periods` | json | нет | `[1,3,6,12]` | Уникальные целые 1–24, обязательно содержит 1; порядок отображается в UI |
| `discounts` | json | нет | `{}` | Объект `{"3":10,"6":15,"12":25}`; ключи — месяцы из `periods` и ≥ 3; значения — целые 0–99 |
| `target_status` | string(16) | нет | `active` | `active` или `inactive` |
| `qr_ttl_minutes` | unsignedSmallInteger | нет | `15` | 1–60 |
| `is_active` | boolean | нет | `false` | Разрешает новые счета; уже выставленные счета оплачиваются независимо |
| `created_at`, `updated_at` | timestamp | да | — | — |

**Модель**:
- `current(): self` — `Cache::remember('subscription:settings', 60, fn () => firstOrCreate(['id' => 1], defaults()))`.
- `saved`/`deleted` — `Cache::forget('subscription:settings')`.
- `defaults(): array` — значения из таблицы выше.
- `periodOptions(): array<int, array{months:int, discountPercent:int}>` — нормализованный список для API.
- `discountFor(int $months): int` — 0, если скидка не задана или месяц < 3.

**Валидация в админке**:
- `base_price`: `required|numeric|min:0.01|max:999999.99`.
- `periods`: `required|array|min:1`; каждый `integer|min:1|max:24|distinct`; содержит 1.
- `discounts`: `array`; ключи — строки месяцев, входящих в `periods` и ≥ 3; значения `integer|min:0|max:99`.
- `target_status`: `required|in:active,inactive`.
- `qr_ttl_minutes`: `required|integer|min:1|max:60`.
- `is_active`: `boolean`.
- Дополнительно: для каждого срока итог `quote()` > 0; иначе сохранение отклоняется с сообщением о скидке.

**Инварианты**:
- В таблице не больше одной строки; `current()` всегда возвращает строку (создаёт при отсутствии).
- Изменение настроек не трогает существующие `subscription_payments`.

---

## 3. `subscription_payments` (расширение)

Модель `App\Models\SubscriptionPayment`. Существующие поля сохраняются.

### 3.1. Существующие поля

| Поле | Тип | Null | Default | Примечание |
|---|---|---|---|---|
| `id` | bigint unsigned PK | нет | auto | Он же номер счёта (`InvId`) |
| `site_id` | FK → `sites.id`, cascadeOnDelete | нет | — | Неизменяемая привязка к объявлению |
| `user_id` | FK → `users.id`, cascadeOnDelete | нет | — | Плательщик (владелец объявления на момент счёта) |
| `amount` | decimal(10,2) | нет | — | Итоговая сумма; для новых платежей — целое число рублей |
| `currency` | char(3) | нет | `RUB` | Валюта счёта |
| `period_months` | unsignedSmallInteger | нет | — | Срок подписки |
| `status` | string(16) | нет | `pending` | `pending`, `paid`, `failed`, `cancelled`, `requires_review` |
| `paid_at` | timestamp | да | — | Время подтверждения провайдера; может быть заполнено и при `requires_review` |
| `external_id` | string(255) unique | да | — | Идентификатор операции провайдера, если доступен (`opKey`); у старых записей — UUID |
| `created_at`, `updated_at` | timestamp | да | — | — |

### 3.2. Новые поля

| Поле | Тип | Null | Default | Правило |
|---|---|---|---|---|
| `site_domain` | string(253) | да | — | Снимок `sites.domain` на момент счёта; у старых записей null, API подставляет текущий домен |
| `base_price` | decimal(10,2) | да | — | Снимок базовой цены за месяц |
| `base_amount` | decimal(10,2) | да | — | `base_price * period_months` (до скидки) |
| `discount_percent` | unsignedTinyInteger | нет | `0` | Целый процент 0–99; 0 для 1 месяца и старых записей |
| `discount_amount` | decimal(10,2) | нет | `0.00` | `base_amount - amount`; в копейках, чтобы сходился итог |
| `target_status` | string(16) | да | — | `active`/`inactive`; у старых записей null → при применении считается `active` |
| `receipt_email` | string(255) | да | — | Email для чека; у старых записей null |
| `receipt_payload` | json | да | — | Готовый JSON чека (`sno`, `items`), чтобы подпись и фискальные данные не менялись |
| `qr_expires_at` | timestamp | да | — | Срок действия QR/ссылки; у старых записей null |
| `provider` | string(32) | да | — | `robokassa` для новых; у старых null |
| `payment_method` | string(32) | да | — | `sbp` для новых; у старых null |
| `provider_payload` | json | да | — | Санитизированные поля callback (`OutSum`, `InvId`, `PaymentMethod`, `IncCurrLabel`, `Fee`, `EMail`), без подписи |
| `failure_reason` | string(255) | да | — | Причина `failed`/`cancelled` для продавца |
| `review_reason` | string(255) | да | — | Почему платёж требует проверки (`amount_mismatch`, `currency_mismatch`, `method_mismatch`, `site_sold`, `domain_conflict`, `site_not_payable`) |
| `is_test` | boolean | нет | `false` | Тестовая оплата; на production не создаётся и не принимается |

### 3.3. Индексы

- `index(['site_id', 'status'])` — выборка открытой попытки и истории.
- `index(['status', 'qr_expires_at'])` — команда истечения.
- `index('site_domain')` — фильтр по домену-снимку.
- `index('created_at')` — сортировка по умолчанию.
- `index('paid_at')` — сортировка/фильтр по дате оплаты.
- `index('payment_method')` — фильтр по способу.
- Существующие `unique('external_id')`, `index(['site_id','created_at'])`, `index(['user_id','created_at'])`, `index('status')` сохраняются.

### 3.4. Связи

- `site(): BelongsTo` — объявление.
- `payer(): BelongsTo` (`user_id`) — плательщик.
- `reviews(): HasMany` — история решений администратора.
- `latestReview(): HasOne` — последнее решение (`latestOfMany`).
- `Site::latestPayment(): HasOne` — последняя попытка (`latestOfMany`) для карточки кабинета.
- `Site::subscriptionPayments(): HasMany` — полная история (существует).

### 3.5. Приведение типов и ресурсы

- `status` → `PaymentStatus`, `amount`/`base_price`/`base_amount`/`discount_amount` → `decimal:2`, `paid_at`/`qr_expires_at` → `datetime`, `provider_payload`/`receipt_payload` → `array`, `is_test` → `bool`.
- `SubscriptionPaymentResource` отдаёт `amount` целым (`(int)`), `basePrice`/`baseAmount`/`discountAmount` — числами, `invoiceNumber = id`, `statusLabel`, `paymentMethodLabel`, `targetStatusLabel`, `siteDomain` (снимок или текущий), `qrExpiresAt`, `paidAt`, `failureReason`, `reviewReason`, `isTest`, `createdAt`.
- `SubscriptionPaymentSummaryResource` — сокращённый набор для `latestPayment` в списке: `id`, `status`, `statusLabel`, `amount`, `periodMonths`, `qrExpiresAt`, `createdAt`, `reviewReason`.

---

## 4. `subscription_payment_reviews`

Модель `App\Models\SubscriptionPaymentReview`.

| Поле | Тип | Null | Default | Правило |
|---|---|---|---|---|
| `id` | bigint unsigned PK | нет | auto | — |
| `subscription_payment_id` | FK → `subscription_payments.id`, cascadeOnDelete | нет | — | Разбираемый платёж |
| `admin_id` | FK → `users.id`, restrictOnDelete | нет | — | Администратор, принявший решение |
| `decision` | string(16) | нет | — | `accepted` / `rejected` |
| `comment` | text | нет | — | Обязательный комментарий, 5–2000 символов |
| `created_at`, `updated_at` | timestamp | да | — | — |

**Индексы**: `index(['subscription_payment_id', 'created_at'])`.

**Правила**:
- Запись создаётся только при разборе платежа в статусе `requires_review`.
- `accepted` применяет оплату (продление + целевой статус), `rejected` переводит платёж в `failed` без продления.
- Комментарий обязателен и не может быть пустым/короче 5 символов.
- Записи не редактируются и не удаляются через админку; история сохраняется.

---

## 5. `sites` (без изменения схемы)

Используются существующие поля:

| Поле | Роль в оплате |
|---|---|
| `id` | Неизменяемая привязка платежа |
| `user_id` | Владелец; проверка доступа |
| `domain` | Снимок в платеже; проверка конфликта |
| `public_domain` | Генерируемая уникальная колонка для `active`/`inactive`; основа проверки конфликта |
| `status` | `active`, `inactive`, `withdrawn`, `sold`; `sold` не оплачивается |
| `moderation_status` | Влияет на публичную видимость после оплаты, но не блокирует оплату |
| `paid_until` | Продлевается от более поздней даты |
| `unpublish_reason` | Очищается после успешной оплаты |
| `hidden_at` | Не влияет на оплату; скрытие сохраняет историю |

**Новые/изменяемые методы модели**:
- `acceptsPayments(): bool` — `active`, `inactive`, `withdrawn` (было: только `active`, `inactive`).
- `hasDomainConflictFor(ListingStatus $target): bool` — для `active`/`inactive` проверяет `public_domain` другого объявления.
- `applyPaidSubscription(int $months, ListingStatus $target): void` — проверка целевого статуса и конфликта, продление, очистка `unpublish_reason`, смена статуса, одно сохранение.
- `extendSubscription(int $months): void` — сохраняется для совместимости; используется внутри `applyPaidSubscription`.
- `needsRenewal(): bool` — без изменений (7 дней).

**Правила продления**:
- База — `max(paid_until, today)`; если `paid_until` null — `today`.
- Прибавление — `addMonthsNoOverflow($months)`.
- `paid_until` может быть в будущем; новый срок добавляется к нему (FR-001c, FR-018).
- После оплаты `unpublish_reason = null`.
- Целевой статус применяется только из `active`/`inactive`/`withdrawn`; `sold` блокируется до применения.

---

## 6. Статусы и переходы

### 6.1. `PaymentStatus`

| Значение | Подпись | Открыт? | Терминальный? |
|---|---|---|---|
| `pending` | Ожидает оплаты | да | нет |
| `paid` | Оплачен | нет | да |
| `failed` | Не прошёл | нет | да (но поздний callback может перевести в `paid`) |
| `cancelled` | Отменён | нет | да (но поздний callback может перевести в `paid`) |
| `requires_review` | Требует проверки | да | нет |

`isOpen()` — `pending` или `requires_review`. `isFinal()` сохраняется для обратной совместимости (`paid`/`failed`/`cancelled`), но не используется для запрета поздней оплаты.

### 6.2. Матрица переходов

| Из \ В | `paid` | `failed` | `cancelled` | `requires_review` |
|---|---|---|---|---|
| `pending` | callback, если применимо | ошибка провайдера / ручное | истечение QR, новая попытка, продажа | несовпадение суммы/валюты/способа, конфликт домена, sold |
| `requires_review` | админ «Принять», если применимо | админ «Отклонить» | — | — |
| `failed` | поздний корректный callback, если применимо | — | — | поздний callback с проблемой |
| `cancelled` | поздний корректный callback, если применимо | — | — | поздний callback с проблемой |
| `paid` | идемпотентно, без изменений | — | — | — |

### 6.3. Причины (`failure_reason` / `review_reason`)

| Код | Где | Текст для продавца |
|---|---|---|
| `expired` | `failure_reason` | Истёк срок действия QR-кода. Создайте новую попытку. |
| `new_attempt` | `failure_reason` | Создана новая попытка оплаты. |
| `site_sold` | `failure_reason`/`review_reason` | Объявление продано, оплата не может быть применена. |
| `provider_error` | `failure_reason` | Провайдер не подтвердил оплату. Попробуйте ещё раз. |
| `amount_mismatch` | `review_reason` | Сумма в подтверждении не совпадает со счётом. |
| `currency_mismatch` | `review_reason` | Валюта в подтверждении не совпадает со счётом. |
| `method_mismatch` | `review_reason` | Способ оплаты в подтверждении не СБП. |
| `domain_conflict` | `review_reason` | Домен занят другим активным или неактивным объявлением. |
| `site_not_payable` | `review_reason` | Объявление больше не принимает оплату. |
| `rejected_by_admin` | `failure_reason` | Платёж отклонён администратором. |

---

## 7. Инварианты и правила валидации

1. **Одна открытая попытка на объявление**: `pending` или `requires_review`. Обеспечивается транзакцией с `lockForUpdate()` на `sites`; при создании новой `pending` предыдущая `pending` отменяется, `requires_review` блокирует создание.
2. **Уникальность счёта**: `id` уникален; `InvId` не повторяется.
3. **Снимок условий**: `site_domain`, `base_price`, `base_amount`, `discount_percent`, `discount_amount`, `amount`, `period_months`, `target_status`, `receipt_email`, `receipt_payload` не меняются после создания (кроме служебных полей статуса/оплаты/проверки).
4. **Итог > 0**: `amount > 0`, `base_amount > 0`, `discount_amount >= 0`, `amount = base_amount - discount_amount` (с точностью до копейки).
5. **Срок**: `period_months` входит в `subscription_settings.periods` на момент создания; для старых записей — любое ≥ 1.
6. **Валюта**: `RUB`; callback с иной валютой → `requires_review`.
7. **Email**: валидный `receipt_email`; для новых платежей обязателен; не меняет `users.email`.
8. **Целевой статус**: только `active`/`inactive`; для старых записей null трактуется как `active`.
9. **Домен**: `site_domain` — снимок; текущий домен может отличаться; конфликт проверяется по `public_domain` на момент применения.
10. **Тест**: `is_test = true` только при `ROBOKASSA_TEST_MODE=true` и не в production; production callback с `is_test` отклоняется.
11. **Идемпотентность**: повторный callback по `paid` не меняет `paid_until`, `status` и не создаёт записей.
12. **Аудит**: решение по `requires_review` невозможно без комментария 5–2000 символов; запись создаётся в той же транзакции, что и смена статуса.

---

## 8. Миграции

### 8.1. `create_subscription_settings_table`

```php
Schema::create('subscription_settings', function (Blueprint $table) {
    $table->id();
    $table->decimal('base_price', 10, 2)->unsigned()->default(0);
    $table->json('periods');
    $table->json('discounts');
    $table->string('target_status', 16)->default('active');
    $table->unsignedSmallInteger('qr_ttl_minutes')->default(15);
    $table->boolean('is_active')->default(false);
    $table->timestamps();
});
```

`down()` — `dropIfExists('subscription_settings')`.

### 8.2. `add_robokassa_fields_to_subscription_payments_table`

```php
Schema::table('subscription_payments', function (Blueprint $table) {
    $table->string('site_domain', 253)->nullable()->after('site_id');
    $table->decimal('base_price', 10, 2)->nullable()->after('amount');
    $table->decimal('base_amount', 10, 2)->nullable()->after('base_price');
    $table->unsignedTinyInteger('discount_percent')->default(0)->after('base_amount');
    $table->decimal('discount_amount', 10, 2)->default(0)->after('discount_percent');
    $table->string('target_status', 16)->nullable()->after('period_months');
    $table->string('receipt_email', 255)->nullable()->after('target_status');
    $table->json('receipt_payload')->nullable()->after('receipt_email');
    $table->timestamp('qr_expires_at')->nullable()->after('paid_at');
    $table->string('provider', 32)->nullable()->after('external_id');
    $table->string('payment_method', 32)->nullable()->after('provider');
    $table->json('provider_payload')->nullable()->after('payment_method');
    $table->string('failure_reason', 255)->nullable()->after('provider_payload');
    $table->string('review_reason', 255)->nullable()->after('failure_reason');
    $table->boolean('is_test')->default(false)->after('review_reason');

    $table->index(['site_id', 'status']);
    $table->index(['status', 'qr_expires_at']);
    $table->index('site_domain');
    $table->index('created_at');
    $table->index('paid_at');
    $table->index('payment_method');
});
```

`down()` — удалить индексы и колонки в обратном порядке.

### 8.3. `create_subscription_payment_reviews_table`

```php
Schema::create('subscription_payment_reviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('subscription_payment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('admin_id')->constrained('users')->restrictOnDelete();
    $table->string('decision', 16);
    $table->text('comment');
    $table->timestamps();

    $table->index(['subscription_payment_id', 'created_at']);
});
```

`down()` — `dropIfExists('subscription_payment_reviews')`.

### 8.4. Совместимость

- Новые колонки nullable/с дефолтами; старые строки не требуют бэкфилла.
- `site_domain` для старых платежей остаётся null; API отдаёт текущий домен объявления.
- `target_status` для старых платежей null; `PaymentFinalizer` трактует как `active`.
- `receipt_payload` для старых платежей null; повторный `start` для них недоступен (нет `qr_expires_at`/`payment_method`).
- Существующие тесты, создающие платежи через фабрику, получают новые поля из фабрики (значения по умолчанию).

---

## 9. Фабрики и сидеры

### `SubscriptionSettingFactory`

- `base_price` — 500.00 (для тестов; в production сидер создаёт 0 и `is_active=false`).
- `periods` — `[1,3,6,12]`.
- `discounts` — `[3 => 10, 6 => 15, 12 => 25]`.
- `target_status` — `active`.
- `qr_ttl_minutes` — 15.
- `is_active` — true.
- Состояния: `inactive()`, `withoutDiscounts()`, `targetInactive()`, `shortQrTtl()`.

### `SubscriptionPaymentFactory` (расширение)

- `site_domain` — из `site->domain`.
- `base_price` — 500.00, `base_amount` — `base_price * period_months`.
- `discount_percent` — 0, `discount_amount` — 0, `amount` — `base_amount`.
- `target_status` — `active`, `receipt_email` — `payer->email`, `receipt_payload` — минимальный JSON.
- `qr_expires_at` — `now()->addMinutes(15)`, `provider` — `robokassa`, `payment_method` — `sbp`.
- `is_test` — false.
- Состояния: `paid()`, `failed()`, `cancelled()`, `requiresReview()`, `test()`, `expired()`.

### `SubscriptionSettingSeeder`

Создаёт строку `id=1` с production-дефолтами (`base_price=0`, `is_active=false`, `periods=[1,3,6,12]`, `discounts={}`), чтобы администратор явно задал цену перед запуском.

---

## 10. Представление в API

### 10.1. `PricingResource`

```json
{
  "basePrice": 500.0,
  "isActive": true,
  "periods": [
    { "months": 1, "discountPercent": 0, "total": 500, "perMonth": 500.0 },
    { "months": 3, "discountPercent": 10, "total": 1350, "perMonth": 450.0 }
  ]
}
```

### 10.2. `PaymentOptionsResource`

```json
{
  "canPay": true,
  "blockedReason": null,
  "basePrice": 500.0,
  "periods": [ ... ],
  "defaultReceiptEmail": "seller@example.com",
  "hasDeliverableEmail": true,
  "targetStatus": "active",
  "targetStatusLabel": "Активен",
  "willBeHidden": false,
  "activePayment": {
    "id": 42,
    "status": "pending",
    "statusLabel": "Ожидает оплаты",
    "amount": 1350,
    "periodMonths": 3,
    "qrExpiresAt": "2026-11-04T12:15:00+00:00",
    "reviewReason": null
  },
  "site": {
    "id": 7,
    "domain": "example.ru",
    "status": "withdrawn",
    "statusLabel": "Снят с продажи"
  }
}
```

`blockedReason` — код (`sold`, `under_review`, `tariff_inactive`, `domain_conflict`, `email_required`) и текст в `message` (см. контракт).

### 10.3. `SubscriptionPaymentResource`

```json
{
  "id": 42,
  "invoiceNumber": 42,
  "siteId": 7,
  "siteDomain": "example.ru",
  "currentSiteDomain": "example.ru",
  "receiptEmail": "seller@example.com",
  "basePrice": 500.0,
  "baseAmount": 1500.0,
  "discountPercent": 10,
  "discountAmount": 150.0,
  "amount": 1350,
  "currency": "RUB",
  "periodMonths": 3,
  "targetStatus": "active",
  "targetStatusLabel": "Активен",
  "status": "pending",
  "statusLabel": "Ожидает оплаты",
  "paymentMethod": "sbp",
  "paymentMethodLabel": "СБП",
  "provider": "robokassa",
  "isTest": false,
  "qrExpiresAt": "2026-11-04T12:15:00+00:00",
  "paidAt": null,
  "failureReason": null,
  "reviewReason": null,
  "createdAt": "2026-11-04T12:00:00+00:00"
}
```

### 10.4. `RobokassaStartResource`

```json
{
  "merchantLogin": "sites-shop",
  "outSum": "1350",
  "invId": 42,
  "receipt": "{\"sno\":\"osn\",\"items\":[{\"name\":\"Продление подписки на домен example.ru (3 мес.)\",\"quantity\":1,\"sum\":1350,\"payment_method\":\"full_payment\",\"payment_object\":\"service\",\"tax\":\"none\"}]}",
  "signature": "…",
  "isTest": false,
  "scriptUrl": "https://auth.robokassa.ru/merchant/bundle/robokassa-iframe-badge.js"
}
```

---

## 11. Правила отображения в кабинете и админке

- **Кабинет**: `latestPayment` показывает статус и действие; полная история — `GET /my/sites/{site}/payments`. Для `requires_review` показывается «Платёж на проверке» и причина; для `cancelled` — «Истёк срок»/«Создана новая попытка»; для `failed` — причина и «Повторить».
- **Админка**: список показывает `site_domain` (снимок) и текущий домен; если они различаются, оба видимы. Фильтр по доменам ищет по обоим. Карточка показывает снимок цены, скидку, итог, срок, целевой статус, email, QR-срок, `provider_payload` (без подписи), `review_reason` и историю решений.
- **Старые платежи**: `basePrice`/`baseAmount`/`discountAmount` могут быть null/0; UI показывает «—» и не ломается.
