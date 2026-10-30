---

description: "Task list template for feature implementation"
---

# Tasks: Вход через VK ID и Yandex ID

**Input**: Design documents from `/specs/004-vk-yandex-auth/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Включены — plan.md (Implementation Outline, шаг 7) требует feature-тесты PHPUnit и Vitest-тесты.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `app/`, `routes/`, `resources/`, `tests/` at repository root (Laravel-монолит + Vue SPA)

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Установленные зависимости и конфигурация провайдеров

- [x] T001 Установить `laravel/socialite ^5.31`, `socialiteproviders/yandex ^4.2` (composer) и `@vkid/sdk ^2.6.10` (npm) — одобрено пользователем 2026-10-29
- [x] T002 [P] Добавить блоки `vk` и `yandex` в `config/services.php` по `plan.md` §6 (`client_id`, `client_secret`, `redirect_uri`/`redirect`, `allowed_redirects`)
- [x] T003 [P] Добавить в `.env` и `.env.example` ключи `VITE_VK_CLIENT_ID`, `VK_CLIENT_ID`, `VK_CLIENT_SECRET`, `VK_REDIRECT_URI='/api/vk/callback'`, `YA_CLIENT_ID`, `YA_CLIENT_SECRET`, `YA_REDIRECT_URI='/api/ya/callback'`, `YA_ALLOWED_REDIRECTS='https://sites-shop.ru/api/ya/callback,https://localhost/api/ya/callback'` (FR-041, FR-042)
- [x] T004 Зарегистрировать Yandex-провайдер Socialite: `Event::listen(SocialiteWasCalled::class, YandexExtendSocialite::class)` в `app/Providers/AppServiceProvider.php` (по образцу `cheap-eats/app/Providers/AppServiceProvider.php`)

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Ядро авторизации, общее для всех историй

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

- [x] T005 [P] Создать enum `app/Enums/AuthError.php` с кодами и русскими сообщениями из `plan.md` §4: `too_many_requests`, `not_configured`, `vk_not_configured`, `cancelled`, `vk_cancelled`, `state`, `failed`, `vk_failed` (FR-008)
- [x] T006 [P] Создать DTO `app/Services/SocialAuth/ProviderProfile.php`: `provider` (enum vk|ya), `providerId`, `name`, `email`, `avatarUrl` по `data-model.md`
- [x] T007 Создать `app/Services/SocialAuth/AccountProvisioner.php` с алгоритмом из `data-model.md`: поиск по `vk_id`/`ya_id` → поиск по `LOWER(email)` внутри транзакции с `lockForUpdate()` → создание с техническим email `vk<id>@vk.users` / `ya<id>@ya.users`; привязка другого ID провайдера не перезаписывается (FR-012, FR-022…FR-027, FR-035, FR-037); обработка `QueryException` повторным поиском по внешнему ID (depends: T006)
- [x] T008 [P] Создать `app/Exceptions/VkAuthException.php` и `app/Exceptions/YandexAuthException.php` (код ошибки из `AuthError`, без технических деталей в сообщении)
- [x] T009 В `bootstrap/app.php`: (а) рендер `ThrottleRequestsException` — JSON-запрос → `429 {error, message}`, навигация → `302 /?auth_error=too_many_requests`, без изменения сессии (FR-016, FR-034); (б) `preventRequestForgery(except: ['api/vk/callback'])`; (в) `encryptCookies(except: ['vk_code_verifier'])` (FR-011, FR-015)
- [x] T010 [P] Добавить `<meta name="csrf-token" content="{{ csrf_token() }}">` в `resources/views/app.blade.php`
- [x] T011 [P] Создать `resources/js/services/http.js`: fetch с `credentials: 'same-origin'`, заголовками `X-CSRF-TOKEN`, `X-Requested-With: XMLHttpRequest`, `Accept: application/json` (по `contracts/auth-api.yaml`)
- [x] T012 [P] Создать `resources/js/lib/authErrors.js`: маппинг кодов ошибок из `contracts/spa-auth-errors.md` на русские сообщения

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Первый вход через VK ID (Priority: P1) 🎯 MVP

**Goal**: Гость нажимает «Войти через VK ID», проходит OAuth и оказывается авторизованным; аккаунт создаётся автоматически (FR-001…FR-016)

**Independent Test**: В чистом браузере без сессии открыть сайт, нажать кнопку VK, подтвердить доступ → возврат на сайт, имя и аватар в шапке, пользователь с `vk_id` в админке, вход переживает обновление страницы; отдельно — вход по email существующего аккаунта

### Tests for User Story 1

- [x] T013 [P] [US1] Feature-тест `tests/Feature/Auth/VkAuthTest.php` (FAIL до реализации): первый вход создаёт аккаунт с `vk_id`, именем, аватаром, `vk_last_login_at`; email-матчинг в существующий аккаунт без дубля; коллизия email → технический `vk<id>@vk.users` без перезаписи `vk_id`; повторный вход без дубля и с обновлением профиля; отмена → сессия не выдана; `throttle:10,1` → 429/редирект `/?auth_error=too_many_requests` без изменения сессии; `GET /api/vk/callback` без параметров → ошибка без аккаунта (FR-011…FR-016)

### Implementation for User Story 1

- [x] T014 [US1] Создать `app/Services/VkAuthService.php`: обмен `code` + `code_verifier` + `device_id` на токен через `POST /api/vk/callback` (собственный HTTP-клиент, без Socialite), сборка `redirect_uri` из `$request->url()`, нормализация профиля в `ProviderProfile` (depends: T006, T007)
- [x] T015 [US1] Создать `app/Http/Controllers/Api/VkAuthController.php`: JSON-путь (fetch с JSON-телом `{code, device_id, code_verifier}`) и навигационный путь (redirect на `/`), `Auth::login($user, true)`, `session()->regenerate()`, удаление cookie `vk_code_verifier`, ошибки → `AuthError` (FR-003, FR-044, FR-015) (depends: T014)
- [x] T016 [US1] Добавить маршрут `ANY /api/vk/callback` с `throttle:10,1` в `routes/web.php` (по образцу `cheap-eats/routes/web.php`) (depends: T015)
- [x] T017 [US1] Создать `resources/js/composables/useAuth.js`: генерация `state`/`code_verifier`, cookie `vk_code_verifier`, запуск `VKID.Auth.login()` через `@vkid/sdk` с `VITE_VK_CLIENT_ID`, fetch-обмен на `/api/vk/callback`, обработка ошибок через `authErrors.js`, запрет повторного входа при активной сессии (FR-004, FR-042) (depends: T011, T012)
- [x] T018 [US1] Переписать `resources/js/services/sessionService.js` на реальные запросы (`GET /api/v1/user`, `POST /api/v1/auth/logout`) вместо localStorage; `MOCK_SCENARIO_KEY` оставить только для каталога (FR-005, FR-006)
- [x] T019 [US1] Обновить `resources/js/stores/session.js`: состояние `{authenticated, user, csrfToken}`, загрузка при старте, действия `login/logout` (depends: T018)
- [x] T020 [US1] Обновить `resources/js/App.vue`: bootstrap сессии при монтировании, обработка параметров возврата (`code`, `error`, `auth_error`), toast один раз, очистка адресной строки через `history.replaceState` (FR-033) (depends: T019)
- [x] T021 [US1] Обновить обработчики в `resources/js/components/layout/SiteHeader.vue`: кнопка VK → `useAuth`, после входа — имя и аватар вместо кнопок; визуальный стиль и порядок (VK, затем Яндекс) не менять (FR-001, FR-002) (depends: T017, T019)
- [x] T022 [P] [US1] Vitest-тест `tests/js/useAuth.spec.js`: запуск VK-потока, JSON-обмен, состояния ошибок и редиректы, запрет входа при активной сессии (depends: T017)

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - Первый вход через Yandex ID (Priority: P1)

**Goal**: Гость входит через Яндекс: серверный редирект, `state`-проверка, аккаунт из профиля Яндекса (FR-017…FR-032)

**Independent Test**: В чистом браузере нажать «Войти через Яндекс», подтвердить → возврат на `/` авторизованным, данные профиля сохранены; локальный домен `https://localhost` возвращает на локальный, а не production

### Tests for User Story 2

- [x] T023 [P] [US2] Feature-тест `tests/Feature/Auth/YandexAuthTest.php` (FAIL до реализации): успешный вход создаёт/находит аккаунт с `ya_id`; email-матчинг и коллизия → `ya<id>@ya.users`; `state` отсутствует/не совпал/просрочен (>10 мин)/повторно использован → сообщение `state` без сессии; `access_denied` → `cancelled` без изменения сессии; домен вне `YA_ALLOWED_REDIRECTS` → `not_configured` без обращения к Яндексу; отсутствие конфигурации → `not_configured`; `throttle:10,1` (FR-018…FR-034)

### Implementation for User Story 2

- [x] T024 [US2] Создать `app/Services/YandexAuthService.php`: получение профиля через Socialite `->user()`, нормализация в `ProviderProfile`, заглушка имени/аватара при отсутствии данных (FR-027), хранение внешней ссылки на фото без копирования (FR-028) (depends: T006, T007)
- [x] T025 [US2] Создать `app/Http/Controllers/Api/YandexAuthController.php`: `redirect()` — проверка конфигурации и белого списка `YA_ALLOWED_REDIRECTS`, `state` в сессии, `ya_oauth_started_at` (10 мин); `callback()` — проверка `state` до обмена, обработка `access_denied`, `Auth::login($user, true)` + `session()->regenerate()`, очистка попытки (FR-018…FR-021, FR-044, FR-045) (depends: T024)
- [x] T026 [US2] Добавить маршруты `GET /api/ya/redirect` и `GET /api/ya/callback` с `throttle:10,1` в `routes/web.php` (depends: T025)
- [x] T027 [US2] Подключить кнопку Яндекса в `resources/js/components/layout/SiteHeader.vue` и `resources/js/composables/useAuth.js`: редирект на `/api/ya/redirect`, запрет при активной сессии (FR-004, FR-017) (depends: T017)

**Checkpoint**: At this point, User Story 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - Повторный вход, сохранение сессии и выход (Priority: P2)

**Goal**: Постоянная сессия, повторный вход в тот же аккаунт, рабочий выход (FR-003, FR-005, FR-006, FR-013, FR-023, FR-044)

**Independent Test**: Войти каждым провайдером, выйти, войти снова — количество пользователей не изменилось; после полного закрытия и повторного открытия браузера вход сохраняется; выход возвращает состояние гостя

### Tests for User Story 3

- [x] T028 [P] [US3] Feature-тест `tests/Feature/Auth/SessionTest.php` (FAIL до реализации): `GET /api/v1/user` → всегда `200` с `{authenticated, user}` (гость → `user: null`); `POST /api/v1/auth/logout` → сессия уничтожена, `authenticated: false`, новый `csrfToken`; remember-cookie действует после регенерации сессии; `GET /api/vk/callback` при уже активной сессии не меняет её (FR-004, FR-005, FR-006)

### Implementation for User Story 3

- [x] T029 [US3] Создать `app/Http/Resources/SessionUserResource.php`: единый payload `{id, name, email, avatar, source, last_login_at}` для `GET /api/v1/user` (FR-005, FR-039)
- [x] T030 [US3] Создать `app/Http/Controllers/Api/SessionController.php`: `user()` → `{authenticated, user}` всегда 200; `logout()` → `Auth::logout()`, `session()->invalidate()`, `session()->regenerateToken()` (FR-006) (depends: T029)
- [x] T031 [US3] Добавить маршруты `GET /api/v1/user` и `POST /api/v1/auth/logout` в `routes/web.php` (depends: T030)
- [x] T032 [US3] Связать фронтенд: `sessionService.js` + `stores/session.js` + выход в `SiteHeader.vue` через новый маршрут; убедиться, что `credentials: 'same-origin'` сохраняет remember-cookie (FR-003, FR-006) (depends: T018, T019, T031)
- [x] T033 [P] [US3] Обновить `tests/js/sessionStore.spec.js` под реальные ответы API и `tests/js/SiteHeader.spec.js` под обработчики выхода (depends: T032)

**Checkpoint**: All user stories should now be independently functional

---

## Phase 6: User Story 4 - Понятный отказ и восстановление после ошибки (Priority: P3)

**Goal**: Любая ошибка входа → понятное русское сообщение, состояние сессии не меняется, можно повторить (FR-008, FR-014, FR-029…FR-034, FR-040, FR-043)

**Independent Test**: Поочерёдно воспроизвести отказ Яндекса, повторное открытие использованной ссылки, незаполненные параметры приложения, превышение лимита — сообщения понятны, сессия не изменилась, повторная попытка работает

### Tests for User Story 4

- [x] T034 [P] [US4] Feature-тест `tests/Feature/Auth/AuthErrorTest.php` (FAIL до реализации): превышение лимита → JSON `429 {error: too_many_requests}` для fetch и `302 /?auth_error=too_many_requests` для навигации, сессия не изменена; ошибки не содержат кодов/токенов/исключений; журнал содержит запись без секретов (FR-008, FR-016, FR-040, FR-043)

### Implementation for User Story 4

- [x] T035 [US4] Доработать `resources/js/App.vue`: показ toast по `?auth_error=` и `?error=`, одноразовое отображение, очистка query-параметров, страница продолжает работать (FR-021, FR-029, FR-031…FR-033) (depends: T012, T020)
- [x] T036 [US4] Проверить и закрыть все пути ошибок в `VkAuthController`/`YandexAuthController`: каждый отказ → свой `AuthError`, попытка очищается, сессия не выдаётся (FR-014, FR-015, FR-045) (depends: T015, T025)
- [x] T037 [P] [US4] Добавить Vitest-кейсы ошибок в `tests/js/useAuth.spec.js`: `too_many_requests`, `not_configured`, отмена, повторная попытка (depends: T035)

**Checkpoint**: Все четыре истории работают независимо

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Административная панель и итоговая валидация

- [x] T038 [P] Обновить `app/Models/User.php`: `$fillable` += `avatar`, `vk_id`, `ya_id`, `vk_last_login_at`, `ya_last_login_at`; `$allowedFilters`/`$allowedSorts` += `vk_id`, `ya_id`, времена входа (FR-039)
- [x] T039 [P] Обновить `app/Orchid/Layouts/User/UserListLayout.php`: колонки `id`, `vk_id`, `ya_id` (с `filter(Input::make())`, сортировкой, прочерком для `null`) и скрытые по умолчанию `vk_last_login_at`/`ya_last_login_at` через `DateTimeSplit`; существующий поиск по имени/email сохранить (FR-039)
- [x] T040 [P] Обновить `app/Orchid/Presenters/UserPresenter.php`: `image()` → `users.avatar`, если заполнен, иначе прежний Gravatar (FR-039)
- [x] T041 Feature-тест `tests/Feature/Admin/UserListAuthColumnsTest.php`: колонки видны, фильтр по `vk_id`/`ya_id` находит пользователя, сортировка работает (SC-009) (depends: T038, T039, T040)
- [x] T042 Запустить `php artisan test --compact`, `npm run test`, `vendor/bin/pint --dirty --format agent` и исправить падения (plan.md, шаг 7)
- [ ] T043 [P] Пройти сценарии `specs/004-vk-yandex-auth/quickstart.md` вручную (10 сценариев, таблица диагностики)

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: T001 выполнен; T002–T004 независимы друг от друга
- **Foundational (Phase 2)**: зависит от Setup (T001–T004) — **BLOCKS all user stories**
  - T005, T006, T008, T010, T011, T012 — параллельны ([P])
  - T007 зависит от T006; T009 — отдельный файл `bootstrap/app.php`
- **User Stories**: все зависят от Phase 2
  - **US1 (P1, MVP)** — приоритет первый, включает фронтенд-каркас (`http.js`, `sessionService`, store), который переиспользуют остальные истории
  - **US2 (P1)** — серверная часть независима от US1 (свой сервис/контроллер/маршруты); кнопка в шапке зависит от `useAuth.js` из US1
  - **US3 (P2)** — зависит от фронтенд-каркаса US1 (sessionService/store), серверные маршруты независимы
  - **US4 (P3)** — опирается на `authErrors.js` (Phase 2) и обработчики App.vue из US1/US2
- **Polish (Final Phase)**: серверные задачи T038–T040 независимы от US1–US4; T041 зависит от них; T042/T043 — последние

### User Story Dependencies

- **User Story 1 (P1)**: после Foundational; закладывает общий фронтенд-каркас
- **User Story 2 (P1)**: после Foundational; использует `useAuth.js`/кнопку из US1 только для запуска редиректа
- **User Story 3 (P2)**: после Foundational + каркас сессии из US1
- **User Story 4 (P3)**: после Foundational + обработчиков возврата из US1/US2

### Within Each User Story

- Tests (T013, T023, T028, T034) написать ДО реализации и убедиться, что они падают
- Сервис → контроллер → маршруты → фронтенд → тесты фронтенда
- История завершена → переходим к следующей по приоритету

### Parallel Opportunities

- Phase 1: T002 и T003 параллельно (разные файлы)
- Phase 2: T005, T006, T008, T010, T011, T012 — одновременно
- US1: T013 (тест) параллельно с T017–T021 (фронтенд); T022 параллельно с T014–T016
- US2: T023 (тест) параллельно с T024–T027
- US3: T028 (тест) параллельно с T029–T031; T033 параллельно с T032
- US4: T034 (тест) параллельно с T035–T036; T037 параллельно
- Polish: T038, T039, T040 — три разных файла, параллельно

---

## Parallel Example: User Story 1

```bash
# Тест и фронтенд одновременно (разные файлы):
Task: "T013 tests/Feature/Auth/VkAuthTest.php"
Task: "T017 resources/js/composables/useAuth.js"
Task: "T018 resources/js/services/sessionService.js"
Task: "T019 resources/js/stores/session.js"

# После каркаса — сервер и обработчики:
Task: "T014 app/Services/VkAuthService.php"
Task: "T020 resources/js/App.vue"
Task: "T021 resources/js/components/layout/SiteHeader.vue"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup (T001 ✅, T002–T004)
2. Complete Phase 2: Foundational (CRITICAL — blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: `php artisan test --filter=VkAuthTest` + ручной сценарий входа через VK
5. Демо: реальный вход через VK ID на `https://localhost`

### Incremental Delivery

1. Setup + Foundational → каркас готов
2. Add User Story 1 → тесты + ручной сценарий → **MVP**
3. Add User Story 2 → тесты + ручной сценарий
4. Add User Story 3 → повторный вход, сессия, выход
5. Add User Story 4 → ошибки и лимиты
6. Polish → Orchid-колонки, полный прогон, quickstart

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Не менять зависимости сверх T001 (одобрено 2026-10-29)
- Визуальный стиль шапки и порядок кнопок (VK, затем Яндекс) не менять (FR-001)
