# Implementation Plan: Оплата подписки через СБП Robokassa

**Branch**: `006-robokassa-sbp-payment` | **Date**: 2026-11-04 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/006-robokassa-sbp-payment/spec.md`

## Summary

Добавить в личный кабинет продавца оплату продления подписки объявления через СБП по QR-коду Robokassa: выбор срока, серверный расчёт цены со скидкой, email для чека, QR-код и ссылка на оплату, автоматическое подтверждение через ResultURL, продление `paid_until`, перевод объявления в целевой статус из админки и история попыток. В админке появляются настройки тарифа (базовая цена, сроки, скидки, целевой статус, срок жизни QR), фильтры платежей по нескольким доменам, статусу, дате и способу оплаты, поиск по номеру счёта и разбор платежей со статусом «Требует проверки». Публичная страница «Тарифы» начинает показывать те же цену и сроки, что и кабинет.

Технический подход: Laravel-монолит с Vue 3 SPA и Orchid. Настройки тарифа хранятся в singleton-таблице `subscription_settings` и кешируются на 60 секунд. Попытка оплаты — расширенная запись `subscription_payments` со снимком домена, базовой цены, скидки, итоговой суммы, срока, целевого статуса, email для чека и срока жизни QR. Секреты Robokassa остаются на сервере: сервер считает `invId`, `outSum`, фискальный чек и MD5-подпись, а браузер только вызывает документированный `Robokassa.pay.startOp({ paymentMethod: 'SBP', … })` и рисует QR в контейнере. Подтверждение приходит на публичный ResultURL, проверяется по `Пароль#2`, применяется идемпотентно в транзакции с блокировкой платежа и объявления. Спорные случаи (несовпадение суммы, поздняя оплата проданного объявления, конфликт домена) переводятся в «Требует проверки» и разбираются администратором с обязательным комментарием и записью аудита.

## Technical Context

**Language/Version**: PHP 8.3.36; JavaScript (ESM, без TypeScript), Node.js 22.22

**Primary Dependencies**: Laravel Framework 13.39, Orchid Platform 14.53, Vue 3.5.44, Vue Router 4.6, Pinia 3.0, reka-ui 2.11 / shadcn-vue (компоненты уже в репозитории), Tailwind CSS 4, Vite 8, PHPUnit 12, Vitest 5 + `@vue/test-utils`. Новых composer/npm-зависимостей нет. Клиентский скрипт Robokassa (`https://auth.robokassa.ru/merchant/bundle/robokassa-iframe-badge.js`) подключается динамически только при открытии окна оплаты и не входит в Vite-бандл.

**Storage**: MySQL 8 в production; SQLite `:memory:` в тестах (`phpunit.xml`). Новые таблицы: `subscription_settings` (singleton-настройки тарифа), `subscription_payment_reviews` (аудит решений администратора). Расширяется `subscription_payments`: снимок домена и цены, скидка, целевой статус, email и фискальные данные чека, срок жизни QR, способ оплаты, данные провайдера, причина проверки, признак теста. Схема `sites` не меняется: `paid_until`, `status`, `public_domain`, `unpublish_reason` уже достаточны.

**Testing**: PHPUnit 12 feature-тесты в `tests/Feature/Payments/**` и `tests/Feature/Orchid/**`; фабрики `SubscriptionPaymentFactory`, `SubscriptionSettingFactory`, `SubscriptionPaymentReviewFactory`, `SiteFactory`; Vitest 5 в `tests/js/**` для окна оплаты, истории, polling и страницы «Тарифы»; `vendor/bin/pint --dirty --format agent`.

**Target Platform**: Linux-сервер (production `https://sites-shop.ru`), браузеры с поддержкой ES2020; мобильные экраны от 375 px (QR и ссылка на оплату должны быть доступны на телефоне).

**Project Type**: Web application — Laravel-монолит с Vue 3 SPA (`resources/js`) и админкой Orchid (`/admin`).

**Performance Goals**: расчёт цены и `payment-options` — p95 < 100 мс; создание счёта — < 500 мс; обработка ResultURL — < 300 мс; появление QR после подтверждения — < 3 с (SC-002); polling статуса — раз в 4 с с паузой при скрытой вкладке; список платежей на 100+ записях с фильтрами — < 1 с.

**Constraints**: только СБП и один тариф; суммы счёта и чека — целые рубли (математическое округление, 0,5 вверх); не более одной открытой попытки на объявление (`pending` или `requires_review`); секреты Robokassa не попадают в браузер, логи и тексты ошибок; ResultURL без сессии и CSRF, но с проверкой подписи; повторные уведомления идемпотентны; автоматических возвратов нет; тестовый режим отделён от боевого; изменения тарифа не пересчитывают уже выставленные счета.

**Scale/Scope**: десятки продавцов и сотни объявлений; тысячи платежей; 3 миграции, 2 новые таблицы, 5 сервисов, 3 API-контроллера оплаты + публичный pricing + ResultURL, 1 Form Request, 2–3 Resource, 1 команда истечения QR, 2 Orchid-экрана (настройки и разбор платежа), 5 фильтров, 6 Vue-компонентов/сервисов, 2 набора тестов (PHPUnit/Vitest).

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` — нератифицированный шаблон с плейсхолдерами (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]` и т.д.), поэтому формальных гейтов нет. Применяются правила репозитория из `AGENTS.md`, ограничения спецификации и существующие конвенции `005-seller-cabinet`:

| Проверка | Статус | Обоснование |
|---|---|---|
| Существующая структура каталогов | PASS | Новые классы — в `app/Http/{Controllers/Api,Requests,Resources}`, `app/Models`, `app/Services/Payments`, `app/Orchid/{Screens,Layouts,Filters}`, `app/Notifications`, `app/Console/Commands`, `resources/js/{components,services,composables,views}`, `tests/Feature/Payments`, `tests/js`; новые базовые каталоги не создаются |
| Зависимости без согласования | PASS | Новых composer/npm-зависимостей нет; Robokassa JS — внешний runtime-скрипт, а не пакет |
| Конвенции Laravel | PASS | Form Request, API Resource, именованные маршруты, `make:` генераторы, фабрики и сидеры, транзакции и `lockForUpdate` |
| Деньги и округление | PASS | Расчёт только на сервере; итог — целое число рублей, `round(..., 0, PHP_ROUND_HALF_UP)`; в API сумма целая, в БД `decimal(10,2)` |
| Безопасность | PASS | Секреты только в `config/services.php`/env; в браузер уходит одноразовая подпись, а не пароль; ownership-выборки → 404; ResultURL проверяет подпись; CSRF-исключение только для callback; секреты не логируются |
| Идемпотентность и конкурентность | PASS | Блокировка платежа и объявления в транзакции; повторный callback не продлевает подписку; одна открытая попытка на объявление через блокировку строки объявления |
| Тестируемость | PASS | Feature-тесты на каждый эндпоинт, callback, переходы статусов, фильтры и разбор; Vitest на окно оплаты, polling и историю; `quickstart.md` с ручным сценарием |
| Обратная совместимость | PASS | Старые платежи остаются видимыми; новые поля nullable; `PaymentStatus::requires_review` добавляется без изменения колонки; существующий тест про «Снят с продажи» обновляется по новому правилу |
| Админка Orchid | PASS | Настройки и разбор платежа добавляются в существующую админку; права `platform.marketplace.payments` и новое право настроек |
| Производительность | PASS | Кеш настроек 60 с, индексы `site_id+status`, `status+qr_expires_at`, `site_domain`, `created_at`; список платежей без N+1 (`with`) |

Нарушений, требующих `Complexity Tracking`, нет. Клиентский вызов `startOp` — не архитектурное отклонение, а единственный документированный способ получить QR СБП; секреты при этом остаются на сервере (обоснование в `research.md` §1).

### Повторная проверка после проектирования (Phase 1)

Артефакты `research.md`, `data-model.md`, `contracts/cabinet-payments-api.yaml`, `contracts/ui-contract.md` и `quickstart.md` не меняют выводы гейта: новых зависимостей нет, базовые каталоги не добавлены, миграции расширяют существующие таблицы nullable-колонками и создают две новые таблицы с честным `down()`, персональные данные (email для чека) не логируются, доступ к чужим платежам отсекается на уровне запросов. Контракты покрывают все FR спецификации (матрица — в `ui-contract.md` §8). Гейт PASS.

## Project Structure

### Documentation (this feature)

```text
specs/006-robokassa-sbp-payment/
├── plan.md              # Этот файл
├── research.md          # Phase 0: решения, формулы, альтернативы
├── data-model.md        # Phase 1: сущности, поля, переходы, инварианты
├── quickstart.md        # Phase 1: проверка сценариев
├── contracts/
│   ├── cabinet-payments-api.yaml # Phase 1: OpenAPI кабинета, pricing и ResultURL
│   └── ui-contract.md            # Phase 1: экраны, состояния, матрица FR
├── checklists/
│   └── requirements.md
├── spec.md
└── tasks.md             # Phase 2 (/speckit.tasks)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   ├── PaymentStatus.php                  # + requires_review, isOpen(), label()
│   ├── PaymentMethod.php                  # sbp / «СБП»
│   ├── PaymentProvider.php                # robokassa
│   └── PaymentReviewDecision.php          # accepted / rejected
├── Models/
│   ├── Site.php                           # acceptsPayments() + withdrawn, applyPaidSubscription(), domain conflict
│   ├── SubscriptionPayment.php            # новые поля, связи, переходы статусов
│   ├── SubscriptionSetting.php            # singleton настроек тарифа
│   └── SubscriptionPaymentReview.php      # аудит решений администратора
├── Services/Payments/
│   ├── SubscriptionPricing.php            # цена, скидка, округление, набор сроков
│   ├── RobokassaGateway.php               # подпись, чек, URL, проверка callback
│   ├── PaymentInvoiceService.php          # создание счёта, отмена предыдущей попытки
│   ├── PaymentFinalizer.php               # применение callback, продление, целевой статус
│   ├── PaymentReviewService.php           # принять/отклонить, аудит
│   └── PaymentExpiryService.php           # истечение QR и отмена pending
├── Http/
│   ├── Controllers/Api/
│   │   ├── PricingController.php          # GET /api/v1/pricing
│   │   ├── SitePaymentController.php      # options/index/store/start
│   │   ├── PaymentStatusController.php    # GET /api/v1/my/payments/{payment}
│   │   └── RobokassaResultController.php  # POST /api/v1/payments/robokassa/result
│   ├── Requests/StoreSubscriptionPaymentRequest.php
│   └── Resources/
│       ├── PricingResource.php
│       ├── PaymentOptionsResource.php
│       ├── RobokassaStartResource.php
│       ├── SubscriptionPaymentResource.php
│       └── SubscriptionPaymentSummaryResource.php
├── Orchid/
│   ├── Screens/SubscriptionSetting/SubscriptionSettingScreen.php
│   ├── Screens/SubscriptionPayment/SubscriptionPaymentListScreen.php  # фильтры/колонки
│   ├── Screens/SubscriptionPayment/SubscriptionPaymentShowScreen.php  # разбор
│   ├── Layouts/SubscriptionPayment/PaymentReviewLayout.php
│   └── Filters/
│       ├── PaymentDomainsFilter.php
│       ├── PaymentStatusFilter.php
│       ├── PaymentMethodFilter.php
│       ├── PaymentInvoiceFilter.php
│       └── PaymentPaidAtFilter.php
├── Notifications/PaymentRequiresReview.php
└── Console/Commands/ExpirePendingPayments.php

config/services.php                          # robokassa: login/passwords/test/urls/receipt
.env.example                                 # ROBOKASSA_* переменные

database/
├── migrations/
│   ├── 2026_11_04_XXXXXX_create_subscription_settings_table.php
│   ├── 2026_11_04_XXXXXX_add_robokassa_fields_to_subscription_payments_table.php
│   └── 2026_11_04_XXXXXX_create_subscription_payment_reviews_table.php
├── factories/
│   ├── SubscriptionSettingFactory.php
│   └── SubscriptionPaymentReviewFactory.php
└── seeders/SubscriptionSettingSeeder.php

routes/
├── web.php                                  # публичный pricing + auth API оплаты + ResultURL
├── platform.php                             # настройки подписки и разбор платежа
└── console.php                              # payments:expire-pending каждую минуту

bootstrap/app.php                            # CSRF-исключение для ResultURL

resources/js/
├── services/paymentService.js               # pricing/options/create/status/start/history
├── composables/usePaymentPolling.js         # polling с паузой при скрытой вкладке
├── components/cabinet/
│   ├── PaymentDialog.vue                    # срок, email, QR, ссылка, статусы
│   ├── PaymentHistory.vue                   # история и повторные действия
│   ├── SiteCard.vue                         # «Продлить»/«Продолжить оплату»
│   └── SubscriptionBlock.vue                # дата и пометка продления
├── stores/cabinet.js                        # latestPayment, обновление после оплаты
└── views/PricingView.vue                    # публичные тарифы вместо заглушки

tests/
├── Feature/Payments/
│   ├── SubscriptionPricingTest.php
│   ├── PaymentInvoiceTest.php
│   ├── PaymentCallbackTest.php
│   ├── PaymentExpiryTest.php
│   ├── PaymentReviewTest.php
│   ├── PaymentAdminListTest.php
│   └── PricingPageTest.php
├── Feature/Orchid/SubscriptionSettingScreenTest.php
├── Feature/Models/SiteSubscriptionTest.php  # обновление правила withdrawn
├── Feature/Orchid/SubscriptionPaymentReadOnlyTest.php # новые поля/фильтры
└── js/
    ├── PaymentDialog.spec.js
    ├── PaymentHistory.spec.js
    ├── usePaymentPolling.spec.js
    ├── paymentService.spec.js
    └── PricingView.spec.js
```

**Structure Decision**: используется существующий Laravel-монолит с SPA (`resources/js`) и админкой Orchid. Серверные классы раскладываются по уже принятым `app/Http/{Controllers/Api,Requests,Resources}` и `app/Services`; платёжная логика выносится в `app/Services/Payments`, чтобы не раздувать модель и контроллеры. Новых базовых каталогов нет.

## Design Overview

### 1. Настройки тарифа и расчёт цены

- `subscription_settings` — singleton-строка (`id = 1`) с полями `base_price`, `periods` (JSON, по умолчанию `[1,3,6,12]`), `discounts` (JSON, например `{"3":10,"6":15,"12":25}`), `target_status` (`active`/`inactive`, по умолчанию `active`), `qr_ttl_minutes` (по умолчанию 15), `is_active` (по умолчанию `false`, чтобы до настройки цены нельзя было выставить бесплатный счёт), timestamps.
- `SubscriptionSetting::current()` читает строку через `Cache::remember('subscription:settings', 60, …)`; сохранение из админки сбрасывает кеш. Значения по умолчанию отдаются, даже если строка ещё не создана.
- `SubscriptionPricing::quote(int $months)`:
  - `gross = base_price * months`;
  - `discountPercent = discounts[months] ?? 0` (для 1 месяца всегда 0);
  - `total = (int) round(gross * (100 - discountPercent) / 100, 0, PHP_ROUND_HALF_UP)`;
  - `discountAmount = gross - total` (в копейках, чтобы сумма сходилась);
  - если `total <= 0` или срок вне `periods` — 422 с понятной ошибкой.
- Все значения фиксируются в платеже; изменение настроек влияет только на новые счета. Отключение тарифа блокирует новые счета, но не мешает оплатить уже выставленный.

### 2. Создание счёта

- `POST /api/v1/my/sites/{site}/payments` с `{ periodMonths, receiptEmail }`.
- `PaymentInvoiceService` в транзакции блокирует объявление (`lockForUpdate`), проверяет владельца, статус (не `sold`), активность тарифа, допустимый срок, email, отсутствие открытой попытки и конфликт домена для целевого статуса.
- Если у объявления есть `pending`, она отменяется с причиной «Создана новая попытка». Если есть `requires_review`, новый счёт не выставляется — продавец видит «Платёж на проверке».
- Домен, базовая цена, скидка, итог, срок, целевой статус, email, фискальный чек и `qr_expires_at` сохраняются снимком. Номер счёта — `subscription_payments.id` (`InvId`), уникальный и числовой.
- Ответ: `{ data: Payment, robokassa: { merchantLogin, outSum, invId, receipt, signature, isTest, scriptUrl } }`.

### 3. Robokassa SBP и фронтенд

- Сервер формирует `outSum` целым числом рублей (например, `1500`), `invId`, JSON-чек (`sno`, `items[0]` с названием «Продление подписки на домен …», `quantity: 1`, `sum`, `payment_method: full_payment`, `payment_object: service`, `tax`) и подпись `md5(login:outSum:invId:receipt:Пароль#1)`. В браузер не попадают `Пароль#1`, `Пароль#2` и merchant-секреты.
- `PaymentDialog.vue` динамически загружает `robokassa-iframe-badge.js`, вызывает `Robokassa.pay.startOp({ paymentMethod: 'SBP', merchantLogin, outSum, invId, receipt, email, signature, qrContainerId, qrContainerSize, onpaymentlink })`, рисует QR в контейнере и показывает ссылку для банковского приложения.
- Пока платёж `pending`, фронтенд опрашивает `GET /api/v1/my/payments/{payment}` раз в 4 секунды, останавливается на `paid`/`failed`/`cancelled`/`requires_review`, делает паузу при `document.hidden` и возобновляется при возврате. После `paid` обновляет карточку и историю без перезагрузки.
- «Продолжить оплату» для pending вызывает `POST /api/v1/my/payments/{payment}/start`, который возвращает те же параметры, если QR ещё действует; истёкшая попытка сначала переводится в `cancelled` и возвращает 409. «Повторить» создаёт новый счёт.
- Ошибка загрузки скрипта/провайдера показывается понятным текстом; счёт остаётся pending до истечения, продавец может повторить или открыть заново. Секреты и stack trace не показываются.

### 4. ResultURL и применение оплаты

- `POST /api/v1/payments/robokassa/result` — публичный, без сессии, исключён из CSRF, с throttle по IP. Принимает `OutSum`, `InvId`, `SignatureValue`, `PaymentMethod`, `EMail`, `Fee`, `IncCurrLabel`.
- `RobokassaGateway::verifyCallback()` проверяет `md5(OutSum:InvId:Пароль#2)` (для тестового режима — тестовый `Пароль#2`), существование счёта, способ оплаты `SBP`, валюту и сумму. Неверная подпись → 400; неизвестный `InvId` → 200 `OK{InvId}` с записью в лог; несовпадение суммы/валюты/способа → `requires_review` и 200 `OK{InvId}`.
- `PaymentFinalizer` в транзакции блокирует платёж и объявление. Если объявление `sold`, домен конфликтует или объявление больше не принимает оплату — платёж получает `requires_review`, `paid_at` фиксируется, подписка и статус не меняются. Иначе `paid_at`, `status = paid`, `provider_payload`, продление `paid_until` от более поздней даты и перевод в `target_status`.
- Повторный callback по `paid` — идемпотентный ответ `OK{InvId}` без повторного продления. Поздний callback по `cancelled`/`failed` обрабатывается как обычный, если объявление всё ещё принимает оплату.
- Ответ провайдеру всегда `text/plain` `OK{InvId}` при принятом уведомлении; это предотвращает бесконечные повторы Robokassa.

### 5. Статусы, истечение и повторные попытки

- `PaymentStatus`: `pending`, `paid`, `failed`, `cancelled`, `requires_review`. `isOpen()` — `pending` или `requires_review`; `isFinal()` сохраняется для совместимости, но поздняя оплата обрабатывается явными переходами, а не через `isFinal()`.
- Переходы: `pending → paid/cancelled/failed/requires_review`; `requires_review → paid/failed` (решение администратора); `cancelled/failed → paid` (поздний callback); `paid` — терминальный для повторных callback.
- `payments:expire-pending` запускается каждую минуту и переводит `pending` с `qr_expires_at < now()` в `cancelled` с причиной «Истёк срок действия QR-кода». Дополнительно `PaymentExpiryService` проверяет срок лениво при `options`/`start`/`status`, чтобы не зависеть от планировщика.
- При создании новой попытки предыдущая `pending` отменяется; её QR перестаёт быть действительным. `requires_review` блокирует новую попытку до решения администратора.

### 6. Кабинет продавца

- `SiteCard` получает действие «Продлить» для `active`/`inactive`/`withdrawn`; для `sold` действия нет. Если есть `latestPayment` в `pending`, кнопка называется «Продолжить оплату», если `failed`/`cancelled` — «Повторить», если `requires_review` — «Платёж на проверке» (кнопка ведёт в историю).
- `PaymentDialog.vue` — пошаговое окно: параметры (срок, базовая цена, скидка, итог, email, целевой статус и предупреждение «объявление останется скрытым»), QR/ссылка с таймером и polling, результат (`paid`, `failed`, `cancelled`, `requires_review`), ошибки и повтор.
- `PaymentHistory.vue` показывает базовую цену, скидку, итог, срок, способ «СБП», статус, время создания/оплаты и причину; действия «Продолжить оплату»/«Повторить»; для старых записей без новых полей отображаются только доступные данные.
- `SubscriptionBlock` по-прежнему показывает `paid_until`, «Требует продления» и «Скрыто из каталога»; после оплаты блок обновляется без перезагрузки.
- `GET /api/v1/my/sites` дополняется `latestPayment` (hasOne latestOfMany) и `canRenew`; полная история — `GET /api/v1/my/sites/{site}/payments` (последние 50, от новых к старым).

### 7. Публичная страница «Тарифы»

- `GET /api/v1/pricing` отдаёт `basePrice`, `periods` (месяцы, процент скидки, итог, цена за месяц), `isActive`. `PricingView.vue` заменяет заглушку на карточки сроков, показывает скидку и итог, ведёт в кабинет; при `isActive = false` показывает «Оплата временно недоступна». Кеш/актуализация — не дольше 1 минуты (SC-012).

### 8. Админка

- `SubscriptionSettingScreen` (`/admin/marketplace/subscription-settings`, право `platform.marketplace.subscription-settings`) редактирует цену, сроки, скидки, целевой статус, TTL и активность; валидирует, что сроки уникальны, содержат 1, скидки — целые 0–99 и только для сроков ≥ 3, итоговая цена > 0.
- `SubscriptionPaymentListScreen` получает колонки номера счёта, домена-снимка и текущего домена, плательщика, суммы, срока, статуса, способа, теста, дат; фильтры по нескольким доменам (через запятую), статусу, дате, способу, поиску по номеру счёта; сортировку `created_at`/`paid_at` в обе стороны; по умолчанию — новые сверху.
- `SubscriptionPaymentShowScreen` показывает снимок и текущее состояние, причину проверки и историю решений. Для `requires_review` — форма «Принять/Отклонить» с обязательным комментарием; «Принять» вызывает `PaymentReviewService::accept()` (продлевает и выставляет статус), «Отклонить» — `reject()` (статус `failed`, без продления); оба пишут `subscription_payment_reviews`.
- При переходе в `requires_review` уведомление `PaymentRequiresReview` уходит администраторам с правом `platform.marketplace.payments` (и дублируется в лог, если получателей нет). Продавец видит статус и пояснение в истории.

### 9. Безопасность, наблюдаемость и совместимость

- Секреты Robokassa — только `config/services.php` + env; в логи пишутся `invId`, `site_id`, `user_id`, сумма и результат, но не пароли, подпись и полный `receipt`.
- ResultURL исключён из CSRF в `bootstrap/app.php`; дополнительно можно ограничить IP-адресами Robokassa (`185.59.216.65`, `185.59.217.65`), но подпись остаётся основным контролем.
- Rate limiting: создание счёта — 5/мин на пользователя, статус/start — 60/мин, callback — 120/мин на IP.
- Старые платежи без новых полей остаются видимыми: ресурс отдаёт `null` для снимков и подставляет текущий домен; фильтры учитывают и `site_domain`, и текущий домен.
- Тестовый режим: отдельные `ROBOKASSA_TEST_*` креды, `isTest` в параметрах, тестовая подпись `Пароль#2`; на production тестовые счета и callback отклоняются. На стенде тестовая оплата проходит полный сценарий и помечается `is_test`.
- Существующий тест `test_withdrawn_and_sold_sites_reject_payment` разделяется: `withdrawn` теперь принимает оплату, `sold` — нет; `PaymentStatus::requires_review` добавляется в фильтры и ресурсы.

## Implementation Outline

1. **Срез 0 — настройки и цена**: миграция `subscription_settings`, модель, фабрика, сидер, `SubscriptionPricing`, `PricingController`, `PricingResource`, `PricingView.vue`, тесты расчёта/округления/скидок/неактивного тарифа.
2. **Срез 1 — данные платежа**: миграция полей `subscription_payments`, миграция `subscription_payment_reviews`, модели `SubscriptionPayment`/`SubscriptionPaymentReview`, enum-ы, фабрики, обновление `PaymentStatus`, `Site::acceptsPayments()`/`applyPaidSubscription()`/`hasDomainConflictFor()`, тесты модели и переходов.
3. **Срез 2 — выставление счёта**: `StoreSubscriptionPaymentRequest`, `PaymentInvoiceService`, `SitePaymentController` (`options`, `store`, `index`, `start`), `PaymentOptionsResource`, `RobokassaStartResource`, `RobokassaGateway` (подпись/чек), feature-тесты прав, sold/withdrawn, email, одной попытки, конфликта домена, снимков.
4. **Срез 3 — callback и применение**: `RobokassaResultController`, `PaymentFinalizer`, `PaymentExpiryService`, `ExpirePendingPayments`, CSRF-исключение, throttle, тесты подписи, суммы, валюты, дублей, поздней оплаты, sold/конфликта, тестового режима.
5. **Срез 4 — кабинет**: `paymentService.js`, `usePaymentPolling.js`, `PaymentDialog.vue`, `PaymentHistory.vue`, обновление `SiteCard.vue`/`SubscriptionBlock.vue`/`cabinet.js`, `latestPayment` в `SiteResource` и `MySiteController`, Vitest на окно/историю/polling.
6. **Срез 5 — админка**: `SubscriptionSettingScreen`, меню/права, фильтры платежей, колонки и сортировки, `PaymentReviewLayout`, `PaymentReviewService`, `PaymentRequiresReview`, Orchid-тесты.
7. **Срез 6 — публичные тарифы и уведомления**: `PricingView` с реальными данными, обновление `SubscriptionExpiring` (ссылка на оплату), проверка, что после оплаты напоминания не отправляются до нового порога.
8. **Срез 7 — доводка**: полный прогон PHPUnit/Vitest, `pint --dirty --format agent`, `npm run build`, ручной сценарий `quickstart.md`, проверка отсутствия секретов в логах/сетевых ответах.

## Complexity Tracking

> Нарушений Constitution Check нет; таблица не заполняется.

Клиентский `Robokassa.pay.startOp` — вынужденное решение: Robokassa документирует получение QR СБП только через браузерный JS. Риск раскрытия секретов снят тем, что сервер считает подпись и чек, а в браузер передаётся только одноразовая подпись, привязанная к сумме и номеру счёта (см. `research.md` §1).
