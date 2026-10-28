# Implementation Plan: Модели данных биржи по продаже сайтов

**Branch**: `001-marketplace-data-model` | **Date**: 2026-10-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/001-marketplace-data-model/spec.md`

## Summary

Собрать слой данных биржи по продаже сайтов: миграции, Eloquent-модели, enum-статусы, фабрики, сидеры и PHPUnit-тесты. Слой должен обеспечивать регистрацию через VK ID/Yandex ID без пароля, объявления с несколькими тематиками, подписку и дату «оплачено до» на каждом объявлении отдельно, вычисляемую публичную видимость, переходы статусов, переписку «покупатель ↔ продавец» в рамках диалога и подтверждение принадлежности сайта продавцу через файл проверки с административным флагом `verified`.

Технический подход: схема MySQL 8 с переносимой на SQLite проверкой уникальности домена через генерируемый столбец, PHP-enum вместо нативного MySQL ENUM, локальные scopes вместо глобальных, доменные методы на моделях для переходов статусов и продления подписки. HTTP-слой, OAuth-потоки, платёжный провайдер и UI в эту фазу не входят.

## Technical Context

**Language/Version**: PHP 8.3.36

**Primary Dependencies**: Laravel 13.39.0 (Eloquent, Schema Builder, Artisan), PHPUnit 12.5.42. Новые Composer/NPM-пакеты не добавляются.

**Storage**: MySQL 8.0.46 (локальная и целевая среда); SQLite 3.37.2 in-memory в тестах (`phpunit.xml`). Схема должна быть совместима с обеими СУБД.

**Testing**: PHPUnit 12.5.42, feature-тесты с `RefreshDatabase`, файлы в `tests/Feature/Models`.

**Target Platform**: Linux-сервер, веб-приложение Laravel.

**Project Type**: single Laravel web application — в этой фазе только слой данных.

**Performance Goals**: первая страница каталога — менее 2 секунд (SC-009); выборка видимых объявлений опирается на составной индекс `(status, paid_until)`; фильтры по цене, выручке и дате создания индексированы; пагинация — 25 записей.

**Constraints**: без новых зависимостей и без новых корневых каталогов; валюта RUB; подписка привязана к объявлению; статус не перезаписывается при истечении подписки; имя файла проверки генерируется один раз и неизменяемо; `verified` меняется только администратором и не влияет на видимость; объём данных в спецификации не задан — индексы рассчитаны на десятки тысяч объявлений.

**Scale/Scope**: 7 таблиц (5 новых, 1 изменяемая `users`, 1 pivot), 5 новых моделей, 2 enum, 5 фабрик, 1 сидер, 9 тестовых классов.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` содержит только шаблонные заглушки (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]` и т. п.) и не ратифицирован. Принципов и ограничений, которые можно проверить, нет, поэтому ворот не зафиксировано.

- Нарушений нет: план не добавляет зависимостей, не меняет архитектуру приложения и следует существующим конвенциям Laravel-проекта.
- Наблюдение (не блокер): конституцию стоит ратифицировать до командной разработки, иначе проверки качества остаются неформальными.
- Повторная проверка после Phase 1: нарушений не появилось; отложенные части (HTTP, OAuth, платежи) не входят в эту фазу и не создают скрытых зависимостей.

## Project Structure

### Documentation (this feature)

```text
specs/001-marketplace-data-model/
├── plan.md              # этот файл
├── research.md          # Phase 0: решения и альтернативы
├── data-model.md        # Phase 1: сущности, поля, связи, переходы
├── quickstart.md        # Phase 1: сценарии проверки
└── tasks.md             # Phase 2 (/speckit.tasks, не создаётся здесь)
```

`contracts/` не создаётся: у фазы нет внешних интерфейсов (API, CLI, библиотечного контракта). Контракт — сама схема БД и публичные методы моделей, они описаны в `data-model.md`.

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── ListingStatus.php
│   └── PaymentStatus.php
└── Models/
    ├── Category.php
    ├── Conversation.php
    ├── Message.php
    ├── Site.php
    ├── SubscriptionPayment.php
    └── User.php                     # изменяется

database/
├── factories/
│   ├── CategoryFactory.php
│   ├── ConversationFactory.php
│   ├── MessageFactory.php
│   ├── SiteFactory.php
│   ├── SubscriptionPaymentFactory.php
│   └── UserFactory.php              # дополняется состояниями vk/yandex
├── migrations/
│   ├── ..._add_social_identity_fields_to_users_table.php
│   ├── ..._create_categories_table.php
│   ├── ..._create_sites_table.php
│   ├── ..._create_category_site_table.php
│   ├── ..._create_conversations_table.php
│   ├── ..._create_messages_table.php
│   └── ..._create_subscription_payments_table.php
└── seeders/
    ├── CategorySeeder.php
    └── DatabaseSeeder.php           # вызывает CategorySeeder

tests/
└── Feature/
    └── Models/
        ├── CategorySeederTest.php
        ├── ConversationTest.php
        ├── SiteFilterTest.php
        ├── SiteSubscriptionTest.php
        ├── SiteStatusTest.php
        ├── SiteVerificationTest.php
        ├── SiteVisibilityTest.php
        ├── SiteDomainUniquenessTest.php
        └── UserSocialIdentityTest.php
```

**Structure Decision**: используется существующая структура Laravel-приложения. Новые каталоги — только стандартные подкаталоги `app/Enums` и `tests/Feature/Models`; корневые каталоги не добавляются. Контроллеры, маршруты, Form Requests, политики и представления не создаются.

## Phase 0: Research

Все неизвестные Technical Context закрыты в [research.md](./research.md). Ключевые решения:

| Решение | Обоснование | Альтернативы |
|---|---|---|
| `Site` + таблица `sites` | Короткое имя доменной сущности, совпадает с «сайт» в спецификации | `Listing` + `listings` |
| `paid_until` (date) на `sites` | Подписка принадлежит объявлению (FR-010, FR-027) | подписка на аккаунт, отдельная таблица подписок |
| Видимость — локальный scope, не глобальный | Не скрывает данные от владельца и от служебных задач | глобальный scope, материализованный флаг |
| Уникальность домена — генерируемый столбец + уникальный индекс | Освобождение домена после «Снят с продажи»/«Продан» (FR-009) на уровне БД, переносимо MySQL/SQLite | проверка только в приложении, обычный уникальный индекс, partial index |
| Статус — `varchar` + PHP-enum cast | Переносимость, явные переходы в enum, без нативного MySQL ENUM | нативный `ENUM`, пакет state machine |
| `Conversation` на пару «объявление + покупатель» | FR-029, история и доступ участников (FR-030) | сообщения без диалога, один тред на продавца |
| Продление подписки — `max(сегодня, paid_until) + period_months` в транзакции с блокировкой | Идемпотентность и независимость объявлений (FR-011, SC-013) | вычисление «на лету», отдельная таблица подписок |
| Соцполя: `vk_id`, `ya_id`, `avatar`, время входа по провайдеру; `email`/`password` nullable | FR-001, FR-002; email не ключ входа | отдельная таблица identities, обязательный пароль |
| Деньги — `decimal(12,2)` / `decimal(10,2)` + cast `decimal:2` | Точность RUB без ошибок округления | целые копейки, float |
| Тематики — many-to-many + уникальный `slug` | FR-005, FR-006, стабильные ссылки | JSON-массив, одна тематика |
| Проверка владения: `verification_filename` (random, immutable) + `verified` (admin-only) | FR-031, FR-032; файл размещается вручную, флаг ставит администратор | ручной ввод имени файла, автоматическая проверка по HTTP, отдельная таблица проверок |

## Phase 1: Design

- [data-model.md](./data-model.md) — сущности, поля, типы, индексы, связи, casts, scopes, доменные методы, переходы статусов, правила видимости и оплаты.
- [quickstart.md](./quickstart.md) — команды миграции, сидирования и запуска тестов с ожидаемыми результатами.
- `contracts/` не требуется (внутренний слой данных).

## Scope Coverage

| Требования | Статус в этой фазе |
|---|---|
| FR-001–FR-016, FR-018, FR-019, FR-021–FR-023, FR-026–FR-029 | Покрываются схемой, моделями, enum, scopes, связями, нормализацией домена и доменными методами |
| FR-017, FR-020, FR-025 | Частично: модели требуют аутентифицированного `User` и проверяют видимость объявления; блокировка гостя, длины полей, формат домена, «хотя бы одна тематика» и лимит сообщения — Form Requests/middleware следующей HTTP-фазы |
| FR-024, FR-030 | Предикаты доступа в моделях (`isOwnedBy`, `isParticipant`, `canBeRepliedToBy`); маршрутная авторизация — в HTTP-фазе |
| FR-031, FR-032 | Покрываются схемой и моделью: генерация уникального `verification_filename` при создании, неизменяемость, `verified` вне mass assignment, метод `markVerified()` для администратора |
| OAuth-редиректы, платёжный провайдер, контроллеры, маршруты, UI | Вне фазы |

## Testing Strategy

- Только feature-тесты уровня данных (`tests/Feature/Models`), без HTTP-маршрутов.
- `RefreshDatabase` + SQLite in-memory; отдельный прогон миграций на MySQL подтверждает генерируемый столбец и индексы.
- Проверяются наблюдаемое поведение: состав выборки, значения полей, переходы, исключения, счётчики непрочитанных, идемпотентность сидера.
- Фабрики используют состояния вместо ручной подстановки атрибутов.
- Покрытие по классам: видимость, переходы, уникальность домена, подписка/платежи, фильтры и пагинация, диалоги и доступ, сидер тематик, соцполя пользователя, проверка владения сайтом.

## Risks & Mitigations

| Риск | Митигация |
|---|---|
| Генерируемый столбец не поддерживается старой СУБД | Требования: MySQL ≥ 5.7, SQLite ≥ 3.31; в проекте MySQL 8.0.46 и SQLite 3.37.2 |
| Расхождение поведения MySQL и SQLite (регистр, уникальность NULL) | Нормализация домена в модели до сохранения; тесты на обеих СУБД при релизе |
| Дублирование платежа при гонке | Транзакция + `lockForUpdate` на платёж и объявление, идемпотентный `markAsPaid` |
| `LIKE '%домен%'` не использует индекс | Приемлемо для v1; при росте — полнотекстовый или префиксный поиск |
| Денормализованный `last_message_at` рассинхронизируется | Обновление в событии `created` модели `Message` + тест |
| Отложенная валидация FR-020/FR-025 | Явно зафиксирована в Scope Coverage; реализуется в HTTP-фазе |
| Имя файла проверки может быть угадано или перехвачено | 32 символа из `[a-z0-9]` (≈165 бит), уникальный индекс, неизменяемость; файл не содержит секретов |
| `verified` может быть изменён продавцом через mass assignment | Поле вне `#[Fillable]`; изменение только через `markVerified()`, вызываемый административным слоем |

## Complexity Tracking

> **Fill ONLY if Constitution Check has violations that must be justified**

Нарушений нет.
