# Data Model: Все модели в административной панели

**Feature**: `002-orchid-admin-models` | **Date**: 2026-10-28 | **Spec**: [spec.md](./spec.md) | **Plan**: [plan.md](./plan.md)

Схема данных не меняется: используются сущности из `specs/001-marketplace-data-model/data-model.md`. Документ описывает административные проекции, редактируемость полей, правила валидации и ограничения доступа.

## Entity Access Matrix

| Сущность | Список | Карточка | Создание | Редактирование | Удаление | Режим |
|---|---|---|---|---|---|---|
| Пользователь (`User`) | да | да | существующий | имя, email, роли | **нет** | существующие экраны + блоки биржи |
| Тематика (`Category`) | да | да | да | название | только без объявлений | полный CRUD с ограничением |
| Объявление (`Site`) | да | да | нет | коммерческие поля, тематики, статус, подтверждение | нет | редактирование с guardrails |
| Диалог (`Conversation`) | да | да | нет | нет | нет | только чтение |
| Сообщение (`Message`) | да | да | нет | нет | нет | только чтение |
| Платёж (`SubscriptionPayment`) | да | да | нет | нет | нет | только чтение |

## Permissions

| Право | Раздел | Экран |
|---|---|---|
| `platform.systems.users` | Пользователи | `UserListScreen`, `UserEditScreen` (существующие) |
| `platform.marketplace.categories` | Тематики | `CategoryListScreen`, `CategoryEditScreen` |
| `platform.marketplace.sites` | Объявления | `SiteListScreen`, `SiteEditScreen` |
| `platform.marketplace.conversations` | Диалоги | `ConversationListScreen`, `ConversationShowScreen` |
| `platform.marketplace.messages` | Сообщения | `MessageListScreen`, `MessageShowScreen` |
| `platform.marketplace.payments` | Платежи | `SubscriptionPaymentListScreen`, `SubscriptionPaymentShowScreen` |

Права регистрируются в `App\Orchid\PlatformProvider::permissions()` в группе «Биржа». Каждый пункт меню объявляет соответствующее право через `->permission()`.

## Site

### Редактируемые поля

| Поле формы | Тип | Правила | Примечание |
|---|---|---|---|
| `site.title` | текст | обязательно, 3–255 символов | FR-017 |
| `site.domain` | текст | обязательно, формат домена, уникальность среди `active`/`inactive` | нормализуется мутатором модели |
| `site.description` | многострочный текст | обязательно, 50–5000 символов | FR-017 |
| `site.price` | число | обязательно, `>= 0`, шаг 0.01 | FR-017 |
| `site.monthly_revenue` | число | обязательно, `>= 0`, шаг 0.01 | FR-017 |
| `site.has_trademark` | переключатель | boolean | FR-017 |
| `site.categories` | множественный выбор | минимум одна, каждая существует | синхронизация pivot |
| `site.status` | выбор | только допустимый переход | отдельное действие `changeStatus` |
| `site.verified` | переключатель | boolean | отдельное действие `save`, вызов `markVerified()` |

### Только для чтения

| Поле | Причина |
|---|---|
| `verification_filename` | генерируется один раз, неизменяемо (FR-019) |
| `paid_until` | меняется только успешным платежом (FR-019) |
| `sold_at` | выставляется моделью при переходе в «Продан» |
| `owner` | владелец не меняется через административный экран |
| `created_at`, `updated_at` | служебные отметки |

### Валидация домена

1. Значение приводится к нижнему регистру, обрезаются пробелы, удаляется префикс `www.` и завершающий `/`.
2. Проверяется формат домена.
3. Проверяется отсутствие другого объявления с тем же нормализованным доменом в статусах `active` или `inactive`.
4. Домен объявления в статусах `withdrawn` или `sold` не участвует в проверке уникальности, поэтому может быть выставлен повторно.

### Допустимые переходы статусов

```text
active    ↔ inactive
active    ↔ withdrawn
inactive  ↔ withdrawn
active    → sold
inactive  → sold
withdrawn → sold
sold      → (конечный)
```

Переход выполняется методом `Site::changeStatus(ListingStatus)`. Недопустимый переход возвращает `DomainException`, экран показывает сообщение об ошибке и не сохраняет изменение.

### Публичная видимость

Пересчитывается существующим методом `Site::isPubliclyVisible()`: статус `active`, `paid_until` не пуст и не раньше сегодняшней даты. Административное редактирование не меняет это правило (FR-021).

### Фильтры и сортировка

`Site` получает конфигурацию штатных фильтров Orchid:

| Поле | Фильтр | UI |
|---|---|---|
| `domain` | `Like` | текстовый ввод |
| `status` | `Where` | выбор статуса |
| `price` | `WhereMaxMin` | диапазон |
| `monthly_revenue` | `WhereMaxMin` | диапазон |
| `verified` | `Where` | выбор «да/нет» |
| `created_at` | `WhereDateStartEnd` | диапазон дат |

Сортировка: `id`, `title`, `domain`, `price`, `monthly_revenue`, `verified`, `paid_until`, `created_at`.

Фильтр по тематике реализуется отдельным `SiteCategoryFilter` через `whereHas('categories')`.

### Запрос списка

```text
Site::query()
    ->with(['owner', 'categories'])
    ->withCount(['conversations', 'subscriptionPayments'])
    ->filters(SiteFiltersLayout::class)
    ->defaultSort('created_at', 'desc')
    ->paginate()
```

### Запрос карточки

```text
site: Site::with(['owner', 'categories'])->findOrFail($id)
payments: $site->subscriptionPayments()->latest()->limit(10)->get()
conversations: $site->conversations()->with(['buyer', 'seller'])->latest('last_message_at')->limit(10)->get()
```

## Category

### Поля

| Поле | Тип | Правила | Примечание |
|---|---|---|---|
| `name` | текст | обязательно, уникально, до 255 символов | редактируемое |
| `slug` | текст | генерируется при создании, не меняется | только для чтения |
| `sites_count` | число | вычисляется | только для чтения |

### Правила

- Создание: `name` сохраняется, `slug` генерируется моделью.
- Переименование: `slug` остаётся прежним; связанные объявления отображают новое название через связь.
- Удаление: запрещено, если `sites()->exists()`; иначе запись удаляется.
- Дубликат названия отклоняется с ошибкой валидации.

### Фильтры и сортировка

| Поле | Фильтр | UI |
|---|---|---|
| `name` | `Like` | текстовый ввод |
| `sites_count` | сортировка | — |

## User

### Редактируемые поля

| Поле | Примечание |
|---|---|
| `name` | существующий экран |
| `email` | существующий экран; уникальность |
| `roles` | существующий экран |
| `permissions` | существующий экран |

Смена пароля остаётся существующей возможностью панели и не входит в объём изменений. Удаление пользователя убирается из списка и карточки (FR-028), чтобы не терять связанные объявления, диалоги и платежи.

### Связанные данные в карточке

| Блок | Выборка | Ограничение |
|---|---|---|
| Объявления | `$user->sites()->latest()->get()` | 10 последних |
| Диалоги | `Conversation::where('buyer_id', $user->id)->orWhere('seller_id', $user->id)->with(['site', 'buyer', 'seller'])->latest('last_message_at')->get()` | 10 последних |
| Платежи | `$user->subscriptionPayments()->with('site')->latest()->get()` | 10 последних |

Каждая таблица содержит ссылку на карточку сущности; действия изменения отсутствуют.

### Счётчики в списке

`UserListScreen` получает `withCount(['sites', 'subscriptionPayments'])` и отображает количество объявлений и платежей. Счётчик диалогов вычисляется как сумма покупательских и продавцовых связей.

## Conversation

### Поля карточки

| Поле | Источник |
|---|---|
| Объявление | `site.title`, `site.domain`, ссылка на карточку |
| Покупатель | `buyer.name`, `buyer.email` |
| Продавец | `seller.name`, `seller.email` |
| Последнее сообщение | `last_message_at` |
| Количество сообщений | `messages_count` |
| История | таблица сообщений в хронологическом порядке |

### Правила

- Только чтение: экран не объявляет методов сохранения, удаления или отправки.
- История доступна независимо от статуса объявления.
- Фильтры: объявление (домен/название), участник (имя/email покупателя или продавца).

### Запрос списка

```text
Conversation::query()
    ->with(['site', 'buyer', 'seller'])
    ->withCount('messages')
    ->filters(ConversationFiltersLayout::class)
    ->defaultSort('last_message_at', 'desc')
    ->paginate()
```

## Message

### Поля карточки

| Поле | Источник |
|---|---|
| Диалог | ссылка на карточку диалога |
| Объявление | `conversation.site.title` |
| Отправитель | `sender.name`, `sender.email` |
| Текст | `body`, полностью |
| Отправлено | `created_at` |
| Прочитано | `read_at` или отметка «не прочитано» |

### Правила

- Только чтение.
- Фильтры: диалог (id или домен объявления), отправитель (имя/email), прочтение (`read`/`unread`).
- В списке текст сокращается; в карточке показывается полностью.

### Запрос списка

```text
Message::query()
    ->with(['conversation.site', 'sender'])
    ->filters(MessageFiltersLayout::class)
    ->defaultSort('created_at', 'desc')
    ->paginate()
```

## SubscriptionPayment

### Поля карточки

| Поле | Источник |
|---|---|
| Объявление | `site.title`, `site.domain`, ссылка |
| Плательщик | `payer.name`, `payer.email`, ссылка |
| Сумма | `amount` |
| Валюта | `currency` |
| Период | `period_months` |
| Статус | `status` (подпись enum) |
| Оплачен | `paid_at` |
| Внешний идентификатор | `external_id` |
| Текущая дата «оплачено до» | `site.paid_until` |

### Правила

- Только чтение.
- Фильтры: статус, объявление (домен/название), плательщик (имя/email), диапазон `paid_at`.
- Суммы отображаются с двумя знаками после запятой; валюта — `RUB`.

### Запрос списка

```text
SubscriptionPayment::query()
    ->with(['site', 'payer'])
    ->filters(PaymentFiltersLayout::class)
    ->defaultSort('created_at', 'desc')
    ->paginate()
```

## Validation Summary

| Сущность | Поле | Правило | FR |
|---|---|---|---|
| Site | `title` | required, string, 3–255 | FR-017 |
| Site | `domain` | required, формат, уникальность среди active/inactive | FR-017 |
| Site | `description` | required, string, 50–5000 | FR-017 |
| Site | `price` | required, numeric, min 0 | FR-017 |
| Site | `monthly_revenue` | required, numeric, min 0 | FR-017 |
| Site | `has_trademark` | boolean | FR-017 |
| Site | `categories` | required, array, min 1, exists | FR-017 |
| Site | `status` | допустимый переход | FR-020 |
| Site | `verified` | boolean, только через `markVerified()` | FR-018 |
| Category | `name` | required, string, max 255, unique | FR-012 |
| User | `name` | required, string, max 255 | FR-008 |
| User | `email` | required, email, max 255, unique | FR-009 |

## Invariants & Enforcement

| Инвариант | FR | Где обеспечивается |
|---|---|---|
| Каждый раздел имеет отдельное право | FR-003 | `permission()` экрана, `->permission()` меню |
| Меню содержит все шесть разделов | FR-002 | `PlatformProvider::menu()` |
| Служебные поля объявления неизменяемы | FR-019 | поля вне формы; `verification_filename` вне `Fillable`; `paid_until` меняется только платежом |
| Статус меняется по правилам | FR-020 | `Site::changeStatus()` |
| Подтверждение владения — только администратор | FR-018 | `Site::markVerified()` |
| Публичная видимость пересчитывается | FR-021 | `Site::isPubliclyVisible()` |
| Используемая тематика не удаляется | FR-013 | проверка `sites()->exists()` |
| Диалоги, сообщения и платежи только для чтения | FR-024, FR-026 | отсутствие методов-действий; ограничение маршрута |
| Нет destructive-удаления пользователей и объявлений | FR-028 | удаление не добавляется; существующее удаление пользователей убирается |
| Связанные данные видны в карточке пользователя | FR-007 | три таблицы в `UserEditScreen` |
| Списки не создают N+1 | SC-011 | `with()`, `withCount()` |

## No Schema Changes

Миграции не создаются. Единственные изменения слоя данных:

- подключение трейта `Orchid\Filters\Filterable` к `Site`, `Category`, `Conversation`, `Message` и `SubscriptionPayment` для штатных `->filters()` и `->defaultSort()` Orchid;
- `Site::$allowedFilters` и `Site::$allowedSorts` для штатных фильтров Orchid;
- `Category::$allowedFilters` для поиска по названию;
- `SubscriptionPayment::$allowedFilters` для фильтра по статусу и дате оплаты;
- `Conversation::$allowedFilters` и `Message::$allowedFilters` при необходимости сортировки по датам.

Все изменения — декларативная конфигурация, не затрагивающая таблицы и существующие инварианты.
