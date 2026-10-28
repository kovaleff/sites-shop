# Quickstart: Проверка слоя данных биржи

**Feature**: `001-marketplace-data-model` | **Date**: 2026-10-28

Руководство проверяет, что миграции, модели, enum, фабрики, сидер и поведение видимости/подписок работают end-to-end. Реализация описана в [data-model.md](./data-model.md); здесь — только команды и ожидаемые результаты.

## Prerequisites

- PHP 8.3+ с расширениями `pdo_mysql` и `pdo_sqlite`.
- Composer-зависимости установлены (`vendor/` существует).
- MySQL 8.0+ для локальной проверки схемы; SQLite 3.31+ используется тестами автоматически.
- Настроенный `.env` с `DB_CONNECTION=mysql` и существующей базой `sites-shop`.

## 1. Установка и миграции

```bash
composer install
php artisan migrate:fresh --seed
```

> `migrate:fresh` удаляет все таблицы текущей базы. Используйте только локальную/тестовую БД.

**Ожидаемо**:

- Выполняются 7 новых миграций (соцполя `users`, `categories`, `sites`, `category_site`, `conversations`, `messages`, `subscription_payments`).
- `DatabaseSeeder` создаёт 31 тематику и демо-пользователя.
- Команда завершается без ошибок.

## 2. Проверка схемы

```bash
php artisan db:table sites
php artisan db:table subscription_payments
```

**Ожидаемо для `sites`**:

- Столбцы `domain`, `status`, `public_domain`, `verification_filename`, `verified`, `paid_until`, `sold_at` присутствуют.
- `public_domain` — генерируемый столбец.
- Уникальный индекс `sites_public_domain_unique`, уникальный индекс `sites_verification_filename_unique` и индекс `sites_visibility_index (status, paid_until)`.
- `verified` имеет значение по умолчанию `false`.
- Внешний ключ `user_id → users.id` с ограничением удаления.

**Ожидаемо для `subscription_payments`**:

- Уникальный индекс по `external_id`.
- Индексы по `(site_id, created_at)` и `(user_id, created_at)`.

## 3. Сидер тематик

```bash
php artisan db:seed --class=CategorySeeder
php artisan db:seed --class=CategorySeeder
```

**Ожидаемо**: повторный запуск не создаёт дубликатов; в `categories` остаётся ровно 31 запись, все `name` и `slug` уникальны. Точного дубликата «Медицина и здоровье» нет.

## 4. Тесты

Полный прогон:

```bash
php artisan test --compact tests/Feature/Models
```

**Ожидаемо**: все тесты зелёные, без предупреждений о массивном заполнении и без ошибок SQLite.

Точечные проверки:

| Команда | Что доказывает | Ожидаемо |
|---|---|---|
| `php artisan test --compact tests/Feature/Models/UserSocialIdentityTest.php` | Вход через VK/Yandex, nullable email/password, связи пользователя | Аккаунты различаются по провайдеру; повторный идентификатор не создаёт дубликат |
| `php artisan test --compact tests/Feature/Models/CategorySeederTest.php` | 31 уникальная тематика, уникальные slug, идемпотентность | 31/31, повторный запуск не меняет количество |
| `php artisan test --compact tests/Feature/Models/SiteVisibilityTest.php` | Видимость по статусу и `paid_until` | Видны только `Active` с оплатой на сегодня/будущее; `null` и прошлое скрыты |
| `php artisan test --compact tests/Feature/Models/SiteStatusTest.php` | Переходы FR-008 | Разрешены только допустимые переходы; `Sold` конечен; `sold_at` выставляется |
| `php artisan test --compact tests/Feature/Models/SiteDomainUniquenessTest.php` | Нормализация и уникальность домена | `Example.com`, `www.example.com`, `example.com/` — один домен; дубликат среди активных блокируется; после `Sold`/`Withdrawn` домен свободен |
| `php artisan test --compact tests/Feature/Models/SiteVerificationTest.php` | Подтверждение владения сайтом | `verification_filename` генерируется автоматически и уникален; не меняется при обновлении; `verified` по умолчанию `false`, не заполняется массово и меняется через `markVerified()` |
| `php artisan test --compact tests/Feature/Models/SiteSubscriptionTest.php` | Независимость подписок и платежи | Платёж продлевает только своё объявление; неуспешный не продлевает; `Withdrawn`/`Sold` не оплачиваются; повторный `markAsPaid` не продлевает дважды |
| `php artisan test --compact tests/Feature/Models/SiteFilterTest.php` | Фильтры и пагинация | Тематики по OR, диапазоны цены/выручки, поиск по домену, комбинация фильтров, 25 на страницу, новые сначала |
| `php artisan test --compact tests/Feature/Models/ConversationTest.php` | Диалоги, доступ, прочтение | Один диалог на пару; владелец не инициирует; посторонний не участник; read-only при непубличном объявлении; непрочитанные считаются верно |

## 5. Проверка на целевой СУБД

Тесты идут на SQLite in-memory. Чтобы убедиться, что генерируемый столбец и индексы корректны в MySQL:

```bash
php artisan migrate:fresh --seed
php artisan db:table sites
```

Повторите один раз после изменения схемы. Если MySQL недоступен, достаточно зелёного прогона тестов на SQLite и ревью миграции.

## 6. Стиль кода

```bash
vendor/bin/pint --dirty --format agent
```

**Ожидаемо**: форматтер не находит ошибок или исправляет их; повторный запуск не меняет файлы.

## 7. Ручные сценарии (по желанию)

Эти сценарии уже покрыты тестами; ручная проверка нужна только для демонстрации.

1. **Публичный каталог**: создайте активное объявление с `paid_until = сегодня` — оно видно; измените дату на вчерашнюю — скрыто; верните дату — снова видно.
2. **Независимость подписок**: два объявления одного продавца с разными `paid_until`; оплатите одно — дата второго не меняется.
3. **Статусы**: переведите объявление в `Sold`; попытка вернуть в `Active` отклоняется; домен можно использовать в новом объявлении.
4. **Диалог**: покупатель начинает переписку; продавец отвечает; посторонний не видит диалог; при снятии объявления с продажи новые сообщения отклоняются, история читается.
5. **Тематики**: объявление с двумя тематиками находится по каждой; удаление используемой тематики блокируется.
6. **Проверка владения**: создайте объявление — у него появилось имя файла вида `<32 символа>.txt`; измените объявление — имя файла не изменилось; вызовите `markVerified()` — флаг `verified` стал `true`, при этом видимость в каталоге не изменилась.

## Troubleshooting

| Симптом | Причина | Решение |
|---|---|---|
| `Unknown column type "generated"` или ошибка `storedAs` | СУБД старше MySQL 5.7 / SQLite 3.31 | Обновите СУБД |
| Ошибка уникального индекса при миграции `sites` | В базе уже есть дубликаты домена среди активных/неактивных | Очистите локальную БД (`migrate:fresh`) |
| Диалог создан без проверки владельца/видимости | Создание в обход `Conversation::startFor()` | Используйте `Conversation::startFor()` или фабрику |
| Тесты падают на `paid_until` | Сравнение с `now()` вместо даты | Сравнивайте даты без времени (`today()`) |
| Сидер создаёт дубликаты тематик | Запущен неидемпотентный код | Используйте `firstOrCreate` по `name` |
| `verification_filename` пустой после `Site::create()` | Поле не сгенерировано в событии `creating` или запись создана через `insert()` | Создавайте через Eloquent (`create`/`factory`), проверьте `booted()` |
| `verified` не меняется при `update()` | Поле вне `#[Fillable]` — это ожидаемое поведение | Используйте `Site::markVerified()` из административного слоя |
