# Quickstart: Вход через VK ID и Yandex ID

**Feature**: `004-vk-yandex-auth` | **Date**: 2026-10-29

Руководство по настройке и проверке фичи. Реализация описана в `tasks.md` (создаётся `/speckit.tasks`); здесь — только команды, сценарии и ожидаемые результаты.

Ссылки: [spec.md](./spec.md) · [plan.md](./plan.md) · [data-model.md](./data-model.md) · [contracts/auth-api.yaml](./contracts/auth-api.yaml) · [contracts/spa-auth-errors.md](./contracts/spa-auth-errors.md)

---

## 1. Предварительные требования

| Требование | Проверка | Ожидаемо |
|---|---|---|
| PHP 8.3+ | `php -v` | `PHP 8.3.x` |
| Composer-зависимости | `composer install` | без ошибок |
| Node.js 22+ | `node -v` | `v22.x` или выше |
| Зависимости фронтенда | `npm install` | без ошибок |
| Ключ приложения | `php artisan key:generate` | `APP_KEY` заполнен |
| База данных | `php artisan migrate` | миграции применены, включая `2026_10_28_140034_add_social_identity_fields_to_users_table` |
| Драйвер сессий | `php artisan config:show session.driver` | `database` |
| Локальный HTTPS | открыть `https://localhost` | приложение доступно по HTTPS |

Фича не требует новых миграций: поля `avatar`, `vk_id`, `ya_id`, `vk_last_login_at`, `ya_last_login_at` уже существуют.

> `php artisan serve` поднимает HTTP-сервер и сам TLS не включает. Для реальных callback'ов локальный домен должен открываться по `https://localhost` (в корне проекта лежат `localhost.pem` и `localhost-key.pem`); настройте локальный TLS-прокси/веб-сервер, если он ещё не настроен.

---

## 2. Согласование и установка зависимостей

Установка новых библиотек требует отдельного согласования (см. `plan.md`). После согласования:

```bash
composer require laravel/socialite:^5.31 socialiteproviders/yandex:^4.2
npm install @vkid/sdk@^2.6.5
```

Проверка:

```bash
composer show laravel/socialite socialiteproviders/yandex
npm ls @vkid/sdk
```

Ожидаемо: обе PHP-зависимости и `@vkid/sdk` присутствуют, версии совместимы с Laravel 13 / Vite 8.

---

## 3. Переменные окружения

Добавьте в `.env` (и продублируйте без секретов в `.env.example`):

```dotenv
# Серверные параметры
VK_CLIENT_ID=
VK_CLIENT_SECRET=
VK_REDIRECT_URI=/api/vk/callback

YA_CLIENT_ID=
YA_CLIENT_SECRET=
YA_REDIRECT_URI=/api/ya/callback
YA_ALLOWED_REDIRECTS=https://sites-shop.ru/api/ya/callback,https://localhost/api/ya/callback

# Публичный идентификатор VK для SPA
VITE_VK_CLIENT_ID=
```

| Переменная | Где используется | Секрет |
|---|---|---|
| `VK_CLIENT_ID` | серверный обмен кода | нет |
| `VK_CLIENT_SECRET` | серверный обмен кода | да |
| `VK_REDIRECT_URI` | путь возврата VK | нет |
| `YA_CLIENT_ID` | Socialite | нет |
| `YA_CLIENT_SECRET` | Socialite | да |
| `YA_REDIRECT_URI` | путь возврата Яндекса | нет |
| `YA_ALLOWED_REDIRECTS` | белый список абсолютных адресов | нет |
| `VITE_VK_CLIENT_ID` | запуск VK SDK в браузере | нет |

После изменения `.env`:

```bash
php artisan config:clear
```

Ожидаемо: `php artisan config:show services.vk` и `php artisan config:show services.yandex` показывают заполненные значения; `VK_CLIENT_SECRET`/`YA_CLIENT_SECRET` отсутствуют в собранном JS.

---

## 4. Настройка приложений у провайдеров

### VK ID

- Доверенные адреса возврата: `https://sites-shop.ru/api/vk/callback` и `https://localhost/api/vk/callback`.
- Публичный ID приложения → `VITE_VK_CLIENT_ID`; защищённый ключ → `VK_CLIENT_SECRET`.
- В приложении включён доступ к базовому профилю (имя, фото) и email, если он нужен.

### Yandex ID

- Redirect URI: `https://sites-shop.ru/api/ya/callback` и `https://localhost/api/ya/callback` — ровно те же строки, что в `YA_ALLOWED_REDIRECTS`.
- Доступ к базовому профилю: имя, фото, email.
- `YA_CLIENT_ID`/`YA_CLIENT_SECRET` — из кабинета приложения.

Ожидаемо: адреса совпадают байт-в-байт; при расхождении Яндекс вернёт ошибку, а приложение — сообщение «Вход через Яндекс пока не настроен» (для домена вне белого списка).

---

## 5. Запуск

```bash
composer run dev
```

Или раздельно:

```bash
php artisan serve
npm run dev
```

Проверка маршрутов:

```bash
php artisan route:list --path=api --except-vendor
```

Ожидаемо:

```text
GET|POST|... /api/vk/callback
GET         /api/ya/redirect
GET         /api/ya/callback
GET         /api/v1/user
POST        /api/v1/auth/logout
```

Production-сборка:

```bash
npm run build
```

Ожидаемо: сборка без ошибок; в `public/build/manifest.json` есть `resources/js/app.js` и `resources/css/app.css`. Если интерфейс не обновился или появилась ошибка «Unable to locate file in Vite manifest» — перезапустите `npm run dev` или выполните `npm run build`.

---

## 6. Автоматические проверки

```bash
php artisan test --compact
npm run test
vendor/bin/pint --dirty --format agent
```

Ожидаемо: все тесты проходят, Pint не вносит изменений.

Целевые наборы:

```bash
php artisan test --compact tests/Feature/Auth
php artisan test --compact tests/Feature/Admin/UserListAuthColumnsTest.php
npm run test -- tests/js/useAuth.spec.js tests/js/sessionStore.spec.js
```

---

## 7. Ручные сценарии

Перед проверкой откройте сайт в приватном окне без сохранённой сессии.

### 7.1. Первый вход через VK ID

1. Нажать «Войти через VK ID».
2. Подтвердить доступ в окне VK ID.
3. Вернуться на сайт.

**Ожидаемо**: пользователь авторизован, в шапке имя и аватар, кнопки входа скрыты; в адресной строке нет `code`, `state`, `device_id`, `code_verifier`; в БД появился пользователь с `vk_id`, `avatar` и `vk_last_login_at`.

### 7.2. Первый вход через Yandex ID

1. Нажать «Войти через Яндекс ID».
2. Подтвердить доступ.
3. Вернуться на главную.

**Ожидаемо**: пользователь авторизован; сохранены имя, email (если передан) и ссылка на фото; заполнены `ya_id`/`ya_last_login_at`. При локальном запуске возврат приходит на `https://localhost/api/ya/callback`, а не на production.

### 7.3. Привязка по email

1. Создать аккаунт с email `test@example.com` (например, через админку или сидер).
2. Войти через провайдера, который вернёт тот же email.

**Ожидаемо**: новый аккаунт не создан; к существующему добавлен `vk_id`/`ya_id`; вход открывает существующий аккаунт.

### 7.4. Конфликт привязки

1. Создать аккаунт A с `vk_id=1` и email `same@example.com`.
2. Войти через VK с другим `vk_id=2`, который вернёт `same@example.com`.

**Ожидаемо**: аккаунт A не изменён; создан аккаунт B с `vk_id=2` и техническим email `vk2@vk.users`. Повторить для Яндекса: `ya<id>@ya.users`.

### 7.5. Повторный вход и постоянная сессия

1. Войти через провайдера, выйти, войти снова.
2. Обновить страницу; полностью закрыть и открыть браузер.

**Ожидаемо**: количество пользователей не увеличилось; имя/аватар обновились; вход сохраняется после перезапуска браузера; выход возвращает кнопки входа.

### 7.6. Отмена и ошибки

| Действие | Ожидаемое сообщение |
|---|---|
| Отменить доступ в Яндексе | «Вход через Яндекс отменён», пользователь остаётся гостем |
| Отменить доступ в VK | «Вход через VK ID отменён» |
| Повторно открыть использованный callback | «Не удалось войти через … Попробуйте ещё раз» |
| Открыть callback в другом браузере без сессии | «Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз» |
| Указать домен вне `YA_ALLOWED_REDIRECTS` | «Вход через Яндекс пока не настроен», обращения к Яндексу нет |
| Очистить `YA_CLIENT_ID` | «Вход через Яндекс пока не настроен», страница работает |

Во всех случаях сессия не меняется, в URL не остаётся кодов/токенов, повторный вход работает.

### 7.7. Лимит попыток

1. 11 раз подряд открыть `/api/ya/callback` (или `/api/vk/callback`) с одного адреса.

**Ожидаемо**: первые 10 обрабатываются, 11-е — редирект на `/?auth_error=too_many_requests` с сообщением «Слишком много попыток входа. Попробуйте позже»; сессия не меняется. Для JSON-запроса — `429` с `{ "error": "too_many_requests" }`. Через минуту вход снова работает.

### 7.8. Выход

1. Войти, нажать «Выйти».

**Ожидаемо**: `POST /api/v1/auth/logout` возвращает `200`, шапка показывает кнопки входа, защищённые действия снова требуют авторизации, CSRF-токен в мета-теге обновлён.

### 7.9. Административная панель

1. Войти в `/admin` под администратором.
2. Открыть список пользователей.
3. Отфильтровать по `VK ID` и по `Яндекс ID`.

**Ожидаемо**: видны колонки «ID», «VK ID» и «Яндекс ID»; фильтр находит нужного пользователя; для непривязанных отображается `—`; сортировка по ID работает; в колонке имени виден аватар провайдера (или Gravatar-заглушка); время последнего входа доступно через переключатель колонок; список и пагинация не деградировали.

### 7.10. Безопасность

- В исходниках собранного JS нет `VK_CLIENT_SECRET`/`YA_CLIENT_SECRET`.
- В `storage/logs/laravel.log` нет кодов авторизации, токенов и секретов; есть только `reason`/`provider`/`user_id`.
- В адресной строке после возврата не остаётся служебных параметров.
- Cookie сессии имеет `HttpOnly`; `vk_code_verifier` удаляется после попытки.

---

## 8. Быстрая диагностика

| Симптом | Вероятная причина | Действие |
|---|---|---|
| «Вход через Яндекс пока не настроен» | пустые `YA_*` или домен не в белом списке | проверить `.env`, `YA_ALLOWED_REDIRECTS`, `php artisan config:clear` |
| «Вход через VK ID пока не настроен» | пустые `VK_*`/`VITE_VK_CLIENT_ID` | заполнить `.env`, пересобрать фронтенд |
| VK возвращает ошибку `redirect_uri` | адрес в кабинете VK не совпадает с текущим доменом | добавить `https://localhost/api/vk/callback` и production-адрес |
| Яндекс возвращает `redirect_uri` | адрес не совпадает с зарегистрированным | сверить строку в кабинете и `YA_ALLOWED_REDIRECTS` |
| После входа шапка остаётся гостем | не выполнен `session.initialize()` после callback | проверить `App.vue` и `GET /api/v1/user` |
| `419` при выходе | не передан `X-CSRF-TOKEN` | проверить мета-тег и `services/http.js` |
| `429` при обычном входе | лимит исчерпан предыдущими тестами | подождать минуту или очистить cache/rate limiter |
| Стили не обновились | устаревший Vite manifest | `npm run build` или перезапуск `npm run dev` |
