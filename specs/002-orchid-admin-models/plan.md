# Implementation Plan: Все модели в административной панели

**Branch**: `002-orchid-admin-models` | **Date**: 2026-10-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/002-orchid-admin-models/spec.md`

## Summary

Добавить в существующую панель Orchid 14.53 разделы для всех сущностей биржи: пользователей, тематик, объявлений, диалогов, сообщений и платежей за подписку. Каждый раздел получает пункт в меню «Биржа», собственное право доступа, список с фильтрами и карточку. Объявления и тематики редактируются с соблюдением бизнес-правил (переходы статусов, подтверждение владения, уникальность домена и тематики). Диалоги, сообщения и платежи доступны только для чтения. Пользователи используют существующие экраны Orchid, дополненные блоками связанных данных биржи.

## Technical Context

**Language/Version**: PHP 8.3.36

**Primary Dependencies**: Laravel Framework 13.39.0, orchid/platform 14.53.0, orchid/blade-icons, tabuna/breadcrumbs

**Storage**: MySQL 8.0.46 (рабочая среда), SQLite 3.37.2 (тесты); новых миграций нет

**Testing**: PHPUnit 12.5, feature-тесты через HTTP-маршруты Orchid

**Target Platform**: серверное веб-приложение, административная панель `/admin`

**Project Type**: монолит Laravel с серверным рендерингом

**Performance Goals**: открытие списка или карточки при 10 000 объявлений — менее 2 секунд; отсутствие N+1 при выводе списков

**Constraints**: доступ только для аутентифицированных пользователей с правом на раздел; диалоги, сообщения и платежи — только чтение; удаление пользователей и объявлений не выполняется; имя файла проверки и дата «оплачено до» не редактируются напрямую

**Scale/Scope**: 6 разделов меню, 10 экранов, 1 расширение существующего экрана пользователя, 5 новых прав доступа

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` содержит только незаполненный шаблон, поэтому формальные принципы и ворота не определены. Применяются правила проекта из `AGENTS.md`:

- следовать существующим соглашениям Orchid-экранов и Laravel;
- использовать именованные маршруты и существующие модели без изменения схемы данных;
- покрывать поведение PHPUnit-тестами и запускать Pint перед завершением;
- не менять зависимости и структуру каталогов без необходимости.

Нарушений нет. Раздел Complexity Tracking не заполняется.

**Post-Design Re-check**: после проектирования маршрутов, экранов и фильтров нарушения также отсутствуют. Новые зависимости не добавляются, схема данных не меняется, структура каталогов Orchid сохранена. Все guardrails опираются на существующие методы моделей.

## Project Structure

### Documentation (this feature)

```text
specs/002-orchid-admin-models/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   └── admin-routes.md  # Phase 1 output
├── checklists/
│   └── requirements.md
└── tasks.md             # Phase 2 output (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Orchid/
│   ├── Filters/
│   │   ├── SiteCategoryFilter.php
│   │   ├── ConversationSiteFilter.php
│   │   ├── ConversationParticipantFilter.php
│   │   ├── MessageConversationFilter.php
│   │   ├── MessageSenderFilter.php
│   │   ├── MessageReadFilter.php
│   │   ├── PaymentSiteFilter.php
│   │   └── PaymentPayerFilter.php
│   ├── Layouts/
│   │   ├── Site/
│   │   │   ├── SiteListLayout.php
│   │   │   ├── SiteFiltersLayout.php
│   │   │   ├── SiteEditLayout.php
│   │   │   ├── SiteStatusLayout.php
│   │   │   ├── SiteVerificationLayout.php
│   │   │   ├── SitePaymentsTable.php
│   │   │   └── SiteConversationsTable.php
│   │   ├── Category/
│   │   │   ├── CategoryListLayout.php
│   │   │   └── CategoryEditLayout.php
│   │   ├── Conversation/
│   │   │   ├── ConversationListLayout.php
│   │   │   ├── ConversationFiltersLayout.php
│   │   │   └── ConversationMessagesTable.php
│   │   ├── Message/
│   │   │   ├── MessageListLayout.php
│   │   │   ├── MessageFiltersLayout.php
│   │   │   └── MessageShowLayout.php
│   │   ├── SubscriptionPayment/
│   │   │   ├── SubscriptionPaymentListLayout.php
│   │   │   ├── PaymentFiltersLayout.php
│   │   │   └── SubscriptionPaymentShowLayout.php
│   │   └── User/
│   │       ├── UserSitesTable.php
│   │       ├── UserConversationsTable.php
│   │       └── UserPaymentsTable.php
│   ├── Screens/
│   │   ├── Site/
│   │   │   ├── SiteListScreen.php
│   │   │   └── SiteEditScreen.php
│   │   ├── Category/
│   │   │   ├── CategoryListScreen.php
│   │   │   └── CategoryEditScreen.php
│   │   ├── Conversation/
│   │   │   ├── ConversationListScreen.php
│   │   │   └── ConversationShowScreen.php
│   │   ├── Message/
│   │   │   ├── MessageListScreen.php
│   │   │   └── MessageShowScreen.php
│   │   └── SubscriptionPayment/
│   │       ├── SubscriptionPaymentListScreen.php
│   │       └── SubscriptionPaymentShowScreen.php
│   └── PlatformProvider.php          # menu + permissions
└── Models/                           # + trait Filterable и конфигурация фильтров

routes/platform.php                   # новые именованные маршруты

tests/Feature/Orchid/
├── AdminMenuTest.php
├── AccessControlTest.php
├── SiteManagementTest.php
├── CategoryManagementTest.php
├── ConversationReadOnlyTest.php
├── MessageReadOnlyTest.php
├── SubscriptionPaymentReadOnlyTest.php
└── UserMarketplaceTest.php
```

**Structure Decision**: используется существующая структура Orchid (`app/Orchid/Screens`, `Layouts`, `Filters`) с группировкой по сущностям, как у сгенерированных экранов `User` и `Role`. Новые базовые каталоги не создаются. Экраны следуют шаблону `Screen` + `Table`/`Rows`, фильтры — шаблону `Filter` + `Selection`.

## Implementation Phases

### Phase A — Подготовка и навигация

1. Подключить трейт `Orchid\Filters\Filterable` к `Site`, `Category`, `Conversation`, `Message` и `SubscriptionPayment`; без него недоступны `->filters()` и `->defaultSort()` Orchid.
2. Зарегистрировать пять прав `platform.marketplace.*` в `PlatformProvider::permissions()`.
3. Добавить группу меню «Биржа» с шестью ссылками; ссылка пользователей переносится в эту группу, роли остаются в «Access Controls».
4. Добавлять именованные маршруты в `routes/platform.php` вместе с соответствующими экранами (списки — в US1, карточки — в своих историях), с breadcrumbs по образцу существующих.

### Phase B — Объявления

1. `SiteListScreen`: выборка с `with(['owner', 'categories'])` и `withCount`, фильтры по домену, статусу, тематике, цене, доходу и подтверждению, сортировка по новизне.
2. `SiteEditScreen`: коммерческие поля, тематики, подтверждение владения, смена статуса; служебные поля только для чтения; связанные платежи и диалоги.
3. Валидация и guardrails: `changeStatus()`, `markVerified()`, запрет прямого изменения `verification_filename` и `paid_until`, транзакция на сохранение.

### Phase C — Тематики

1. `CategoryListScreen`: название, публичный идентификатор, число объявлений, поиск, создание, удаление.
2. `CategoryEditScreen`: создание и переименование; удаление запрещено при наличии объявлений.
3. Проверка уникальности названия и стабильности публичного идентификатора.

### Phase D — Пользователи

1. Дополнить `UserListScreen` счётчиками связанных сущностей.
2. Дополнить `UserEditScreen` тремя таблицами только для чтения: объявления, диалоги, платежи (ограничение по 10 последних записей).
3. Убрать действие удаления пользователя из списка и карточки, чтобы не терять связанные данные (FR-028).
4. Сохранить существующие блоки профиля, пароля, ролей и прав без изменения поведения.

### Phase E — Только чтение

1. `ConversationListScreen` и `ConversationShowScreen`: участники, объявление, история сообщений, без методов изменения.
2. `MessageListScreen` и `MessageShowScreen`: фильтры по диалогу, отправителю и прочтению, без методов изменения.
3. `SubscriptionPaymentListScreen` и `SubscriptionPaymentShowScreen`: фильтры по статусу, объявлению, плательщику и дате, без методов изменения.

### Phase F — Проверка

1. Feature-тесты на меню, доступ, списки, фильтры, редактирование, guardrails и read-only-гарантии.
2. `vendor/bin/pint --dirty` и полный прогон `php artisan test --compact`.
3. Ручная проверка по `quickstart.md`.

## Complexity Tracking

Нарушений конституции нет; раздел не заполняется.
