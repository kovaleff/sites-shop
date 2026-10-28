---
description: "Task list for feature implementation"
---

# Tasks: Все модели в административной панели

**Input**: Design documents from `/specs/002-orchid-admin-models/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/, quickstart.md

**Tests**: Включены, потому что проект требует покрытия feature-тестами. Тесты пишутся до реализации и должны падать до её завершения.

**Organization**: Задачи сгруппированы по пользовательским историям. US1 — навигация и списки (MVP), US2–US6 добавляют управление и карточки.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (разные файлы, нет зависимостей)
- **[Story]**: US1–US6
- В каждой задаче указан точный путь к файлу

## Path Conventions

- Приложение: `app/`, маршруты: `routes/`, тесты: `tests/`
- Административные классы: `app/Orchid/Screens`, `app/Orchid/Layouts`, `app/Orchid/Filters`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Подготовить модели к фильтрам Orchid и тестовую инфраструктуру.

- [X] T001 [P] Подключить трейт `Orchid\Filters\Filterable` и объявить `$allowedFilters`/`$allowedSorts` в `app/Models/Site.php`: `domain => Like`, `status => Where`, `price => WhereMaxMin`, `monthly_revenue => WhereMaxMin`, `verified => Where`, `created_at => WhereDateStartEnd`; сортировка по `id`, `title`, `domain`, `price`, `monthly_revenue`, `verified`, `paid_until`, `created_at`.
- [X] T002 [P] Подключить `Orchid\Filters\Filterable` и объявить `$allowedFilters`/`$allowedSorts` в `app/Models/Category.php`: `name => Like`; сортировка по `name`, `created_at`.
- [X] T003 [P] Подключить `Orchid\Filters\Filterable` и объявить `$allowedFilters`/`$allowedSorts` в `app/Models/Conversation.php`: `last_message_at => WhereDateStartEnd`; сортировка по `last_message_at`, `created_at`.
- [X] T004 [P] Подключить `Orchid\Filters\Filterable` и объявить `$allowedFilters`/`$allowedSorts` в `app/Models/Message.php`: `created_at => WhereDateStartEnd`; сортировка по `created_at`.
- [X] T005 [P] Подключить `Orchid\Filters\Filterable` и объявить `$allowedFilters`/`$allowedSorts` в `app/Models/SubscriptionPayment.php`: `status => Where`, `paid_at => WhereDateStartEnd`; сортировка по `created_at`, `paid_at`, `amount`.
- [X] T006 [P] Создать базовый класс `tests/Feature/Orchid/OrchidTestCase.php` с `RefreshDatabase` и helper-методом `admin(array $permissions = []): User`, выдающим по умолчанию `platform.index`, `platform.systems.users` и все права `platform.marketplace.*`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Зарегистрировать права, без которых экраны и меню недоступны.

**⚠️ CRITICAL**: До завершения этой фазы пользовательские истории не начинаются.

- [X] T007 Зарегистрировать права `platform.marketplace.categories`, `platform.marketplace.sites`, `platform.marketplace.conversations`, `platform.marketplace.messages`, `platform.marketplace.payments` в группе «Биржа» в `app/Orchid/PlatformProvider.php::permissions()`.

**Checkpoint**: Права зарегистрированы, можно создавать экраны и меню.

---

## Phase 3: User Story 1 - Все разделы доступны из меню (Priority: P1) 🎯 MVP

**Goal**: Администратор видит в меню группу «Биржа» с шестью ссылками; каждая открывает список сущности.

**Independent Test**: Войти администратором, проверить шесть пунктов меню, открыть каждый список, убедиться в наличии пагинации и пустого состояния; проверить скрытие пунктов и `403` без права.

### Tests for User Story 1

- [X] T008 [P] [US1] Написать `tests/Feature/Orchid/AdminMenuTest.php`: группа «Биржа» содержит ровно шесть пунктов (Пользователи, Тематики, Объявления, Диалоги, Сообщения, Платежи); каждый пункт открывает свой список с кодом `200`.
- [X] T009 [P] [US1] Написать `tests/Feature/Orchid/AccessControlTest.php`: неаутентифицированный получает `302` на `/admin/login`; пользователь без права получает `403` и не видит пункт меню; пользователь с одним правом видит только один пункт.

### Implementation for User Story 1

- [X] T010 [P] [US1] Реализовать список объявлений: `app/Orchid/Layouts/Site/SiteListLayout.php` (ID, название, домен, статус, цена, доход, подтверждение, «оплачено до», владелец, создано) и `app/Orchid/Screens/Site/SiteListScreen.php` (`with(['owner','categories'])`, `withCount`, `defaultSort('created_at','desc')`, `paginate()`, право `platform.marketplace.sites`).
- [X] T011 [P] [US1] Реализовать список тематик: `app/Orchid/Layouts/Category/CategoryListLayout.php` (название, публичный идентификатор, число объявлений) и `app/Orchid/Screens/Category/CategoryListScreen.php` (`withCount('sites')`, поиск по названию, право `platform.marketplace.categories`).
- [X] T012 [P] [US1] Реализовать список диалогов: `app/Orchid/Layouts/Conversation/ConversationListLayout.php` (объявление, покупатель, продавец, последнее сообщение, число сообщений) и `app/Orchid/Screens/Conversation/ConversationListScreen.php` (`with(['site','buyer','seller'])`, `withCount('messages')`, право `platform.marketplace.conversations`).
- [X] T013 [P] [US1] Реализовать список сообщений: `app/Orchid/Layouts/Message/MessageListLayout.php` (диалог, отправитель, сокращённый текст, прочитано, создано) и `app/Orchid/Screens/Message/MessageListScreen.php` (`with(['conversation.site','sender'])`, право `platform.marketplace.messages`).
- [X] T014 [P] [US1] Реализовать список платежей: `app/Orchid/Layouts/SubscriptionPayment/SubscriptionPaymentListLayout.php` (объявление, плательщик, сумма, валюта, период, статус, оплачен) и `app/Orchid/Screens/SubscriptionPayment/SubscriptionPaymentListScreen.php` (`with(['site','payer'])`, право `platform.marketplace.payments`).
- [X] T015 [US1] Добавить в `routes/platform.php` пять маршрутов списков: `platform.marketplace.sites`, `platform.marketplace.categories`, `platform.marketplace.conversations`, `platform.marketplace.messages`, `platform.marketplace.payments` с breadcrumbs от `platform.index` (зависит от T010–T014).
- [X] T016 [US1] Добавить в `app/Orchid/PlatformProvider.php::menu()` группу «Биржа» с шестью пунктами: Пользователи (`platform.systems.users`), Тематики, Объявления, Диалоги, Сообщения, Платежи; перенести пункт пользователей из «Access Controls», роли оставить там.
- [X] T017 [US1] Запустить `php artisan test --compact tests/Feature/Orchid/AdminMenuTest.php tests/Feature/Orchid/AccessControlTest.php` и устранить расхождения.

**Checkpoint**: US1 самостоятельно проверяема: все модели видны в меню и открываются.

---

## Phase 4: User Story 2 - Управление объявлениями и подтверждение владения (Priority: P1)

**Goal**: Администратор фильтрует все объявления, редактирует коммерческие поля и тематики, подтверждает владение, меняет статус по правилам.

**Independent Test**: Найти объявления через каждый фильтр, изменить поля и тематики, переключить подтверждение, проверить недопустимый переход статуса и неизменность служебных полей.

### Tests for User Story 2

- [X] T018 [P] [US2] Написать `tests/Feature/Orchid/SiteManagementTest.php`: фильтры по домену, статусу, тематике, цене, доходу и подтверждению; редактирование `title` (3–255), `domain` (формат, уникальность среди `active`/`inactive`), `description` (50–5000), `price`/`monthly_revenue` (`>= 0`), `categories` (минимум одна); переключение `verified`; недопустимый переход статуса отклоняется; `verification_filename` и `paid_until` не меняются через форму.

### Implementation for User Story 2

- [X] T019 [P] [US2] Создать `app/Orchid/Layouts/Site/SiteFiltersLayout.php` и `app/Orchid/Filters/SiteCategoryFilter.php`: фильтр по тематике через `whereHas('categories')`; остальные фильтры — через `$allowedFilters` и `TD::filter()`.
- [X] T020 [P] [US2] Создать `app/Orchid/Layouts/Site/SiteEditLayout.php`: `title` (required, 3–255), `domain` (required, формат, уникальность среди `active`/`inactive`), `description` (required, 50–5000), `price` и `monthly_revenue` (required, numeric, `>= 0`, шаг 0.01), `has_trademark`, `categories` (required, array, min 1); владелец только для чтения.
- [X] T021 [P] [US2] Создать `app/Orchid/Layouts/Site/SiteStatusLayout.php` (текущий статус и выбор только из `allowedTransitions()`, для `sold` — конечный статус) и `app/Orchid/Layouts/Site/SiteVerificationLayout.php` (`verification_filename` только для чтения, `Switcher::make('site.verified')->sendTrueOrFalse()`, `paid_until` только для чтения).
- [X] T022 [P] [US2] Создать таблицы только для чтения `app/Orchid/Layouts/Site/SitePaymentsTable.php` и `app/Orchid/Layouts/Site/SiteConversationsTable.php` со ссылками на карточки платежа и диалога.
- [X] T023 [US2] Реализовать `app/Orchid/Screens/Site/SiteEditScreen.php`: `query()` с `owner`, `categories`, последними 10 платежами и диалогами; `save()` в транзакции (валидация, `fill`, синхронизация тематик, `markVerified()` по `site[verified]`); `changeStatus()` с перехватом `DomainException`; проверка уникальности нормализованного домена; право `platform.marketplace.sites`.
- [X] T024 [US2] Добавить маршрут `platform.marketplace.sites.edit` (`marketplace/sites/{site}/edit`) в `routes/platform.php` с breadcrumbs (зависит от T023).
- [X] T025 [US2] Дополнить `app/Orchid/Screens/Site/SiteListScreen.php`: подключить `SiteFiltersLayout`, колоночные фильтры `TD::FILTER_TEXT`, `TD::FILTER_SELECT` (опции `ListingStatus` и `0/1` для подтверждения), `TD::FILTER_NUMBER_RANGE`, сортировку и ссылку на карточку объявления (зависит от T019 и T024).
- [X] T026 [US2] Запустить `php artisan test --compact tests/Feature/Orchid/SiteManagementTest.php` и устранить расхождения.

**Checkpoint**: US2 работает поверх списка из US1; служебные поля и правила статусов защищены.

---

## Phase 5: User Story 3 - Управление пользователями и доступами (Priority: P2)

**Goal**: Администратор видит связанные данные биржи в карточке пользователя; удаление пользователей недоступно.

**Independent Test**: Открыть пользователя с объявлениями, диалогами и платежами, увидеть три блока; убедиться, что действия удаления нет и POST на удаление возвращает `404`.

### Tests for User Story 3

- [X] T027 [P] [US3] Написать `tests/Feature/Orchid/UserMarketplaceTest.php`: карточка показывает последние 10 объявлений, диалогов (как покупатель и продавец) и платежей; список показывает счётчики; действие удаления отсутствует в списке и карточке; POST на `method=remove` для списка и карточки отклоняется (`405` от fallback-маршрута Orchid).

### Implementation for User Story 3

- [X] T028 [P] [US3] Создать таблицы только для чтения `app/Orchid/Layouts/User/UserSitesTable.php`, `app/Orchid/Layouts/User/UserConversationsTable.php`, `app/Orchid/Layouts/User/UserPaymentsTable.php` со ссылками на карточки.
- [X] T029 [US3] Дополнить `app/Orchid/Screens/User/UserListScreen.php`: `withCount(['sites','subscriptionPayments'])`, счётчик диалогов и колонки связанных сущностей.
- [X] T030 [US3] Дополнить `app/Orchid/Screens/User/UserEditScreen.php`: в `query()` загрузить `sites`, `conversations` (покупатель или продавец) и `payments` по 10 последних; добавить три блока с таблицами T028; сохранить существующие блоки профиля, пароля, ролей и прав.
- [X] T031 [US3] Убрать действие удаления: удалить кнопку и метод `remove()` из `app/Orchid/Screens/User/UserEditScreen.php`, метод `remove()` из `app/Orchid/Screens/User/UserListScreen.php`, пункт «Delete» из `app/Orchid/Layouts/User/UserListLayout.php` (FR-028).
- [X] T032 [US3] Запустить `php artisan test --compact tests/Feature/Orchid/UserMarketplaceTest.php` и устранить расхождения.

**Checkpoint**: US3 не зависит от US2/US4/US5/US6; использует существующие экраны пользователей.

---

## Phase 6: User Story 4 - Справочник тематик (Priority: P2)

**Goal**: Администратор создаёт, переименовывает и удаляет тематики; используемую тематику удалить нельзя.

**Independent Test**: Создать тематику, переименовать, проверить стабильность публичного идентификатора, попытаться удалить используемую и неиспользуемую тематики.

### Tests for User Story 4

- [X] T033 [P] [US4] Написать `tests/Feature/Orchid/CategoryManagementTest.php`: создание, переименование без изменения `slug`, дубликат названия отклоняется, удаление используемой тематики отклоняется, удаление неиспользуемой тематики проходит, публичный идентификатор не принимается формой.

### Implementation for User Story 4

- [X] T034 [P] [US4] Создать `app/Orchid/Layouts/Category/CategoryEditLayout.php`: `name` (required, string, max 255, unique с игнорированием текущей записи); `slug` только для чтения.
- [X] T035 [US4] Реализовать `app/Orchid/Screens/Category/CategoryEditScreen.php`: `query(?Category $category)` для создания и редактирования, `save()` с валидацией, `remove()` с проверкой `sites()->exists()` и сообщением об ошибке; право `platform.marketplace.categories`.
- [X] T036 [US4] Добавить маршруты `platform.marketplace.categories.create` (`marketplace/categories/create`) и `platform.marketplace.categories.edit` (`marketplace/categories/{category}/edit`) в `routes/platform.php` (зависит от T035).
- [X] T037 [US4] Дополнить `app/Orchid/Screens/Category/CategoryListScreen.php`: кнопка «Создать», действия «Изменить» и «Удалить» с подтверждением, метод `remove()` с проверкой `sites()->exists()`; удаление через `method=remove`.
- [X] T038 [US4] Запустить `php artisan test --compact tests/Feature/Orchid/CategoryManagementTest.php` и устранить расхождения.

**Checkpoint**: US4 работает поверх списка тематик из US1 и не зависит от US2.

---

## Phase 7: User Story 5 - Просмотр переписки (Priority: P2)

**Goal**: Администратор фильтрует диалоги и сообщения, просматривает историю; создание, редактирование и удаление недоступны.

**Independent Test**: Открыть диалог, увидеть историю; применить фильтры к диалогам и сообщениям; POST на `save`/`remove` отклоняется (`405` от fallback-маршрута Orchid).

### Tests for User Story 5

- [X] T039 [P] [US5] Написать `tests/Feature/Orchid/ConversationReadOnlyTest.php`: список с объявлением, участниками и числом сообщений; фильтры по объявлению и участнику; карточка показывает историю в хронологическом порядке; POST на `save` и `remove` отклоняется (`405` от fallback-маршрута Orchid).
- [X] T040 [P] [US5] Написать `tests/Feature/Orchid/MessageReadOnlyTest.php`: фильтры по диалогу, отправителю и прочтению; карточка показывает полный текст, отправителя и `read_at`; POST на `save` и `remove` отклоняется (`405` от fallback-маршрута Orchid).

### Implementation for User Story 5

- [X] T041 [P] [US5] Создать `app/Orchid/Layouts/Conversation/ConversationFiltersLayout.php`, `app/Orchid/Filters/ConversationSiteFilter.php` и `app/Orchid/Filters/ConversationParticipantFilter.php`: поиск по домену/названию объявления и по имени/email покупателя или продавца.
- [X] T042 [P] [US5] Создать `app/Orchid/Layouts/Message/MessageFiltersLayout.php`, `app/Orchid/Filters/MessageConversationFilter.php`, `app/Orchid/Filters/MessageSenderFilter.php` и `app/Orchid/Filters/MessageReadFilter.php`: фильтры по диалогу/домену, отправителю и `read`/`unread`.
- [X] T043 [P] [US5] Создать `app/Orchid/Layouts/Conversation/ConversationMessagesTable.php`: отправитель, текст, создано, прочитано; сортировка по возрастанию `created_at`.
- [X] T044 [P] [US5] Создать `app/Orchid/Layouts/Message/MessageShowLayout.php`: диалог, объявление, отправитель, полный текст, создано, `read_at` — все поля только для чтения.
- [X] T045 [US5] Реализовать `app/Orchid/Screens/Conversation/ConversationShowScreen.php`: `query()` с объявлением, участниками и сообщениями; только чтение; право `platform.marketplace.conversations`.
- [X] T046 [US5] Реализовать `app/Orchid/Screens/Message/MessageShowScreen.php`: `query()` с диалогом, объявлением и отправителем; только чтение; право `platform.marketplace.messages`.
- [X] T047 [US5] Добавить маршруты `platform.marketplace.conversations.show` (`marketplace/conversations/{conversation}`) и `platform.marketplace.messages.show` (`marketplace/messages/{message}`) в `routes/platform.php` (зависит от T045, T046).
- [X] T048 [US5] Дополнить `app/Orchid/Screens/Conversation/ConversationListScreen.php`: подключить `ConversationFiltersLayout`, колонки участников и ссылку на карточку.
- [X] T049 [US5] Дополнить `app/Orchid/Screens/Message/MessageListScreen.php`: подключить `MessageFiltersLayout`, колонки диалога/отправителя/прочтения и ссылку на карточку.
- [X] T050 [US5] Запустить `php artisan test --compact tests/Feature/Orchid/ConversationReadOnlyTest.php tests/Feature/Orchid/MessageReadOnlyTest.php` и устранить расхождения.

**Checkpoint**: US5 работает поверх списков из US1; read-only гарантия проверена HTTP-тестами.

---

## Phase 8: User Story 6 - Просмотр платежей за подписку (Priority: P3)

**Goal**: Администратор фильтрует платежи и просматривает карточку; создание и изменение статуса недоступны.

**Independent Test**: Открыть список, применить фильтры по статусу, объявлению, плательщику и дате, открыть карточку, убедиться в отсутствии действий и `404` на POST.

### Tests for User Story 6

- [X] T051 [P] [US6] Написать `tests/Feature/Orchid/SubscriptionPaymentReadOnlyTest.php`: список с объявлением, плательщиком, суммой, валютой, периодом, статусом и временем оплаты; фильтры по статусу, объявлению, плательщику и диапазону `paid_at`; карточка показывает `site.paid_until`; POST на `save`/`remove` отклоняется (`405` от fallback-маршрута Orchid).

### Implementation for User Story 6

- [X] T052 [P] [US6] Создать `app/Orchid/Layouts/SubscriptionPayment/PaymentFiltersLayout.php`, `app/Orchid/Filters/PaymentSiteFilter.php` и `app/Orchid/Filters/PaymentPayerFilter.php`: поиск по домену/названию объявления и по имени/email плательщика; статус (`PaymentStatus`) и дата — через `$allowedFilters` и `TD::filter()`.
- [X] T053 [P] [US6] Создать `app/Orchid/Layouts/SubscriptionPayment/SubscriptionPaymentShowLayout.php`: объявление, плательщик, сумма, валюта, период, статус, `paid_at`, `external_id`, текущая дата «оплачено до» — только для чтения.
- [X] T054 [US6] Реализовать `app/Orchid/Screens/SubscriptionPayment/SubscriptionPaymentShowScreen.php`: `query()` с `site` и `payer`; только чтение; право `platform.marketplace.payments`.
- [X] T055 [US6] Добавить маршрут `platform.marketplace.payments.show` (`marketplace/payments/{subscriptionPayment}`) в `routes/platform.php` (зависит от T054).
- [X] T056 [US6] Дополнить `app/Orchid/Screens/SubscriptionPayment/SubscriptionPaymentListScreen.php`: подключить `PaymentFiltersLayout`, фильтры по статусу и `paid_at`, ссылку на карточку.
- [X] T057 [US6] Запустить `php artisan test --compact tests/Feature/Orchid/SubscriptionPaymentReadOnlyTest.php` и устранить расхождения.

**Checkpoint**: US6 работает поверх списка платежей из US1; изменения платежей недоступны.

---

## Phase 9: Polish & Cross-Cutting Concerns

**Purpose**: Общая проверка, форматирование и производительность.

- [X] T058 [P] Запустить полный набор `php artisan test --compact` и убедиться, что все тесты проходят.
- [X] T059 [P] Запустить `vendor/bin/pint --dirty --format agent` и исправить форматирование PHP-файлов.
- [X] T060 [P] Проверить `php artisan route:list --path=marketplace`: 11 маршрутов, корректные имена, права и breadcrumbs.
- [X] T061 [P] Проверить отсутствие N+1 в списках: `with()`/`withCount()` для `owner`, `categories`, `site`, `buyer`, `seller`, `payer`, `sender`; при необходимости исправить запросы.
- [X] T062 Выполнить ручную проверку по `specs/002-orchid-admin-models/quickstart.md`, включая права существующего администратора и read-only-разделы.
- [X] T063 Сверить реализацию со `spec.md` и `contracts/admin-routes.md`; обновить документы при выявленных расхождениях.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: без зависимостей.
- **Foundational (Phase 2)**: после Setup; блокирует все истории.
- **US1 (Phase 3)**: после Foundational; создаёт списки и маршруты, на которые опираются остальные истории.
- **US2–US6 (Phases 4–8)**: после US1; могут выполняться параллельно между собой.
- **Polish (Phase 9)**: после завершения нужных историй.

### User Story Dependencies

- **US1 (P1)**: независима после Foundational; MVP.
- **US2 (P1)**: зависит от US1 (список объявлений и маршрут списка).
- **US3 (P2)**: зависит только от Foundational; пункт меню появляется в US1.
- **US4 (P2)**: зависит от US1 (список тематик).
- **US5 (P2)**: зависит от US1 (списки диалогов и сообщений).
- **US6 (P3)**: зависит от US1 (список платежей).

### Within Each User Story

- Тесты пишутся и падают до реализации.
- Layouts и фильтры — до экранов.
- Экраны — до маршрутов.
- Маршруты — до прогона тестов истории.

### Parallel Opportunities

- T001–T006 — параллельно.
- T008–T009 — параллельно.
- T010–T014 — параллельно.
- T019–T022 — параллельно.
- T028 — параллельно с T027.
- T034 — параллельно с T033.
- T039–T044 — параллельно.
- T052–T053 — параллельно.
- T058–T061 — параллельно.

---

## Parallel Example: User Story 1

```bash
# Тесты
Task: "Написать tests/Feature/Orchid/AdminMenuTest.php"
Task: "Написать tests/Feature/Orchid/AccessControlTest.php"

# Списки по сущностям
Task: "Реализовать SiteListLayout + SiteListScreen"
Task: "Реализовать CategoryListLayout + CategoryListScreen"
Task: "Реализовать ConversationListLayout + ConversationListScreen"
Task: "Реализовать MessageListLayout + MessageListScreen"
Task: "Реализовать SubscriptionPaymentListLayout + SubscriptionPaymentListScreen"
```

## Parallel Example: User Story 5

```bash
Task: "Написать ConversationReadOnlyTest.php"
Task: "Написать MessageReadOnlyTest.php"
Task: "Создать ConversationFiltersLayout и фильтры"
Task: "Создать MessageFiltersLayout и фильтры"
Task: "Создать ConversationMessagesTable"
Task: "Создать MessageShowLayout"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Phase 1: Setup.
2. Phase 2: Foundational.
3. Phase 3: US1 — меню и списки всех шести сущностей.
4. **STOP and VALIDATE**: `php artisan test --compact tests/Feature/Orchid/AdminMenuTest.php tests/Feature/Orchid/AccessControlTest.php`.
5. Демонстрация: все модели видны и открываются из меню.

### Incremental Delivery

1. US1 → меню и списки (MVP).
2. US2 → управление объявлениями и подтверждение владения.
3. US3 → связанные данные в карточке пользователя.
4. US4 → CRUD тематик.
5. US5 → просмотр переписки.
6. US6 → просмотр платежей.
7. Polish → полный прогон, Pint, quickstart.

### Parallel Team Strategy

1. Все вместе: Setup + Foundational + US1.
2. После US1:
   - Разработчик A: US2 (объявления).
   - Разработчик B: US3 (пользователи) + US4 (тематики).
   - Разработчик C: US5 (переписка) + US6 (платежи).
3. Polish после слияния.

---

## Notes

- [P] — разные файлы, нет зависимостей.
- [US1]–[US6] — трассировка к `spec.md`.
- Тесты включены по требованию проекта; каждый тест должен падать до реализации.
- Не добавлять удаление объявлений, диалогов, сообщений и платежей.
- Не менять `verification_filename`, `paid_until`, `sold_at` и `user_id` через формы.
- Коммит после каждой задачи или логической группы.
