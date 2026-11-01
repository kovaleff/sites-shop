# Уведомления: письма о сообщениях и решениях

Документ объясняет, как устроены уведомления площадки: что и когда уходит,
через какие события, почему письма откладываются, как пользователь управляет
типами писем и что нужно на сервере, чтобы всё это работало.

Речь только об уведомлениях о сообщениях и решениях (п. 2 в
`.agents/propose.md`). Оплата подписки и платёжные письма сюда не входят.

---

## Какая была проблема

До этой доработки в проекте не было ни `app/Notifications`, ни `app/Mail`,
а `MAIL_MAILER=log`. Продавец узнавал о новом сообщении, только если сам
заходил в кабинет. Покупатель, написавший продавцу, уходил, не дождавшись
ответа. Диалог — ядро сделки, а без уведомлений он не работает.

Отдельно не хватало обратной связи по решениям: модератор отклонял объявление
или принимал жалобу, а продавец об этом никак не узнавал — объявление просто
переставало показываться.

---

## Что уходит и когда

| Событие | Кому | Письмо | Когда |
|---|---|---|---|
| Новое сообщение в диалоге | второй стороне диалога | `NewMessageReceived` | через `message_digest_minutes` минут |
| Объявление одобрено или отклонено | владельцу | `SiteModerated` | сразу |
| Жалоба принята или отклонена | владельцу объявления | `ReportResolved` | сразу |
| Подписка истекает | владельцу | `SubscriptionExpiring` | за 7 дней, за 1 день и в день окончания |

Возврат объявления в очередь модерации («объявление отредактировано продавцом»)
письма **не** вызывает: это не решение модератора, а техническая переотправка.

---

## Как устроено

Между моделью и уведомлением стоит событие. Модель не знает ни про письма,
ни про почтовые настройки — она только объявляет факт изменения.

```
Conversation::postMessage()  → MessagePosted           → SendNewMessageNotification   → NewMessageReceived
Site::setModeration()        → SiteModerationChanged   → SendSiteModeratedNotification → SiteModerated
SiteReport::resolve()        → SiteReportResolved      → SendReportResolvedNotification → ReportResolved
```

Почему событие, а не вызов уведомления прямо в контроллере: сообщение создаётся
в двух местах — `ConversationController::store` (новый диалог) и
`MessageController::store` (ответ). Оба идут через `Conversation::postMessage()`,
поэтому единственная точка подписки — модель, и контроллеры остались нетронутыми.

События — обычные классы в `app/Events`, без интерфейса `ShouldBroadcast`: это
не WebSocket-события, а внутренняя шина. Слушатели в `app/Listeners`
подхватываются обнаружением событий Laravel, регистрировать их вручную не нужно.

---

## Путь одного сообщения

```
покупатель отправляет сообщение
  POST /api/v1/conversations/{id}/messages
        ↓
  MessageController::store → Conversation::postMessage()
        ↓ транзакция: INSERT messages, UPDATE conversations.last_message_at
        ↓ MessagePosted::dispatch()
  SendNewMessageNotification
        ├── получатель = вторая сторона диалога (не автор)
        ├── проверка wantsNotification(Messages)
        ├── Cache::add(key, ttl) — окно дайджеста
        └── notify(new NewMessageReceived($conversation))
              ↓ уведомление в очереди, с задержкой
              ↓ через N минут: shouldSend() → есть ли непрочитанные?
                    ├── да  → mail + database
                    └── нет → письмо не отправляется (прочитали в кабинете)
```

---

## Дайджест вместо спама

Писать на каждую реплику нельзя: живой диалог — это десяток сообщений подряд,
и продавец получит десяток писем.

Сделано в два слоя:

1. **Окно в слушателе.** При первом сообщении в диалоге ставится ключ
   `notifications:message:{conversation}:{recipient}` на
   `config('notifications.message_digest_minutes')` минут. Пока ключ жив,
   следующие сообщения уведомление не создают. `Cache::add()` атомарен, поэтому
   параллельные запросы не наплодят дублей.
2. **Задержка самой отправки.** `NewMessageReceived::withDelay()` откладывает
   задачу в очереди на то же окно. К моменту отправки в письме уже есть свежий
   текст (`latestMessage`) и актуальное число непрочитанных.

Плюс `shouldSend()`: если за это время сообщения прочитали в кабинете, письмо
отменяется. Без этой проверки пользователь получал бы письмо о том, что уже
прочитал.

`NOTIFICATIONS_MESSAGE_DIGEST_MINUTES=0` выключает и окно, и задержку — письмо
уходит на каждое сообщение. Это удобно на отладке и в тестах.

---

## Настройки пользователя

Четыре типа (`App\Enums\NotificationType`): `messages`, `moderation`,
`subscription`, `reports`. Хранятся в `users.notification_settings` (JSON),
по умолчанию всё включено.

`User::notificationSettings()` подмешивает значения по умолчанию к тому, что
лежит в БД, поэтому старые аккаунты без записи ведут себя как «всё включено»,
а новый тип уведомления не требует миграции данных.

`updateNotificationSettings()` меняет только переданные ключи и не трогает
остальные — частичное обновление из интерфейса работает как переключатель,
а не как перезапись всего набора.

**API:**

| Метод | Путь | Назначение |
|---|---|---|
| `GET` | `/api/v1/notification-settings` | типы, подписи, текущие значения, адрес доставки |
| `PATCH` | `/api/v1/notification-settings` | `{"settings": {"messages": false}}` |

**Интерфейс:** `/cabinet/settings/notifications`, пункт «Уведомления» в
навигации кабинета. Переключатель меняет состояние оптимистично и откатывается
назад, если запрос упал.

Выключенный тип означает `via()` → `[]`: не уходит ни письмо, ни запись
в `notifications`.

---

## Отписка по ссылке

Требование к рассылкам: отписаться можно в один клик и без входа в аккаунт.

```
GET /notifications/unsubscribe/{type}/{user}   (middleware: signed)
```

Ссылка собирается через `URL::signedRoute()` в `MarketplaceNotification`.
Подпись защищает от подбора: без валидной подписи маршрут отдаёт 403.
Обработчик отключает один тип и показывает простую страницу
`resources/views/notifications/unsubscribed.blade.php` — без SPA, без JS:
пользователь может открыть письмо с телефона или из почтового клиента.

В каждом письме есть и заголовок `List-Unsubscribe`, чтобы почтовые клиенты
показали кнопку «Отписаться».

---

## Напоминания о подписке

Команда `sites:notify-expiring` (`app/Console/Commands/NotifyExpiringSubscriptions.php`)
ищет объявления в статусах `active`/`inactive`, у которых `paid_until` попадает
на один из дней из `config('notifications.subscription_reminder_days')`
(по умолчанию 7, 1 и 0), и пишет владельцу.

Защита от повторов: ключ `notifications:subscription:{site}:{paid_until}:{daysLeft}`
живёт сутки. Повторный запуск команды в тот же день письма не дублирует —
это актуально, если cron сработает дважды или команду запустят руками.

Расписание — в `routes/console.php`, ежедневно в 09:00:

```php
Schedule::command('sites:notify-expiring')->dailyAt('09:00');
```

На сервере нужен cron:

```
* * * * * php artisan schedule:run
```

---

## Каналы и аккаунты без почты

Вход через VK ID и Яндекс ID может не отдать email. Тогда
`AccountProvisioner` создаёт аккаунт с техническим адресом вида
`vk123@vk.users` — письма на него физически не доставляются, а почтовый сервис
ещё и испортит репутацию домена отскоками.

Поэтому `User::hasDeliverableEmail()` отсекает домены `@vk.users` и `@ya.users`,
и каналы выбираются так:

```php
$channels = ['database'];

if ($notifiable->hasDeliverableEmail()) {
    array_unshift($channels, 'mail');
}
```

Записи в `notifications` (таблица уже была в проекте) остаются всегда — на них
можно построить колокольчик в интерфейсе, когда до него дойдёт очередь.

---

## Что нужно на проде

1. **Очередь.** Уведомления реализуют `ShouldQueue`, а задержка дайджеста
   живёт в очереди. Без воркера письма не уйдут вообще — они просто останутся
   в `jobs`:

   ```
   php artisan queue:work --tries=3
   ```
   Под supervisor или systemd.

2. **Почта.** `MAIL_MAILER=log` пишет письма в лог. Для реальной отправки
   нужен `smtp` (или `resend`, `postmark`, `ses`) и реквизиты в `.env`:
   `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`,
   `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.

3. **Расписание.** `* * * * * php artisan schedule:run` — иначе напоминаний
   о подписке не будет.

4. **`APP_URL`.** Ссылки в письмах и подпись ссылки отписки строятся от него.
   Неверный `APP_URL` даёт нерабочие ссылки во всех письмах.

---

## Связанные файлы

| Файл | Роль |
|---|---|
| `app/Enums/NotificationType.php` | четыре типа, подписи, значения по умолчанию |
| `app/Notifications/MarketplaceNotification.php` | очередь, каналы, учёт настроек, отписка |
| `app/Notifications/NewMessageReceived.php` | письмо о сообщении, дайджест, проверка прочтения |
| `app/Notifications/SiteModerated.php` | решение модератора с причиной |
| `app/Notifications/SubscriptionExpiring.php` | напоминание о подписке |
| `app/Notifications/ReportResolved.php` | результат рассмотрения жалобы |
| `app/Events/MessagePosted.php` | сообщение создано |
| `app/Events/SiteModerationChanged.php` | модерация изменила решение |
| `app/Events/SiteReportResolved.php` | жалоба рассмотрена |
| `app/Listeners/SendNewMessageNotification.php` | выбор адресата и окно дайджеста |
| `app/Listeners/SendSiteModeratedNotification.php` | фильтр «не `pending`» и отправка |
| `app/Listeners/SendReportResolvedNotification.php` | отправка владельцу объявления |
| `app/Console/Commands/NotifyExpiringSubscriptions.php` | `sites:notify-expiring` |
| `app/Http/Controllers/Api/NotificationSettingsController.php` | `GET`/`PATCH` настроек |
| `app/Http/Controllers/NotificationUnsubscribeController.php` | отписка по подписанной ссылке |
| `app/Http/Requests/UpdateNotificationSettingsRequest.php` | валидация типов и `boolean` |
| `app/Models/User.php` | настройки, `wantsNotification()`, `hasDeliverableEmail()` |
| `app/Models/Conversation.php` | `MessagePosted` после создания сообщения |
| `app/Models/Site.php` | `SiteModerationChanged` в `setModeration()` |
| `app/Models/SiteReport.php` | `SiteReportResolved` в `resolve()` |
| `config/notifications.php` | окно дайджеста, дни напоминаний |
| `routes/web.php` | API настроек и маршрут отписки |
| `routes/console.php` | расписание `sites:notify-expiring` |
| `database/migrations/2026_11_01_101118_add_notification_settings_to_users_table.php` | колонка `notification_settings` |
| `resources/views/notifications/unsubscribed.blade.php` | страница «уведомления отключены» |
| `resources/js/services/notificationService.js` | запросы настроек |
| `resources/js/views/cabinet/CabinetNotificationSettingsView.vue` | экран настроек |
| `resources/js/components/cabinet/CabinetNav.vue` | пункт «Уведомления» |

---

## Чем это не является

- **Не Telegram-бот.** В предложении он помечен как опциональный. Без токена
  бота, привязки аккаунта к чату и отдельного экрана настройки канал был бы
  нерабочим. `MarketplaceNotification` расширяется одним методом `via()`,
  когда до него дойдёт очередь.
- **Не рассылка.** Письма только транзакционные, по конкретному событию.
  Массовых отправок по базе нет.
- **Не колокольчик в интерфейсе.** Записи в `notifications` пишутся, но экрана
  со списком уведомлений нет — счётчик непрочитанных сообщений остаётся
  прежним, из диалогов.
- **Не realtime.** Уведомление — это письмо и запись в БД, а не WebSocket.

---

## Узкие места

1. **Дайджест и очередь вместе.** Окно в слушателе и задержка в очереди
   задаются одним и тем же конфигом. Если поменять его на ходу, уже стоящие
   в очереди задачи сохранят старую задержку.
2. **`shouldSend()` смотрит на текущее состояние.** Если все сообщения прочитали
   до отправки, письмо отменяется. Это правильно для дайджеста, но означает,
   что «письмо уже в очереди» не гарантирует доставку.
3. **Подпись ссылки отписки зависит от `APP_KEY` и `APP_URL`.** Ротация ключа
   ломает все старые ссылки в разосланных письмах — пользователю придётся
   отключать тип в настройках кабинета.
4. **Настройки не синхронизируются с VK/Яндекс-профилем.** Пользователь с
   техническим email не может получить ни одно письмо, пока не привяжет почту;
   такого экрана в кабинете пока нет.
5. **`Cache` в слушателе.** Окно дайджеста живёт в кэше: при `CACHE_STORE=array`
   (тесты) или после `cache:clear` дедупликация сбрасывается. Для прода нужен
   общий кэш — `database` или Redis, как настроено в `.env`.
6. **Проверки доставляемости нет.** `hasDeliverableEmail()` отсекает только
   технические адреса. Реальные, но несуществующие адреса дадут отскоки —
   их обработка это уже инфраструктура почтового провайдера.

---

## Тесты

- `tests/Feature/Notifications/MessageNotificationTest.php` — адресат письма,
  дайджест (1 письмо на пачку), отключённый тип, прочитанные сообщения,
  выбор каналов для аккаунтов без email, реальная отправка через `array`-транспорт
  с проверкой заголовка `List-Unsubscribe`.
- `tests/Feature/Notifications/ModerationNotificationTest.php` — одобрение,
  отклонение с причиной, отсутствие письма при возврате в `pending`,
  отсутствие дубля при повторном решении, отключённый тип.
- `tests/Feature/Notifications/ReportNotificationTest.php` — принятая и
  отклонённая жалоба, отсутствие повтора, отключённый тип.
- `tests/Feature/Notifications/SubscriptionReminderTest.php` — 7 дней, день
  окончания, «прочие даты игнорируются», одно письмо в сутки, отключённый тип.
- `tests/Feature/Notifications/NotificationSettingsTest.php` — 401 для гостя,
  значения по умолчанию, частичное обновление, валидация типа и `boolean`,
  отписка по подписи, 403 без подписи.
- `tests/js/CabinetNotificationSettingsView.spec.js` — список типов, адрес
  доставки, переключение и откат при ошибке.

Команды: `php artisan test tests/Feature/Notifications --compact` и `npm test`.
