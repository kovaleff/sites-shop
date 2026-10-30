# Контракт ошибок авторизации для SPA

**Feature**: `004-vk-yandex-auth` | **Date**: 2026-10-29

Документ фиксирует, как сервер сообщает об ошибке входа и как SPA её показывает. Общий контракт маршрутов — [auth-api.yaml](./auth-api.yaml).

---

## 1. Два транспорта

| Ситуация | Транспорт | Формат |
|---|---|---|
| Браузерная навигация (VK-форма, Yandex redirect, любой не-JSON запрос) | `302` | `/?auth_error=<code>` |
| Запрос с `Accept: application/json` (VK fetch, сессионные маршруты) | `4xx`/`5xx` | `{ "error": "<code>", "message": "<текст>" }` |
| Успешный вход | `302 /` или `200` | `SessionState` |

Правило выбора: `$request->expectsJson()`. VK fetch из SPA всегда получает JSON; навигация из VK — редирект.

---

## 2. Коды и сообщения

| Код | HTTP | Сообщение | Когда |
|---|---|---|---|
| `too_many_requests` | `429` / `302` | Слишком много попыток входа. Попробуйте позже | Превышен `throttle:10,1` на любом auth-маршруте |
| `not_configured` | `302` | Вход через Яндекс пока не настроен | Нет `YA_CLIENT_ID`/`YA_CLIENT_SECRET`, либо домен не в `YA_ALLOWED_REDIRECTS` |
| `vk_not_configured` | `302` | Вход через VK ID пока не настроен | Нет `VK_CLIENT_ID`/`VK_CLIENT_SECRET`/`VITE_VK_CLIENT_ID` |
| `cancelled` | `302` | Вход через Яндекс отменён | Яндекс вернул `error=access_denied` |
| `vk_cancelled` | `302` | Вход через VK ID отменён | VK вернул `error=access_denied` |
| `state` | `302` | Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз | `state` отсутствует, не совпал, просрочен (> 10 минут) или использован |
| `failed` | `302` | Не удалось войти через Яндекс. Попробуйте ещё раз | Ошибка обмена кода, сервиса Яндекса или сети |
| `vk_failed` | `400`/`500`/`302` | Не удалось войти через VK ID. Попробуйте ещё раз | Нет кода, ошибка обмена, ошибка `users.get`, сеть |

**Требования к текстам** (FR-008, FR-033): русский язык, без кодов ошибок, названий параметров, токенов и текстов исключений. Причина пишется только в журнал.

---

## 3. Поведение сервера

### 3.1. Yandex

| Шаг | Условие | Результат |
|---|---|---|
| `GET /api/ya/redirect` | конфигурация пуста | `302 /?auth_error=not_configured`, к Яндексу не обращаемся |
| `GET /api/ya/redirect` | `redirect_uri` не в белом списке | `302 /?auth_error=not_configured`, журнал `redirect_not_allowed` |
| `GET /api/ya/redirect` | всё корректно | `302` на `oauth.yandex.ru`, в сессии `ya_oauth_started_at` |
| `GET /api/ya/callback` | конфигурация пуста | `302 /?auth_error=not_configured` |
| `GET /api/ya/callback` | `error` заполнен | `302 /?auth_error=cancelled` |
| `GET /api/ya/callback` | `ya_oauth_started_at` отсутствует или старше 10 минут | `302 /?auth_error=state` |
| `GET /api/ya/callback` | `InvalidStateException` | `302 /?auth_error=state` |
| `GET /api/ya/callback` | ошибка обмена/сервиса/сети | `302 /?auth_error=failed` |
| `GET /api/ya/callback` | успех | вход, `session()->regenerate()`, очистка `state`/`ya_oauth_started_at`, `302 /` |

Во всех ошибках очищаются `state` и `ya_oauth_started_at`; сессия авторизации не меняется.

### 3.2. VK

| Условие | JSON-запрос | Навигация |
|---|---|---|
| Конфигурация пуста | `400 { error: "vk_not_configured" }` | `302 /?auth_error=vk_not_configured` |
| `error=access_denied` | `400 { error: "vk_cancelled" }` | `302 /?auth_error=vk_cancelled` |
| Нет `code` | `400 { error: "vk_failed" }` | `302 /?auth_error=vk_failed` |
| Ошибка обмена/`users.get` | `400 { error: "vk_failed" }` | `302 /?auth_error=vk_failed` |
| Внутренняя ошибка | `500 { error: "vk_failed" }` | `302 /?auth_error=vk_failed` |
| Успех | `200 SessionState` | `302 /` |

Cookie `vk_code_verifier` удаляется после любой попытки. Сессия при ошибке не меняется.

### 3.3. Лимит

`ThrottleRequestsException` рендерится централизованно:

```text
Accept: application/json → 429 { error: "too_many_requests", message: "..." }
иначе                    → 302 /?auth_error=too_many_requests
```

Контроллер не вызывается, поэтому сессия и незавершённая попытка не меняются. Cookie сессии может быть отправлена, но её содержимое не обновляется.

### 3.4. Логаут

`POST /api/v1/auth/logout`:

- без/с неверным CSRF → `419`;
- успех → `200 { "authenticated": false, "csrfToken": "<новый>" }`;
- токен меняется из-за `invalidate()` + `regenerateToken()`, поэтому SPA обязан обновить `<meta name="csrf-token">` из ответа.

---

## 4. Поведение SPA

### 4.1. Возврат VK

1. Прочитать `code`, `state`, `device_id`, `error` из `URLSearchParams`.
2. Если есть `error` → toast с текстом `vk_cancelled`/`vk_failed`, очистить `sessionStorage` и URL.
3. Сравнить `state` с сохранённым; при несовпадении → toast `vk_failed`, очистить URL.
4. Взять `code_verifier` из `sessionStorage`, удалить его.
5. Вызвать `POST /api/vk/callback` с `Accept: application/json` и JSON-телом `{ code, device_id, code_verifier }` — служебные данные не попадают в URL.
6. При `200` → сохранить пользователя в store; при ошибке → toast по `error` из JSON.
7. В `finally` очистить `sessionStorage` и `history.replaceState` (убрать `code`, `state`, `device_id`, `error`).

### 4.2. Возврат Yandex

1. Прочитать `auth_error` из URL.
2. Если код известен → показать toast с сообщением из таблицы.
3. Удалить `auth_error` из URL, сохранив остальные query и hash.
4. Повторно вызвать `GET /api/v1/user` (на случай, если сессия уже установлена).

### 4.3. Общие правила

- Сообщение показывается один раз; параметр сразу удаляется из адресной строки.
- Никакие коды/токены не попадают в `localStorage`, `document.title` или текст ошибки.
- Если пользователь уже авторизован, кнопки входа не запускают новый OAuth (FR-004).
- При ошибке интерфейс остаётся работоспособным: шапка, каталог и навигация не блокируются.
- Кнопки входа блокируются на время запуска (`isLoggingIn`), повторное нажатие не создаёт вторую попытку.

---

## 5. Матрица «код → действие»

| Код | Toast | Очистка URL | Повтор входа |
|---|---|---|---|
| `too_many_requests` | «Слишком много попыток входа. Попробуйте позже» | да | после истечения минуты |
| `not_configured` / `vk_not_configured` | «Вход через … пока не настроен» | да | после настройки |
| `cancelled` / `vk_cancelled` | «Вход через … отменён» | да | сразу |
| `state` | «Не удалось подтвердить вход…» | да | сразу |
| `failed` / `vk_failed` | «Не удалось войти через …» | да | сразу |

---

## 6. Безопасность

- В URL возврата допустимы только `code`, `state`, `device_id`, `error`, `error_description`, `auth_error`; секреты и `code_verifier` в него не попадают.
- `code_verifier` живёт в first-party cookie и `sessionStorage` не более 10 минут.
- Сервер логирует `reason`, `provider`, `user_id`, `auth_error`; коды, токены, пароли и секреты не логируются.
- Сообщения не раскрывают, существует ли аккаунт с таким email.
