# Contracts: Административные маршруты и формы

**Feature**: `002-orchid-admin-models` | **Date**: 2026-10-28

Контракт описывает HTTP-границы административной панели: именованные маршруты, права, действия экранов и поля форм. Все URL указаны относительно префикса `/admin`.

## Route Table

| Имя маршрута | Методы | URL | Экран | Право | Действия |
|---|---|---|---|---|---|
| `platform.marketplace.sites` | GET, HEAD, POST | `/marketplace/sites/{method?}` | `SiteListScreen` | `platform.marketplace.sites` | `view` |
| `platform.marketplace.sites.edit` | GET, HEAD, POST | `/marketplace/sites/{site}/edit/{method?}` | `SiteEditScreen` | `platform.marketplace.sites` | `view`, `save`, `changeStatus` |
| `platform.marketplace.categories` | GET, HEAD, POST | `/marketplace/categories/{method?}` | `CategoryListScreen` | `platform.marketplace.categories` | `view`, `remove` |
| `platform.marketplace.categories.create` | GET, HEAD, POST | `/marketplace/categories/create/{method?}` | `CategoryEditScreen` | `platform.marketplace.categories` | `view`, `save` |
| `platform.marketplace.categories.edit` | GET, HEAD, POST | `/marketplace/categories/{category}/edit/{method?}` | `CategoryEditScreen` | `platform.marketplace.categories` | `view`, `save`, `remove` |
| `platform.marketplace.conversations` | GET, HEAD, POST | `/marketplace/conversations/{method?}` | `ConversationListScreen` | `platform.marketplace.conversations` | `view` |
| `platform.marketplace.conversations.show` | GET, HEAD, POST | `/marketplace/conversations/{conversation}/{method?}` | `ConversationShowScreen` | `platform.marketplace.conversations` | `view` |
| `platform.marketplace.messages` | GET, HEAD, POST | `/marketplace/messages/{method?}` | `MessageListScreen` | `platform.marketplace.messages` | `view` |
| `platform.marketplace.messages.show` | GET, HEAD, POST | `/marketplace/messages/{message}/{method?}` | `MessageShowScreen` | `platform.marketplace.messages` | `view` |
| `platform.marketplace.payments` | GET, HEAD, POST | `/marketplace/payments/{method?}` | `SubscriptionPaymentListScreen` | `platform.marketplace.payments` | `view` |
| `platform.marketplace.payments.show` | GET, HEAD, POST | `/marketplace/payments/{subscriptionPayment}/{method?}` | `SubscriptionPaymentShowScreen` | `platform.marketplace.payments` | `view` |

Маршрут `Route::screen()` автоматически ограничивает сегмент `{method?}` списком публичных методов экрана. Запрос метода, которого нет в экране, отклоняется с `405`: URI совпадает с fallback-маршрутом панели `admin/{fallbackPlaceholder}`, который зарегистрирован только для GET.

Параметры карточек (`{site}`, `{conversation}`, `{message}`, `{subscriptionPayment}`) ограничены регулярным выражением `[0-9]+`, чтобы URL действий списка (например, `/marketplace/messages/save`) не связывался с моделью карточки. Привязка выполняется по `id`.

## Response Contract

| Ситуация | Ответ |
|---|---|
| Неаутентифицированный пользователь | `302` на `/admin/login` |
| Аутентифицированный пользователь без права | `403` |
| Успешное сохранение | `302` назад с flash-уведомлением об успехе |
| Ошибка валидации | `302` назад с ошибками в сессии; данные не сохраняются |
| Недопустимый переход статуса | `302` назад с сообщением об ошибке; статус не меняется |
| Удаление используемой тематики | `302` назад с сообщением об ошибке; запись сохраняется |
| Несуществующая запись | `404` |
| POST на действие, отсутствующее в read-only-экране | `405` |

## Site List Contract

**Query**: `Site` со связями `owner`, `categories`; счётчики `conversations`, `subscriptionPayments`.

**Filters (query string)**:

| Параметр | Значение | Поведение |
|---|---|---|
| `filter[domain]` | строка | частичное совпадение без учёта регистра |
| `filter[status]` | `active`, `inactive`, `withdrawn`, `sold` | точное совпадение |
| `filter[price][min]`, `filter[price][max]` | число | включительный диапазон |
| `filter[monthly_revenue][min]`, `[max]` | число | включительный диапазон |
| `filter[verified]` | `0`, `1` | точное совпадение |
| `filter[created_at][start]`, `[end]` | дата | диапазон |
| `category` | id тематики | объявления хотя бы с одной выбранной тематикой |

**Sort**: `sort=domain`, `sort=-price`, `sort=-created_at` и другие из `$allowedSorts`.

**Columns**: ID, название (ссылка на карточку), домен, статус, цена, доход, подтверждение, «оплачено до», владелец, создано.

## Site Edit Contract

**Route model binding**: `{site}` по `id`.

**GET** возвращает:

- `site` — объявление со связями `owner`, `categories`;
- `payments` — последние 10 платежей;
- `conversations` — последние 10 диалогов.

**POST `method=save`**:

```text
site[title]              required|string|min:3|max:255
site[domain]             required|string|max:253|domain-format|unique-among-active-inactive
site[description]        required|string|min:50|max:5000
site[price]              required|numeric|min:0
site[monthly_revenue]    required|numeric|min:0
site[has_trademark]      boolean
site[categories][]       required|array|min:1
site[categories][].*     exists:categories,id
site[verified]           boolean (переключатель с sendTrueOrFalse)
```

Сохранение:

1. Валидация.
2. Транзакция.
3. Заполнение коммерческих полей и сохранение.
4. Синхронизация тематик.
5. `markVerified()` по значению `site[verified]`; переключатель использует `sendTrueOrFalse()`, поэтому значение присутствует всегда.
6. Уведомление об успехе.

Поля `verification_filename`, `paid_until`, `sold_at`, `user_id` не принимаются формой; если переданы — игнорируются, так как отсутствуют в `Fillable`.

**POST `method=changeStatus`**:

```text
site[status]  required|in:<allowed transitions from current status>
```

Вызывается `Site::changeStatus()`. `DomainException` перехватывается и превращается в сообщение об ошибке.

## Category Contracts

**List**: название, публичный идентификатор, количество объявлений, действия «Изменить» и «Удалить».

**POST `platform.marketplace.categories` `method=remove`**:

```text
id  required|exists:categories,id
```

Удаление выполняется только при `sites_count = 0`; иначе flash-ошибка.

**Create/edit form**:

```text
category[name]  required|string|max:255|unique:categories,name,<id>
```

Публичный идентификатор не принимается формой и не меняется при редактировании.

## User Screen Contract

Существующие маршруты не меняются:

| Имя | URL | Право |
|---|---|---|
| `platform.systems.users` | `/users/{method?}` | `platform.systems.users` |
| `platform.systems.users.edit` | `/users/{user}/edit/{method?}` | `platform.systems.users` |
| `platform.systems.users.create` | `/users/create/{method?}` | `platform.systems.users` |

**Изменения**:

- `UserListScreen::query()` добавляет `withCount(['sites', 'subscriptionPayments'])`.
- `UserEditScreen::query()` добавляет `sites`, `conversations`, `payments` (по 10 последних).
- Действие `remove` удаляется из `UserEditScreen` и из списка; маршрут `method=remove` становится недоступным (`405`).
- Существующие поля `user[name]`, `user[email]`, `user[roles]`, `permissions` сохраняются.

## Conversation Contracts

**List filters**:

| Параметр | Поведение |
|---|---|
| `site` | домен или название объявления, частичное совпадение |
| `participant` | имя или email покупателя/продавца, частичное совпадение |

**Show**: только GET-просмотр. Сообщения выводятся по возрастанию `created_at`. POST-действия отсутствуют.

## Message Contracts

**List filters**:

| Параметр | Поведение |
|---|---|
| `conversation` | ID диалога или домен объявления |
| `sender` | имя или email отправителя |
| `read` | `read` — `read_at` не пуст; `unread` — пуст |

**Show**: только GET-просмотр. POST-действия отсутствуют.

## Subscription Payment Contracts

**List filters**:

| Параметр | Поведение |
|---|---|
| `filter[status]` | `pending`, `paid`, `failed`, `cancelled` |
| `filter[paid_at][start]`, `[end]` | диапазон даты оплаты |
| `site` | домен или название объявления |
| `payer` | имя или email плательщика |

**Show**: только GET-просмотр. POST-действия отсутствуют. Изменение статуса платежа через панель не предусмотрено.

## Menu Contract

Группа «Биржа» содержит ровно шесть пунктов в порядке:

1. Пользователи — `platform.systems.users`
2. Тематики — `platform.marketplace.categories`
3. Объявления — `platform.marketplace.sites`
4. Диалоги — `platform.marketplace.conversations`
5. Сообщения — `platform.marketplace.messages`
6. Платежи — `platform.marketplace.payments`

Пункт отображается только при наличии права. Активный пункт выделяется Orchid автоматически по текущему маршруту.
