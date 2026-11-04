# Quickstart: проверка оплаты подписки через СБП Robokassa

**Feature**: `006-robokassa-sbp-payment` | **Date**: 2026-11-04

Пошаговая проверка того, что оплата работает end-to-end. Контракты — [`contracts/cabinet-payments-api.yaml`](./contracts/cabinet-payments-api.yaml) и [`contracts/ui-contract.md`](./contracts/ui-contract.md), данные — [`data-model.md`](./data-model.md).

---

## 1. Предварительные требования

- PHP 8.3, Composer, Node.js 22+, MySQL 8 (или SQLite для локальной проверки).
- Настроенный вход через VK ID/Yandex ID (см. `specs/004-vk-yandex-auth/quickstart.md`) — кабинет доступен только авторизованным.
- Тестовый магазин Robokassa с включённым СБП и тестовыми `Пароль#1`/`Пароль#2` (или боевой магазин для production-проверки).
- Публичный HTTPS-адрес для ResultURL (для локальной проверки — туннель или эмуляция callback через `curl`).

---

## 2. Переменные окружения

Добавить в `.env`:

```dotenv
ROBOKASSA_MERCHANT_LOGIN=your-shop
ROBOKASSA_PASSWORD1=...
ROBOKASSA_PASSWORD2=...
ROBOKASSA_TEST_MERCHANT_LOGIN=your-test-shop
ROBOKASSA_TEST_PASSWORD1=...
ROBOKASSA_TEST_PASSWORD2=...
ROBOKASSA_TEST_MODE=true
ROBOKASSA_RESULT_URL=/api/v1/payments/robokassa/result
ROBOKASSA_SUCCESS_URL=/cabinet/sites?payment=success
ROBOKASSA_FAIL_URL=/cabinet/sites?payment=fail
ROBOKASSA_SNO=osn
ROBOKASSA_TAX=none
ROBOKASSA_PAYMENT_OBJECT=service
```

В технических настройках Robokassa указать ResultURL:

```
https://<ваш-домен>/api/v1/payments/robokassa/result
```

и SuccessURL/FailURL из `.env` (или абсолютные адреса). Для production `ROBOKASSA_TEST_MODE=false`.

---

## 3. Подготовка

```bash
composer install
npm install

php artisan migrate
php artisan db:seed --class=SubscriptionSettingSeeder

npm run build
```

Проверить, что появились таблицы и поля:

```bash
php artisan tinker --execute 'dump(Schema::getColumnListing("subscription_payments"));'
php artisan tinker --execute 'dump(App\Models\SubscriptionSetting::current()->toArray());'
```

Ожидается наличие `site_domain`, `base_price`, `base_amount`, `discount_percent`, `discount_amount`, `target_status`, `receipt_email`, `receipt_payload`, `qr_expires_at`, `provider`, `payment_method`, `provider_payload`, `failure_reason`, `review_reason`, `is_test`, а также строки настроек с `base_price = 0`, `is_active = false`.

---

## 4. Настройка тарифа в админке

1. Войти в `/admin` под пользователем с правом `platform.marketplace.subscription-settings`.
2. Открыть «Настройки подписки».
3. Задать базовую цену (например, `500`), оставить сроки `1, 3, 6, 12`, скидки `10/15/25`, целевой статус «Активен», TTL `15`, включить «Тариф активен».
4. Сохранить.

Ожидается: тост «Настройки сохранены»; на `/pricing` в течение минуты видны цена, сроки и скидки.

---

## 5. Запуск

```bash
composer run dev
```

Открыть локальный адрес, войти через VK ID или Yandex ID.

---

## 6. Автоматические проверки

```bash
# расчёт цены, скидки, округление, неактивный тариф
php artisan test --compact tests/Feature/Payments/SubscriptionPricingTest.php

# выставление счёта: владелец, sold/withdrawn, email, одна попытка, конфликт домена, снимки
php artisan test --compact tests/Feature/Payments/PaymentInvoiceTest.php

# callback: подпись, сумма, валюта, метод, дубли, поздняя оплата, sold, конфликт, тест
php artisan test --compact tests/Feature/Payments/PaymentCallbackTest.php

# истечение QR и отмена pending
php artisan test --compact tests/Feature/Payments/PaymentExpiryTest.php

# разбор «Требует проверки», комментарий, аудит
php artisan test --compact tests/Feature/Payments/PaymentReviewTest.php

# админские фильтры по доменам, статусу, дате, способу, поиску и сортировке
php artisan test --compact tests/Feature/Payments/PaymentAdminListTest.php

# публичная страница «Тарифы»
php artisan test --compact tests/Feature/Payments/PricingPageTest.php

# обновлённые правила подписки и старые платежи
php artisan test --compact tests/Feature/Models/SiteSubscriptionTest.php
php artisan test --compact tests/Feature/Orchid/SubscriptionPaymentReadOnlyTest.php

# фронтенд
npm test -- tests/js/PaymentDialog.spec.js tests/js/PaymentHistory.spec.js tests/js/usePaymentPolling.spec.js tests/js/paymentService.spec.js tests/js/PricingView.spec.js
```

Все тесты должны быть зелёными. Перед финалом:

```bash
vendor/bin/pint --dirty --format agent
npm run build
```

---

## 7. Ручной сценарий: успешная оплата

1. Создать объявление (или взять существующее) в статусе «Активен»/«Неактивен»/«Снят с продажи» без оплаченной подписки.
2. В кабинете нажать «Продлить».
3. Проверить: видны базовая цена, сроки, скидка, итог, email, целевой статус; для «Снят с продажи» есть предупреждение о статусе.
4. Выбрать 3 месяца, проверить итог (например, `500 × 3 − 10% = 1350`), подтвердить.
5. Убедиться, что появились QR-код и ссылка, а в истории — попытка «Ожидает оплаты».
6. Оплатить в тестовом банковском приложении (или эмулировать callback, см. §8).
7. Дождаться статуса «Оплачен» без перезагрузки страницы.
8. Проверить:
   - `paid_until` увеличился на 3 месяца от более поздней даты (сегодня или прежней `paid_until`);
   - объявление перешло в целевой статус (по умолчанию «Активен») и вернулось в каталог, если модерация одобрена;
   - в истории видны сумма, срок, дата, способ «СБП»;
   - в админке платёж виден с номером счёта, доменом, плательщиком, снимком цены и статусом.

---

## 8. Эмуляция callback без банка

Подставить `InvId` созданного платежа и `OutSum` (целое число рублей). Подпись считается тестовым `Пароль#2`:

```bash
INV_ID=42
OUT_SUM=1350
PASSWORD2='test-password-2'

SIGNATURE=$(php -r "echo md5('$OUT_SUM:$INV_ID:$PASSWORD2');")

curl -i -X POST 'http://localhost/api/v1/payments/robokassa/result' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "OutSum=$OUT_SUM.000000" \
  --data-urlencode "InvId=$INV_ID" \
  --data-urlencode "SignatureValue=$SIGNATURE" \
  --data-urlencode 'PaymentMethod=SBP' \
  --data-urlencode 'IncCurrLabel=RUB' \
  --data-urlencode 'EMail=seller@example.com' \
  --data-urlencode 'Fee=13.50'
```

Ожидается `HTTP/1.1 200` и тело `OK{InvId}` (например, `OK42`). Повторить запрос — тело то же, `paid_until` не меняется второй раз.

Проверить неверную подпись:

```bash
curl -i -X POST 'http://localhost/api/v1/payments/robokassa/result' \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode "OutSum=$OUT_SUM.000000" \
  --data-urlencode "InvId=$INV_ID" \
  --data-urlencode 'SignatureValue=deadbeef' \
  --data-urlencode 'PaymentMethod=SBP'
```

Ожидается `400` и тело `ERROR`.

---

## 9. Проверка спорных случаев

### 9.1. Несовпадение суммы

Отправить callback с `OutSum`, отличающимся от счёта, и корректной подписью. Ожидается `200 OK{InvId}`, статус «Требует проверки», `paid_until` не изменился, администратору ушло уведомление. В админке открыть платёж, увидеть причину, ввести комментарий и нажать «Принять» или «Отклонить».

- «Принять» → «Оплачен», подписка продлена, целевой статус выставлен, в аудите запись `accepted`.
- «Отклонить» → «Не прошёл», подписка не меняется, в аудите запись `rejected`.

### 9.2. Поздняя оплата

Создать счёт, дождаться истечения QR (или уменьшить TTL в настройках до 1 минуты), убедиться, что попытка стала «Отменён». Отправить корректный callback, пока объявление принимает оплату. Ожидается: «Оплачен», подписка продлена.

### 9.3. Оплата проданного объявления

Создать счёт, перевести объявление в «Продан», отправить корректный callback. Ожидается: «Требует проверки», `paid_until` не изменился, статус не изменился; администратор может отклонить и оформить возврат вручную.

### 9.4. Конфликт домена

Создать два объявления с одинаковым доменом в разных статусах (например, одно «Снят с продажи», другое «Активен»). Попробовать оплатить снятое с целевым статусом «Активен». Ожидается: счёт не выставляется, сообщение «Домен уже занят…».

Если конфликт возник после выставления счёта — callback переводит платёж в «Требует проверки», подписка не продлевается.

### 9.5. Двойная оплата

Создать две попытки по одному объявлению (вторая отменяет первую). Оплатить обе (эмуляцией callback). Пока объявление принимает оплату, обе учитываются, `paid_until` продлевается на оба срока; в истории две записи «Оплачен»; администратор может оформить возврат лишней вручную.

### 9.6. Истечение QR

Уменьшить TTL до 1 минуты, создать счёт, подождать. Ожидается: статус «Отменён», QR недоступен, `paid_until` не изменился, кнопка «Создать новую попытку».

### 9.7. Тестовый режим

На стенде с `ROBOKASSA_TEST_MODE=true` провести тестовую оплату. Ожидается: полный сценарий (продление, целевой статус), платёж помечен «Тест». На production с `ROBOKASSA_TEST_MODE=false` отправить callback с тестовым счётом — ожидается `400`, запись в лог, данные не меняются.

---

## 10. Проверка админских фильтров

1. Создать не менее 3 платежей с разными доменами, статусами и датами.
2. В `/admin/marketplace/payments`:
   - ввести два домена через запятую — видны платежи по обоим;
   - выбрать статус «Оплачен» — видны только оплаченные;
   - задать диапазон дат — видны только попавшие;
   - выбрать способ «СБП» — видны только СБП;
   - ввести номер счёта — виден один платёж;
   - переключить сортировку по дате — порядок меняется;
   - по умолчанию — новые сверху.
3. Открыть платёж — проверить снимок домена/цены, текущий домен, email, историю решений.

---

## 11. Проверка публичной страницы «Тарифы»

1. Открыть `/pricing` в приватном окне.
2. Убедиться, что видны цена за месяц, сроки, скидки и итог, совпадающие с кабинетом.
3. Изменить цену/скидку в админке, обновить страницу в течение минуты — значения обновились.
4. Выключить «Тариф активен» — страница показывает «Оплата временно недоступна», новые счета в кабинете не выставляются.

---

## 12. Ожидаемые результаты

- Успешная оплата: `status = paid`, `paid_at` заполнен, `paid_until` продлён, целевой статус применён, объявление вернулось в каталог при `active` + одобренной модерации.
- Повторный callback: без изменений, `OK{InvId}`.
- Неверная подпись: `400`, без изменений.
- Несовпадение суммы/валюты/способа: `requires_review`, без продления, уведомление администратору.
- Поздняя оплата: применяется, если объявление принимает оплату; иначе `requires_review`.
- Проданное объявление: оплата не применяется, `requires_review`.
- Конфликт домена: счёт не выставляется; поздний конфликт — `requires_review`.
- Истечение QR: `cancelled`, `paid_until` не меняется.
- Тестовый режим: полный сценарий с пометкой «Тест»; production отклоняет тестовые callback.
- В логах и ответах нет `Пароль#1`, `Пароль#2`, полного `receipt` и подписи.

---

## 13. Диагностика

| Симптом | Что проверить |
|---|---|
| QR не появляется | Загрузился ли `robokassa-iframe-badge.js`; существует ли контейнер; совпадает ли `outSum`/`invId`/`receipt` с подписью; нет ли блокировки скрипта браузером |
| Callback возвращает 400 | Алгоритм подписи, тестовый/боевой `Пароль#2`, кодировку, `InvId`, `OutSum` |
| `paid_until` не изменился | Статус платежа, `site.status`, конфликт домена, `is_test` на production, логи `PaymentFinalizer` |
| Платёж «Требует проверки» | `review_reason`, `provider_payload`, сумма/валюта/метод, статус объявления, `public_domain` |
| Фильтр по домену пуст | Домен нормализован (lower, без `www.`), есть ли `site_domain` или текущий домен, не мешает ли другой фильтр |
| Настройки не применяются | `SubscriptionSetting::current()`, кеш `subscription:settings`, `is_active`, валидация скидок |
| Страница «Тарифы» пуста | `GET /api/v1/pricing`, `is_active`, ошибка сети, кеш 60 с |

Логи: `storage/logs/laravel.log`; для платежей искать `invId`, `site_id`, `payment`, `requires_review`, но не пароли и не полный чек.
