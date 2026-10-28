# Contract: Маршруты, URL и UI-компоненты

**Feature**: `003-catalog-home-page` | **Date**: 2026-10-28

Контракт фиксирует внешние для реализации границы: адреса страниц, правила query-строки, состав экранов и интерфейсы компонентов. Внутренняя реализация компонентов может меняться, но props, события и поведение — нет.

---

## 1. Маршруты

### 1.1 Laravel (`routes/web.php`)

| Метод | URI | Имя | Ответ | Примечание |
|---|---|---|---|---|
| GET | `/` | `catalog` | `view('app')` | Публичная главная страница |
| GET | `/how-it-works` | `how-it-works` | `view('app')` | Заглушка «Как это работает» |
| GET | `/pricing` | `pricing` | `view('app')` | Заглушка «Тарифы» |

**Запрещено**: `Route::fallback()`, `Route::get('/{any}')` и любые catch-all маршруты — они перехватывают `/admin/*` (Orchid, `config/platform.php`, `prefix = /admin`), `/up` и `/api/*`.

### 1.2 Vue Router (`resources/js/router/index.js`)

| `name` | `path` | Компонент | Мета |
|---|---|---|---|
| `catalog` | `/` | `views/CatalogView.vue` | `{ navKey: 'catalog', title: 'Каталог готовых сайтов' }` |
| `how-it-works` | `/how-it-works` | `views/HowItWorksView.vue` | `{ navKey: 'how-it-works', title: 'Как это работает' }` (lazy) |
| `pricing` | `/pricing` | `views/PricingView.vue` | `{ navKey: 'pricing', title: 'Тарифы' }` (lazy) |
| `not-found` | `/:pathMatch(.*)*` | `views/NotFoundView.vue` | `{ navKey: null, title: 'Страница не найдена' }` |

**Правила**:
- Режим истории — `createWebHistory()`; ссылки без `#`.
- `scrollBehavior`: при смене страницы каталога — к элементу `#catalog-list` (или `top: 0` при первом входе); сохранение позиции при «назад»/«вперёд» (`savedPosition ?? { top: 0 }`).
- `router.afterEach` обновляет `document.title` из `meta.title`.
- Активный пункт меню определяется по `meta.navKey`, а не по сравнению URL — так заглушки и 404 не ломают подсветку.
- Переход между разделами не сбрасывает `catalog`-стор; ссылки на заглушки и обратно переносят текущий query каталога, поэтому при возврате на `/` состояние восстанавливается из URL.

---

## 2. Query-параметры каталога

Единственный источник восстановления состояния (FR-027, US4).

| Параметр | Формат | Пример | Значение по умолчанию | Правило записи |
|---|---|---|---|---|
| `price_min` | целое ≥ 0 | `50000` | `0` | Не пишется при значении по умолчанию |
| `price_max` | целое ≥ 0 | `1200000` | `10000000` | Не пишется при значении по умолчанию |
| `income_min` | целое ≥ 0 | `0` | `0` | Не пишется при значении по умолчанию |
| `income_max` | целое ≥ 0 | `150000` | `1000000` | Не пишется при значении по умолчанию |
| `zones` | CSV | `ru,com` | все зоны | Не пишется при пустом выборе; порядок нормализуется (`ru,com,de`) |
| `verified` | `1` | `1` | `false` | Не пишется при `false` |
| `page` | целое ≥ 1 | `2` | `1` | Не пишется при `1` |

**Нормализация (FR-028, SC-012)**:

| Вход | Результат |
|---|---|
| `price_min=abc` | `0` |
| `price_min=-5` | `0` |
| `price_max=99999999` | `10000000` |
| `price_min=2000000&price_max=100000` | обе границы по умолчанию |
| `zones=ru,xx` | `['ru']` |
| `zones=` | все зоны, включая лоты без распознанной зоны |
| `zones=ru,com,de` | выбраны все конкретные зоны; лоты без распознанной зоны скрыты (FR-011) |
| `verified=0` / `verified=yes` | `false` |
| `page=0`, `page=-1`, `page=x` | `1` |
| `page=999` при `lastPage=3` | после ответа `router.replace` на `page=3` |
| Неизвестный параметр (`utm_source`, `mock`) | Игнорируется, в состояние не попадает |

**История**:
- «Применить» и смена страницы → `router.push`.
- «Сбросить все» → `router.push({ query: {} })`.
- Замена невалидной страницы на последнюю → `router.replace`.
- Изменения `draft` → URL не трогают.

---

## 3. Экраны

### 3.1 Каталог (`CatalogView`)

Порядок сверху вниз (FR-001): `SiteHeader` → `CatalogFilters` → строка «Показано X из Y» → `ListingList` → `CatalogPagination`.

Состояния:

| Условие | Что рендерится |
|---|---|
| `status === 'loading'` и `items.length === 0` | `CatalogSkeletons` (6 карточек) |
| `status === 'loading'` и есть предыдущие `items` | Список + `aria-busy="true"`, панель фильтров активна |
| `status === 'error'` | `CatalogErrorState` с кнопкой «Повторить» |
| `status === 'success'`, `total === 0`, фильтры не активны | `CatalogEmptyState` «Предложений пока нет» |
| `status === 'success'`, `total === 0`, фильтры активны | `CatalogEmptyState` «Ничего не найдено» + «Сбросить все» |
| `status === 'success'`, `total > 0` | Список, счётчик, пагинация |

### 3.2 Заглушки (`HowItWorksView`, `PricingView`)

Обе используют `PlaceholderView` с props:
- `title` — заголовок раздела;
- `message` — «Раздел в разработке. Скоро здесь появится подробная информация.»;
- ссылка «Вернуться в каталог» → `{ name: 'catalog', query: route.query }`, чтобы сохранить фильтры при возврате; при прямом открытии заглушки query пустой.

Требования: общая шапка, активный пункт меню, страница открывается по прямой ссылке (FR-030, SC-016).

### 3.3 404 (`NotFoundView`)

Заголовок «Страница не найдена», пояснение, ссылка «Вернуться в каталог». Шапка сохраняется, ни один пункт меню не активен.

---

## 4. Компоненты

### 4.1 `SiteHeader`

| Вход | Тип | Описание |
|---|---|---|
| — | — | Данные берёт из `useSessionStore()` и `useRoute()` |

**Поведение**:
- Бренд: логотип-квадрат с буквой «S» + «Sites-shop» (акцентная часть «shop») + подпись «Маркетплейс готовых сайтов».
- Меню ровно из трёх пунктов: «Каталог», «Как это работает», «Тарифы» (FR-002). Пункта «Помощь» нет. При переходе внутри разделов (`route.meta.navKey !== null`) ссылки передают текущий `route.query`, чтобы возврат сохранил фильтры (FR-027, US4).
- Активный пункт: лаймовый текст + подложка `bg-primary/15` (FR-003).
- Гость: две кнопки (`Button` с вариантом `ghost` и логотипом провайдера). Тексты: «Войти через VK ID» и «Войти через Яндекс ID». Логотипы — SVG из `/home/kovaleff/www/cheap-eats/resources/js/components/HomeView.vue` (VK `#0077FF`, Yandex `#f8604a`).
- Обработчики: `loginWithVk` и `loginWithYandex` (FR-004).
- Авторизован: `DropdownMenu` с `Avatar` (инициалы, если `avatarUrl === null`); пункты «Профиль» (тост о разработке, перехода нет) и «Выйти» (FR-005). Переход в личный кабинет в эту итерацию не выполняется — это зафиксировано в спецификации.
- Мобильный (`< md`): меню в `Sheet` по кнопке с `aria-label="Открыть меню"`; кнопки входа — только иконки с `aria-label`; горизонтальной прокрутки нет (FR-032).
- Липкость: `sticky top-0 z-40` + `bg-background/90 backdrop-blur` (FR-006).

**События**: нет (вся логика внутри).

### 4.2 `CatalogFilters`

| Prop | Тип | По умолчанию | Описание |
|---|---|---|---|
| `applied` | `CatalogFiltersState` | обязателен | Применённый набор |
| `zones` | `DomainZone[]` | `[]` | Доступные зоны |
| `zonesLoading` | boolean | `false` | Состояние загрузки зон |
| `activeCount` | number | `0` | Счётчик активных фильтров |

| Emit | Payload | Когда |
|---|---|---|
| `apply` | `CatalogFiltersState` | Нажата «Применить» |
| `reset` | — | Нажата «Сбросить все» |

**Поведение**:
- Внутренний `draft` инициализируется из `applied` и пересоздаётся при изменении `applied` (сброс/гидратация).
- Заголовок «Фильтры» + бейдж «N активных» (FR-016); при `0` бейдж скрыт.
- «Сбросить все» — `variant="link"`, доступна всегда (при отсутствии активных фильтров — `disabled`).
- «Применить» — `Button` с вариантом `default` (лайм), всегда доступна.
- Сетка: 4 колонки на `≥ xl`, 2 на `md`, 1 на мобильном.

### 4.3 `PriceRangeField` / `IncomeRangeField`

| Prop | Тип | Описание |
|---|---|---|
| `modelValue` | `[number, number]` | `[min, max]` |
| `min` | number | Нижняя граница из `catalogConfig` |
| `max` | number | Верхняя граница |
| `step` | number | Шаг |
| `label` | string | «Цена, ₽» / «Доход, ₽/мес» |

| Emit | Payload |
|---|---|
| `update:modelValue` | `[number, number]` |

**Поведение**:
- `Slider` с двумя `SliderThumb`; `:min`, `:max`, `:step`, `:model-value`.
- Подписи значений под ползунком: «0 ₽ — 10 000 000 ₽ и выше»; при `max === bounds.max` добавляется «и выше» (SC-018).
- `aria-label` ручек: «Минимальная цена» / «Максимальная цена» (аналогично для дохода); `aria-valuetext` — форматированные рубли.
- Пересечение ручек запрещено: значение ручки ограничивается значением соседней.

### 4.4 `DomainZoneField`

| Prop | Тип |
|---|---|
| `modelValue` | `string[]` |
| `zones` | `DomainZone[]` |
| `loading` | boolean |

| Emit | Payload |
|---|---|
| `update:modelValue` | `string[]` |

**Поведение**:
- `ToggleGroup` `type="multiple"`, `variant="outline"`, `size="sm"`.
- Кнопка «Все зоны» активна при `modelValue.length === 0`; нажатие очищает выбор (FR-010).
- Выбор зоны добавляет/убирает её значение; визуальная подпись — `.ru`, `.com`, `.de`.
- Доступно с клавиатуры (`ToggleGroup` даёт `role="group"` и фокус-кольца).

### 4.5 `VerifiedField`

| Prop | Тип |
|---|---|
| `modelValue` | boolean |

| Emit | Payload |
|---|---|
| `update:modelValue` | boolean |

**Поведение**: `Switch` + `Label` «Проверен»; клик по подписи переключает; по умолчанию выключен (FR-012).

### 4.6 `ListingCard`

| Prop | Тип | Описание |
|---|---|---|
| `listing` | `Listing` | Обязателен |

| Emit | Payload | Когда |
|---|---|---|
| `contact` | `Listing` | Нажата «Связаться» |

**Обязательное содержимое** (FR-020):
- Блок иконки: `faviconUrl` либо градиентная заглушка с первой буквой домена.
- Название (`title`), обрезка до 2 строк (`line-clamp-2`).
- Домен + зона, обрезка с `truncate`, `title`-атрибут с полным доменом.
- Описание: `line-clamp-2`; при отсутствии — пустая строка без сдвига сетки.
- Колонки: «Цена» (`formattedPrice`, лайм), «Доход» (`formattedIncome`, зелёный), «Статус» (`Badge` + текст «Активен»), «Проверен» (бейдж/иконка + текст «Да» / «Нет»).
- Кнопка «Связаться» (`Button`, лайм) — единственное действие карточки.

**Поведение**:
- Нажатие «Связаться» эмитит `contact`; `CatalogView` показывает тост «Связь с продавцом появится в следующей версии» (FR-029, SC-017). Навигации нет, URL не меняется, данные не меняются.
- Статус и проверка передаются текстом, не только цветом (FR-021).
- Hover: `border-primary/40` и `bg-secondary`; фокус внутри карточки видим (FR-031, FR-033).
- Раскладка: строка (7 колонок) на `≥ lg`, вертикальная стопка на `< lg`; длинные строки не ломают сетку.

### 4.7 `ListingList`

| Prop | Тип |
|---|---|
| `items` | `Listing[]` |
| `total` | number |

| Emit | Payload |
|---|---|
| `contact` | `Listing` |

**Поведение**: заголовок «Показано X из Y» (`items.length`, `total`), сетка карточек, `key = listing.id`. Контейнер имеет `id="catalog-list"` и `aria-busy` при загрузке.

### 4.8 `CatalogPagination`

| Prop | Тип |
|---|---|
| `page` | number |
| `lastPage` | number |

| Emit | Payload |
|---|---|
| `update:page` | number |

**Поведение** (FR-023):
- «Назад» недоступна на первой странице, «Вперёд» — на последней (`aria-disabled` + `disabled`).
- Номера страниц: окно до 5 номеров вокруг текущей, крайние страницы всегда доступны, между разрывами — `PaginationEllipsis`.
- Текущая страница: `aria-current="page"` + лаймовый фон.
- При `lastPage === 1` пагинация не рендерится (US2-5).
- Размер кнопок не меньше 40×40 px для мобильных.

### 4.9 `CatalogSkeletons`, `CatalogEmptyState`, `CatalogErrorState`

| Компонент | Props | Поведение |
|---|---|---|
| `CatalogSkeletons` | `count = 6` | `Skeleton`-карточки с фиксированной высотой, без layout shift |
| `CatalogEmptyState` | `title`, `description`, `showReset`, `resetLabel` | Emit `reset`; вариант без кнопки для пустого каталога |
| `CatalogErrorState` | `message` | Emit `retry`; кнопки «Повторить» и «Сбросить все» |

---

## 5. Правила доступности

- Все интерактивные элементы достижимы клавишей `Tab`, активируются `Enter`/`Space` (FR-033, SC-013).
- Фокус: `focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background`.
- Слайдеры: `aria-label` на каждой ручке, `aria-valuetext` с рублями.
- Переключатель «Проверен» связан с `Label` через `id`/`for`.
- Пагинация: `nav aria-label="Пагинация каталога"`, «Назад»/«Вперёд» с полными подписями.
- Тост «Связаться» — `aria-live="polite"` (обеспечивает `sonner`).
- Контраст текста: основной `#e8e9e2` на `#1f2022` ≈ 11:1; второстепенный `#a8a9a3` — не ниже 4.5:1.
- Иконки-логотипы провайдеров декоративны (`aria-hidden`), текстовая подпись — источник смысла.

---

## 6. Матрица «требование → компонент»

| Требование | Где реализуется |
|---|---|
| FR-001 | `CatalogView` |
| FR-002, FR-003 | `SiteHeader`, `router.meta.navKey` |
| FR-004, FR-005 | `SiteHeader` (кнопки/`Avatar` + `DropdownMenu`) |
| FR-006 | `SiteHeader` (`sticky`) |
| FR-007, FR-014, FR-016 | `CatalogFilters` |
| FR-008, FR-009 | `PriceRangeField`, `IncomeRangeField`, `catalogConfig` |
| FR-010, FR-011 | `DomainZoneField`, `catalogService` |
| FR-012 | `VerifiedField` |
| FR-013, FR-015, FR-036 | `stores/catalog.js` |
| FR-017 | `catalogService` |
| FR-018, FR-019 | `ListingList`, `CatalogPagination` |
| FR-020, FR-021, FR-022 | `ListingCard`, `lib/format.js` |
| FR-023, FR-024 | `CatalogPagination`, `router.scrollBehavior` |
| FR-025 | `CatalogEmptyState` |
| FR-026 | `CatalogSkeletons`, `CatalogErrorState` |
| FR-027, FR-028 | `useCatalogQuery`, `lib/catalogFilters.js` |
| FR-029 | `ListingCard` → `contact` → тост |
| FR-030 | `router`, `PlaceholderView` |
| FR-031, FR-032 | `app.css`, Tailwind-классы, адаптивные варианты |
| FR-033 | раздел 5 |
| FR-034 | `lib/format.js`, русские подписи |
| FR-035 | `CatalogView` без проверок авторизации |
