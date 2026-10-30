# Data Model: Личный кабинет продавца

**Feature**: `005-seller-cabinet` | **Date**: 2026-10-30

Модель данных и правила статусов уже описаны в `specs/001-marketplace-data-model/data-model.md`; здесь зафиксированы только изменения и представления, нужные кабинету. Все суммы — целые рубли в API и `decimal(12,2)` в БД; даты — ISO 8601 в API.

---

## 1. Объявление (`sites`)

### Существующие поля, используемые кабинетом

| Поле | Тип | Правило кабинета |
|---|---|---|
| `id` | bigint PK | Идентификатор в API |
| `user_id` | FK → `users.id`, `restrictOnDelete` | Владелец; определяется сервером, продавцу недоступен |
| `title` | string(255) | Обязательно, 3–255 символов |
| `domain` | string(253) | Обязательно, нормализуется: trim, нижний регистр, без `www.`, без завершающего `/`; корректный домен |
| `status` | string(16), default `active` | Одно из `active`, `inactive`, `withdrawn`, `sold` |
| `public_domain` | генерируемая колонка | Уникальность домена среди активных/неактивных; в кабинете не редактируется |
| `description` | text | Обязательно, 50–5000 символов |
| `price` | decimal(12,2), default 0 | Обязательно, ≥ 0, ≤ 9 999 999 999.99 |
| `monthly_revenue` | decimal(12,2), default 0 | Обязательно, ≥ 0, ≤ 9 999 999 999.99 |
| `has_trademark` | boolean, default false | Переключатель в форме |
| `verification_filename` | string(64), unique | Генерируется при создании (`Str::random(32).'.txt'`), не меняется никогда; только чтение в кабинете |
| `verified` | boolean, default false | Только чтение в кабинете; меняет администратор в Orchid |
| `paid_until` | date, nullable | Дата «оплачено до»; только отображение |
| `sold_at` | timestamp, nullable | Заполняется при переходе в «Продан» |
| `created_at`, `updated_at` | timestamps | `created_at` — сортировка списка |

### Новые поля

| Поле | Тип | Назначение |
|---|---|---|
| `hidden_at` | timestamp, nullable | Скрытие проданного объявления из списка кабинета; `null` — показывается |
| `domain_zone` | string(16), nullable | Доменная зона (`ru`, `com`, `de`, …), выводится из `domain`; нужна фильтру каталога |

### Индексы

- `sites_public_domain_unique` (существующий) — защита от гонки при дублях домена.
- `sites_visibility_index (status, paid_until)` (существующий) — публичная выдача.
- `sites_user_hidden_index (user_id, hidden_at)` — новый, для списка и раздела скрытых.
- `sites_domain_zone_index (domain_zone)` — новый, для фильтра зон каталога.
- Существующие индексы `price`, `monthly_revenue`, `created_at` сохраняются.

### Правила жизненного цикла

Переходы статусов (существующее правило, повторяется кабинетом):

| Из \ В | active | inactive | withdrawn | sold |
|---|---|---|---|---|
| active | — | ✓ | ✓ | ✓ |
| inactive | ✓ | — | ✓ | ✓ |
| withdrawn | ✓ | ✓ | — | ✓ |
| sold | ✗ | ✗ | ✗ | — |

- `sold` — терминальный: редактирование, смена статуса, скрытие допустимы только для скрытия (`hidden_at`), но не для возврата в продажу и не для оплаты.
- Переход в `sold` проставляет `sold_at = now()`.
- Смена статуса не меняет `paid_until` других объявлений и не влияет на них.
- Скрытие (`hidden_at`) допустимо только при `status = sold`; не меняет статус, `paid_until`, `sold_at`, диалоги и платежи.
- Создание всегда даёт `status = active`, `verified = false`, уникальный `verification_filename`; в каталог объявление не попадает, пока `paid_until` не задан или не наступил.

### Публичная видимость

`isPubliclyVisible()` (существующее): `status = active` AND `paid_until !== null` AND `paid_until >= today()`. `hidden_at` на публичность не влияет (проданное и так скрыто).

### Производные значения

| Значение | Формула | Где используется |
|---|---|---|
| `needsRenewal` | `paid_until === null` OR `paid_until <= today()->addDays(7)` | Пометка «требует продления», FR-025 |
| `statusLabel` | `ListingStatus::label()` | Бейдж статуса |
| `isPubliclyVisible` | см. выше | Пометка «скрыто из каталога», ссылка на каталог |
| `unreadMessages` | непрочитанные сообщения по диалогам этого объявления | Карточка в списке |

---

## 2. Пользователь (`users`)

Существующая модель без изменений: `name`, `avatar`, `vk_id`, `ya_id`, `vk_last_login_at`, `ya_last_login_at`, `permissions`.

Связи, используемые кабинетом:

- `sites()` — объявления пользователя;
- `conversationsAsBuyer()` и `conversationsAsSeller()` — диалоги по ролям;
- `messages()` — отправленные сообщения;
- `subscriptionPayments()` — платежи (только чтение).

Кабинет не меняет профиль: имя и аватар приходят от провайдера (Assumptions спецификации).

---

## 3. Тематика (`categories`)

Существующая модель: `id`, `name`, `slug`, timestamps. Кабинет использует справочник для выбора нескольких тематик и для отображения в карточке; редактирование справочника остаётся в Orchid.

Связь: `sites` ↔ `categories` через `category_site` (существующая).

---

## 4. Диалог (`conversations`)

Существующие поля: `site_id`, `buyer_id`, `seller_id`, `last_message_at`, timestamps. Уникальность `(site_id, buyer_id)`.

Правила кабинета:

- доступ только участнику (`buyer_id` или `seller_id`); выборка — scope `forParticipant(User)`;
- один диалог на пару «объявление + покупатель»; повторное сообщение добавляется в существующий;
- продавец не может инициировать диалог; покупатель инициирует по публично видимому объявлению;
- режим «только чтение», если объявление неактивно, снято с продажи, продано или его подписка истекла; причина показывается участникам;
- `last_message_at` обновляется при создании сообщения (существующий хук модели).

Производные значения: `unreadCount` (для текущего пользователя), `lastMessage`, `isReadOnly`, `role` (`buyer`/`seller`).

---

## 5. Сообщение (`messages`)

Существующие поля: `conversation_id`, `sender_id`, `body` (text), `read_at`, timestamps.

Правила кабинета:

- текст 1–2000 символов, не пустой (после trim);
- отправка только участником и только если диалог не в режиме «только чтение»;
- входящие сообщения помечаются прочитанными при открытии диалога (`markReadFor`);
- сообщения не редактируются и не удаляются участниками;
- `isMine` вычисляется относительно текущего пользователя.

Агрегат для шапки: количество сообщений, где `sender_id != current_user` и `read_at IS NULL`, по всем диалогам пользователя.

---

## 6. Платёж (`subscription_payments`)

Существующие поля: `site_id`, `user_id`, `amount`, `currency`, `period_months`, `status` (`pending`/`paid`/`failed`/`cancelled`), `paid_at`, `external_id`, timestamps.

В кабинете — только чтение: история по объявлению (сумма, период, статус, время). Никаких операций создания/изменения платежа нет (FR-029). Наличие и статус платежа в кабинете не влияет на видимость объявления — видимость считается только по `status` и `paid_until`.

---

## 7. Представления API

### 7.1 `SiteResource` (кабинет)

| Поле | Тип | Источник |
|---|---|---|
| `id` | integer | `id` |
| `title` | string | `title` |
| `domain` | string | `domain` |
| `domainZone` | string \| null | `domain_zone` |
| `description` | string | `description` |
| `price` | integer | `price` → int |
| `monthlyRevenue` | integer | `monthly_revenue` → int |
| `hasTrademark` | boolean | `has_trademark` |
| `status` | string | `status->value` |
| `statusLabel` | string | `status->label()` |
| `verified` | boolean | `verified` |
| `verificationFilename` | string | `verification_filename` |
| `paidUntil` | string \| null | `paid_until` (`Y-m-d`) |
| `needsRenewal` | boolean | `needsRenewal()` |
| `isPubliclyVisible` | boolean | `isPubliclyVisible()` |
| `soldAt` | string \| null | `sold_at` (ISO) |
| `hiddenAt` | string \| null | `hidden_at` (ISO) |
| `categories` | array<{id, name, slug}> | `categories` |
| `unreadMessages` | integer | счётчик диалогов объявления |
| `createdAt` | string | `created_at` (ISO) |
| `updatedAt` | string | `updated_at` (ISO) |

Детальная карточка (`GET /my/sites/{site}`) дополнительно содержит:

| Поле | Тип | Источник |
|---|---|---|
| `payments` | array<SubscriptionPaymentResource> | `subscriptionPayments` (новые сначала) |
| `availableStatuses` | array<{value,label}> | `status->allowedTransitions()` |

### 7.2 `SubscriptionPaymentResource`

`id`, `amount` (integer), `currency`, `periodMonths`, `status`, `statusLabel`, `paidAt` (ISO|null), `createdAt` (ISO).

### 7.3 `PublicSiteResource` (каталог)

Совпадает с контрактом `003`: `id` (число), `title`, `domain`, `domainZone`, `description`, `price` (int), `monthlyIncome` (int), `status`, `isVerified`, `faviconUrl` (`null`), `publishedAt` (ISO).

### 7.4 `ConversationResource`

| Поле | Тип | Описание |
|---|---|---|
| `id` | integer | Идентификатор |
| `role` | `buyer` \| `seller` | Роль текущего пользователя |
| `site` | `{id,title,domain,status,isPubliclyVisible}` | Объявление диалога |
| `counterparty` | `{id,displayName,avatarUrl}` | Собеседник |
| `lastMessage` | `{body,createdAt,isMine}` \| null | Последнее сообщение |
| `unreadCount` | integer | Непрочитанные для текущего пользователя |
| `isReadOnly` | boolean | Причина — `site` не публично видимое |
| `lastMessageAt` | string \| null | ISO |

### 7.5 `MessageResource`

`id`, `body`, `isMine`, `createdAt` (ISO), `readAt` (ISO|null).

### 7.6 `CategoryResource`

`id`, `name`, `slug`.

---

## 8. Правила валидации (Form Request)

| Поле | Создание | Редактирование | Сообщение об ошибке (пример) |
|---|---|---|---|
| `title` | required, string, 3–255 | то же | «Название должно быть от 3 до 255 символов» |
| `domain` | required, string, ≤253, формат домена, `AvailableDomain` | то же, текущий id исключён | «Этот домен уже занят другим объявлением» |
| `description` | required, string, 50–5000 | то же | «Описание должно быть от 50 до 5000 символов» |
| `price` | required, numeric, ≥0, ≤9999999999 | то же | «Цена не может быть отрицательной» |
| `monthlyRevenue` | required, numeric, ≥0, ≤9999999999 | то же | «Доход не может быть отрицательным» |
| `hasTrademark` | boolean | то же | — |
| `categories` | required, array, min:1, `exists:categories,id` | то же | «Выберите хотя бы одну тематику» |
| `status` (отдельный запрос) | — | required, in enum, допустимый переход | «Нельзя перейти из статуса «Продан»» |
| `body` (сообщение) | required, string, 1–2000 | — | «Сообщение должно быть не длиннее 2000 символов» |
| `siteId` (старт диалога) | required, integer, exists | — | «Объявление не найдено» |

Поля `user_id`, `verified`, `verification_filename`, `hidden_at`, `paid_until`, `sold_at` в запросах кабинета не принимаются.

---

## 9. Гарантии и инварианты

1. Владелец объявления не меняется через API кабинета.
2. `verification_filename` уникален и не меняется после создания.
3. Домен уникален среди объявлений в статусах `active`/`inactive`; БД-индекс `public_domain` — последний барьер.
4. `sold` не возвращается в другие статусы и не редактируется.
5. Скрыть можно только `sold`; скрытие не удаляет данные.
6. Один диалог на пару «объявление + покупатель».
7. Непрочитанные считаются только для получателя; свои сообщения не считаются.
8. Переписка по непубличному объявлению доступна только для чтения обеим сторонам.
9. Списки кабинета всегда ограничены текущим пользователем на уровне запроса.
10. `needsRenewal` вычисляется сервером; фронтенд не пересчитывает даты.

---

## 10. Миграции

1. `add_hidden_at_to_sites_table` — nullable timestamp + индекс `(user_id, hidden_at)`; `down()` удаляет индекс и колонку.
2. `add_domain_zone_to_sites_table` — nullable string(16) + индекс; бэкфилл существующих строк через `DB::table('sites')->orderBy('id')->chunkById(...)`, значение — последняя метка домена в нижнем регистре, `null` при отсутствии точки; `down()` удаляет индекс и колонку.

Обе миграции безопасны на заполненной таблице: новые колонки nullable, бэкфилл не блокирует чтение и не меняет существующие данные, кроме производного `domain_zone`.