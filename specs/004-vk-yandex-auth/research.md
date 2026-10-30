# Research: Вход через VK ID и Yandex ID

**Feature**: `004-vk-yandex-auth` | **Date**: 2026-10-29

Спецификация не содержит маркеров `[NEEDS CLARIFICATION]`: все поведенческие вопросы закрыты в `spec.md`. Ниже зафиксированы технические решения, необходимые для реализации, и рассмотренные альтернативы.

---

## 1. OAuth-библиотеки и разделение потоков

**Decision**: Для Яндекса — `laravel/socialite ^5.31` + `socialiteproviders/yandex ^4.2`; для VK ID — `@vkid/sdk ^2.6.5` во фронтенде и обмен кода на сервере через HTTP-клиент Laravel. Это повторяет эталон `/home/kovaleff/www/cheap-eats`.

**Rationale**:
- Яндекс-провайдер Socialite уже реализует авторизацию, обмен кода, `state` и нормализацию профиля; писать OAuth-клиент вручную нет причин.
- VK ID SDK — официальный способ получить `code` + `device_id` + `code_verifier` с PKCE; серверный обмен повторяет документированный flow VK ID.
- Обе библиотеки используются в эталонном проекте на той же версии Laravel, что снижает риск несовместимости.

**Alternatives considered**:
- Единый Socialite-провайдер для VK: в эталоне не используется, а VK ID требует PKCE и `device_id`, которые удобнее отдавать официальным SDK.
- Полностью серверный redirect-flow для VK без SDK: потребовал бы вручную поддерживать PKCE, `state` и формат возврата VK; больше кода и риска.
- Собственный OAuth-клиент для Яндекса: дублирует Socialite и увеличивает поверхность ошибок.

**Compatibility**:
- `laravel/socialite ^5.31` совместим с Laravel 13; провайдер Яндекса регистрируется через `SocialiteProviders\Manager\SocialiteWasCalled`.
- `@vkid/sdk ^2.6.5` не имеет серверных зависимостей и собирается Vite 8.
- **Установка требует согласования**: зависимости отсутствуют в `composer.json`/`package.json` проекта.

---

## 2. Аутентификация: сессия, а не токены

**Decision**: Использовать штатную сессионную аутентификацию Laravel в группе `web`; Sanctum не подключать.

**Rationale**:
- SPA и API находятся на одном домене; cookie-сессия уже настроена (`SESSION_DRIVER=database`), а `Auth::login()` в web-группе сразу делает пользователя авторизованным для последующих запросов.
- Спецификация требует постоянную сессию после закрытия браузера и не требует мобильных клиентов или сторонних API-потребителей.
- Эталон использует тот же подход для веб-колбэков.

**Alternatives considered**:
- Sanctum SPA-аутентификация: добавляет зависимость, `/sanctum/csrf-cookie`, `statefulApi()` и отдельную конфигурацию, не давая выгоды в монолите.
- Токены в `localStorage`: противоречат требованию серверной сессии и повышают риск XSS.
- JWT: избыточен, требует управления подписью и отзывом.

**Details**:
- `Auth::login($user, true)` включает «запомнить меня» (FR-003).
- `$request->session()->regenerate()` после входа защищает от фиксации сессии (FR-044).
- После `logout()` выполняются `invalidate()` и `regenerateToken()`; новый CSRF-токен возвращается фронтенду.

---

## 3. Единый провижининг аккаунтов

**Decision**: Оба провайдера приводят профиль к `ProviderProfile` и вызывают один `AccountProvisioner`. Сервисы `VkAuthService`/`YandexAuthService` отвечают только за получение профиля.

**Rationale**:
- Правила привязки по email, конфликта идентификаторов и технических адресов должны совпадать для VK и Яндекса (FR-012, FR-024, FR-025, FR-035, FR-038). Дублирование логики почти гарантирует расхождение.
- Один сервис — одна точка для транзакции, блокировки строки, обработки `QueryException` и логирования.
- В эталоне логика продублирована, но у VK там нет привязки по email; спецификация требует её для обоих, поэтому копирование эталона недостаточно.

**Alternatives considered**:
- Две независимые реализации, как в `cheap-eats`: проще скопировать, но нарушает единые правила и усложняет тесты.
- Полиморфная таблица `social_identities`: архитектурно чище для многих провайдеров, но требует миграции и переделки существующих колонок; спецификация фиксирует текущую схему.

**Details**:
- Поиск по email — `whereRaw('LOWER(email) = ?', [...])` + `lockForUpdate()` внутри транзакции.
- Чужая непустая привязка не перезаписывается; создаётся отдельный аккаунт с `vk<id>@vk.users` / `ya<id>@ya.users`.
- При `QueryException` (параллельный callback) выполняется повторный поиск по внешнему идентификатору.
- Пароль генерируется случайно только для удовлетворения схемы и не используется для входа.

---

## 4. Хранение аватара

**Decision**: Ссылка на фото любого провайдера сохраняется в существующую колонку `users.avatar` (`varchar(2048)`), файл не копируется.

**Rationale**:
- Схема уже содержит одно поле `avatar`; отдельных `vk_photo`/`ya_photo` в проекте нет.
- Спецификация разрешает хранить ссылку и требует заглушку при недоступной картинке (FR-028).
- Одно поле упрощает payload и шапку.

**Alternatives considered**:
- Добавить `vk_photo`/`ya_photo`, как в эталоне: лишняя миграция ради данных, которые не нужны одновременно.
- Скачивать изображения в storage: требует дисковой политики, очистки и обработки размеров; вне рамок фичи.

---

## 5. Ошибки, коды возврата и лимиты

**Decision**: Единый enum `App\Enums\AuthError` с кодами и русскими сообщениями. OAuth-маршруты защищены `throttle:10,1`. `ThrottleRequestsException` рендерится в `bootstrap/app.php`: JSON → `429`, навигация → `302 /?auth_error=too_many_requests`.

**Rationale**:
- Спецификация требует одинаковое сообщение при лимите и разные понятные сообщения при отказе/ошибке провайдера.
- Централизованный enum исключает расхождение текстов между контроллерами.
- Рендер исключения в одном месте покрывает все три маршрута, не дублируя проверку в контроллерах.
- Сессия не меняется, потому что throttle срабатывает до контроллера.

**Alternatives considered**:
- Отдельный middleware на каждом маршруте: дублирование и риск забыть маршрут.
- Возврат `429` всегда: violates FR-016/FR-034 для браузерной навигации.
- Тексты прямо в контроллерах: рассинхронизация с фронтендом.

**Details**:
- Коды Яндекса сохраняют паритет с эталоном: `cancelled`, `state`, `failed`, `not_configured`.
- Для VK добавляются `vk_cancelled`, `vk_failed`, `vk_not_configured`.
- Общий код лимита — `too_many_requests`.
- JSON-формат ошибки: `{ "error": "<code>", "message": "<русский текст>" }`.
- В журнал пишутся только `reason`, `user_id`, `provider`, `auth_error`; коды, токены и секреты не логируются.

---

## 6. CSRF и cookie для VK PKCE

**Decision**: `preventRequestForgery(except: ['api/vk/callback'])` и `encryptCookies(except: ['vk_code_verifier'])`. Остальные маршруты сохраняют штатную CSRF-защиту.

**Rationale**:
- VK возвращает код формой с чужого домена; CSRF-токен для этого запроса недоступен.
- `code_verifier` кладётся в first-party cookie, потому что `redirect_uri` обязан байт-в-байт совпадать с зарегистрированным и не может содержать лишних параметров.
- Laravel 13 помечает `validateCsrfTokens()` как deprecated; используется `preventRequestForgery()`.

**Alternatives considered**:
- Отключить CSRF для всех `/api/*`: снижает защиту сессионных маршрутов.
- Передавать `code_verifier` в `redirect_uri`: ломает сверку адреса в кабинете VK.
- Хранить verifier только в `sessionStorage` и передавать его в fetch: работает для fetch-ветки, но не для навигационной; cookie покрывает оба случая.

**Details**:
- Cookie ставится на `path=/`, `max-age=600`, `SameSite=Lax` на HTTP и `SameSite=None; Secure` на HTTPS (как в эталоне).
- Контроллер удаляет cookie после попытки.
- `state` VK проверяется на клиенте (SDK генерирует его в браузере); серверная проверка `state` для VK невозможна без изменения контракта SDK.

---

## 7. Сессионные маршруты и CSRF для SPA

**Decision**: `GET /api/v1/user` и `POST /api/v1/auth/logout` в web-группе. В `app.blade.php` добавляется `<meta name="csrf-token">`; фронтенд использует общий `http.js`, который шлёт `X-CSRF-TOKEN`.

**Rationale**:
- SPA нужен один bootstrap-запрос и защищённый выход (FR-005, FR-006).
- Web-группа даёт доступ к сессии; `shouldRenderJsonWhen(api/*)` уже настроен в `bootstrap/app.php`.
- Мета-тег — стандартный способ передать CSRF-токен без дополнительного запроса.

**Alternatives considered**:
- `GET /sanctum/csrf-cookie`: требует Sanctum.
- Читать `XSRF-TOKEN` из cookie: Laravel не ставит её по умолчанию.
- Отключить CSRF для logout: позволяет CSRF-выход.

**Details**:
- `GET /api/v1/user` всегда `200`: `{ authenticated: false, user: null }` или `{ authenticated: true, user: {...} }`.
- `POST /api/v1/auth/logout` возвращает `{ authenticated: false, csrfToken }`, чтобы фронтенд обновил мета-тег после `regenerateToken()`.
- Payload пользователя: `id`, `displayName`, `email`, `avatarUrl`, `provider`. `provider` вычисляется по последнему входу (`vk_last_login_at`/`ya_last_login_at`); при равенстве — `vk`.
- `http.js` использует `credentials: 'same-origin'`, иначе браузер не сохранит сессионную и remember-cookie после fetch-входа.
- VK callback в JSON-ветке возвращает тот же payload, чтобы store не делал второй запрос.

---

## 8. Фронтенд: замена демо-сессии

**Decision**: `sessionService.js` и `stores/session.js` переводятся на реальные HTTP-запросы; логика VK SDK и разбора возврата выносится в `composables/useAuth.js`. `App.vue` обрабатывает возврат после инициализации store.

**Rationale**:
- Сейчас `sessionService` хранит демо-пользователя в `localStorage`; это должно исчезнуть, иначе реальный вход не влияет на интерфейс.
- `app.js` инициализирует store до монтирования, поэтому обработку `code`/`auth_error` нужно делать в `App.vue` после `initialize()`, иначе можно перезаписать реальную сессию.
- Шапка уже вызывает `session.loginWithVk()`/`loginWithYandex()` и показывает `displayName`/`avatarUrl`; контракт payload сохраняет эти имена, поэтому шаблон не меняется.

**Alternatives considered**:
- Оставить демо-сервис и добавить второй путь: два источника истины, тесты станут неоднозначными.
- Обрабатывать callback в `app.js` до монтирования: усложняет тестирование и смешивает bootstrap с UI-уведомлениями.
- Переписать шапку на composable целиком: лишние изменения визуально проверенного компонента.

**Details**:
- `useAuth` предоставляет `bootstrap`, `loginWithVk`, `loginWithYandex`, `handleVkCallback`, `consumeAuthError`.
- `handleVkCallback` сравнивает `state`, берёт `code_verifier` из `sessionStorage`/cookie, вызывает `POST /api/vk/callback` через `http.js` с JSON-телом `{code, device_id, code_verifier}`, очищает `sessionStorage` и адресную строку.
- `consumeAuthError` показывает toast по коду и удаляет `auth_error` из URL один раз.
- Демонстрационные `MOCK_SCENARIO_KEY`/`SESSION_STORAGE_KEY` больше не используются сервисом сессии; `MOCK_SCENARIO_KEY` остаётся для мок-каталога.

---

## 9. Административные колонки и фильтры Orchid

**Decision**: В `App\Models\User` добавить `vk_id`/`ya_id` в `$allowedFilters` (тип `Where`) и `$allowedSorts`; в `UserListLayout` добавить два `TD` с `filter(Input::make())`, `sort()` и `defaultHidden()` не применять.

**Rationale**:
- Orchid применяет фильтры только к полям из `$allowedFilters`; без этого `TD::filter()` не сработает.
- Точное сравнение по числовому ID предсказуемо и использует уникальный индекс.
- Колонки — поля текущей строки, поэтому `withCount` и пагинация не меняются.

**Alternatives considered**:
- Отдельный экран/отчёт: спецификация explicitly не требует.
- `Like`-фильтр по ID: не использует индекс и даёт неожиданные частичные совпадения.
- Вычисляемая колонка «источник входа»: можно добавить позже; сейчас достаточно двух ID и времён входа.

**Details**:
- Для `null` в колонке отображается `—`.
- В список также добавляются `id` и скрытые по умолчанию `vk_last_login_at`/`ya_last_login_at` (FR-039: идентификатор и время входа доступны администратору).
- `UserPresenter::image()` использует `users.avatar` с fallback на Gravatar, иначе аватар провайдера не виден в списке.
- `$fillable` расширяется, иначе `create()`/`fill()` в провижининге молча отбросит поля.

---

## 10. Тестирование

**Decision**: PHPUnit feature-тесты на оба потока и сессию; `Http::fake` для VK, Mockery для Socialite; Vitest для store/composable/шапки. Rate-limit тесты очищают лимитер перед каждым сценарием.

**Rationale**:
- Реальные обращения к провайдерам в тестах недопустимы.
- Feature-тесты проверяют маршруты, сессию, CSRF-исключения и редиректы — то, что нельзя надёжно проверить unit-тестами.
- Vitest покрывает разбор возврата, очистку URL и состояния кнопок.

**Alternatives considered**:
- Только unit-тесты сервисов: не поймают проблемы маршрутов/сессии/CSRF.
- End-to-end с реальными провайдерами: нестабильно и требует секретов.
- Snapshot-тесты вёрстки: не проверяют поведение.

**Details**:
- VK: `Http::fake` для `https://id.vk.ru/oauth2/auth` и `https://api.vk.com/method/users.get`.
- Yandex: mock `Laravel\Socialite\Contracts\Factory` → `driver()->redirectUrl()->user()`.
- Проверки: создание, повторный вход без дубля, привязка по email, конфликт привязки → технический email, отказ, просроченный state, лимит, `Auth::check()`, remember-cookie, регенерация сессии.
- Админка: HTTP-запрос к `platform.systems.users` с `filter[vk_id]`/`filter[ya_id]` под администратором; при хрупкости — проверка состава `UserListLayout::columns()` и `User::allowedFilters`.
- `php artisan test --compact`, `npm run test`, `vendor/bin/pint --dirty --format agent`.

---

## 11. Открытые вопросы и зависимости

| Вопрос | Статус | Решение |
|---|---|---|
| Установка `laravel/socialite`, `socialiteproviders/yandex`, `@vkid/sdk` | BLOCKED | Требуется явное согласование пользователя |
| `VITE_VK_CLIENT_ID` отсутствует в `.env` | TODO | Добавить в `.env` и `.env.example` при реализации |
| Боевой `VK_CLIENT_ID`/`VK_CLIENT_SECRET` | Настроены | Значения уже есть в `.env`; в документации не раскрываются |
| Локальный HTTPS `https://localhost` | Настроен | В проекте есть `localhost.pem`/`localhost-key.pem`; OAuth-приложение зарегистрировано на локальный callback |
| Нужна ли миграция | Нет | Все поля и индексы уже присутствуют |

Маркеров `[NEEDS CLARIFICATION]` не осталось.
