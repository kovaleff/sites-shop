---
description: "Task list for the marketplace data model feature"
---

# Tasks: Модели данных биржи по продаже сайтов

**Input**: Design documents from `/specs/001-marketplace-data-model/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `quickstart.md`

**Tests**: Включены, потому что `plan.md` → Testing Strategy определяет 9 PHPUnit-классов, а `spec.md` содержит проверяемые acceptance scenarios. Тесты пишутся первыми и должны падать по ожидаемой причине (нет класса/таблицы), затем реализуется код.

**Organization**: Задачи сгруппированы по пользовательским историям из `spec.md`. Каждая история — самостоятельный проверяемый инкремент. Общие сущности размещены в самой ранней истории, которой они нужны.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (другой файл, нет зависимости от незавершённой задачи)
- **[Story]**: `[US1]`…`[US5]` — только для фаз пользовательских историй
- В описании всегда указан путь к файлу

## Path Conventions

Единое Laravel-приложение: `app/`, `database/`, `tests/` в корне репозитория.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Подтвердить базовое окружение и подготовить каталоги.

- [x] T001 Проверить baseline: `phpunit.xml` использует SQLite `:memory:`, команда `php artisan test --compact` зелёная до начала работ; зафиксировать версии PHP 8.3 / Laravel 13 / PHPUnit 12 из `php artisan about`.
- [x] T002 Создать каталоги `app/Enums/` и `tests/Feature/Models/`, чтобы `php artisan make:enum` генерировал классы в `App\Enums`, а тесты моделей имели собственный каталог.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Общие enum, от которых зависят истории US2, US3 и US5.

**⚠️ CRITICAL**: US2/US3/US5 не начинаются, пока T003–T004 не готовы.

- [x] T003 Создать `app/Enums/ListingStatus.php` — string-backed enum: `Active = 'active'`, `Inactive = 'inactive'`, `Withdrawn = 'withdrawn'`, `Sold = 'sold'`; методы `label()` (русские подписи), `allowedTransitions()` (FR-008: Active↔Inactive, Active/Inactive↔Withdrawn, Active/Inactive/Withdrawn→Sold), `canTransitionTo(self $target): bool`, `isTerminal(): bool` (только Sold), `values(): array`.
- [x] T004 [P] Создать `app/Enums/PaymentStatus.php` — string-backed enum: `Pending = 'pending'`, `Paid = 'paid'`, `Failed = 'failed'`, `Cancelled = 'cancelled'`; методы `label()`, `isSuccessful()` (только Paid), `isFinal()` (Paid/Failed/Cancelled), `values(): array`.

**Checkpoint**: Enum готовы; истории US2/US3/US5 могут использовать статусы.

---

## Phase 3: User Story 1 - Регистрация и вход через VK ID или Yandex ID (Priority: P1) 🎯 MVP

**Goal**: Схема и модель пользователя поддерживают вход через VK ID/Yandex ID без пароля, необязательный email, аватар и время последнего входа по каждому провайдеру.

**Independent Test**: Создать пользователя только с `vk_id` (email/password = null), повторно найти его по `vk_id`, создать отдельный аккаунт с `ya_id`, убедиться, что два пользователя с `email = null` сосуществуют и даты входа кастуются в datetime.

### Tests for User Story 1

- [x] T005 [P] [US1] Написать `tests/Feature/Models/UserSocialIdentityTest.php` (`RefreshDatabase`): аккаунт создаётся с `vk_id`, `email = null`, `password = null`; поиск по `vk_id` возвращает тот же аккаунт; аккаунт с `ya_id` не смешивается с VK-аккаунтом; два пользователя с `email = null` допустимы (unique не блокирует NULL); `vk_last_login_at` и `ya_last_login_at` — экземпляры `Carbon`; `avatar` сохраняется. Тест должен упасть до T006–T008.

### Implementation for User Story 1

- [x] T006 [US1] Создать миграцию `database/migrations/*_add_social_identity_fields_to_users_table.php`: `email` varchar(255) → nullable (unique сохранить), `password` varchar(255) → nullable, добавить `avatar` varchar(2048) nullable, `vk_id` bigint unsigned nullable unique, `ya_id` bigint unsigned nullable unique, `vk_last_login_at` timestamp nullable, `ya_last_login_at` timestamp nullable. В `down()` вернуть `email`/`password` в not null и удалить новые столбцы.
- [x] T007 [US1] Обновить `app/Models/User.php`: добавить casts `vk_last_login_at`/`ya_last_login_at` → datetime; добавить связи `sites(): HasMany`, `conversationsAsBuyer(): HasMany`, `conversationsAsSeller(): HasMany`, `messages(): HasMany`, `subscriptionPayments(): HasMany` (классы `Site`, `Conversation`, `Message`, `SubscriptionPayment` появятся в следующих историях — это нормально, методы декларативны). `#[Fillable(['name', 'email', 'password'])]` и `#[Hidden]` не расширять соцполями.
- [x] T008 [P] [US1] Дополнить `database/factories/UserFactory.php` состояниями `vk()` и `yandex()`: `email = null`, `password = null`, уникальный `vk_id`/`ya_id` (`fake()->unique()`), `avatar`, соответствующее `*_last_login_at = now()`; базовое состояние и `unverified()` не менять.

**Checkpoint**: US1 проверяется отдельно через `php artisan test --compact tests/Feature/Models/UserSocialIdentityTest.php`.

---

## Phase 4: User Story 2 - Продавец публикует сайт на продажу (Priority: P1)

**Goal**: Справочник тематик, схема объявлений, нормализация домена, уникальность среди активных/неактивных, допустимые переходы статусов, подтверждение владения сайтом и фабрики/сидер.

**Independent Test**: Создать объявление с двумя тематиками и владельцем; проверить нормализацию домена (`Example.com`, `www.example.com`, `example.com/` — один домен), блокировку дубликата среди `active`/`inactive`, освобождение домена после `withdrawn`/`sold`, переходы статусов, автогенерацию неизменяемого имени файла проверки с флагом `verified` и идемпотентный сидер из 31 тематики.

### Tests for User Story 2

- [x] T009 [P] [US2] Написать `tests/Feature/Models/CategorySeederTest.php`: после `$this->seed(CategorySeeder::class)` ровно 31 запись; все `name` и `slug` уникальны; каждый `slug === Str::slug($name)`; «Медицина и здоровье» отсутствует, «Здоровье и медицина» присутствует; повторный запуск сидера не создаёт дубликатов. Тест должен упасть до T016/T020.
- [x] T010 [P] [US2] Написать `tests/Feature/Models/SiteDomainUniquenessTest.php`: варианты `Example.com`, `www.example.com`, `example.com/` дают одинаковый `domain`; дубликат среди `active`/`inactive` вызывает исключение уникальности; после перевода в `withdrawn` или `sold` домен можно использовать в новом объявлении; несколько скрытых объявлений с одним доменом допустимы. Тест должен упасть до T014/T017/T019.
- [x] T011 [P] [US2] Написать `tests/Feature/Models/SiteStatusTest.php`: разрешены `Active↔Inactive`, `Active/Inactive↔Withdrawn`, `Active/Inactive/Withdrawn→Sold`; `Sold` конечен; повторный переход в текущий статус — no-op; при `Sold` выставляется `sold_at`; недопустимый переход бросает `DomainException`. Тест должен упасть до T017.
- [x] T012 [P] [US2] Написать `tests/Feature/Models/SiteVerificationTest.php`: при создании объявления `verification_filename` непустой, соответствует `/^[a-z0-9]{32}\.txt$/` и уникален у двух объявлений; повторное сохранение (изменение `title`) не меняет имя файла; `verified` по умолчанию `false`; массовое заполнение `verified`/`verification_filename` через `create()`/`fill()` не меняет значения; `markVerified()` выставляет `true`, сохраняет и идемпотентен, `markVerified(false)` снимает флаг; `verified` не влияет на `publiclyVisible()`/`isPubliclyVisible()`. Тест должен упасть до T014/T017/T019.

### Implementation for User Story 2

- [x] T013 [US2] Создать миграцию `database/migrations/*_create_categories_table.php`: `id`, `name` varchar(255) not null unique, `slug` varchar(255) not null unique, `timestamps`.
- [x] T014 [US2] Создать миграцию `database/migrations/*_create_sites_table.php`: `id`; `user_id` foreignId → `users.id` с `restrictOnDelete()`; `title` varchar(255) not null; `domain` varchar(253) not null; `status` varchar(16) not null default `active` (объявить **до** generated-столбца); `public_domain` varchar(253) nullable `storedAs("case when status in ('active', 'inactive') then domain else null end")`; `description` text not null; `price` decimal(12,2) default 0; `monthly_revenue` decimal(12,2) default 0; `has_trademark` boolean default false; `verification_filename` varchar(64) not null unique; `verified` boolean default false; `paid_until` date nullable; `sold_at` timestamp nullable; `timestamps`. Индексы: unique `public_domain`, unique `verification_filename`, `(status, paid_until)`, `price`, `monthly_revenue`, `created_at`.
- [x] T015 [US2] Создать миграцию `database/migrations/*_create_category_site_table.php`: `category_id` foreignId → `categories.id` restrict, `site_id` foreignId → `sites.id` cascade, составной primary key `(category_id, site_id)`, индекс по `site_id`.
- [x] T016 [P] [US2] Создать `app/Models/Category.php`: `#[Fillable(['name'])]`, `#[RouteKey('slug')]`; в `booted()` при `creating` генерировать `slug` через `Str::slug($name)`, если он не задан; связь `sites(): BelongsToMany`.
- [x] T017 [US2] Создать `app/Models/Site.php`: `#[Fillable(['title', 'domain', 'description', 'price', 'monthly_revenue', 'has_trademark'])]` (без `verified` и `verification_filename`); `$attributes` по умолчанию (`status` = `active`, `has_trademark` = false, `price` = 0, `monthly_revenue` = 0, `verified` = false); casts `status` → `ListingStatus`, `price`/`monthly_revenue` → `decimal:2`, `has_trademark`/`verified` → boolean, `paid_until` → date, `sold_at` → datetime; в `booted()` при `creating` генерировать `verification_filename = Str::lower(Str::random(32)).'.txt'`, если поле не задано (при `updating` не перезаписывать); мутатор `domain` (нижний регистр, trim, без `www.`, без завершающего `/`); связи `owner(): BelongsTo`, `categories(): BelongsToMany`, `conversations(): HasMany`, `subscriptionPayments(): HasMany`; константа `public const CATALOG_PER_PAGE = 25;`; методы `isOwnedBy(User $user): bool`, `acceptsPayments(): bool` (только Active/Inactive), `changeStatus(ListingStatus $status): void` (no-op для текущего, проверка `canTransitionTo`, `sold_at = now()` при Sold, иначе `DomainException`), `markVerified(bool $verified = true): void` (сохраняет флаг; идемпотентен).
- [x] T018 [P] [US2] Создать `database/factories/CategoryFactory.php`: уникальное `name` и `slug` (slug через `Str::slug()` или модель), без побочных эффектов.
- [x] T019 [P] [US2] Создать `database/factories/SiteFactory.php`: владелец `User::factory()`, уникальный домен, заголовок, описание не короче 50 символов, цена/выручка, `has_trademark`, статус `active`, `paid_until = null`; `verification_filename` не задавать — генерируется моделью. Состояния: `inactive()`, `withdrawn()`, `sold()` (с `sold_at`), `paid()` (`paid_until` = сегодня + 1 месяц), `expired()` (`paid_until` = вчера), `unpaid()` (`paid_until = null`), `withTrademark()`, `verified()` (через `markVerified()` или `afterCreating`).
- [x] T020 [US2] Создать `database/seeders/CategorySeeder.php` с 31 названием из `data-model.md` через `Category::query()->firstOrCreate(['name' => $name])`; вызвать `CategorySeeder` из `database/seeders/DatabaseSeeder.php`, сохранив демо-пользователя.

**Checkpoint**: US2 проверяется отдельно четырьмя тестами; объявления и тематики создаются, домен нормализуется, статусы переходят по FR-008, имя файла проверки генерируется и не меняется, `verified` управляется администратором.

---

## Phase 5: User Story 3 - Посетитель просматривает и фильтрует каталог (Priority: P1)

**Goal**: Публичная видимость по статусу и подписке, фильтры каталога, сортировка и размер страницы.

**Independent Test**: Подготовить объявления с разными статусами и датами `paid_until`, проверить truth table видимости; отфильтровать по тематикам (OR), диапазонам цены/выручки и части домена, скомбинировать фильтры, проверить порядок «новые сначала» и константу 25.

### Tests for User Story 3

- [x] T021 [P] [US3] Написать `tests/Feature/Models/SiteVisibilityTest.php`: видны только `Active` с `paid_until >= today`; `Active` с `paid_until = null` или прошлой датой скрыт; `Inactive`/`Withdrawn`/`Sold` скрыты при любой дате; граница «сегодня» включена; `ownedBy()` возвращает все объявления владельца независимо от видимости. Тест должен упасть до T023.
- [x] T022 [P] [US3] Написать `tests/Feature/Models/SiteFilterTest.php`: `ofCategories([...])` — совпадение хотя бы по одной; `priceBetween()` и `revenueBetween()` — включительные границы и `null`; `domainContains()` — частичное совпадение без учёта регистра; комбинация фильтров через AND; `newestFirst()` — от новых к старым; `Site::CATALOG_PER_PAGE === 25`; `paginate(25)` отдаёт не более 25. Тест должен упасть до T023.

### Implementation for User Story 3

- [x] T023 [US3] Добавить в `app/Models/Site.php` scopes через атрибут `#[Scope]` на `protected`-методах: `publiclyVisible()` (`status = Active`, `paid_until` not null, `paid_until >= today()->toDateString()`), `ownedBy(User $user)`, `ofCategories(array $categoryIds)` (пустой массив — без ограничения), `priceBetween(?float $min, ?float $max)`, `revenueBetween(?float $min, ?float $max)`, `domainContains(?string $term)` (нижний регистр + `LIKE %term%`), `newestFirst()` (`latest('created_at')`, затем `latest('id')`); добавить `isPubliclyVisible(): bool` с той же семантикой.

**Checkpoint**: US1–US3 (все P1) дают публичный каталог поверх готовой схемы.

---

## Phase 6: User Story 4 - Переписка покупателя с продавцом (Priority: P2)

**Goal**: Диалог на пару «объявление + покупатель», сообщения, доступ участников, read-only при непубличном объявлении, непрочитанные и время прочтения.

**Independent Test**: Покупатель начинает диалог по публичному объявлению, продавец отвечает, повторное сообщение покупателя попадает в тот же диалог; посторонний не участник; при скрытии объявления новые сообщения отклоняются, история читается; непрочитанные считаются и сбрасываются.

### Tests for User Story 4

- [x] T024 [P] [US4] Написать `tests/Feature/Models/ConversationTest.php`: `Conversation::startFor()` создаёт один диалог на пару и ставит `seller_id = site.user_id`; повторный вызов возвращает тот же диалог; владелец объявления получает `DomainException`; непубличное объявление (скрытое/просроченное/проданное) отклоняет создание; `isParticipant()` истинно только для buyer/seller; `postMessage()` сохраняет сообщение, обновляет `last_message_at`, покупатель и продавец могут отвечать; посторонний получает `DomainException`; при непубличном объявлении `isReadOnly()` = true и отправка отклоняется; `unreadCountFor()` не считает собственные сообщения; `markReadFor()` проставляет `read_at` только собеседнику; `latestMessage()` возвращает последнее сообщение.
- [x] T025 [US4] Создать миграцию `database/migrations/*_create_conversations_table.php`: `id`; `site_id` foreignId → `sites.id` cascade; `buyer_id` foreignId → `users.id` cascade; `seller_id` foreignId → `users.id` cascade; `last_message_at` timestamp nullable; `timestamps`; unique `(site_id, buyer_id)`; индексы `(buyer_id, last_message_at)` и `(seller_id, last_message_at)`.
- [x] T026 [US4] Создать миграцию `database/migrations/*_create_messages_table.php`: `id`; `conversation_id` foreignId → `conversations.id` cascade; `sender_id` foreignId → `users.id` cascade; `body` text not null; `read_at` timestamp nullable; `timestamps`; индексы `(conversation_id, created_at)` и `(conversation_id, read_at)`.
- [x] T027 [P] [US4] Создать `app/Models/Conversation.php`: `#[Fillable(['site_id', 'buyer_id', 'seller_id'])]`; cast `last_message_at` → datetime; связи `site()`, `buyer()`, `seller()`, `messages()`, `latestMessage()` (`latestOfMany()`); `startFor(Site $site, User $buyer): self` (владелец → `DomainException`, непубличное объявление → `DomainException`, `firstOrCreate` по `site_id`+`buyer_id` с `seller_id = site.user_id`); `isParticipant(User $user): bool`; `isReadOnly(): bool` (`! $this->site->isPubliclyVisible()`); `canBeRepliedToBy(User $user): bool`; `postMessage(User $sender, string $body): Message` (проверка `canBeRepliedToBy`, транзакция, `messages()->forceCreate([...])`); `unreadCountFor(User $user): int`; `markReadFor(User $user): int`.
- [x] T028 [P] [US4] Создать `app/Models/Message.php`: `#[Fillable(['body'])]`; cast `read_at` → datetime; связи `conversation(): BelongsTo`, `sender(): BelongsTo`; в `booted()` событие `created` обновляет `last_message_at` у диалога (query-builder update, без mass assignment); методы `isSentBy(User $user): bool`, `isRead(): bool`, `markAsRead(): void` (идемпотентно).
- [x] T029 [P] [US4] Создать `database/factories/ConversationFactory.php`: `site_id`/`buyer_id`/`seller_id`; гарантировать, что `seller_id` совпадает с владельцем сайта (при переопределении — использовать переданные значения). Состояние `forSite(Site $site, User $buyer)` при необходимости.
- [x] T030 [P] [US4] Создать `database/factories/MessageFactory.php`: `conversation_id` → `Conversation::factory()`, `sender_id` — участник диалога (по умолчанию buyer), `body`, `read_at = null`; состояние `read()`.

**Checkpoint**: US4 работает независимо: диалоги, ответы, доступ и read-only проверены тестом.

---

## Phase 7: User Story 5 - Продавец оплачивает подписку объявления (Priority: P2)

**Goal**: Платёж привязан к объявлению и плательщику; успешный платёж продлевает только своё объявление, неуспешный не меняет дату; оплата снятых/проданных отклоняется.

**Independent Test**: Записать успешный платёж за объявление с истёкшей подпиской — дата продлевается от сегодня; за объявление с будущей датой — от более поздней даты; дата второго объявления не меняется; повторный `markAsPaid()` не продлевает дважды; `Failed`/`Cancelled` не меняют дату; `Withdrawn`/`Sold` отклоняются; некорректные `amount`/`period_months` отклоняются.

### Tests for User Story 5

- [x] T031 [P] [US5] Написать `tests/Feature/Models/SiteSubscriptionTest.php`: успешный платёж продлевает только `site_id` платежа; при `paid_until = null` или в прошлом база — сегодня; при будущей дате база — `paid_until`; `markAsPaid()` идемпотентен (повторный вызов не добавляет месяцы); `markAsFailed()`/`markAsCancelled()` не меняют `paid_until`; оплата `Withdrawn`/`Sold` бросает `DomainException`; `amount <= 0` и `period_months < 1` бросают `DomainException`; связи `site()` и `payer()` корректны. Тест должен упасть до T032–T035.

### Implementation for User Story 5

- [x] T032 [US5] Создать миграцию `database/migrations/*_create_subscription_payments_table.php`: `id`; `site_id` foreignId → `sites.id` cascade; `user_id` foreignId → `users.id` cascade; `amount` decimal(10,2) not null; `currency` char(3) default `RUB`; `period_months` smallint unsigned not null; `status` varchar(16) default `pending`; `paid_at` timestamp nullable; `external_id` varchar(255) nullable unique; `timestamps`; индексы `(site_id, created_at)` и `(user_id, created_at)`.
- [x] T033 [P] [US5] Создать `app/Models/SubscriptionPayment.php`: `$attributes` по умолчанию (`status` = `pending`, `currency` = `RUB`); casts `status` → `PaymentStatus`, `amount` → `decimal:2`, `paid_at` → datetime; связи `site(): BelongsTo`, `payer(): BelongsTo` (FK `user_id`); `markAsPaid(): void` (транзакция, `lockForUpdate` платежа и объявления, no-op для уже оплаченного, `DomainException` для `Failed`/`Cancelled`, проверка `acceptsPayments()`, `amount > 0`, `period_months >= 1`, затем `Site::extendSubscription()`, `status = Paid`, `paid_at = now()`); `markAsFailed(): void` и `markAsCancelled(): void` только из `Pending`.
- [x] T034 [P] [US5] Создать `database/factories/SubscriptionPaymentFactory.php`: `site_id`/`user_id` (плательщик — владелец сайта), `amount`, `currency = 'RUB'`, `period_months = 1`, `status = pending`, `external_id` уникальный; состояния `paid()`, `failed()`, `cancelled()`.
- [x] T035 [US5] Добавить в `app/Models/Site.php` метод `extendSubscription(int $months): void`: `$months < 1` → `DomainException`; база — `max(today, paid_until)`; `paid_until = CarbonImmutable::parse($base)->addMonthsNoOverflow($months)`; сохранить модель.

**Checkpoint**: US5 проверяется отдельно; подписки объявлений независимы, история платежей сохраняется.

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Финальная проверка стиля, полного набора тестов и схемы на целевой СУБД.

- [x] T036 Запустить `vendor/bin/pint --dirty --format agent` для всех изменённых PHP-файлов в `app/`, `database/`, `tests/` и исправить замечания.
- [x] T037 Запустить `php artisan test --compact tests/Feature/Models` и добиться зелёного прогона всех 9 классов; при падении исправить код, а не ослаблять тесты.
- [x] T038 Проверить схему на MySQL: `php artisan migrate:fresh --seed` для миграций из `database/migrations/` (только локальная БД), затем `php artisan db:table sites` и `php artisan db:table subscription_payments`; убедиться в наличии generated-столбца `public_domain`, уникальных индексов `public_domain`/`verification_filename`, `sites_visibility_index`, `verified` default `false`, внешних ключей и 31 тематики.
- [x] T039 [P] Сверить `specs/001-marketplace-data-model/quickstart.md` с фактическими путями/командами; обновить только расхождения и прогнать перечисленные в нём команды.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: начинается сразу; T001 → T002.
- **Foundational (Phase 2)**: после T002; T003 и T004 можно параллельно.
- **US1 (Phase 3)**: после T002; не зависит от enum.
- **US2 (Phase 4)**: после T003 и существующей таблицы `users` (из стандартной миграции); может идти параллельно с US1.
- **US3 (Phase 5)**: после T014, T017, T019 (схема и модель `Site`).
- **US4 (Phase 6)**: после T014/T017 (сайты) и T007 (связи `User`); может идти параллельно с US3.
- **US5 (Phase 7)**: после T004, T014/T017/T019; может идти параллельно с US4.
- **Polish (Phase 8)**: после всех выбранных историй.

### User Story Dependencies

| История | Зависит от | Можно ли параллельно |
|---|---|---|
| US1 | Setup | Да, с US2 |
| US2 | T003, `users` | Да, с US1 |
| US3 | US2 | Да, с US4/US5 после US2 |
| US4 | US2, T007 | Да, с US3/US5 |
| US5 | T004, US2 | Да, с US3/US4 |

### Within Each User Story

- Тесты пишутся первыми и падают по ожидаемой причине.
- Миграции → модели → фабрики → сидер.
- Связи и методы модели — до тестов, которые их вызывают.
- После каждого PHP-изменения запускать `vendor/bin/pint --dirty --format agent`.

### Parallel Opportunities

- T003 и T004 — разные enum-файлы.
- T005, T008 — тест и фабрика US1 в разных файлах.
- T009–T012 — четыре независимых теста US2.
- T016, T018, T019 — модель `Category` и две фабрики.
- T021–T022 — два теста US3.
- T027–T030 — модели и фабрики US4 (после миграций T025–T026).
- T033–T034 — модель и фабрика US5.
- T039 — документация, не конфликтует с кодом.

---

## Parallel Example: User Story 2

```bash
# После T003 и T002 — четыре независимых теста:
Task: "Написать tests/Feature/Models/CategorySeederTest.php (T009)"
Task: "Написать tests/Feature/Models/SiteDomainUniquenessTest.php (T010)"
Task: "Написать tests/Feature/Models/SiteStatusTest.php (T011)"
Task: "Написать tests/Feature/Models/SiteVerificationTest.php (T012)"

# После T014/T017 — модель Category и фабрики:
Task: "Создать app/Models/Category.php (T016)"
Task: "Создать database/factories/CategoryFactory.php (T018)"
Task: "Создать database/factories/SiteFactory.php (T019)"
```

## Parallel Example: User Story 4

```bash
# После T025/T026 — модели и фабрики в разных файлах:
Task: "Создать app/Models/Conversation.php (T027)"
Task: "Создать app/Models/Message.php (T028)"
Task: "Создать database/factories/ConversationFactory.php (T029)"
Task: "Создать database/factories/MessageFactory.php (T030)"
```

---

## Implementation Strategy

### MVP First (P1 stories)

Строгий MVP по шаблону — US1, но для слоя данных он не даёт каталога. Практический MVP этой фичи — **US1 + US2 + US3** (все P1): пользователи, объявления и публичный каталог. US4 и US5 добавляются как P2-инкременты.

1. Phase 1 → Phase 2.
2. US1: пользователи и соцполя.
3. US2: тематики, объявления, статусы, уникальность домена, проверка владения.
4. US3: видимость и фильтры.
5. **STOP and VALIDATE**: прогнать тесты US1–US3, проверить каталог на seed-данных.
6. US4 → US5 → Polish.

### Incremental Delivery

- После US1: аккаунты готовы, но каталога нет.
- После US2: объявления создаются и валидируются.
- После US3: публичный каталог работает (MVP).
- После US4: переписка.
- После US5: монетизация подписки.
- Каждый шаг не ломает предыдущие и проверяется своим тестовым классом.

### Parallel Team Strategy

1. Все вместе: Setup + Foundational.
2. Developer A — US1; Developer B — US2.
3. После US2: Developer A — US3; Developer B — US4; Developer C — US5.
4. Финал: T036–T039 вместе.

---

## Notes

- `[P]` — разные файлы и нет зависимости от незавершённой задачи.
- `[USx]` связывает задачу с историей из `spec.md`.
- Валидация формата домена, длин полей и «хотя бы одной тематики» (FR-017/FR-020/FR-025) в этой фазе не реализуется — она отложена до HTTP-слоя согласно `plan.md`.
- `verification_filename` генерируется только моделью; `verified` не входит в `#[Fillable]` и меняется через `Site::markVerified()`. Административный UI — вне фазы.
- Не создавать контроллеры, маршруты, политики, Form Requests и представления: это вне фазы.
- Тесты используют `RefreshDatabase` и SQLite in-memory; MySQL проверяется отдельно в T038.
- После каждого логического блока — прогон узкого теста и Pint.
