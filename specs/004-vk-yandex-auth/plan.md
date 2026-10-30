# Implementation Plan: Вход через VK ID и Yandex ID

**Branch**: `004-vk-yandex-auth` | **Date**: 2026-10-29 | **Spec**: [spec.md](./spec.md)

**Input**: Feature specification from `/specs/004-vk-yandex-auth/spec.md`

## Summary

Заменить демонстрационную авторизацию реальным входом через VK ID и Yandex ID, сохранив текущие кнопки и визуальный стиль шапки. Оба провайдера используют серверную сессию Laravel («запомнить меня»), создают или находят аккаунт по внешнему идентификатору, а при неизвестном идентификаторе привязываются к существующему аккаунту по совпавшему email. Конфликт привязки не перезаписывается: создаётся отдельный аккаунт с техническим email. Ошибки и превышение лимита возвращают пользователя на главную с русским сообщением, не меняя сессию.

Технический подход: Yandex — серверный OAuth-поток `laravel/socialite` + `socialiteproviders/yandex`; VK — `@vkid/sdk` в SPA с обменом кода на сервере. Общая логика сопоставления аккаунтов вынесена в один провижининг-сервис, чтобы правила для VK и Яндекса совпадали. Состояние авторизации отдаётся SPA через `GET /api/v1/user`, выход — `POST /api/v1/auth/logout`. В существующий список пользователей Orchid добавляются колонки и фильтры по `vk_id`/`ya_id`.

**Dependency gate**: установка `laravel/socialite`, `socialiteproviders/yandex` и `@vkid/sdk` требует согласования с пользователем до начала реализации.

## Technical Context

**Language/Version**: PHP 8.3.36; JavaScript (ESM, без TypeScript), Node.js 22.22

**Primary Dependencies**: Laravel Framework 13.39, Orchid Platform 14.53, Vue 3.5.44, Vue Router 4.6, Pinia 3.0, vue-sonner 2.0, Vite 8, Tailwind CSS 4. Новые зависимости (ожидают согласования): `laravel/socialite ^5.31`, `socialiteproviders/yandex ^4.2`, `@vkid/sdk ^2.6.5`.

**Storage**: MySQL. Таблица `users` уже содержит `avatar`, `vk_id`, `ya_id`, `vk_last_login_at`, `ya_last_login_at`; `email` и `password` nullable, уникальные индексы `users_email_unique`, `users_vk_id_unique`, `users_ya_id_unique` есть. Сессии — драйвер `database`. Новых миграций не требуется.

**Testing**: PHPUnit 12 (feature-тесты, `Http::fake`, mock Socialite), Vitest 5 + `@vue/test-utils` (jsdom), Laravel Pint.

**Target Platform**: Linux-сервер; браузеры с поддержкой ES2020. Production — `https://sites-shop.ru`, локальная разработка — `https://localhost` (OAuth-приложения зарегистрированы на оба адреса).

**Project Type**: Web application — Laravel-монолит с Vue 3 SPA и административной панелью Orchid.

**Performance Goals**: bootstrap сессии — один запрос `GET /api/v1/user`; обработка callback без учёта времени провайдера — p95 < 500 мс; список пользователей админки не получает дополнительных запросов (колонки — поля той же строки).

**Constraints**: секреты провайдеров только на сервере; адрес возврата Яндекса сверяется с явным списком; `state` Яндекса живёт не более 10 минут; не более 10 обращений к auth-маршрутам в минуту с адреса; сессия постоянная для обоих провайдеров; ошибки не раскрывают коды и токены.

**Scale/Scope**: небольшая биржа; 3 OAuth-маршрута, 2 сессионных маршрута, 2 новых столбца и 2 фильтра в админке, 2 провайдера.

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

`.specify/memory/constitution.md` остаётся нератифицированным шаблоном с плейсхолдерами (`[PROJECT_NAME]`, `[PRINCIPLE_1_NAME]` и т.д.), поэтому формальных гейтов и принципов, подлежащих проверке, нет. Вместо них применяются правила репозитория из `AGENTS.md`:

| Проверка | Статус |
|---|---|
| Не создавать новые базовые каталоги без согласования | PASS — используются существующие `app/Services`, `app/Http/Controllers/Api`, `app/Exceptions` |
| Не менять зависимости без согласования | GATE — три новые библиотеки вынесены на явное согласование |
| Следовать стилю соседних файлов, использовать `make:` команды | PASS — план использует штатные генераторы Laravel |
| Покрыть изменение тестами, прогнать Pint | PASS — тесты и форматирование включены в план |
| Не трогать незакоммиченные изменения пользователя | PASS — изменения ограничены перечисленными файлами |

Нарушений, требующих `Complexity Tracking`, нет. Единственный блокирующий пункт — согласование зависимостей; он не является архитектурным нарушением.

**Post-design re-check (Phase 1)**: PASS. Дизайн не добавляет новых базовых каталогов, не вводит второго фронтенд-проекта и не расширяет схему БД. Зависимости остаются единственным внешним согласованием.

## Project Structure

### Documentation (this feature)

```text
specs/004-vk-yandex-auth/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/
│   ├── auth-api.yaml    # OpenAPI-контракт OAuth и сессионных маршрутов
│   └── spa-auth-errors.md # Коды ошибок и сообщения для SPA
├── checklists/
│   └── requirements.md  # Уже создан
└── tasks.md             # Phase 2 output (/speckit.tasks, не создаётся здесь)
```

### Source Code (repository root)

```text
app/
├── Enums/
│   └── AuthError.php                         # NEW: коды и русские сообщения ошибок
├── Exceptions/
│   ├── VkAuthException.php                   # NEW
│   └── YandexAuthException.php               # NEW
├── Http/
│   ├── Controllers/Api/
│   │   ├── SessionController.php             # NEW: GET /api/v1/user, POST /api/v1/auth/logout
│   │   ├── VkAuthController.php              # NEW
│   │   └── YandexAuthController.php          # NEW
│   └── Resources/
│       └── SessionUserResource.php           # NEW: единый payload пользователя для SPA
├── Models/
│   └── User.php                              # MODIFIED: fillable + allowedFilters/allowedSorts
├── Orchid/
│   ├── Layouts/User/UserListLayout.php       # MODIFIED: колонки ID/VK ID/Яндекс ID/вход
│   └── Presenters/UserPresenter.php          # MODIFIED: аватар из users.avatar
├── Providers/
│   └── AppServiceProvider.php                # MODIFIED: SocialiteWasCalled → Yandex provider
└── Services/
    ├── SocialAuth/
    │   ├── AccountProvisioner.php            # NEW: единые правила привязки/создания
    │   └── ProviderProfile.php               # NEW: DTO профиля провайдера
    ├── VkAuthService.php                     # NEW: обмен кода VK и получение профиля
    └── YandexAuthService.php                 # NEW: профиль Socialite → ProviderProfile

bootstrap/app.php                             # MODIFIED: CSRF/cookie exceptions, throttle renderer
config/services.php                           # MODIFIED: vk, yandex
routes/web.php                                # MODIFIED: OAuth + session routes
resources/
├── views/app.blade.php                       # MODIFIED: <meta name="csrf-token">
└── js/
    ├── App.vue                               # MODIFIED: обработка возврата и bootstrap
    ├── app.js                                # MODIFIED: инициализация сессии (при необходимости)
    ├── components/layout/SiteHeader.vue      # MODIFIED: только обработчики входа/выхода
    ├── composables/useAuth.js                # NEW: VK SDK, callback, ошибки, редиректы
    ├── lib/authErrors.js                     # NEW: коды → сообщения
    ├── services/http.js                      # NEW: fetch с CSRF и JSON
    ├── services/sessionService.js            # MODIFIED: реальные запросы вместо localStorage
    └── stores/session.js                     # MODIFIED: реальное состояние сессии

tests/
├── Feature/Auth/
│   ├── VkAuthTest.php                        # NEW
│   ├── YandexAuthTest.php                    # NEW
│   └── SessionTest.php                       # NEW
├── Feature/Admin/
│   └── UserListAuthColumnsTest.php           # NEW
└── js/
    ├── useAuth.spec.js                       # NEW
    ├── sessionStore.spec.js                  # MODIFIED
    └── SiteHeader.spec.js                    # MODIFIED при изменении пропсов/обработчиков
```

**Structure Decision**: Используется существующий Laravel-монолит с Vue SPA. Серверная авторизация живёт в `app/Http/Controllers/Api`, бизнес-правила — в `app/Services/SocialAuth`, админ-представление — в существующих Orchid layouts. Отдельный API-проект или второй фронтенд не создаются.

## Design Overview

### 1. Потоки входа

**VK ID**: SPA генерирует `state` и `code_verifier`, сохраняет их в `sessionStorage`, кладёт `code_verifier` в first-party cookie `vk_code_verifier` и запускает `VKID.Auth.login()`. Возврат приходит на `/api/vk/callback`:
- fetch с `Accept: application/json` и JSON-телом `{code, device_id, code_verifier}` → контроллер обменивает код, логинит пользователя и отдаёт JSON; служебные данные не попадают в URL;
- навигация/форма → контроллер читает `code`/`state`/`device_id` из запроса, логинит пользователя и делает `redirect('/')`.

`redirect_uri` для обмена собирается из текущего запроса (`$request->url()`), чтобы совпадать с зарегистрированным адресом на localhost и production. Cookie `vk_code_verifier` удаляется после попытки.

**Yandex ID**: кнопка ведёт на `GET /api/ya/redirect`. Контроллер проверяет конфигурацию и белый список адресов, кладёт `ya_oauth_started_at` в сессию и уводит в Яндекс через Socialite. Возврат `GET /api/ya/callback` проверяет срок попытки, обрабатывает отказ, обменивает код, логинит пользователя и очищает `state`/`ya_oauth_started_at`.

### 2. Сопоставление аккаунтов

Оба провайдера передают `ProviderProfile` (provider, providerId, name, email, avatarUrl) в `AccountProvisioner`. Порядок:

1. Найти пользователя по `vk_id`/`ya_id` → обновить профиль и время входа.
2. Если не найден и email передан → найти по `LOWER(email)` с блокировкой строки:
   - привязка провайдера свободна → привязать и обновить профиль;
   - привязка занята этим же ID → обновить профиль;
   - привязка занята другим ID → не перезаписывать, создать отдельный аккаунт с техническим email.
3. Иначе создать аккаунт с техническим email `vk<id>@vk.users` / `ya<id>@ya.users`, случайным паролем (только из-за ограничения схемы) и временем входа.

Уникальные индексы и транзакция защищают от дублей при параллельных callback; `QueryException` обрабатывается повторным поиском по внешнему идентификатору.

### 3. Сессия и SPA

- `Auth::login($user, true)` — постоянная сессия «запомнить меня».
- `$request->session()->regenerate()` после входа (FR-044).
- `GET /api/v1/user` → `{ authenticated, user }`; для гостя `200` с `user: null`.
- `POST /api/v1/auth/logout` → `Auth::logout()`, `invalidate()`, `regenerateToken()`, ответ `{ authenticated: false, csrfToken }`.
- CSRF-токен доступен через `<meta name="csrf-token">`; `services/http.js` отправляет `X-CSRF-TOKEN`, `X-Requested-With: XMLHttpRequest` и использует `credentials: 'same-origin'`, чтобы браузер сохранил сессионную и remember-cookie.
- `App.vue` при монтировании обрабатывает параметры возврата (`code`/`error`/`auth_error`), показывает toast один раз и очищает адресную строку через `history.replaceState`.

### 4. Ошибки и лимиты

`app/Enums/AuthError` — единственный источник кодов и сообщений:

| Код | Сообщение |
|---|---|
| `too_many_requests` | Слишком много попыток входа. Попробуйте позже |
| `not_configured` | Вход через Яндекс пока не настроен |
| `vk_not_configured` | Вход через VK ID пока не настроен |
| `cancelled` | Вход через Яндекс отменён |
| `vk_cancelled` | Вход через VK ID отменён |
| `state` | Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз |
| `failed` | Не удалось войти через Яндекс. Попробуйте ещё раз |
| `vk_failed` | Не удалось войти через VK ID. Попробуйте ещё раз |

Маршруты используют `throttle:10,1`. В `bootstrap/app.php` `ThrottleRequestsException` рендерится так: JSON-запрос → `429` с `{ error, message }`; браузерная навигация → `302 /?auth_error=too_many_requests`. Сессия при этом не изменяется.

### 5. Административная панель

В `App\Models\User` переопределяются `$fillable` (добавляются `avatar`, `vk_id`, `ya_id`, `vk_last_login_at`, `ya_last_login_at`) и `$allowedFilters`/`$allowedSorts` (добавляются `vk_id`, `ya_id`, времена входа). В `UserListLayout` добавляются:
- `TD::make('id', 'ID')` — идентификатор аккаунта;
- `TD::make('vk_id')` и `TD::make('ya_id')` — с `filter(Input::make())`, сортировкой и прочерком для `null`;
- `TD::make('vk_last_login_at')` и `TD::make('ya_last_login_at')` — `DateTimeSplit`, сортировка, `defaultHidden()` (доступны через переключатель колонок).

`UserPresenter::image()` отдаёт `users.avatar`, если он заполнен, иначе прежний Gravatar; это делает аватар провайдера видимым в списке. Поиск по имени/email сохраняется.

**Критерий приёмки (SC-009)**: администратор находит пользователя по `vk_id`/`ya_id`, видит его имя, email, аватар, источник входа и время последнего входа не более чем за 30 секунд.

### 6. Конфигурация окружения

`config/services.php`:

```php
'vk' => [
    'client_id' => env('VK_CLIENT_ID'),
    'client_secret' => env('VK_CLIENT_SECRET'),
    'redirect_uri' => env('VK_REDIRECT_URI', '/api/vk/callback'),
],
'yandex' => [
    'client_id' => env('YA_CLIENT_ID'),
    'client_secret' => env('YA_CLIENT_SECRET'),
    'redirect' => env('YA_REDIRECT_URI', '/api/ya/callback'),
    'allowed_redirects' => env('YA_ALLOWED_REDIRECTS'),
],
```

В `.env` и `.env.example` добавляются `VITE_VK_CLIENT_ID` и полный набор `VK_*`/`YA_*`. Секреты в клиентский бандл не попадают.

## Implementation Outline

1. Согласовать и установить зависимости; добавить конфигурацию и listener Socialite.
2. Реализовать `ProviderProfile` + `AccountProvisioner`, затем `VkAuthService` и `YandexAuthService`.
3. Добавить контроллеры, маршруты, enum ошибок, throttle-renderer и CSRF/cookie исключения.
4. Добавить сессионные маршруты, ресурс пользователя и CSRF meta.
5. Перевести фронтенд на реальные запросы: `http.js`, `sessionService`, store, `useAuth`, `App.vue`, обработчики шапки.
6. Добавить Orchid-колонки и фильтры, обновить `User`.
7. Написать feature-тесты и Vitest-тесты; прогнать `php artisan test --compact`, `npm run test`, `vendor/bin/pint --dirty --format agent`.

## Complexity Tracking

Нарушений конституции нет. Таблица не заполняется.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
