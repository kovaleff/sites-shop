 # SSR-lite: как устроен серверный HTML с метатегами

Документ объясняет схему, по которой сайт отдаёт поисковикам и мессенджерам
готовые метатеги, оставаясь SPA. Здесь же — что именно делает PHP, что делает
браузер, где узкие места и чего эта схема не умеет.

Термин «SSR-lite» условный: полноценного серверного рендеринга Vue нет.
Правильнее называть это «серверные метатеги + SPA-гидратация».

---

## Зачем это нужно

Исходная проблема была не в Google. Google исполняет JavaScript (Evergreen
Googlebot, двухволновое индексирование), поэтому разметку он увидит и без SSR.
Проблемы другие:

1. **Соцсети JS не исполняют.** VK, Telegram, WhatsApp читают только серверный
   HTML. Без `og:title` и `og:image` ссылка на биржу разворачивается пустой
   заглушкой. Для аудитории, которая логинится через VK ID, это прямая потеря
   трафика.
2. **Яндекс и Bing рендерят хуже и медленнее.** Яндекс JS-рендеринг поддерживает,
   но с ограничениями и задержками; Bing — частично. Для русскоязычной площадки
   Яндекс — заметная доля поиска.
3. **У лота не было URL.** До этой доработки в роутере были только `/`,
   `/how-it-works`, `/pricing`, `/cabinet/*` и 404. Отдельной страницы объявления
   не существовало — индексировать было нечего.
4. **`<title>` ставился на клиенте.** При сбое рендера оставался общий заголовок
   из `app.blade.php`.

Отсюда решение: серверные метатеги и отдельный URL для лота, без полного SSR.

---

## Ключевая идея

Страница делится на две части, и они отдаются разными путями:

| Часть | Кто формирует | Зачем |
|---|---|---|
| `<head>`: title, description, canonical, `og:*`, JSON-LD | **PHP (Blade)** | это читают поисковики и мессенджеры, JS им не нужен |
| `<body>`: сама разметка | **браузер (Vue)** | интерфейс, клики, фильтры — как в обычном SPA |

Сервер не рендерит Vue. Он подставляет данные в шаблон и передаёт их клиенту
в готовом виде (`window.__INITIAL_STATE__`), чтобы клиенту не пришлось идти
за ними в API.

---

## Путь запроса: первая загрузка `/site/1-testovyi-magaz-velov`

### 1. Роутинг

```php
// routes/web.php
Route::get('/site/{slug}', ListingPageController::class)
    ->where('slug', '[0-9]+(-[A-Za-z0-9-]+)?')
    ->name('listing');
```

nginx отдаёт всё, что не файл, в `index.php` (штатный `try_files`), дальше Laravel.

### 2. Контроллер ищет лот

```php
// app/Http/Controllers/ListingPageController.php
$site = Site::query()
    ->publiclyVisible()          // active + approved + оплачено
    ->with('categories')
    ->findOrFail($siteId);
```

- Лот не найден или не опубликован (pending, истёкшая подписка) → **404**.
- Слаг в URL не совпадает с названием → **301** на канонический адрес
  (`/site/1` → `/site/1-testovyi-magaz-velov`).

### 3. Формируются метаданные

```php
// app/Support/Seo/SeoMeta.php
SeoMeta::forListing($site, $categories, $imageUrl)
// → title:       "Тестовый магаз велов — vely.ru за 100 001 ₽"
// → description: обрезано до 160 символов
// → canonical:   url($site->canonicalPath())
// → og:image:    сгенерированная картинка лота
// → jsonLd:      Product / Offer с ценой в RUB
```

Картинку рисует `app/Services/Seo/ListingImageGenerator.php` средствами GD
(1200×630, цвета темы: название, домен, цена, доход). Кэш —
`storage/app/public/og/site-{id}.png`, сбрасывается в `Site::saved()`.

### 4. Blade отдаёт HTML

```blade
{{-- resources/views/app.blade.php --}}
<head>
    @include('partials.seo')          {{-- title, og:*, JSON-LD уже здесь --}}
    <script>
        window.__INITIAL_STATE__ = @js($initialState ?? []);
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div id="app"></div>              {{-- пусто, Vue отрисует сам --}}
</body>
```

`@js()` — это `Illuminate\Support\Js::from()`, он экранирует `<`, `>`, `'`, `"`,
`&` (`JSON_HEX_TAG` и т.п.), поэтому `</script>` внутри названия лота не разорвёт
тег.

### 5. Что получает каждый потребитель

- **Telegram / VK** — читают только `<head>`, сразу показывают превью с картинкой.
- **Googlebot** — в первом волне видит title/description/canonical/JSON-LD,
  дальше исполняет JS и рендерит `<div id="app">`.
- **Яндекс** — то же, но рендер отложенный и менее предсказуемый.
- **Браузер** — грузит JS, Vue монтируется.

### 6. Гидратация во Vue

```js
// resources/js/views/ListingView.vue
const hydrated = consumeInitialState('listing');

if (hydrated && hydrated.slug === slug) {
    listing.value = hydrated;
    status.value = 'ready';

    return;                       // запрос к API не нужен
}
```

Данные уже в `window.__INITIAL_STATE__` — второй запрос к серверу не делается.
Отсюда и «lite»: сервер экономит клиенту один round-trip.

`consumeInitialState()` **удаляет** ключ после чтения
(`resources/js/lib/initialState.js`), поэтому при следующем переходе данных нет
и компонент честно идёт в API.

---

## Путь запроса: переход внутри SPA (клик по карточке)

Страница не перезагружается — серверный HTML не участвует вообще.

1. **RouterLink ведёт на `/site/:slug`.** Vue Router перехватывает клик и меняет
   URL через History API.
2. **`consumeInitialState()` возвращает `null`** (состояние одноразовое),
   компонент запрашивает API:
   ```js
   const data = await fetchListing(slug);   // GET /api/v1/listings/{slug}
   ```
3. **Метатеги обновляются вручную** — иначе в `<head>` остались бы данные
   предыдущей страницы:
   ```js
   // resources/js/lib/pageMeta.js
   applyListingMeta(data);   // title, description, canonical, og:*
   ```
4. **Флаг `preserveTitle` в роутере:**
   ```js
   // resources/js/router/index.js
   if (to.meta.preserveTitle) return;    // не затирать серверный заголовок

   document.title = to.meta.title ? `${to.meta.title} — Sites-shop` : 'Sites-shop';
   ```
   Без него `afterEach` перезаписывал заголовок на «Объявление — Sites-shop»
   сразу после монтирования.

---

## Схема целиком

```
ПЕРВАЯ ЗАГРУЗКА
  nginx → php-fpm → Laravel
        ├── SELECT sites WHERE publiclyVisible
        ├── SeoMeta::forListing()  → <head>
        └── window.__INITIAL_STATE__ = {listing: {...}}
              ↓
        браузер: JS → Vue монтируется → читает состояние → рисует
                                    (без запроса к API)

ПЕРЕХОД ВНУТРИ SPA
  клик → Vue Router → URL меняется
        ├── состояние пустое
        ├── GET /api/v1/listings/{slug}
        └── applyListingMeta() → обновляет <head>
```

---

## Что нужно на проде

Node **не нужен** в рантайме.

```
npm run build   →   public/build/assets/*.js, *.css, manifest.json
```

Это статика. На проде остаётся то же, что и раньше:

```
nginx  →  php-fpm  →  Laravel
```

Никаких новых процессов, портов, pm2, supervisor для Node, записей в systemd.

Проверить, что схема действительно такая:

```bash
grep -n "ssr\|entry-server" vite.config.js     # пусто
ls resources/js/entry-server*                  # нет файла
ls public/build/ssr-manifest.json              # нет файла
```

Требования к окружению:

- PHP-расширение **GD с FreeType** (для `og:image`).
- `php artisan storage:link` (симлинк `public/storage`).
- После деплоя: `php artisan cache:forget sitemap.xml`, если карта сайта
  закэширована старой версией.

---

## Связанные файлы

| Файл | Роль |
|---|---|
| `app/Support/Seo/SeoMeta.php` | сборка title, description, canonical, JSON-LD |
| `app/Services/Seo/ListingImageGenerator.php` | PNG 1200×630 для `og:image` |
| `app/Http/Controllers/ListingPageController.php` | серверный HTML страницы лота, 301/404 |
| `app/Http/Controllers/SitemapController.php` | `/sitemap.xml`, кэш 60 минут |
| `app/Http/Controllers/Api/ListingController.php` | `GET /api/v1/listings/{slug}` для SPA |
| `resources/views/app.blade.php` | оболочка: метатеги + `__INITIAL_STATE__` |
| `resources/views/partials/seo.blade.php` | сами теги `<head>` |
| `resources/js/views/ListingView.vue` | гидратация и клиентская загрузка |
| `resources/js/lib/initialState.js` | одноразовое чтение состояния |
| `resources/js/lib/pageMeta.js` | обновление метатегов при клиентском переходе |
| `routes/web.php` | `/site/{slug}`, `/sitemap.xml`, `/robots.txt` |

`/robots.txt` и `/sitemap.xml` — **маршруты**, а не файлы в `public/`.
Статический `public/robots.txt` удалён намеренно: nginx отдавал бы его
вместо маршрута, и `Sitemap:` указывал бы на неверный домен.

---

## Чем это не является

- **Не SSR.** Vue на сервере не исполняется, разметки в HTML нет — только
  `<div id="app"></div>`.
- **Не Node на проде.** `npm run build` — этап сборки, дальше только PHP
  и статика.
- **Не полная индексация текста.** Описание лота лежит в `<head>`
  (в `description` и JSON-LD), но сама вёрстка появляется после JS.
  Google это переварит, Яндекс — хуже.

---

## Узкие места

1. **Дублирование логики метатегов.** `SeoMeta` (PHP) и `pageMeta.js` (JS)
   формируют одни и те же формулировки независимо. Нужно держать их
   синхронными, иначе title на сервере и после клика будут отличаться.
   Кандидат на исправление — тест, сверяющий обе реализации на одинаковых данных.
2. **Данные в `__INITIAL_STATE__`.** `@js()` экранирует HTML-опасные символы,
   но при добавлении новых полей стоит помнить об этом.
3. **Кэш картинок.** PNG создаётся при первом запросе страницы и сбрасывается
   в `Site::saved()`. Если менять цену массово через SQL, превью останутся
   старыми.
4. **`robots.txt` и `sitemap.xml` — маршруты.** Если кто-то положит файлы
   обратно в `public/`, маршруты перестанут работать.
5. **Слаг в URL.** При смене названия лота канонический URL меняется, старый
   отдаёт 301. Для уже проиндексированных страниц это нормально, но стоит
   помнить при переименованиях.

---

## Когда понадобится настоящий SSR

Только если потребуется, чтобы **текст описания** был в исходном HTML,
а не подгружался JS. Два пути:

- **Пререндер** (`spatie/laravel-prebuild`, prerender.io) — страницы рендерятся
  заранее и кладутся статикой. Node работает на этапе сборки или по крону,
  не в рантайме. Для каталога с быстро меняющимися лотами подходит плохо.
- **Полный SSR** (Nuxt или Vue SSR) — отдельный Node-процесс: память, деплой,
  health-check, кэш. Записано в `.agents/propose.md` в разделе
  «Что не делать сейчас».

Сейчас ни то, ни другое не требуется: для индексации хватает метатегов
и JSON-LD, для соцсетей — Open Graph, который читается из серверного HTML.

---

## Тесты

- `tests/Feature/Seo/ListingPageTest.php` — метатеги, JSON-LD, гидратация,
  тематики, 301 на устаревший слаг, 404 для неопубликованных, `noindex` кабинета.
- `tests/Feature/Seo/SitemapTest.php` — состав карты, исключение скрытых лотов,
  сброс кэша при изменении лота, содержимое `robots.txt`.
- `tests/Feature/Catalog/ListingApiTest.php` — API лота, окупаемость, 404.
- `tests/js/initialState.spec.js` — одноразовое чтение состояния.
- `tests/js/pageMeta.spec.js` — обновление и переиспользование тегов.
