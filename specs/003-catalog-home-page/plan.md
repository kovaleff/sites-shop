# Implementation Plan: Публичная главная страница с каталогом сайтов

**Branch**: `003-catalog-home-page` | **Date**: 2026-10-28 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/003-catalog-home-page/spec.md`

## Summary

Собрать публичную главную страницу биржи как Vue 3 SPA внутри существующего Laravel-приложения: шапка с брендом, меню из трёх пунктов и входом через VK ID/Yandex ID, панель из четырёх фильтров (диапазон цены, доменные зоны, диапазон дохода, «Проверен»), список карточек лотов с кнопкой «Связаться» и пагинация по 25 записей. Состояние каталога хранится в Pinia и восстанавливается из query-параметров URL; данные приходят из мок-сервиса, повторяющего ожидаемый контракт будущего API. Вспомогательные разделы «Как это работает» и «Тарифы» — страницы-заглушки с общей шапкой.

Технический подход: Vue 3.5 (только JS, без TypeScript) + Vue Router 4 (`createWebHistory`) + Pinia 3; UI — сгенерированные компоненты shadcn-vue (`button`, `card`, `slider`, `switch`, `toggle-group`, `pagination`, `skeleton`, `alert`, `sheet`, `dropdown-menu`, `avatar`, `sonner`) на `reka-ui`; стили — Tailwind 4 с токенами shadcn-vue, перенесёнными из `resources/des/sites-shop_list.html`; сборка — существующий Vite 8 + Laravel Vite Plugin с добавлением `@vitejs/plugin-vue`. Серверная часть ограничена тремя именованными маршрутами, отдающими Blade-shell; `/admin` Orchid не затрагивается. Проверка — Vitest + `@vue/test-utils` для логики фильтров, мок-сервиса, стора и ключевых компонентов плюс ручной чек-лист из `quickstart.md`.

## Technical Context

**Language/Version**: JavaScript (ES2022, ESM), Node.js 22+, без TypeScript; PHP 8.3.36 и Laravel 13.39.0 для отдачи shell-шаблона

**Primary Dependencies**: Vue 3.5.44, Vue Router 4.6.4, Pinia 3.0.4, `@vitejs/plugin-vue` 6.0.10, Vite 8, Laravel Vite Plugin 3.1, Tailwind CSS 4, `@tailwindcss/vite`, shadcn-vue CLI 2.9, `reka-ui` 2.11, `class-variance-authority`, `clsx`, `tailwind-merge`, `lucide-vue-next`, `vue-sonner`

**Storage**: N/A — данные каталога и сессия живут в памяти мок-сервиса; сессия дополнительно кэшируется в `localStorage` (`catalog:session`), сценарий моков — в `catalog:mock-scenario`. Серверная БД не используется

**Testing**: Vitest 5.0.5 + `@vue/test-utils` 2.5.1 + `jsdom` 30.1.3, тесты в `tests/js/**/*.spec.js`; ручная проверка через `quickstart.md`; PHPUnit не затрагивается

**Target Platform**: браузеры с поддержкой ES2022 (Chrome/Edge/Firefox/Safari последних двух версий), экраны от 375 px; сервер — существующий Laravel-инстанс с Vite-сборкой

**Project Type**: web-application (Laravel backend + Vue SPA-фронтенд в одном репозитории)

**Performance Goals**: применение фильтров и смена страницы — видимый результат < 1 с при мок-задержке; первая пригодная для чтения страница < 2 с; начальный JS-бандл SPA ≤ 200 КБ gzip (без учёта шрифтов); отсутствие layout shift при загрузке за счёт скелетонов фиксированной высоты

**Constraints**: только JavaScript, без `.ts` и `lang="ts"`; UI строится на shadcn-vue; тёмная тема и палитра референса; Tailwind 4 без `tailwind.config.js`; `createWebHistory` (ссылки без `#`); 25 лотов на страницу; фиксированные границы диапазонов; `/admin` Orchid и `/up` не должны перехватываться SPA-маршрутами; каталог доступен без входа

**Scale/Scope**: 3 клиентских маршрута (каталог + 2 заглушки) и 1 клиентский 404; 1 экран каталога; 4 фильтра; 63 мок-фикстуры (60 публичных → 3 страницы по 25); ориентировочный объём фронтенд-кода — ~30 файлов, из них ~15 генерируются shadcn-vue

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` на момент планирования остаётся нератифицированным шаблоном (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]`, плейсхолдеры) — исполнимых ворот (gates) и обязательных принципов в нём нет. Формальные проверки по конституции не применяются.

Вместо них план проверен по действующим правилам проекта (`AGENTS.md`) и по ограничениям спецификации:

| Проверка | Статус | Обоснование |
|---|---|---|
| Следование существующей структуре репозитория | PASS | Используются `resources/js`, `resources/css`, `resources/views`, `routes/web.php`; новые базовые каталоги не создаются |
| Отсутствие изменений зависимостей без согласования | PASS (с оговоркой) | Vue, Router, Pinia, shadcn-vue и Tailwind-плагин явно затребованы заказчиком; Vitest — dev-зависимость для проверки, перечислена в `research.md` и `tasks.md` |
| Соответствие конвенциям Laravel | PASS | Маршруты объявляются именованными, отдают Blade-view; PHP-код не добавляется, кроме маршрутов |
| Отсутствие «второго» фронтенд-стека | PASS | Один Vite-билд, одна точка входа `resources/js/app.js` |
| Тестируемость | PASS | Логика фильтрации, нормализация URL и стор покрыты Vitest; UI-сценарии — чек-лист quickstart |
| Безопасность | PASS | Каталог только для чтения, без секретов; мок-авторизация не выполняет сетевых вызовов; `v-html` не используется |
| Доступность | PASS | Подписи, клавиатурное управление и видимый фокус заложены в контракт компонентов |
| Производительность | PASS | Скелетоны, отсечение устаревших запросов, ленивая загрузка маршрутов-заглушек, индивидуальные импорты shadcn-компонентов |

Нарушений, требующих раздела Complexity Tracking, нет.

## Project Structure

### Documentation (this feature)

```text
specs/003-catalog-home-page/
├── plan.md                                  # Этот файл
├── research.md                              # Phase 0: решения и альтернативы
├── data-model.md                            # Phase 1: сущности и правила
├── quickstart.md                            # Phase 1: запуск и проверка
├── contracts/
│   ├── catalog-data-contract.md             # Контракт данных каталога и сессии
│   └── ui-contract.md                       # Маршруты, query-параметры, компоненты
├── checklists/requirements.md               # Чек-лист качества спецификации
└── tasks.md                                 # Phase 2 (/speckit.tasks — не создаётся здесь)
```

### Source Code (repository root)

```text
app/                                          # без изменений (кроме маршрутов в routes/web.php)
resources/
├── css/
│   └── app.css                               # Tailwind 4 + токены shadcn-vue из референса
├── views/
│   └── app.blade.php                         # SPA-shell: <html class="dark">, #app, @vite
├── des/
│   └── sites-shop_list.html                  # визуальный референс (не изменяется)
└── js/
    ├── app.js                                # bootstrap: createApp + Pinia + Router + Toaster
    ├── App.vue                               # корневой layout: SiteHeader + <RouterView> + Toaster
    ├── router/
    │   └── index.js                          # 4 маршрута, scrollBehavior
    ├── stores/
    │   ├── catalog.js                        # применённые фильтры, страница, данные, loading/error
    │   └── session.js                        # пользователь, вход/выход, ошибка входа
    ├── composables/
    │   └── useCatalogQuery.js                # гидратация из URL и запись query
    ├── lib/
    │   ├── utils.js                          # cn()
    │   ├── catalogConfig.js                   # границы диапазонов, PAGE_SIZE, зоны по умолчанию
    │   ├── catalogFilters.js                 # нормализация/сериализация фильтров, счётчик активных
    │   └── format.js                          # рубли, числа, даты
    ├── services/
    │   ├── catalogService.js                 # fetchListings(query), fetchZones()
    │   └── sessionService.js                 # fetchSession(), login(provider), logout()
    ├── mocks/
    │   ├── listings.js                       # 63 фикстуры лотов
    │   ├── zones.js                          # fallback-набор зон при ошибке загрузки
    │   └── session.js                        # гость и демо-пользователь
    ├── components/
    │   ├── ui/                               # сгенерированные shadcn-vue компоненты
    │   ├── layout/
    │   │   └── SiteHeader.vue                # бренд, меню, кнопки входа/аватар, мобильный Sheet
    │   └── catalog/
    │       ├── CatalogFilters.vue            # панель фильтров, счётчик, «Применить»/«Сбросить все»
    │       ├── PriceRangeField.vue           # двойной Slider цены
    │       ├── IncomeRangeField.vue          # двойной Slider дохода
    │       ├── DomainZoneField.vue           # ToggleGroup доменных зон
    │       ├── VerifiedField.vue             # Switch «Проверен»
    │       ├── ListingCard.vue               # карточка лота
    │       ├── ListingList.vue               # сетка карточек и счётчик «Показано X из Y»
    │       ├── CatalogPagination.vue         # пагинация под списком
    │       ├── CatalogSkeletons.vue          # скелетоны загрузки
    │       ├── CatalogEmptyState.vue         # «Ничего не найдено» / «Предложений пока нет»
    │       └── CatalogErrorState.vue         # ошибка + «Повторить»
    └── views/
        ├── CatalogView.vue                   # главный экран
        ├── PlaceholderView.vue               # общий каркас заглушки
        ├── HowItWorksView.vue                # «Как это работает»
        ├── PricingView.vue                   # «Тарифы»
        └── NotFoundView.vue                  # клиентский 404
routes/
└── web.php                                 # именованные маршруты catalog, how-it-works, pricing
tests/
└── js/
    ├── catalog-filters-lib.spec.js
    ├── catalogService.spec.js
    ├── catalogStore.spec.js
    ├── catalogQuery.spec.js
    ├── format.spec.js
    ├── ListingCard.spec.js
    ├── ListingList.spec.js
    ├── CatalogPagination.spec.js
    ├── CatalogStates.spec.js
    ├── CatalogFilters.spec.js
    ├── SiteHeader.spec.js
    └── sessionStore.spec.js
vitest.config.js                            # jsdom + алиас @ + плагин vue
jsconfig.json                               # paths для CLI shadcn-vue и IDE
components.json                             # конфигурация shadcn-vue (typescript: false)
```

**Structure Decision**: выбран вариант «web application» внутри одного репозитория: Laravel остаётся владельцем маршрутов и Blade-shell, весь клиентский код живёт в `resources/js` согласно текущей структуре проекта. Отдельный `frontend/`-каталог не создаётся, новые базовые каталоги не появляются. Тесты фронтенда вынесены в `tests/js`, чтобы не смешиваться с PHPUnit-наборами в `tests/Feature` и `tests/Unit`.

## Phase 0: Outline & Research

Артефакт: [research.md](./research.md). Ключевые решения:

1. Vue монтируется в `app.blade.php` через единый Vite-билд с `@vitejs/plugin-vue` и алиасом `@` → `resources/js`.
2. Версии: Vue 3.5, Vue Router 4.6, Pinia 3.0, плагин Vue 6.0 — сознательный отказ от `vue-router@5`/`pinia@4` из-за peer-зависимостей на `@pinia/colada`.
3. shadcn-vue инициализируется с `"typescript": false` и `jsconfig.json`, иначе CLI генерирует TS-компоненты.
4. Тема Tailwind 4: палитра референса переносится в семантические токены shadcn-vue, приложение всегда тёмное.
5. Роутинг: `createWebHistory` + три явных Laravel-маршрута; `Route::fallback` отклонён из-за риска перехвата `/admin`.
6. Состояние: Pinia (`catalog`, `session`) + URL как источник восстановления; черновик фильтров отделён от применённого набора; счётчик запросов отсекает устаревшие ответы.
7. Данные: мок-сервис с контрактом будущего API, фикстуры 63 лотов, сценарии `empty`/`error`/`slow`/`auth-error` через `localStorage`.
8. Проверка: Vitest + `@vue/test-utils` + jsdom для логики и компонентов, ручной чек-лист для визуала, адаптивности и клавиатуры.

## Phase 1: Design & Contracts

Артефакты: [data-model.md](./data-model.md), [contracts/catalog-data-contract.md](./contracts/catalog-data-contract.md), [contracts/ui-contract.md](./contracts/ui-contract.md), [quickstart.md](./quickstart.md).

Ключевые проектные решения:

- **Сущности**: публичный лот, доменная зона, состояние фильтров (черновик + применённый набор), состояние каталога (страница, размер), результат каталога, сессия посетителя. Полные поля, правила валидации и производные значения — в `data-model.md`.
- **Контракт данных**: `fetchListings(query)` → `{ items, meta: { page, perPage, total, lastPage, from, to } }`, `fetchZones()` → `[{ value, label }]`, `fetchSession()` / `login(provider)` / `logout()`. Формат совпадает с ожидаемым HTTP-контрактом, чтобы замена мока на API была локальной.
- **UI-контракт**: маршруты `catalog`, `how-it-works`, `pricing`, `not-found`; query-параметры `price_min`, `price_max`, `income_min`, `income_max`, `zones`, `verified`, `page`; props/events компонентов; правила нормализации невалидного URL.
- **Проверка**: `quickstart.md` описывает запуск, сценарии моков и пошаговые проверки, привязанные к FR/SC.

## Post-Design Constitution Check

Повторная проверка после Phase 1: нарушений нет. Новые сущности не добавили серверного хранилища, сетевых вызовов и секретов; зависимости остались в рамках согласованного списка; структура каталогов соответствует `AGENTS.md`; тесты покрывают всю детерминированную логику. Раздел Complexity Tracking не требуется.

## Scope Decisions & Deviations

| Решение | Причина | Влияние на приёмку |
|---|---|---|
| Профиль/личный кабинет не реализуется: аватар открывает `DropdownMenu` с пунктом «Профиль» и тостом о разработке | Страница профиля вне рамок итерации; поведение зафиксировано в FR-005 и Assumptions | FR-005 выполняется в рамках итерации: кнопки входа заменяются аватаром, меню профиля открывается; переход в кабинет откладывается вместе с фичей профиля |
| «Все зоны» и выбор всех конкретных зон — разные состояния | Лот без распознанной зоны нельзя отнести ни к одной конкретной зоне (FR-011) | SC-015 проверяется в двух состояниях: пустой выбор и выбор всех зон |
| Кнопка «Связаться» показывает тост вместо сценария связи | Явное решение заказчика (clarification) | FR-029/SC-017 выполняются полностью |
| Публичный API каталога отсутствует, данные — моки | Явное решение заказчика (clarification) | SC-003 проверяется на уровне мок-сервиса (публичная видимость фильтруется при выдаче) |
| Доменная зона приходит готовым полем `domainZone` от API | Способ хранения — предмет фичи API | Фронтенд не парсит домен и не ломается при неизвестной зоне |
| Серверный deep link на неизвестный путь отдаёт 404 Laravel | Отказ от `Route::fallback` ради безопасности `/admin` | Внутри SPA неизвестные пути показывают клиентский 404; deep link на 4 известных пути работает |
| Серверный рендеринг и SEO отсутствуют | Вне рамок спецификации | Мета-теги задаются статически в shell-шаблоне |

## Risks & Mitigations

| Риск | Влияние | Смягчение |
|---|---|---|
| CLI shadcn-vue неверно определит JS-проект и сгенерирует TS | Сборка упадёт на `lang="ts"` | `"typescript": false` в `components.json` + `jsconfig.json`; после генерации проверяется отсутствие `lang="ts"` и `npm run build` |
| `@vitejs/plugin-vue` конфликтует с Vite 8/плагином Laravel | Сборка не стартует | Плагин 6.0.10 официально поддерживает Vite 8; порядок `laravel() → vue() → tailwindcss()`; проверка `npm run build` в первой задаче |
| SPA-маршруты перехватывают Orchid `/admin` | Ломается админка | Только три явных маршрута, без catch-all; smoke-проверка `/admin` в quickstart |
| Тема shadcn-vue расходится с референсом | SC-011 не выполняется | Палитра переносится таблицей токенов из `research.md`; визуальная сверка на 1440 px и 375 px |
| Слайдер с двумя ручками даёт невалидный диапазон | Некорректная фильтрация | Нормализация в `catalogFilters.js` (min ≤ max, clamp по границам) + юнит-тесты |
| Мок-задержки скрывают гонку ответов | Устаревший ответ перезаписывает новый | Счётчик `requestId` в сторе + тест «медленный первый, быстрый второй» |
| Добавление зависимостей без явного согласования | Нарушение правил проекта | Список зависимостей зафиксирован в `research.md`; все пункты либо затребованы заказчиком, либо являются dev-инструментами проверки |

## Complexity Tracking

> Заполняется только при нарушениях Constitution Check. Нарушений нет — таблица не требуется.
