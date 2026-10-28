---

description: "Task list for feature implementation"
---

# Tasks: Публичная главная страница с каталогом сайтов

**Input**: Design documents from `/specs/003-catalog-home-page/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/`, `quickstart.md`

**Tests**: включены — стратегия проверки зафиксирована в `plan.md` и `research.md` (Vitest 5 + `@vue/test-utils` 2.5 + jsdom 30). Тестовые задачи пишутся **до** реализации соответствующего кода и на первом прогоне должны падать.

**Organization**: задачи сгруппированы по пользовательским историям; каждая история даёт независимо проверяемый инкремент. Фазы 1–2 создают собираемую и запускаемую базу, после чего истории можно вести параллельно.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (разные файлы, нет зависимостей от незавершённых задач)
- **[Story]**: к какой истории относится задача (`US1`…`US4`); в фазах Setup, Foundational и Polish метки нет
- Пути к файлам указаны от корня репозитория

## Path Conventions

Проект — Laravel + Vue SPA в одном репозитории: клиентский код в `resources/js`, стили в `resources/css`, Blade-шаблоны в `resources/views`, маршруты в `routes/web.php`, тесты фронтенда в `tests/js`. Структура зафиксирована в `plan.md` → Project Structure.

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: подключить Vue-стек, shadcn-vue, Tailwind-тему и минимальную собираемую SPA-цепочку `app.blade.php → app.js → App.vue → router → CatalogView`.

- [X] T001 Установить runtime-зависимости в `package.json`: `npm install vue@^3.5 vue-router@^4.6 pinia@^3.0`
- [X] T002 Установить dev-зависимости в `package.json`: `npm install -D @vitejs/plugin-vue@^6.0 vitest@^5 @vue/test-utils@^2.5 jsdom@^30`
- [X] T003 [P] Обновить `vite.config.js`: подключить `@vitejs/plugin-vue` после `laravel()` и перед `tailwindcss()`, добавить `resolve.alias` `@` → `fileURLToPath(new URL('./resources/js', import.meta.url))`, добавить `bunny('Inter', { weights: [400, 500, 600, 700, 800] })` рядом с существующим Instrument Sans
- [X] T004 [P] Создать `jsconfig.json` с `baseUrl: "."` и `compilerOptions.paths: { "@/*": ["./resources/js/*"] }` — без него CLI shadcn-vue и IDE не разрешают алиас `@`
- [X] T005 Создать `components.json` для shadcn-vue: `"typescript": false`, `"tailwind": { "css": "resources/css/app.css", "baseColor": "neutral", "cssVariables": true }`, алиасы `components` → `@/components`, `ui` → `@/components/ui`, `utils` → `@/lib/utils`, `lib` → `@/lib`, `composables` → `@/composables`
- [X] T006 Добавить компоненты shadcn-vue: `npx shadcn-vue@latest add button card slider switch label badge skeleton pagination toggle-group avatar dropdown-menu sheet separator alert sonner tooltip`; проверить отсутствие `lang="ts"` в `resources/js/components/ui/**`, наличие `resources/js/lib/utils.js` с `cn()` и экспорт `Toaster` из `resources/js/components/ui/sonner`
- [X] T007 [P] Переписать `resources/css/app.css`: `@import 'tailwindcss'`, `@custom-variant dark (&:is(.dark *))`, шрифт `--font-sans: 'Inter', ...`, CSS-переменные shadcn-vue в `:root` по таблице токенов из `research.md` §4 (`--background: #191a1c`, `--foreground: #e8e9e2`, `--card: #1f2022`, `--popover/--secondary: #26272a`, `--border: #2d2e30`, `--input: #3c3d40`, `--muted-foreground: #a8a9a3`, `--primary/--ring: #b9c94a`, `--primary-foreground: #0f1011`, `--destructive: #c5482a`), блок `@theme inline` с маппингом `--color-background: var(--background)` и остальных токенов shadcn-vue, брендовые токены в `@theme` (`--color-lime`, `--color-lime-dim`, `--color-surface-0/1/2`, `--color-verified`, `--color-danger`)
- [X] T008 [P] Создать `resources/views/app.blade.php`: `<html lang="ru" class="dark">`, `<meta charset>`, `<meta name="viewport" content="width=device-width, initial-scale=1">`, `<title>`, `<div id="app"></div>`, `@vite(['resources/css/app.css', 'resources/js/app.js'])`
- [X] T009 Создать `resources/js/App.vue`: импорт `'vue-sonner/style.css'`, `<RouterView />` и `<Toaster />` из `@/components/ui/sonner`, корневой контейнер `min-h-screen bg-background text-foreground`; `SiteHeader` на этом шаге **не** добавляется (это US3)
- [X] T010 [P] Создать временный `resources/js/views/CatalogView.vue`: заголовок «Каталог готовых сайтов» и контейнер `id="catalog-list"`; логика и панель фильтров появятся в US1
- [X] T011 Создать минимальный `resources/js/router/index.js`: `createWebHistory()` и единственный маршрут `catalog` (`/`, `views/CatalogView.vue`, `meta: { navKey: 'catalog', title: 'Каталог готовых сайтов' }`), `scrollBehavior` с `savedPosition ?? { top: 0 }`; маршруты `how-it-works`, `pricing` и `not-found` добавляются в US3 после создания их представлений
- [X] T012 Создать `resources/js/app.js`: `createApp(App)`, `createPinia()`, `router`, монтирование в `#app`
- [X] T013 [P] Настроить тесты: добавить в `package.json` скрипты `"test": "vitest run"` и `"test:watch": "vitest"`; создать `vitest.config.js` с `@vitejs/plugin-vue`, `environment: 'jsdom'`, `include: ['tests/js/**/*.spec.js']` и алиасом `@` → `./resources/js`

**Checkpoint**: `npm run build` проходит; `npx vitest run --passWithNoTests` завершается с кодом 0 (тестов ещё нет); `/` открывает SPA-shell и рендерит `CatalogView`; в роутере пока только маршрут `catalog`.

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: конфигурация, форматирование, фикстуры, сервисы, стор каталога и сессионный стор — общая база для всех историй.

**⚠️ CRITICAL**: ни одна пользовательская история не начинается до завершения этой фазы.

- [X] T014 [P] Создать `resources/js/lib/catalogConfig.js`: `PAGE_SIZE = 25`, `PRICE_BOUNDS = { min: 0, max: 10_000_000, step: 50_000 }`, `INCOME_BOUNDS = { min: 0, max: 1_000_000, step: 5_000 }`, `DEFAULT_SORT = 'newest'`, `MOCK_LATENCY_MS = 250`, `SLOW_LATENCY_MS = 2000`, `MAX_PAGE_LINKS = 5`, `DEFAULT_ZONES = ['ru', 'com', 'de']`
- [X] T015 [P] Создать `tests/js/format.spec.js`: нулевая цена, отрицательное значение, `null`, `undefined`, `NaN`, `18 000 000`, проверка неразрывных пробелов и суффиксов `₽` / `₽/мес`, формат даты `ДД.ММ.ГГГГ`
- [X] T016 [P] Создать `resources/js/lib/format.js`: `formatRub(value)` → `«1 200 000 ₽»`, `formatRubMonthly(value)` → `«85 000 ₽/мес»`, `formatCount`, `formatDate(iso)` → `«21.10.2026»`; `null`/`undefined`/`NaN`/отрицательные значения трактуются как `0`; используется `Intl.NumberFormat('ru-RU')` без компактной нотации
- [X] T017 [P] Создать `tests/js/catalog-filters-lib.spec.js` (имя файла отличается от компонентного `CatalogFilters.spec.js`): `price_min=abc`, `price_min=-5`, `price_max=99999999`, `min > max`, `zones=ru,xx`, пустой `zones`, `zones=ru,com,de`, `verified=0`/`verified=yes`, `page=0`/`page=x` → `1`; сериализация не пишет значения по умолчанию и сортирует зоны как `ru,com,de`; `countActiveFilters` даёт `0…4`
- [X] T018 [P] Создать `resources/js/lib/catalogFilters.js`: `createDefaultFilters()` (цена `0…10 000 000`, доход `0…1 000 000`, `zones: []`, `verifiedOnly: false`), `normalizeFilters(raw, zones)` (границы по настройкам, `min ⩽ max`, иначе обе границы по умолчанию; неизвестные зоны отбрасываются; `min > bounds.max` → clamp), `parseFiltersFromQuery(query, zones)`, `serializeFiltersToQuery(filters, page)` (значения по умолчанию не пишутся), `normalizePage(raw)`, `countActiveFilters(filters)` (цена активна при `priceMin !== 0 || priceMax !== 10_000_000`, доход — аналогично, зоны — `zones.length > 0`, проверка — `verifiedOnly === true`)
- [X] T019 [P] Создать `resources/js/mocks/zones.js` — **только fallback-набор**, не источник списка зон: `[{ value: 'ru', label: '.ru' }, { value: 'com', label: '.com' }, { value: 'de', label: '.de' }]`; `value` — латиница в нижнем регистре без точки, `label` — с ведущей точкой
- [X] T020 [P] Создать `resources/js/mocks/listings.js`: 63 фикстуры, из них 60 публичных и 3 скрытых (`isPublic: false`); публичные: 24 `.ru`, 21 `.com`, 12 `.de`, 3 с `domainZone: null`; 38 `isVerified: true` и 25 `false` во всём наборе; `price` от `0` до `18 000 000` (2 лота выше `10 000 000`), `monthlyIncome` от `0` до `1 500 000` (2 лота выше `1 000 000`); 2 лота без `description` и 1 с пустой строкой, 4 без `faviconUrl`, 1 с длинными `title`/`description`; `publishedAt` равномерно за 120 дней; у публичных `status: 'active'`; инварианты: `title` 1…120 символов, `description` до 300 символов или `null`, `price ≥ 0`, `monthlyIncome ≥ 0`, `domain` уникален, `id` уникален
- [X] T021 Создать `resources/js/services/catalogService.js` — `fetchListings(query)` и `fetchZones()` по контракту `contracts/catalog-data-contract.md`: нормализация query (числовые границы и страница через `catalogFilters.normalizeFilters`, зоны — по списку, выведенному из публичных фикстур, чтобы новые зоны не отбрасывались), фильтрация до пользовательских фильтров (`isPublic`, `status === 'active'`), цена `price_min ⩽ price ⩽ price_max` с исключением верхней границы при `priceMax === 10_000_000` («и выше»), доход аналогично при `incomeMax === 1_000_000`, зоны «ИЛИ» внутри и «И» с остальными, `verifiedOnly`, сортировка `publishedAt` desc + `id` asc, `slice((page - 1) * 25, page * 25)`, `lastPage = max(1, ceil(total / 25))`, `from`/`to` = `null` при пустой странице; задержка `MOCK_LATENCY_MS`, сценарий `slow` → `SLOW_LATENCY_MS`; сценарии `localStorage['catalog:mock-scenario']` = `empty` / `error` / `slow`; внутренний `deriveZones()` используется и для нормализации, и для `fetchZones()`: зоны строятся из публичных фикстур (24 `.ru`, 21 `.com`, 12 `.de`), `domainZone: null` исключается, `count` считается, порядок: известные `ru`, `com`, `de`, затем новые по алфавиту; сервис возвращает копии объектов
- [X] T022 [P] Создать `tests/js/catalogService.spec.js`: только публичные лоты в выдаче; `total` не учитывает скрытые; границы цены и дохода включительные; `priceMax = 10 000 000` возвращает лот за `18 000 000`; `incomeMax = 1 000 000` возвращает лот с доходом `1 500 000`; фильтр зон «ИЛИ» и `null`-зоны только при пустом `zones`; `verifiedOnly`; сортировка; 25 на страницу; отсутствие дублей между страницами 1–3; `fetchZones()` возвращает `ru: 24`, `com: 21`, `de: 12`, сумма `57` из `60` и не содержит `null`-зону; сценарии `empty` и `error`; иммутабельность фикстур
- [X] T023 [P] Создать `resources/js/mocks/session.js` и `resources/js/services/sessionService.js`: `fetchSession()` (валидный `localStorage['catalog:session']` → пользователь, повреждённый JSON или отсутствующий `id` → удалить запись и вернуть гостя), `login(provider)` (задержка 600 мс, успех с `{ id, displayName, avatarUrl: null, provider }`, сценарий `auth-error` → reject с текстом «Не удалось войти через VK ID. Попробуйте ещё раз.» / «…через Яндекс ID…»), `logout()` (очищает `localStorage` и стор)
- [X] T024 Создать `resources/js/stores/catalog.js` (Pinia): состояние `applied`, `draft`, `page`, `perPage` (`25`), `items`, `total`, `lastPage`, `status` (`idle`/`loading`/`success`/`error`), `error`, `requestId`; `load()` инкрементирует `requestId` и применяет ответ только при совпадении; `applyFilters(filters)` копирует в `applied`, ставит `page = 1`, вызывает `load()`; `hydrateFilters(filters)` записывает `applied` и `draft` без запроса (для гидратации из URL); `resetFilters()` возвращает значения по умолчанию и `page = 1`; `setDraft`; `retry()`; `goToPage(page)` клампит `1 ⩽ page ⩽ lastPage`, ставит страницу и вызывает `load()`; `setPageFromUrl(page)` нормализует целое `≥ 1`, ставит страницу, вызывает `load()` и возвращает `Promise<{ outOfRange: boolean, lastPage: number }>` — при `total > 0 && page > lastPage` флаг `true`, `items` пустой, ошибка не выставляется; ошибка загрузки не меняет `applied`/`draft`
- [X] T025 [P] Создать `tests/js/catalogStore.spec.js`: `applyFilters` сбрасывает страницу в `1`; `resetFilters` обнуляет счётчик; устаревший ответ (медленный первый, быстрый второй) не перезаписывает `items`; при `total = 0` состояние `success`; ошибка сохраняет фильтры и `draft`; `goToPage(0)` → `1`, `goToPage(999)` → `lastPage`; `setPageFromUrl(999)` возвращает `outOfRange: true` и оставляет `items` пустым; `setPageFromUrl(2)` возвращает `outOfRange: false`
- [X] T026 [P] Создать `resources/js/stores/session.js` (Pinia): `user`, `isAuthenticated`, `isLoggingIn`, `authError`, `initialize()` → `fetchSession()`, `loginWithVk()`, `loginWithYandex()`, `logout()`; повторный вызов при `isLoggingIn === true` игнорируется; ошибка не затрагивает `catalog`-стор
- [X] T027 Добавить в `routes/web.php` именованный маршрут `catalog` (`GET /`) → `view('app')`; **не добавлять** `Route::fallback` и catch-all маршруты (риск перехвата `/admin`, `/up`, `/api/*`); маршруты `how-it-works` и `pricing` добавляются в US3 после создания их представлений

**Checkpoint**: `/` открывает SPA-shell, минимальный роутер рендерит `CatalogView`; юнит-тесты `format`, `catalog-filters-lib`, `catalogService`, `catalogStore` зелёные; Laravel отдаёт shell только по `/`.

---

## Phase 3: User Story 1 — Подбор сайта через фильтры (Priority: P1) 🎯 MVP

**Goal**: панель из четырёх фильтров над списком, черновик до «Применить», счётчик активных фильтров и «Сбросить все»; результат фильтрации виден в списке карточек.

**Independent Test**: открыть `/` без входа, поочерёдно и в комбинации применить цену, зоны, доход и «Проверен» — в выдаче только подходящие лоты, счётчик совпадает с числом изменённых фильтров; «Сбросить все» возвращает полный список с первой страницы; лоты без распознанной зоны видны только при «Все зоны».

### Tests for User Story 1 ⚠️

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation.**

- [X] T028 [P] [US1] Создать `tests/js/CatalogFilters.spec.js`: счётчик скрыт при `0` и показывает фактическое число; изменение черновика не эмитит `apply`; «Применить» эмитит текущий черновик; «Сбросить все» эмитит `reset` и при отсутствии активных фильтров кнопка `disabled`; применение внешнего `applied` перезаписывает черновик; рендерятся четыре поля, зоны берутся из prop `zones`
- [X] T029 [P] [US1] Создать `tests/js/ListingCard.spec.js`: все обязательные поля отображаются; нулевая цена и доход → «0 ₽» / «0 ₽/мес»; отсутствие описания не ломает разметку; при `faviconUrl === null` показывается первая буква домена; `title`/`description` обрезаются классами `line-clamp-2`; статус и проверка читаются текстом; клик по «Связаться» эмитит `contact` и не вызывает навигацию

### Implementation for User Story 1

- [X] T030 [P] [US1] Создать `resources/js/components/catalog/PriceRangeField.vue`: `Slider` shadcn-vue с двумя `SliderThumb`, props `modelValue: [number, number]`, `min`, `max`, `step`, `label`; `aria-label` «Минимальная цена» / «Максимальная цена», `aria-valuetext` через `formatRub`; подписи значений под ползунком; при `max === PRICE_BOUNDS.max` к правому значению добавляется «и выше»; ручки не пересекаются
- [X] T031 [P] [US1] Создать `resources/js/components/catalog/IncomeRangeField.vue` по образцу `PriceRangeField.vue` с `INCOME_BOUNDS`, подписью «Доход, ₽/мес», `aria-label` «Минимальный доход» / «Максимальный доход» и суффиксом «и выше» при `max === INCOME_BOUNDS.max`
- [X] T032 [P] [US1] Создать `resources/js/components/catalog/DomainZoneField.vue`: `ToggleGroup type="multiple"` с кнопкой «Все зоны» (активна при пустом массиве) и кнопками по prop `zones` с подписями из `zone.label`; emit `update:modelValue`; `Skeleton` при `loading`; при нажатии «Все зоны» массив очищается
- [X] T033 [P] [US1] Создать `resources/js/components/catalog/VerifiedField.vue`: `Switch` + `Label` «Проверен» со связкой `id`/`for`, по умолчанию `false`, клик по подписи переключает
- [X] T034 [US1] Создать `resources/js/components/catalog/ListingCard.vue` сразу в полном объёме `contracts/ui-contract.md` §4.6 (без последующей переписи в US2): иконка-заглушка по первой букве домена при `faviconUrl === null`, `title` (2 строки), домен + зона (`truncate`, `title`-атрибут), описание (`line-clamp-2`), цена (`formatRub`), доход (`formatRubMonthly`), статус «Активен» (`Badge`), признак проверки текстом «Да»/«Нет», кнопка «Связаться»; emit `contact`; hover `border-primary/40` + `bg-secondary`, видимый `focus-visible`
- [X] T035 [US1] Создать `resources/js/components/catalog/ListingList.vue`: props `items`, `total`; сетка карточек с `key = listing.id`, `id="catalog-list"` и `scroll-mt-24` (компенсация липкой шапки, US3), emit `contact`; счётчик «Показано X из Y» и `aria-busy` добавляются в US2
- [X] T036 [US1] Создать `resources/js/components/catalog/CatalogFilters.vue`: props `applied`, `zones`, `zonesLoading`, `activeCount`; внутренний `draft`, пересоздаваемый при изменении `applied`; сетка 4/2/1 колонки; заголовок «Фильтры» + бейдж «N активных» (скрыт при `0`); «Сбросить все» (`disabled` при `activeCount === 0`) и «Применить»; emit `apply` (черновик) и `reset`; использует `PriceRangeField`, `IncomeRangeField`, `DomainZoneField`, `VerifiedField`
- [X] T037 [US1] Заменить временный `resources/js/views/CatalogView.vue` на рабочий экран: `CatalogFilters` + `ListingList`, подключение `catalog`-стора (`load` при монтировании, `applyFilters`, `resetFilters`), загрузка зон через `fetchZones()` с fallback на `DEFAULT_ZONES.map(value => ({ value, label: '.' + value, count: null }))` при ошибке и флагом `zonesLoading`; передача `activeCount` из `countActiveFilters(applied)`; простое состояние «Загрузка…»; тост по `contact` на этом шаге не добавляется (US2)

**Checkpoint**: фильтры применяются кнопкой, счётчик и сброс работают, выдача соответствует условиям; карточки отображают полный набор полей; лоты без зоны видны только при «Все зоны» (юнит-тесты + ручная проверка `quickstart.md` §6.2).

---

## Phase 4: User Story 2 — Просмотр списка лотов и переход по страницам (Priority: P1)

**Goal**: счётчик «Показано X из Y», пагинация под списком по 25 записей, корректные состояния загрузки, ошибки и пустого результата, тост по кнопке «Связаться».

**Independent Test**: при 60 публичных лотах на странице не более 25 карточек, «Показано 25 из 60», пагинация даёт переходы без дублей и пропусков, крайние переходы недоступны; карточка выдерживает нулевые значения, отсутствие описания и иконки; «Связаться» показывает тост без навигации.

### Tests for User Story 2 ⚠️

- [X] T038 [P] [US2] Создать `tests/js/CatalogPagination.spec.js`: при `lastPage === 1` компонент не рендерится; «Назад» недоступна на первой странице, «Вперёд» — на последней; `aria-current="page"` на текущей; клик эмитит `update:page`; эллипсис при большом числе страниц; `nav aria-label="Пагинация каталога"`
- [X] T039 [P] [US2] Создать `tests/js/ListingList.spec.js`: счётчик «Показано X из Y» использует `items.length` и `total`; `aria-busy="true"` при `loading`; карточки рендерятся по `id`; контейнер имеет `id="catalog-list"`
- [X] T040 [P] [US2] Создать `tests/js/CatalogStates.spec.js`: `CatalogEmptyState` показывает «Предложений пока нет» без кнопки и «Ничего не найдено» с кнопкой сброса; `CatalogErrorState` показывает сообщение и эмитит `retry`/`reset`

### Implementation for User Story 2

- [X] T041 [P] [US2] Создать `resources/js/components/catalog/CatalogPagination.vue`: `Pagination`/`PaginationContent`/`PaginationItem`/`PaginationLink`/`PaginationPrevious`/`PaginationNext`/`PaginationEllipsis`; props `page`, `lastPage`; окно до 5 номеров, крайние страницы, эллипсисы; `aria-current="page"`; `disabled`/`aria-disabled` вне диапазона; emit `update:page`; не рендерится при `lastPage === 1`; цели не меньше 40×40 px
- [X] T042 [P] [US2] Создать `resources/js/components/catalog/CatalogSkeletons.vue`: 6 `Skeleton`-карточек с высотой, совпадающей с реальной карточкой (без layout shift); prop `count = 6`
- [X] T043 [P] [US2] Создать `resources/js/components/catalog/CatalogEmptyState.vue`: props `title`, `description`, `showReset`, `resetLabel`; варианты «Предложений пока нет» (без кнопки) и «Ничего не найдено» (кнопка «Сбросить все» → emit `reset`)
- [X] T044 [P] [US2] Создать `resources/js/components/catalog/CatalogErrorState.vue`: prop `message`; кнопки «Повторить» (emit `retry`) и «Сбросить все» (emit `reset`); используется `Alert`
- [X] T045 [US2] Обновить `resources/js/components/catalog/ListingList.vue`: добавить prop `loading`, счётчик «Показано X из Y» (`items.length` от `total`), `aria-busy` при загрузке; сохранить `id="catalog-list"` и `scroll-mt-24`
- [X] T046 [US2] Собрать состояния в `resources/js/views/CatalogView.vue`: `status === 'loading'` и пустые `items` → `CatalogSkeletons`; загрузка с прежними `items` → список + `aria-busy`; `error` → `CatalogErrorState`; `total === 0` без активных фильтров → «Предложений пока нет»; `total === 0` с фильтрами → «Ничего не найдено» + сброс; успех → список, счётчик, `CatalogPagination`
- [X] T047 [US2] Подключить смену страницы и «Связаться» в `resources/js/views/CatalogView.vue`: обработчик `update:page` → `catalog.goToPage()` с сохранением фильтров и прокруткой к `#catalog-list` (`scrollIntoView({ behavior: 'smooth', block: 'start' })`); тост по `contact` «Связь с продавцом появится в следующей версии» без изменения URL, данных и фильтров

**Checkpoint**: US1 и US2 работают совместно: фильтры сужают выдачу, пагинация листает результат, состояния и тосты соответствуют спецификации.

---

## Phase 5: User Story 3 — Шапка с меню и входом через VK ID или Yandex ID (Priority: P2)

**Goal**: шапка с брендом, меню ровно из трёх пунктов, кнопками входа по референсу, аватаром после входа и страницами-заглушками.

**Independent Test**: на `/` видны бренд, три пункта меню (без «Помощь»), две кнопки входа; вход через VK ID и Yandex ID приводит к аватару, выход возвращает кнопки; «Как это работает» и «Тарифы» открывают заглушки с активным пунктом меню; на 375 px нет горизонтальной прокрутки.

### Tests for User Story 3 ⚠️

- [X] T048 [P] [US3] Создать `tests/js/SiteHeader.spec.js`: ровно три пункта меню и отсутствие «Помощь»; активный пункт определяется по `meta.navKey`; гость видит две кнопки входа, авторизованный — `Avatar` и пункты «Профиль»/«Выйти»; клик по кнопкам вызывает `loginWithVk`/`loginWithYandex`; «Профиль» показывает сообщение и не выполняет переход
- [X] T049 [P] [US3] Создать `tests/js/sessionStore.spec.js`: `initialize()` при повреждённом `localStorage['catalog:session']` удаляет запись и возвращает гостя; `auth-error` выставляет `authError`, не меняет `isAuthenticated` и не затрагивает `catalog`-стор (применённые фильтры и страница сохраняются); повторный `loginWithVk` при `isLoggingIn === true` игнорируется; `logout()` очищает пользователя и `localStorage`

### Implementation for User Story 3

- [X] T050 [P] [US3] Создать `resources/js/views/PlaceholderView.vue`: props `title`, `message`; ссылка «Вернуться в каталог» → `{ name: 'catalog' }`; центрированный блок с общей шапкой
- [X] T051 [US3] Заполнить `resources/js/views/HowItWorksView.vue` и `resources/js/views/PricingView.vue`: `PlaceholderView` с заголовками «Как это работает» / «Тарифы» и текстом «Раздел в разработке. Скоро здесь появится подробная информация.»
- [X] T052 [P] [US3] Создать `resources/js/views/NotFoundView.vue`: заголовок «Страница не найдена», пояснение и ссылка «Вернуться в каталог»; шапка сохраняется, ни один пункт меню не активен
- [X] T053 [US3] Расширить `resources/js/router/index.js` **после** создания представлений (T050–T052): добавить `how-it-works` (`/how-it-works`, lazy), `pricing` (`/pricing`, lazy), `not-found` (`/:pathMatch(.*)*`); `meta.navKey`/`meta.title` по `contracts/ui-contract.md` §1.2; `router.afterEach` обновляет `document.title`
- [X] T054 [US3] Добавить в `routes/web.php` именованные маршруты `how-it-works` (`GET /how-it-works`) и `pricing` (`GET /pricing`), каждый возвращает `view('app')`; **не добавлять** catch-all и `Route::fallback`
- [X] T055 [US3] Создать `resources/js/components/layout/SiteHeader.vue`: бренд (логотип «S» + «Sites-shop» с лаймовым «shop» + подпись «Маркетплейс готовых сайтов»), меню ровно из трёх `RouterLink` с определением активного пункта по `route.meta.navKey`, `sticky top-0 z-40`, `bg-background/90 backdrop-blur`
- [X] T056 [US3] Добавить в `resources/js/components/layout/SiteHeader.vue` кнопки входа: SVG-логотипы VK (`#0077FF`) и Yandex (`#f8604a`) из `/home/kovaleff/www/cheap-eats/resources/js/components/HomeView.vue`, тексты «Войти через VK ID» и «Войти через Яндекс ID», обработчики `loginWithVk`/`loginWithYandex`, блокировка на время `isLoggingIn`, тост по `authError`
- [X] T057 [US3] Добавить в `resources/js/components/layout/SiteHeader.vue` состояние авторизованного пользователя: `Avatar` (инициалы при `avatarUrl === null`), `DropdownMenu` с пунктами «Профиль» (тост о разработке, без навигации) и «Выйти» (`logout`)
- [X] T058 [US3] Добавить в `resources/js/components/layout/SiteHeader.vue` мобильный режим `< md`: меню в `Sheet` по кнопке с `aria-label="Открыть меню"`, кнопки входа только с иконками и `aria-label`, отсутствие горизонтальной прокрутки
- [X] T059 [US3] Подключить шапку в `resources/js/App.vue`: `<SiteHeader />` перед `<RouterView />`, сохранить `<Toaster />` и корневой контейнер
- [X] T060 [US3] Инициализировать сессию в `resources/js/app.js` (или `App.vue`): вызвать `session.initialize()` до первого рендера шапки, чтобы после F5 сразу показывался аватар

**Checkpoint**: шапка и вход работают независимо от фильтров; заглушки открываются по прямой ссылке с активным пунктом меню; `/admin` не перехвачен.

---

## Phase 6: User Story 4 — Возврат к тому же набору фильтров (Priority: P3)

**Goal**: применённые фильтры и номер страницы живут в URL, восстанавливаются после F5, шаринга и переходов «назад»/«вперёд»; некорректные параметры нормализуются без ошибки; переход в заглушки и обратно не теряет набор фильтров.

**Independent Test**: применить фильтры и перейти на 2-ю страницу, обновить страницу, открыть адрес в новой вкладке, использовать «назад»/«вперёд» — состояние совпадает; `?price_min=abc&page=-7&zones=ru,xx` не вызывает ошибку; `?page=999` показывает последнюю доступную страницу; после «Тарифов» и возврата фильтры сохраняются.

### Tests for User Story 4 ⚠️

- [X] T061 [P] [US4] Создать `tests/js/catalogQuery.spec.js`: `hydrateFromRoute` разбирает query в стор и игнорирует неизвестные параметры; `pushFilters` пишет нормализованный query через `push`, `clearFilters` — пустой query, `replacePage` использует `replace`; изменение `route.query` (назад/вперёд) гидрирует состояние ровно один раз и не дублирует запрос при совпадении query; `page=999` при `lastPage=3` требует замены на `page=3` без ошибки; переход `/?price_min=…` → `/pricing?price_min=…` → `/ ?price_min=…` сохраняет query и не сбрасывает стор

### Implementation for User Story 4

- [X] T062 [US4] Создать `resources/js/composables/useCatalogQuery.js`: `hydrateFromRoute(route)` (разбор через `parseFiltersFromQuery`, запись `applied`/`draft` через `catalog.hydrateFilters(filters)`, вызов `setPageFromUrl`, возврат `{ outOfRange, lastPage }`), `pushFilters(filters, page)` и `clearFilters()` через `router.push`, `replacePage(page)` через `router.replace`, наблюдение за `route.query` с защитой от повторной гидратации при совпадении сериализованного состояния
- [X] T063 [US4] Подключить `useCatalogQuery` в `resources/js/views/CatalogView.vue`: гидратация при монтировании вместо прямого `load()`, синхронизация при изменении `route.query`; «Применить»/«Сбросить все»/смена страницы пишут URL через композабл; изменения черновика URL не трогают; избегать двойной загрузки
- [X] T064 [US4] Обработать страницу за пределами диапазона в `resources/js/views/CatalogView.vue`: если `hydrateFromRoute` вернул `outOfRange: true`, выполнить `router.replace` на `lastPage` без показа ошибки и без изменения фильтров; после замены показать последнюю доступную страницу
- [X] T065 [US4] Сохранить состояние при переходе в заглушки: в `resources/js/components/layout/SiteHeader.vue` ссылки меню «Каталог», «Как это работает» и «Тарифы» получают `query: route.query`, когда `route.meta.navKey !== null`; в `resources/js/views/PlaceholderView.vue` ссылка «Вернуться в каталог» использует `{ name: 'catalog', query: route.query }`; поведение покрыто тестом T061

**Checkpoint**: все четыре истории работают совместно; ссылка полностью воспроизводит состояние, история браузера корректна, возврат из заглушек не сбрасывает фильтры.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: доступность, адаптивность, визуальное соответствие, регрессии и финальная проверка.

- [X] T066 [P] Провести проверку доступности по `contracts/ui-contract.md` §5 в `resources/js/components/catalog/PriceRangeField.vue`, `resources/js/components/catalog/IncomeRangeField.vue`, `resources/js/components/catalog/DomainZoneField.vue`, `resources/js/components/catalog/VerifiedField.vue`, `resources/js/components/catalog/CatalogPagination.vue` и `resources/js/components/layout/SiteHeader.vue`: `aria-label`/`aria-valuetext` у всех ручек слайдеров, связка `Label`/`Switch`, `aria-current="page"` в пагинации, `aria-live` у тостов, видимые `focus-visible`-кольца во всех интерактивных элементах
- [X] T067 [P] Проверить адаптивность на 375 / 768 / 1440 px в `resources/js/views/CatalogView.vue`, `resources/js/components/layout/SiteHeader.vue` и `resources/js/components/catalog/ListingCard.vue`: отсутствие горизонтальной прокрутки, сетка фильтров 1/2/4 колонки, карточки-стопка на мобильном, цели не меньше 40×40 px
- [X] T068 [P] Сверить визуал `resources/css/app.css` и `resources/js/components/catalog/ListingCard.vue` с `resources/des/sites-shop_list.html`: палитра, лаймовый акцент, карточный список, типографика Inter, hover-состояния (SC-011)
- [X] T069 [P] Выполнить `npm run test` по `tests/js/**/*.spec.js` и убедиться, что все тесты зелёные; устранить предупреждения Vue в консоли при рендере `resources/js/**/*.vue`
- [X] T070 Выполнить `npm run build` и проверить `public/build/manifest.json` на наличие `resources/js/app.js` и `resources/css/app.css`; размер JS-бандла ≤ 200 КБ gzip
- [X] T071 Пройти `specs/003-catalog-home-page/quickstart.md` §6.1–6.6 и отдельно зафиксировать замеры SC-001 (применение фильтра < 1 с) и SC-002 (первая пригодная для чтения страница < 2 с) через Performance-панель браузера или `performance.now()` вокруг действия; записать результат в отчёт проверки
- [X] T072 Проверить регрессию `routes/web.php` и `resources/js/router/index.js`: `/admin` открывает Orchid, `/up` отвечает, `/how-it-works` и `/pricing` работают по прямой ссылке, `/` не перехватывает `/api/*`
- [X] T073 Запустить `vendor/bin/pint --dirty --format agent` (изменён `routes/web.php`) и `php artisan test --compact`
- [X] T074 Удалить неиспользуемые импорты и компоненты, проверить отсутствие `console.log` и `lang="ts"` в `resources/js/**/*.{js,vue}`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: без зависимостей, стартует сразу; заканчивается собираемой цепочкой `app.blade.php → app.js → App.vue → router → CatalogView`
- **Foundational (Phase 2)**: зависит от Setup — блокирует все истории
- **US1 (Phase 3)**: после Foundational
- **US2 (Phase 4)**: после Foundational; на практике опирается на `ListingCard`/`ListingList`/`CatalogView` из US1, поэтому выполняется после Phase 3
- **US3 (Phase 5)**: после Foundational; не зависит от US1/US2, правит `App.vue` (T059) и `SiteHeader.vue`; `CatalogView.vue` не пересекается
- **US4 (Phase 6)**: после US1 и US2 (нужны фильтры и пагинация в UI) и после US3 (T065 правит `SiteHeader`/`PlaceholderView` для round-trip)
- **Polish (Phase 7)**: после всех выбранных историй

### User Story Dependencies

| История | Зависит от | Может проверяться независимо |
|---|---|---|
| US1 (P1) | Phase 2 | Да — панель фильтров, счётчик, сброс, сужение выдачи, карточки |
| US2 (P1) | Phase 2, компоненты витрины из US1 | Да — счётчик, пагинация, состояния, тост «Связаться» |
| US3 (P2) | Phase 2 | Да — шапка, вход/аватар, заглушки, 404 |
| US4 (P3) | US1, US2, US3 | Да — URL, история, восстановление после F5, round-trip через заглушки |

### Внутри каждой истории

1. Тесты пишутся и **падают** до реализации
2. Сначала библиотеки/конфигурация, затем компоненты, затем сборка во view
3. Стори подключаются к UI после того, как покрыты юнит-тестами
4. Внутри одной истории задачи с меткой `[P]` не пересекаются по файлам

### Parallel Opportunities

- **Setup**: T003, T004, T007, T008, T010, T013 — разные файлы; T005 после T004; T006 после T005; T009/T011/T012 последовательны из-за импортов
- **Foundational**: T014–T020, T023, T026 — разные файлы; T017 → T018, T015 → T016; T019/T020 → T021 → T024; T023 → T026; T024 → T025; T027 после T008
- **US1**: T028/T029 — тесты параллельны; T030–T033 независимы; T034 → T035 → T036 → T037
- **US2**: T038–T040 — тесты параллельны; T041–T044 независимы; T045 → T046 → T047
- **US3**: T048/T049 — тесты параллельны; T050 и T052 независимы; T051 после T050; T053 после T050–T052; T054 после T053; T055–T058 правят один файл последовательно; T059 после T055; T060 после T026/T059
- **US4**: T061 независим; T062 → T063 → T064; T065 после T055/T050
- **Polish**: T066–T069 независимы; T070–T074 выполняются после них

---

## Parallel Example: User Story 1

```bash
# Тесты пишутся первыми и должны упасть
Task: "Создать tests/js/CatalogFilters.spec.js"
Task: "Создать tests/js/ListingCard.spec.js"

# Затем параллельно — четыре поля фильтра (разные файлы)
Task: "Создать resources/js/components/catalog/PriceRangeField.vue"
Task: "Создать resources/js/components/catalog/IncomeRangeField.vue"
Task: "Создать resources/js/components/catalog/DomainZoneField.vue"
Task: "Создать resources/js/components/catalog/VerifiedField.vue"
```

## Parallel Example: User Story 2

```bash
Task: "Создать tests/js/CatalogPagination.spec.js"
Task: "Создать tests/js/ListingList.spec.js"
Task: "Создать tests/js/CatalogStates.spec.js"

# После тестов — независимые компоненты состояний
Task: "Создать resources/js/components/catalog/CatalogPagination.vue"
Task: "Создать resources/js/components/catalog/CatalogSkeletons.vue"
Task: "Создать resources/js/components/catalog/CatalogEmptyState.vue"
Task: "Создать resources/js/components/catalog/CatalogErrorState.vue"
```

## Parallel Example: User Story 3

```bash
Task: "Создать tests/js/SiteHeader.spec.js"
Task: "Создать tests/js/sessionStore.spec.js"

# Независимые представления: T050 и T052 можно делать параллельно, T051 — после T050
Task: "Создать resources/js/views/PlaceholderView.vue"
Task: "Создать resources/js/views/NotFoundView.vue"
Task: "Заполнить resources/js/views/HowItWorksView.vue и PricingView.vue"
```

---

## Implementation Strategy

### MVP First (US1)

1. Phase 1: Setup — Vue-стек, shadcn-vue, тема, собираемая цепочка shell → router → view
2. Phase 2: Foundational — конфиг, форматирование, фикстуры, сервисы, стор
3. Phase 3: US1 — панель фильтров, карточки и витрина результата
4. **STOP и ПРОВЕРКА**: `quickstart.md` §6.2 + юнит-тесты
5. Демонстрация: фильтры сужают каталог, счётчик и сброс работают

### Incremental Delivery

1. Setup + Foundational → база готова, `/` открывается
2. US1 → MVP (фильтры + карточки)
3. US2 → счётчик, пагинация, состояния и тост «Связаться» (полный P1-каталог)
4. US3 → шапка, вход, заглушки
5. US4 → URL, история и round-trip через заглушки
6. Polish → доступность, адаптивность, финальная проверка

### Parallel Team Strategy

1. Команда завершает Setup + Foundational
2. Далее параллельно: разработчик A — US1, разработчик B — US3; после US1 разработчик B переходит на US2, затем на US4
3. US3 не конфликтует с US1/US2 по файлам, кроме `App.vue` (US3) и `CatalogView.vue` (US1/US2/US4) — правки этих файлов согласуются

---

## Notes

- `[P]` — разные файлы, нет зависимостей от незавершённых задач
- Метка `[Story]` обеспечивает трассировку к `spec.md`
- Каждая история завершается независимой проверкой по `quickstart.md`
- Коммит после каждой задачи или логической группы
- Не добавлять catch-all маршруты в `routes/web.php` — это ломает `/admin`
- Не добавлять TypeScript: ни `.ts`-файлов, ни `lang="ts"` в `.vue`
- `mocks/zones.js` — только fallback; список зон всегда выводится из публичных лотов через `fetchZones()`
- Остановка на любом checkpoint допустима для демонстрации инкремента
