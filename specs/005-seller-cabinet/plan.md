# Implementation Plan: Личный кабинет продавца

**Branch**: `005-seller-cabinet` | **Date**: 2026-10-30 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/005-seller-cabinet/spec.md`

## Summary

Добавить в SPA личный кабинет, доступный по пункту «Личный кабинет» в меню иконки пользователя: список своих объявлений с ключевыми полями и пометками о подписке, создание и редактирование объявления с файлом проверки, смена статусов, скрытие проданных объявлений, а также переписку по объявлениям для обеих сторон (продавец и покупатель) с модальным окном «Связаться» на каталоге и счётчиком непрочитанных в шапке. Оплата подписки в итерацию не входит: кабинет показывает дату «оплачено до» (с порогом «требует продления» — 7 дней), историю платежей и объясняет, почему объявление скрыто из каталога.

Технический подход: серверные JSON-эндпоинты в группе `web` (сессионная cookie-аутентификация без Sanctum) под префиксом `/api/v1`, доступ к чужим записям отсекается выборками по связям пользователя (`findOrFail` → 404), валидация — в Form Request, отдача — через API Resources. Для модального окна «Связаться» нужен реальный идентификатор объявления, поэтому в итерацию входит минимальный публичный read-only API каталога (`GET /api/v1/catalog`, `GET /api/v1/catalog/zones`), заменяющий мок-сервис, и колонка `domain_zone`, необходимая фильтру зон. Схема `sites` дополняется nullable-колонкой `hidden_at` для скрытия проданных объявлений из списка кабинета.

**Frontend**: Vue 3.5 без TypeScript, Vue Router 4 (маршруты `/cabinet/*` с guard), Pinia (сторы `cabinet`, `conversations`, счётчик в `session`), shadcn-vue компоненты (`dialog`, `alert-dialog`, `input`, `textarea`, `select`, `form` — добавляются генератором в `resources/js/components/ui`), Tailwind 4. Новых npm-зависимостей нет.

## Technical Context

**Language/Version**: PHP 8.3.36; JavaScript (ESM, без TypeScript), Node.js 22.22

**Primary Dependencies**: Laravel Framework 13.39, Orchid Platform 14.53 (не затрагивается), Vue 3.5.44, Vue Router 4.6, Pinia 3.0, vue-sonner 2.0, reka-ui 2.11, shadcn-vue (CLI `npx shadcn-vue@latest add …`, код копируется в репозиторий), Vite 8, Tailwind CSS 4, PHPUnit 12, Vitest 5 + `@vue/test-utils`

**Storage**: MySQL 8; для тестов — SQLite `:memory:` (`phpunit.xml`). Изменения схемы: `sites.hidden_at` (nullable timestamp + составной индекс), `sites.domain_zone` (string(16), nullable, индекс) с бэкфиллом по существующим строкам. Остальные таблицы (`users`, `categories`, `category_site`, `conversations`, `messages`, `subscription_payments`) уже содержат необходимые поля и индексы; новых таблиц нет

**Testing**: PHPUnit 12 feature-тесты (`tests/Feature/Cabinet/**`, `tests/Feature/Catalog/CatalogApiTest.php`) с фабриками `SiteFactory`, `ConversationFactory`, `MessageFactory`, `CategoryFactory`, `SubscriptionPaymentFactory`; Vitest 5 в `tests/js/**` для сторов, сервисов и ключевых компонентов; `vendor/bin/pint --dirty`

**Target Platform**: Linux-сервер (production `https://sites-shop.ru`), браузеры с поддержкой ES2020; мобильные экраны от 375 px

**Project Type**: Web application — Laravel-монолит с Vue 3 SPA и админкой Orchid (`/admin` не затрагивается)

**Performance Goals**: кабинет со 100 объявлениями открывается не дольше каталога (p95 < 1 с без учёта сети); список объявлений — 1 запрос с `with`/`withCount` без N+1; счётчик непрочитанных — 1 агрегирующий запрос на навигацию; отправка сообщения и создание объявления — один запрос + редирект/ответ < 500 мс

**Constraints**: сессионная cookie-аутентификация и CSRF через `X-CSRF-TOKEN` (существующий `http.js`); чужие объявления и диалоги возвращают 404, а не 403; продавец не может менять `verified`, владельца и имя файла проверки; «Продан» — конечный статус; сообщение 1–2000 символов; списки по 25 записей; реального времени нет — счётчик обновляется при загрузке страниц и навигации; `/admin`, `/up` и API-маршруты не перехватываются SPA

**Scale/Scope**: десятки продавцов и сотни объявлений; 15 API-маршрутов (3 публичных: каталог, зоны, тематики; 12 кабинетных), 2 миграции, 7 контроллеров, 5 Form Request, 6 Resources, 6 Vue-маршрутов, 14 новых Vue-компонентов (из них 6 генерируются shadcn-vue), 3 Pinia-стора, 2 сервиса

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` — нератифицированный шаблон с плейсхолдерами (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]` и т.д.), поэтому формальных гейтов нет. Применяются правила репозитория из `AGENTS.md` и ограничения спецификации:

| Проверка | Статус | Обоснование |
|---|---|---|
| Существующая структура каталогов | PASS | Новые файлы — в `app/Http/{Controllers/Api,Requests,Resources}`, `app/Rules`, `resources/js/{views,components,stores,services}`, `tests/Feature`, `tests/js`; новые базовые каталоги не создаются |
| Зависимости без согласования | PASS | Новых composer/npm-зависимостей нет; shadcn-vue CLI только копирует компоненты в репозиторий |
| Конвенции Laravel | PASS | Form Request, API Resource, именованные маршруты, `make:` генераторы, фабрики в тестах |
| Единый фронтенд-стек | PASS | Один Vite-билд, Vue Router + Pinia, компоненты shadcn-vue |
| Безопасность | PASS | Ownership-выборки и 404, CSRF, `validated()` вместо `all()`, никаких `v-html`, счётчики только для участника диалога |
| Тестируемость | PASS | Feature-тесты на каждый эндпоинт и правило, Vitest на сторы/сервисы/окно, чек-лист `quickstart.md` |
| Производительность | PASS | `with`/`withCount`, индексы (`user_id, hidden_at`, `domain_zone`, существующие индексы диалогов) |
| Отсутствие изменений в Orchid | PASS | Админка не меняется; административные действия остаются там |
| Совместимость с уже отгруженным каталогом | PASS (с оговоркой) | Мок-сервис заменяется HTTP-сервисом с той же сигнатурой; контракт 003 сохраняется, `id` становится числовым — это осознанное изменение, отражено в `research.md` |

Нарушений, требующих `Complexity Tracking`, нет. Единственное расширение объёма — публичный read-only API каталога — вынужденное: без реальных идентификаторов объявлений сценарий «Связаться» неработоспособен. Обоснование в `research.md` §9.

### Повторная проверка после проектирования (Phase 1)

Артефакты `data-model.md`, `contracts/cabinet-api.yaml`, `contracts/ui-contract.md` и `quickstart.md` не меняют выводы гейта: новых зависимостей нет, базовые каталоги не добавлены, схема расширяется nullable-колонками с честным `down()`, персональные данные не логируются, доступ к чужим записям отсекается на уровне запросов. Контракты покрывают все FR спецификации (матрица — в `ui-contract.md` §9 и `data-model.md` §8–9). Гейт PASS.

## Project Structure

### Documentation (this feature)

```text
specs/005-seller-cabinet/
├── plan.md              # Этот файл
├── research.md          # Phase 0: решения и альтернативы
├── data-model.md        # Phase 1: сущности, поля, переходы, правила
├── quickstart.md        # Phase 1: проверка сценариев
├── contracts/
│   ├── cabinet-api.yaml # Phase 1: OpenAPI кабинетных и публичных эндпоинтов
│   └── ui-contract.md   # Phase 1: маршруты, состояния, компоненты
├── checklists/
│   └── requirements.md
├── spec.md
└── tasks.md             # Phase 2 (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Http/
│   ├── Controllers/Api/
│   │   ├── CatalogController.php          # GET /api/v1/catalog, /catalog/zones
│   │   ├── CategoryController.php         # GET /api/v1/categories
│   │   ├── MySiteController.php           # index/store/show/update
│   │   ├── SiteStatusController.php       # PATCH /my/sites/{site}/status
│   │   ├── SiteVisibilityController.php   # POST/DELETE /my/sites/{site}/hide
│   │   ├── ConversationController.php     # index/store/show
│   │   ├── MessageController.php          # POST /conversations/{id}/messages
│   │   └── UnreadMessageController.php    # GET /api/v1/messages/unread
│   ├── Requests/
│   │   ├── StoreSiteRequest.php
│   │   ├── UpdateSiteRequest.php
│   │   ├── ChangeSiteStatusRequest.php
│   │   ├── StartConversationRequest.php
│   │   └── StoreMessageRequest.php
│   └── Resources/
│       ├── SiteResource.php               # карточка кабинета + платежи/категории
│       ├── PublicSiteResource.php         # лот каталога (контракт 003)
│       ├── CategoryResource.php
│       ├── ConversationResource.php
│       ├── MessageResource.php
│       └── SubscriptionPaymentResource.php
├── Models/
│   ├── Site.php                           # hidden_at, domain_zone, needsRenewal, hide/unhide, scopes
│   ├── Conversation.php                   # scope forParticipant
│   └── Message.php                        # scope unreadFor
└── Rules/AvailableDomain.php              # нормализация домена + проверка занятости

database/
├── migrations/
│   ├── 2026_10_30_XXXXXX_add_hidden_at_to_sites_table.php
│   └── 2026_10_30_XXXXXX_add_domain_zone_to_sites_table.php
└── factories/SiteFactory.php              # состояния hidden, needsRenewal, zone

routes/web.php                             # SPA-shell /cabinet/* + API /api/v1/*

resources/js/
├── router/index.js                        # 6 маршрутов кабинета + guard
├── stores/
│   ├── cabinet.js                         # список, пагинация, CRUD, скрытые
│   ├── conversations.js                   # диалоги, активный диалог, отправка
│   └── session.js                         # + unreadMessages, refreshUnread()
├── services/
│   ├── catalogService.js                  # мок → HTTP (сигнатуры сохранены)
│   ├── cabinetService.js
│   └── conversationService.js
├── views/
│   ├── cabinet/CabinetSitesView.vue
│   ├── cabinet/CabinetSiteFormView.vue
│   ├── cabinet/CabinetHiddenView.vue
│   ├── cabinet/CabinetMessagesView.vue
│   └── cabinet/CabinetConversationView.vue
├── components/
│   ├── cabinet/…                          # SiteCard, SiteForm, StatusBadge, SubscriptionBlock, PaymentHistory, CabinetNav
│   ├── messages/…                         # ConversationList, ConversationThread, MessageComposer, ReadOnlyNotice
│   ├── catalog/ContactSellerDialog.vue    # модальное окно «Связаться»
│   └── ui/…                               # dialog, alert-dialog, input, textarea, select, form
└── lib/cabinetConfig.js                   # лимиты полей, порог продления 7 дней

tests/
├── Feature/Cabinet/
│   ├── SiteManagementTest.php
│   ├── SiteStatusTest.php
│   ├── SiteVisibilityTest.php
│   ├── ConversationApiTest.php
│   ├── UnreadMessagesTest.php
│   └── CabinetAccessTest.php
├── Feature/Catalog/CatalogApiTest.php
└── js/
    ├── cabinetStore.spec.js
    ├── cabinetService.spec.js
    ├── conversationsStore.spec.js
    ├── ContactSellerDialog.spec.js
    ├── CabinetSiteForm.spec.js
    └── SiteHeaderUnread.spec.js
```

**Structure Decision**: используется существующий Laravel-монолит с SPA (`resources/js`) и админкой Orchid; новых базовых каталогов нет, серверные классы раскладываются по уже принятым `app/Http/{Controllers/Api,Resources}` и добавляемым в рамках конвенции Laravel `Requests`/`Rules`.

## Design Overview

### 1. Точка входа и маршрутизация

- `SiteHeader` заменяет заглушку «Профиль» на «Личный кабинет» и ведёт на `/cabinet/sites`; выход остаётся вторым пунктом меню.
- Vue Router получает маршруты `/cabinet/sites`, `/cabinet/sites/new`, `/cabinet/sites/:id/edit`, `/cabinet/hidden`, `/cabinet/messages`, `/cabinet/messages/:id` с `meta.requiresAuth`. `/cabinet` редиректит на `/cabinet/sites`.
- Глобальный `beforeEach`: гость отправляется на `/` с тостом «Войдите, чтобы открыть личный кабинет» (FR-002). Сервер отдаёт SPA-shell для `/cabinet/*` одним маршрутом `where('any', '.*')`, кабинетные данные доступны только через API с `auth`.
- Прямой переход на адрес кабинета гостем: shell грузится, guard показывает сообщение и возвращает в каталог — данные не запрашиваются.

### 2. API кабинета

Все маршруты — в `routes/web.php` в группе `prefix('api/v1')` (существующая конвенция сессионных API). Ответы — camelCase, даты ISO 8601, деньги — целые рубли (`price` в БД `decimal`, приводится к int в ресурсе).

| Метод | Путь | Назначение |
|---|---|---|
| GET | `/catalog` | публичный список лотов (фильтры и пагинация из контракта 003) |
| GET | `/catalog/zones` | зоны и количество публичных лотов |
| GET | `/categories` | справочник тематик для формы |
| GET | `/my/sites` | объявления текущего пользователя (`hidden=0|1`, `page`) |
| POST | `/my/sites` | создание |
| GET | `/my/sites/{site}` | карточка для редактирования + история платежей |
| PATCH | `/my/sites/{site}` | редактирование |
| PATCH | `/my/sites/{site}/status` | смена статуса |
| POST | `/my/sites/{site}/hide` | скрыть проданное из списка |
| DELETE | `/my/sites/{site}/hide` | вернуть в список |
| GET | `/conversations` | диалоги пользователя (обе роли) |
| POST | `/conversations` | начать/продолжить переписку `{siteId, body}` |
| GET | `/conversations/{conversation}` | история; входящие помечаются прочитанными |
| POST | `/conversations/{conversation}/messages` | ответ |
| GET | `/messages/unread` | `{count}` для бейджа в шапке |

### 3. Владение и приватность

- Объявления: `$request->user()->sites()->findOrFail($siteId)` → 404 для чужого (FR-015, SC-004).
- Диалоги: выборка `Conversation::forParticipant($user)->findOrFail($conversationId)`; новый scope на модели. Чужой диалог → 404.
- Начало переписки: `Conversation::startFor()` уже запрещает свой лот и непубличное объявление; контроллер дополнительно проверяет публичную видимость и отдаёт 422 с понятным сообщением.
- Ответы в диалоге идут через `Conversation::postMessage()`, который проверяет участие и режим «только чтение»; ошибки домена транслируются в 422.
- Ответ на GET `/conversations/{conversation}` помечает входящие прочитанными (`markReadFor()`), `GET /messages/unread` — агрегат по всем диалогам пользователя.

### 4. Статусы, скрытие и подписка

- Смена статуса — `Site::changeStatus()` с проверкой `ListingStatus::canTransitionTo()`; недопустимый переход → 422, «Продан» терминален.
- Скрытие доступно только для `Sold`: `hidden_at = now()`; `hidden`-выборка кабинета использует `whereNotNull('hidden_at')`, основной список — `whereNull('hidden_at')`. Скрытие не меняет статус, платежи и диалоги; возврат — `hidden_at = null`.
- `Site::needsRenewal()` — `paid_until === null || paid_until <= today()->addDays(7)`; значение отдаётся в `SiteResource` и используется для пометок.
- История платежей — связь `subscriptionPayments` в карточке объявления, только чтение; эндпоинтов оплаты нет (FR-029).
- Файл проверки и `verified` — только отображение в кабинете; менять их может лишь Orchid.

### 5. Каталог как зависимость

`resources/js/services/catalogService.js` переключается с фикстур на `GET /api/v1/catalog` и `/zones` с сохранением сигнатур и формы ответа (контракт 003). `PublicSiteResource` отдаёт `id` числом (вместо `lst_0001`), `domainZone` из новой колонки, `monthlyIncome` из `monthly_revenue`, `isVerified` из `verified`, `publishedAt` из `created_at`. Фильтр зон становится `whereIn('domain_zone', …)`, «верхняя граница = и выше» повторяет правила контракта. Модальное окно «Связаться» получает реальный `id` и работает с `POST /api/v1/conversations`.

### 6. Состояния интерфейса

- Список объявлений: загрузка (skeleton), пусто (CTA «Добавить объявление»), ошибка (повтор), пагинация по 25.
- Форма: клиентская подсветка ошибок из 422 (`errors`), сохранение кнопки в состоянии «Сохраняем…», введённые данные не теряются при ошибке, повторная отправка блокируется.
- Карточка: статус-бейдж, `paidUntil`, `needsRenewal`, «Скрыто из каталога», файл проверки с копированием, число непрочитанных, действия «Редактировать», «Сменить статус», «Скрыть» (только проданное).
- Сообщения: список диалогов с непрочитанными, тред с автоскроллом, поле ввода и причина блокировки в режиме только чтения, пустое состояние.
- Окно «Связаться»: `dialog` с textarea, счётчик 0/2000, ошибка кнопки входа для гостя, подтверждение после отправки без потери фильтров и страницы.
- Шапка: бейдж непрочитанных на аватаре; скрыт при нуле; обновляется при инициализации сессии и `router.afterEach`.

### 7. Ошибки и сессия

- 401 → стор сессии сбрасывается, показывается тост «Войдите снова», навигация в каталог.
- 404 → «Объявление не найдено» / «Диалог не найден», без раскрытия чужих данных.
- 422 → сообщения полей; 500 → общий текст и повтор.
- Истёкшая сессия при сохранении формы: тост, данные остаются в форме (FR-027).

## Implementation Outline

1. **Срез 0 — каталог и зоны**: миграция `domain_zone` + бэкфилл, `domain_zone` в модели и фабрике, `CatalogController`, `PublicSiteResource`, `CategoryController`, перевод `catalogService` на HTTP, тесты `CatalogApiTest`.
2. **Срез 1 — данные кабинета**: миграция `hidden_at`, `Site::needsRenewal()`, `hide()/unhide()`, scopes `visibleInCabinet`/`hiddenFromCabinet`, состояния фабрики, `AvailableDomain`, `StoreSiteRequest`/`UpdateSiteRequest`.
3. **Срез 2 — API объявлений**: `MySiteController`, `SiteStatusController`, `SiteVisibilityController`, `SiteResource`, `SubscriptionPaymentResource`, feature-тесты прав, валидации, статусов и скрытия.
4. **Срез 3 — интерфейс кабинета**: маршруты и guard, `cabinet`-стор и сервис, список, форма, карточка, скрытые, бейдж подписки, Vitest.
5. **Срез 4 — переписка**: `ConversationController`, `MessageController`, `UnreadMessageController`, scopes, ресурсы, `conversations`-стор и сервис, список/тред/композер, feature-тесты 404/422/read-only.
6. **Срез 5 — окно «Связаться»**: `ContactSellerDialog` на каталоге, обработка гостя, сохранение фильтров, Vitest, ручной сценарий.
7. **Срез 6 — шапка и доводка**: пункт «Личный кабинет», бейдж непрочитанных, refresh на навигации, полный прогон PHPUnit/Vitest, `pint --dirty`, `npm run build`.

## Complexity Tracking

> Нарушений Constitution Check нет; таблица не заполняется.

Расширение объёма (публичный API каталога) не является архитектурным отклонением: это минимальный read-only срез, необходимый для работоспособности сценария «Связаться», и он опирается на уже существующие scopes модели `Site` и контракт `003`.