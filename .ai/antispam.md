# Защита от мусора и рекламы в объявлениях

Документ описывает реализованный антиспам-контур биржи сайтов: что именно сделано
(слой 1 и слой 2), как это работает в рантайме, чем управляется и что предлагается
делать дальше (слой 3).

Политика публикации: **автоапрув после успешной проверки владения доменом**.
Объявление создаётся в статусе `pending` и попадает в каталог только после того, как
job подтвердил владение доменом (или модератор одобрил вручную).

---

## Слой 1. Модерация и автоматическая проверка домена

### 1.1. Схема данных

Миграция `database/migrations/2026_10_31_192440_add_moderation_to_sites_table.php`
добавляет в `sites`:

| Колонка | Тип | Назначение |
|---|---|---|
| `moderation_status` | `varchar(16)`, default `pending`, индекс | `pending` / `approved` / `rejected` |
| `moderation_note` | `varchar(255)`, nullable | причина отклонения или заметка модератора |
| `moderated_at` | `timestamp`, nullable | когда принято решение |
| `verification_checked_at` | `timestamp`, nullable | когда последний раз проверялся домен |
| `verification_error` | `varchar(255)`, nullable | причина неудачной проверки (человекочитаемая) |

Существующие на момент миграции объявления помечаются `approved`, чтобы каталог
не опустел.

`App\Enums\ModerationStatus` — `Pending` / `Approved` / `Rejected` с `label()`
и `values()`.

### 1.2. Модель `App\Models\Site`

- Каст `moderation_status` → `ModerationStatus`, `moderated_at` /
  `verification_checked_at` → `datetime`.
- Значение по умолчанию в `$attributes`: `moderation_status = 'pending'`
  (зеркалит DB-default, чтобы модель наблюдала его до сохранения).
- `publiclyVisible()` и `isPubliclyVisible()` требуют
  `status = active` **и** `moderation_status = approved` **и** оплаченную подписку.
  То есть неоплаченное и непромодерированное объявление в каталог не попадает.
- Новые доменные методы:
  - `markDomainVerified()` — ставит `verified = true`, `verification_checked_at = now()`,
    очищает `verification_error`; если объявление было `pending`, переводит в `approved`.
  - `markDomainVerificationFailed(string $reason)` — `verified = false`, пишет причину,
    **не меняет** статус модерации (объявление остаётся в очереди).
  - `setModeration(ModerationStatus $status, ?string $note = null)` — ручное решение
    модератора, идемпотентно.
  - `resetDomainVerification()` — сбрасывает подтверждение и возвращает `approved`
    в `pending`; вызывается при смене домена.
- Фильтр Orchid по `moderation_status` добавлен в `$allowedFilters`.

### 1.3. Проверка владения доменом

`App\Services\Domain\DomainVerifier` выполняет шаги по порядку и возвращает
`DomainVerificationResult` (`passed` + `reason`):

1. **DNS** — `DomainResolver::resolves()`; реализация `DnsDomainResolver` смотрит
   `A | AAAA | CNAME`.
2. **Репутация** — `ReputationChecker::isUnsafe()` (см. слой 2).
3. **Возраст домена** — `DomainAgeChecker::isTooYoung()` (см. слой 2).
4. **Главная страница** — `https://` затем `http://`, успешный ответ и отсутствие
   маркеров парковки (`domain is for sale`, `sedoparking`, `домен продаётся` и т.п.).
5. **Файл подтверждения** — `/{verification_filename}` отдаёт 2xx и это не HTML
   (проверяется `Content-Type` и наличие `<html` в теле).

Таймауты: `connectTimeout` и `timeout` из `config('sites.verification.*')`,
User-Agent `SitesShopBot/1.0 (+verification)`.

Интерфейсы `DomainResolver` и `ReputationChecker` вынесены отдельно, чтобы в тестах
подменять сетевые проверки без реальных запросов.

### 1.4. Job

`App\Jobs\VerifySiteDomain` — `ShouldQueue`, `ShouldBeUnique` (уникальность по `siteId`,
`uniqueFor = 3600`), `tries = 3`, `timeout = 30`, `backoff = [10, 60]`.

Диспатчится:
- при создании объявления (`MySiteController@store`, `->afterCommit()`);
- при смене домена в `MySiteController@update` (после `resetDomainVerification()`);
- при редактировании объявления, у которого ещё нет `verified`;
- вручную из Orchid (кнопка «Проверить домен» и «Проверить домены» в очереди).

Успех → `markDomainVerified()` (verified + автопрув).
Неудача → `markDomainVerificationFailed()` с причиной в `verification_error`.

### 1.5. Валидация контента

`App\Rules\NoAdvertisingContent` навешено на `title` и `description` в
`StoreSiteRequest`. Отклоняет:

- ссылки (`http://`, `https://`, `www.`);
- телефоны (`+7`/`8` с типовым форматированием, любой `+` с 11+ цифрами);
- `@username`, упоминания telegram / whatsapp / телеграм / ватсап;
- запрещённые тематики: казино, букмекер, ставки на спорт, микрозайм, интим, порно,
  `18+`, бренды букмекеров (1xbet, winline, mostbet, vulkan).

### 1.6. Лимиты и троттлинг

- `POST /api/v1/my/sites` под middleware `throttle:create-site`:
  `RateLimiter::for('create-site')` — `per_day` из `config('sites.creation_per_day')`,
  ключ — id пользователя (для гостя — IP), кастомный JSON-ответ 429.
- `MySiteController::ensureListingLimit()` — не больше
  `config('sites.max_active_per_user')` объявлений в статусах `active` + `inactive`
  на продавца; при превышении 422 с понятным сообщением. `0` — без ограничения.

### 1.7. Админка (Orchid)

- `SiteListLayout` — колонка «Модерация» с фильтром по статусу.
- `SiteModerationLayout` — блок «Модерация» в карточке объявления: статус, комментарий
  модератора, время последней проверки домена, результат проверки.
- `SiteEditScreen`:
  - сохраняет `moderation_status` / `moderation_note` (оба необязательны — старые формы
    и тесты без этих полей продолжают работать);
  - кнопка «Проверить домен» — повторный запуск job (нужна, когда продавец залил файл
    позже);
  - блок «Жалобы пользователей» (см. слой 2).

### 1.8. API и фронтенд кабинета

`SiteResource` дополнительно отдаёт:
`moderationStatus`, `moderationStatusLabel`, `moderationNote`,
`verificationCheckedAt`, `verificationError`.

Фронтенд:
- `ModerationBadge.vue` — бейдж «На проверке» (янтарный, токен `--color-warning`)
  или «Отклонено» (красный) рядом со статусом объявления;
- в `SiteCard.vue` при `rejected` показывается комментарий модератора;
- тексты про публикацию обновлены: объявление попадает в каталог после оплаты
  **и** подтверждения владения доменом, проверка автоматическая;
- `VerificationBlock.vue` — пояснение про автоматическую проверку.

---

## Слой 2. Жалобы, очередь модерации, капча, репутация домена

### 2.1. Жалобы на объявления

Миграция `database/migrations/2026_10_31_200000_create_site_reports_table.php`:

| Колонка | Назначение |
|---|---|
| `site_id` | FK → `sites`, `cascadeOnDelete` |
| `user_id` | FK → `users`, `cascadeOnDelete` |
| `reason` | `varchar(32)` — значение `ReportReason` |
| `comment` | `varchar(1000)`, nullable |
| `status` | `varchar(16)`, default `new`, индекс |
| `resolved_at` | `timestamp`, nullable |

Плюс `unique(site_id, user_id)` — одна жалоба на объявление от пользователя.

Модель `App\Models\SiteReport`:
- касты `reason` → `ReportReason`, `status` → `ReportStatus`, `resolved_at` → `datetime`;
- связи `site()`, `reporter()`;
- scope `open()`;
- `resolve(ReportStatus $status)` — идемпотентно закрывает жалобу и ставит `resolved_at`.

`App\Enums\ReportReason`: spam / fraud / wrong_info / forbidden / other.
`App\Enums\ReportStatus`: new / resolved / dismissed.

**API** — `POST /api/v1/sites/{site}/reports`, middleware `auth` + `throttle:5,1`,
тело `{ reason, comment? }`. Правила:
- гость → 401;
- своё объявление → 422 (`site`);
- объявление вне каталога (не `publiclyVisible`) → 404;
- повторная жалоба → 422 (`site`), в БД остаётся одна запись;
- неизвестная причина → 422 (`reason`).

**UI каталога** — кнопка «Пожаловаться» на карточке лота
(`ListingCard` → `ListingList` → `CatalogView`), диалог `ReportListingDialog.vue`
с выбором причины и комментарием; неавторизованному предлагает вход через VK/Яндекс.
Сервис — `resources/js/services/reportService.js` (`REPORT_REASONS`, `reportSite`).

**Админка** — `SiteReportsTable` в карточке объявления. «Принять» → жалоба `resolved`,
а объявление переводится в `rejected` с причиной `Жалоба принята: <причина>`.
«Отклонить» → жалоба `dismissed`, объявление остаётся опубликованным.

### 2.2. Очередь модерации

`App\Orchid\Screens\Site\ModerationQueueScreen` + `ModerationQueueLayout`:

- маршрут `admin/marketplace/moderation`, имя `platform.marketplace.moderation`,
  право `platform.marketplace.sites` (то же, что у «Объявлений», поэтому существующие
  админы видят раздел сразу);
- пункт меню «Модерация» (`bs.shield-check`) сразу после «Объявлений»;
- список только `pending`: объявление, домен, продавец, результат проверки домена,
  «оплачено до», дата создания;
- построчные кнопки «Одобрить» / «Отклонить»;
- командная кнопка «Проверить домены» — ставит в очередь `VerifySiteDomain` для первых
  50 `pending` объявлений без `verified`.

### 2.3. Капча (Яндекс SmartCaptcha)

`App\Rules\SmartCaptcha` — серверная валидация токена на
`https://smartcaptcha.yandexcloud.net/validate` (form-encoded, `secret`, `token`, `ip`,
таймауты 3/5 с). Если `sites.captcha.server_key` пуст — правило пропускает всё.

Подключено в `StoreSiteRequest::after()`: поле `captchaToken` может отсутствовать
в запросе, а обычное правило на отсутствующее поле не срабатывает. При редактировании
(`UpdateSiteRequest::shouldVerifyCaptcha() = false`) капча не требуется.

Публичные настройки — `GET /api/v1/config` (`ConfigController`):
`{ captcha: { enabled, siteKey } }`. Виджет подключается только при включённой капче
(`SmartCaptchaField.vue`, `resources/js/services/configService.js`).

### 2.4. Репутация домена

`ReputationChecker` → `GoogleSafeBrowsingChecker` (Lookup API v4). Включается
`SITES_SAFE_BROWSING_KEY`. Без ключа — no-op. При сетевой ошибке домен считается
безопасным: недоступность внешнего сервиса не должна блокировать публикацию.

`DomainAgeChecker` — возраст домена по RDAP (`https://rdap.org/domain/{domain}`),
порог `SITES_MIN_DOMAIN_AGE_DAYS` (0 = выключено). Если RDAP недоступен или дата
регистрации не найдена — не блокирует.

Обе проверки идут до HTTP-запроса к сайту, чтобы не тратить время на заведомо плохие
домены; причины пишутся в `verification_error`.

### 2.5. Побочное исправление

`phpunit.xml`: добавлен `APP_URL=http://localhost`. Из-за `APP_URL=http://sites-shop`
в `.env` падал `YandexAuthTest::test_redirect_goes_to_yandex_when_configured`
(белый список redirect URI не совпадал). Тест падал и до изменений.

---

## Как это работает целиком

```
POST /api/v1/my/sites
  ├── throttle:create-site (5/сутки)
  ├── лимит активных объявлений (3)
  ├── NoAdvertisingContent (title, description)
  ├── SmartCaptcha (если включена)
  └── создание: moderation_status = pending
        └── VerifySiteDomain::dispatch()->afterCommit()
              ├── DNS
              ├── Safe Browsing
              ├── возраст домена (RDAP)
              ├── главная страница жива и не парковка
              └── файл подтверждения доступен и не HTML
                    ├── успех → verified = true, moderation_status = approved
                    └── неудача → verification_error, объявление остаётся pending
                                      └── очередь модерации в Orchid
                                            ├── «Проверить домены»
                                            ├── «Одобрить» / «Отклонить»
                                            └── жалобы: «Принять» → rejected
```

Каталог (`publiclyVisible`): `active` + `approved` + `paid_until >= today`.

## Конфигурация

`config/sites.php` (все значения через `.env`):

| Ключ конфига | Переменная | Default | Смысл |
|---|---|---|---|
| `max_active_per_user` | `SITES_MAX_ACTIVE_PER_USER` | 3 | активных объявлений на продавца (0 = без лимита) |
| `creation_per_day` | `SITES_CREATION_PER_DAY` | 5 | созданий в сутки на пользователя |
| `verification.connect_timeout` | `SITES_VERIFICATION_CONNECT_TIMEOUT` | 3 | connect-таймаут проверок, с |
| `verification.timeout` | `SITES_VERIFICATION_TIMEOUT` | 5 | общий таймаут проверок, с |
| `verification.min_domain_age_days` | `SITES_MIN_DOMAIN_AGE_DAYS` | 0 | минимальный возраст домена (0 = выкл.) |
| `captcha.server_key` | `SITES_CAPTCHA_SERVER_KEY` | — | серверный ключ SmartCaptcha |
| `captcha.site_key` | `SITES_CAPTCHA_SITE_KEY` | — | клиентский ключ SmartCaptcha |
| `safe_browsing.key` | `SITES_SAFE_BROWSING_KEY` | — | ключ Google Safe Browsing |

Все переменные добавлены в `.env.example`.

## Эксплуатация

```bash
php artisan migrate        # 2 миграции: moderation_* в sites, site_reports
php artisan queue:work     # обязателен: проверка домена идёт в очереди
npm run build              # фронтенд
```

Без воркера объявления останутся в `pending` и в каталог не попадут.
`composer run dev` уже поднимает `queue:listen`.

Что смотреть при разборе: `sites.verification_error` (причина последней неудачи),
`sites.verification_checked_at` (когда проверяли), `site_reports.status = new`
(неразобранные жалобы).

## Тесты

PHP (273 теста в наборе, все зелёные):

- `tests/Feature/Models/SiteModerationTest.php` — статусы, автопрув, сброс при смене домена;
- `tests/Feature/Jobs/VerifySiteDomainTest.php` — успех, отсутствие файла, DNS,
  парковка, unsafe-домен, молодой домен, недоступный RDAP;
- `tests/Feature/Cabinet/SiteCreationRulesTest.php` — pending при создании, ссылки,
  контакты, запрещённые темы, лимит, троттлинг, смена домена, капча;
- `tests/Feature/Cabinet/SiteReportTest.php` — жалобы: гость, своё объявление,
  вне каталога, повтор, неизвестная причина;
- `tests/Feature/Orchid/ModerationQueueTest.php` — очередь, approve/reject,
  приём и отклонение жалобы, права доступа.

JS (155 тестов): `tests/js/reportService.spec.js`, `tests/js/ModerationBadge.spec.js`.

---

## Слой 3. Предложения (не реализовано)

### 3.1. Приоритет 1 — дешёво и закрывает основные дыры

**a) Уведомления продавцу о решении модерации.**
Сейчас продавец узнаёт статус только зайдя в кабинет. Нужно: уведомление при
`approved` / `rejected` (email или внутреннее), с причиной и ссылкой на правку.
Технически — событие `SiteModerationChanged` + `Notification`.

**b) Апелляция на отклонение.**
Кнопка «Оспорить» в кабинете: продавец пишет пояснение, объявление возвращается
в `pending` с пометкой «апелляция». Без этого отклонение = потерянный клиент.

**c) Защита от «бомбинга» жалобами.**
Сейчас 5 жалоб/мин с одного аккаунта, но конкурент может залить объявление жалобами
с нескольких аккаунтов. Нужно: автоприостановка публикации при N жалобах от разных
пользователей за сутки (например, 3), с уведомлением модератору — но не автоматический
reject.

**d) Дедупликация объявлений.**
`hash` нормализованного `description` + сравнение `domain` по «основному» домену
(без поддоменов). Сейчас от повторной публикации того же лота защищает только
`AvailableDomain` по точному домену.

**e) Honeypot + минимальное время заполнения формы.**
Скрытое поле, которое бот заполнит, и проверка, что форма заполнялась не мгновенно.
Бесплатная замена части капчи, не мешает людям.

### 3.2. Приоритет 2 — качество решений

**f) Классификация текста через LLM.**
Один запрос к модели на объявление: «это описание продажи сайта или реклама/скам?» —
возвращает `score` и категорию. Порог: высокий → `rejected` автоматически с пометкой
«авто», средний → `pending` с флагом «требует внимания», низкий → обычный поток.
Даёт то, чего regex не умеет: замаскированные контакты, «звоните в личку», продажа
услуг вместо сайта.

**g) Веса жалоб по репутации.**
Вес жалобы зависит от «возраста» аккаунта, количества своих успешных объявлений
и истории (сколько жалоб подтверждено). Аккаунт без истории весит 0,2; проверенный
продавец — 1,0. Порог авто-приостановки считается по сумме весов, а не по количеству.

**h) Репутация продавца и авто-доверие.**
Счётчик: сколько объявлений одобрено / отклонено. При N успешных проверок домена
и отсутствии отклонений — автопрув без очереди (проверка домена остаётся), при
3 отклонениях — все новые объявления идут в ручную очередь, при 5 — блокировка
создания с обращением в поддержку.

**i) Периодическая перепроверка доменов.**
Scheduled job: раз в неделю проверять `publiclyVisible` объявления на живость домена
и Safe Browsing. Домен отвалился / попал в списки → `rejected` с причиной
«домен недоступен» и уведомлением. Иначе каталог со временем заполняется мёртвыми
ссылками.

**j) Чёрный список доменов и зон.**
Таблица `blocked_domains` (точные домены, поддомены, regex по зоне) + админка.
Дёшево закрывает повторную регистрацию уже забаненных доменов под новым аккаунтом.

### 3.3. Приоритет 3 — на потом, когда пойдёт поток

- **Проверка контактов вне площадки в переписке.** Сейчас антиспам работает только
  на объявлениях; в сообщениях продавец может увести покупателя в Telegram.
  Вариант: мягкое предупреждение + флаг модератору, без блокировки отправки.
- **Аналитика антиспама.** Дашборд: сколько объявлений создано / автоодобрено /
  отклонено, топ причин, доля ложных отклонений, среднее время в очереди.
  Без этого пороги не настроить.
- **A/B порогов.** Вынести пороги (жалобы, LLM-score, возраст домена) в конфиг
  с возможностью менять без деплоя.
- **Юридический контур.** Хранение жалоб и решений для ответов на обращения,
  сроки хранения, выгрузка по запросу.

### 3.4. Что осознанно не делалось

- Отдельный экран «Жалобы» со списком всех жалоб (сейчас только внутри карточки
  объявления) — при потоке понадобится, сейчас достаточно.
- Массовые действия в очереди модерации (bulk approve) — опасно без аудита.
- Автоматический reject по Safe Browsing / RDAP — сейчас это только повод не одобрять
  автоматически, решение остаётся за модератором.
