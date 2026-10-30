# Tasks: Личный кабинет продавца

**Input**: Design documents from `/specs/005-seller-cabinet/`

**Prerequisites**: `plan.md`, `spec.md`, `research.md`, `data-model.md`, `contracts/cabinet-api.yaml`, `contracts/ui-contract.md`, `quickstart.md`

**Tests**: включены, потому что правила проекта (`AGENTS.md`, `testing-best-practices`) требуют feature-тестов для поведения, доступного через запрос, и план фиксирует тестовые срезы. Тесты пишутся до реализации и должны падать.

**Organization**: задачи сгруппированы по пользовательским историям из `spec.md`, чтобы каждую историю можно было реализовать и проверить независимо.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: можно выполнять параллельно (разные файлы, нет зависимостей)
- **[Story]**: к какой истории относится (US1…US6)
- В описании — точные пути файлов

## Path Conventions

Laravel-монолит: `app/`, `database/`, `routes/`, `resources/js/`, `tests/` в корне репозитория. Пути соответствуют `plan.md` → «Project Structure».

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: общие заготовки, нужные всем историям

- [X] T001 Сгенерировать компоненты shadcn-vue в `resources/js/components/ui/`: `npx shadcn-vue@latest add dialog alert-dialog input textarea select form` (новые npm-зависимости не добавлять)
- [X] T002 [P] Создать `resources/js/lib/cabinetConfig.js`: `PAGE_SIZE = 25`, `TITLE_MIN = 3`, `TITLE_MAX = 255`, `DESCRIPTION_MIN = 50`, `DESCRIPTION_MAX = 5000`, `MESSAGE_MAX = 2000`, `RENEWAL_DAYS = 7`, `MAX_AMOUNT = 9999999999`
- [X] T003 Добавить SPA-shell для кабинета в `routes/web.php`: `Route::get('/cabinet/{any?}', fn () => view('app'))->where('any', '.*')->name('cabinet')` (не перехватывать `/admin` и `/api`)
- [X] T004 Добавить группу API в `routes/web.php`: `Route::prefix('api/v1')` с публичными маршрутами каталога/тематик и защищённой группой `middleware('auth')` для `my/sites`, `conversations`, `messages/unread`; имена маршрутов — по таблице `plan.md` §2

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: схема, модель и общие ресурсы, без которых не работает ни одна история

**⚠️ CRITICAL**: до завершения этой фазы истории не начинать

- [X] T005 Создать миграцию `database/migrations/2026_10_30_000001_add_hidden_at_to_sites_table.php`: nullable `timestamp('hidden_at')` + индекс `(user_id, hidden_at)`; в `down()` удалить индекс и колонку
- [X] T006 Создать миграцию `database/migrations/2026_10_30_000002_add_domain_zone_to_sites_table.php`: nullable `string('domain_zone', 16)` + индекс; бэкфилл существующих строк через `DB::table('sites')->orderBy('id')->chunkById(500, …)`, значение — последняя метка домена в нижнем регистре, `null` без точки; в `down()` удалить индекс и колонку
- [X] T007 Обновить `app/Models/Site.php`: cast `hidden_at` → `datetime`; вывод `domain_zone` при сохранении домена; `needsRenewal(): bool` (`paid_until === null` OR `paid_until <= today()->addDays(7)`); `hideFromCabinet()`/`restoreFromCabinet()` (только `sold`, иначе `DomainException`); scopes `visibleInCabinet` (`whereNull('hidden_at')`) и `hiddenFromCabinet` (`whereNotNull('hidden_at')`); не менять существующие инварианты `verification_filename`/`verified`
- [X] T008 [P] Добавить scope `forParticipant(User $user)` в `app/Models/Conversation.php` (условие `buyer_id = ? OR seller_id = ?`)
- [X] T009 [P] Добавить scope `unreadFor(User $user)` в `app/Models/Message.php` (`sender_id != user`, `read_at IS NULL`)
- [X] T010 [P] Добавить `unreadMessagesCount(): int` в `app/Models/User.php` — агрегат непрочитанных по всем диалогам пользователя через `Message::unreadFor($this)`
- [X] T011 [P] Добавить состояния `hidden()`, `needsRenewal()`, `zone(string $zone)` в `database/factories/SiteFactory.php`; при необходимости — `conversationWithUnread()` в `database/factories/ConversationFactory.php`
- [X] T012 Создать `app/Http/Resources/SiteResource.php` по `data-model.md` §7.1: `id, title, domain, domainZone, description, price:int, monthlyRevenue:int, hasTrademark, status, statusLabel, verified, verificationFilename, paidUntil (Y-m-d|null), needsRenewal, isPubliclyVisible, soldAt, hiddenAt, categories, unreadMessages, createdAt, updatedAt`
- [X] T013 Обновить `resources/js/services/http.js` и `resources/js/stores/session.js`: единая обработка `401` (сброс пользователя, тост «Войдите снова», редирект на `/`), `error.status` и `error.data.errors` доступны компонентам

**Checkpoint**: миграции применяются, модель и ресурс готовы, фронтенд умеет обрабатывать потерю сессии.

---

## Phase 3: User Story 1 — Переход в кабинет и обзор своих объявлений (Priority: P1) 🎯 MVP

**Goal**: авторизованный пользователь открывает `/cabinet/sites` из меню иконки и видит только свои объявления с ключевыми полями, пометками подписки и числом непрочитанных.

**Independent Test**: войти, нажать аватар → «Личный кабинет», сверить состав и поля списка; проверить гостя, пустое состояние и пагинацию.

### Tests for User Story 1

- [X] T014 [P] [US1] Feature-тест `tests/Feature/Cabinet/SiteListTest.php`: гость → 401; возвращаются только объявления текущего пользователя; 25 на страницу; сортировка `created_at desc, id desc`; `hidden=1` отдаёт только скрытые; `unreadMessages` считается по диалогам объявления; `categories` загружены без N+1
- [X] T015 [P] [US1] Vitest `tests/js/cabinetService.spec.js` и `tests/js/cabinetStore.spec.js`: загрузка, пагинация, ошибка, сброс при 401; сервис ходит в `/api/v1/my/sites`

### Implementation for User Story 1

- [X] T016 [US1] Реализовать `index()` в `app/Http/Controllers/Api/MySiteController.php`: `$request->user()->sites()` + `with('categories')` + `withCount` непрочитанных + фильтр `hidden` + `paginate(25)` + `SiteResource::collection`
- [X] T017 [P] [US1] Создать `resources/js/services/cabinetService.js` с `fetchMySites({ page, hidden })` на основе `http.js`
- [X] T018 [US1] Создать `resources/js/stores/cabinet.js`: `items`, `meta`, `status`, `error`, `load()`, `goToPage()`, `reset()`
- [X] T019 [US1] Обновить `resources/js/router/index.js`: редирект `/cabinet` → `/cabinet/sites`, маршрут `/cabinet/sites`, `meta.requiresAuth`, `beforeEach` с ожиданием `session.initialize()` и тостом для гостя
- [X] T020 [US1] Обновить `resources/js/components/layout/SiteHeader.vue`: заменить `openProfile()` на переход `/cabinet/sites`, пункт меню «Личный кабинет»
- [X] T021 [US1] Создать `resources/js/components/cabinet/CabinetLayout.vue` и `CabinetNav.vue` (разделы «Мои объявления», «Сообщения», «Скрытые»; активный раздел по маршруту)
- [X] T022 [US1] Создать `resources/js/views/cabinet/CabinetSitesView.vue`, `resources/js/components/cabinet/SiteCard.vue`, `StatusBadge.vue`: поля из `data-model.md` §7.1, плашка «Скрыто из каталога» при `isPubliclyVisible === false`, счётчик непрочитанных, кнопки «Редактировать»/«Сменить статус»/«Скрыть» (видимость по статусу), пагинация 25
- [X] T023 [US1] Добавить состояния экрана в `CabinetSitesView.vue`: skeleton при загрузке, пустое состояние с кнопкой «Добавить объявление», ошибка с «Повторить», `aria-busy`
- [X] T024 [P] [US1] Vitest `tests/js/CabinetSitesView.spec.js`: пустое состояние, ошибка, рендер карточки, скрытие кнопок для проданного

**Checkpoint**: US1 работает и проверяется отдельно; кабинет открывается, список корректен.

---

## Phase 4: User Story 2 — Создание объявления (Priority: P1)

**Goal**: продавец создаёт объявление со всеми полями и тематиками, получает имя файла проверки; объявление скрыто из каталога до оплаты.

**Independent Test**: создать объявление с двумя тематиками, проверить запись, владельца, статус «Активен», `verified = false`, уникальный файл проверки, отсутствие в каталоге; проверить отказ при неполных данных и дубле домена.

### Tests for User Story 2

- [X] T025 [P] [US2] Feature-тест `tests/Feature/Cabinet/SiteManagementTest.php`: пустой payload; `title` 3–255; `description` 50–5000; `price`/`monthlyRevenue` ≥ 0 и ≤ 9999999999; `categories` ≥ 1 и `exists`; домен обязателен и валиден; дубль домена среди `active`/`inactive` отклоняется (с `www.`, верхним регистром и завершающим `/`); повтор с тем же доменом у себя при редактировании разрешён; созданное объявление имеет `user_id` текущего пользователя, `status = active`, `verified = false`, уникальный `verification_filename`; категории привязаны; гость → 401
- [X] T026 [P] [US2] Vitest `tests/js/CabinetSiteForm.spec.js`: обязательные поля, ошибки 422 по полям, блокировка кнопки при отправке, сохранение введённых значений при ошибке

### Implementation for User Story 2

- [X] T027 [US2] Создать `app/Rules/AvailableDomain.php`: нормализация (trim, нижний регистр, снять `www.`, убрать завершающий `/`), поиск другого объявления в статусе `active`/`inactive` с тем же доменом, исключение текущего `id` при обновлении, сообщение «Этот домен уже занят другим объявлением»
- [X] T028 [US2] Создать `app/Http/Requests/StoreSiteRequest.php` с правилами из `data-model.md` §8: `title` `required|string|min:3|max:255`; `domain` `required|string|max:253` + формат + `AvailableDomain`; `description` `required|string|min:50|max:5000`; `price`/`monthlyRevenue` `required|numeric|min:0|max:9999999999`; `hasTrademark` `boolean`; `categories` `required|array|min:1` + `categories.*` `integer|exists:categories,id`
- [X] T029 [P] [US2] Создать `app/Http/Resources/CategoryResource.php` (`id, name, slug`) и `app/Http/Controllers/Api/CategoryController.php` (`GET /api/v1/categories`, `Category::orderBy('name')->get()`)
- [X] T030 [US2] Реализовать `store()` в `app/Http/Controllers/Api/MySiteController.php`: создание через `validated()` только разрешённых полей (`title, domain, description, price, monthly_revenue, has_trademark`), `user_id` из сессии, `status = active`, `verified = false`, `categories()->sync()`, ответ `201` с `SiteResource`
- [X] T031 [P] [US2] Создать `resources/js/services/categoriesService.js` (`fetchCategories()`) и добавить `createSite(payload)` в `resources/js/services/cabinetService.js`
- [X] T032 [US2] Создать `resources/js/views/cabinet/CabinetSiteFormView.vue` и `resources/js/components/cabinet/SiteForm.vue` по `ui-contract.md` §5: поля, обязательность, счётчики, выбор тематик, блокировка отправки, переход в `/cabinet/sites` после успеха
- [X] T033 [P] [US2] Создать `resources/js/components/cabinet/VerificationBlock.vue`: имя файла, кнопка «Скопировать» (Clipboard API + fallback), инструкция разместить файл в корне сайта
- [X] T034 [US2] Добавить отображение ошибок 422 в `SiteForm.vue`: первое сообщение под полем, общий текст в тосте, данные не сбрасываются; при 401 — тост и сохранение значений формы

**Checkpoint**: US1 + US2 дают MVP: кабинет и создание объявлений.

---

## Phase 5: User Story 3 — Редактирование и смена статуса (Priority: P2)

**Goal**: продавец редактирует объявление, меняет статусы по допустимым переходам, подтверждает «Продан», скрывает проданное и возвращает его в список.

**Independent Test**: изменить поля; пройти цепочку статусов; отметить проданным; скрыть/вернуть; проверить, что файл проверки и `verified` не изменились, а чужие объявления дают 404.

### Tests for User Story 3

- [X] T035 [P] [US3] Feature-тест `tests/Feature/Cabinet/SiteStatusTest.php`: matrix переходов через data provider по `ListingStatus`; недопустимый переход → 422; «Продан» терминален; `sold_at` заполняется; смена статуса одного объявления не меняет `paid_until` и статусы других; чужое объявление → 404; гость → 401
- [X] T036 [P] [US3] Feature-тест `tests/Feature/Cabinet/SiteVisibilityTest.php`: скрыть можно только `sold` (иначе 422); после скрытия запись в `hidden=1` и отсутствует в `hidden=0`; возврат работает; `status`, `paid_until`, платежи и диалоги не меняются; чужое → 404
- [X] T037 [P] [US3] Vitest `tests/js/StatusMenu.spec.js`: показываются только `availableStatuses`, подтверждение для «Продан», тосты успеха и ошибки

### Implementation for User Story 3

- [X] T038 [US3] Создать `app/Http/Requests/UpdateSiteRequest.php` с теми же правилами, что `StoreSiteRequest`, с исключением текущего `id` в `AvailableDomain`; запретить редактирование при `status = sold` (422)
- [X] T039 [US3] Добавить `show()` и `update()` в `app/Http/Controllers/Api/MySiteController.php`: выборка `$user->sites()->with('categories')->findOrFail($id)`, обновление только `title, domain, description, price, monthly_revenue, has_trademark` + `categories()->sync()`
- [X] T040 [US3] Создать `app/Http/Requests/ChangeSiteStatusRequest.php`: `status` `required|string|in:active,inactive,withdrawn,sold`
- [X] T041 [US3] Создать `app/Http/Controllers/Api/SiteStatusController.php`: выборка по владельцу, `Site::changeStatus()`, `DomainException` → 422 с текстом причины, ответ `SiteResource`
- [X] T042 [US3] Создать `app/Http/Controllers/Api/SiteVisibilityController.php`: `store` вызывает `hideFromCabinet()`, `destroy` — `restoreFromCabinet()`; выборка по владельцу; ответ `SiteResource`
- [X] T043 [US3] Расширить `app/Http/Resources/SiteResource.php`: `hiddenAt`, `availableStatuses` только для детальной карточки (`when` по `availableStatuses`)
- [X] T044 [US3] Создать `resources/js/components/cabinet/StatusMenu.vue` и `ConfirmSoldDialog.vue` на `alert-dialog`: меню допустимых статусов, подтверждение «Продан», обработка 422
- [X] T045 [US3] Добавить в `resources/js/stores/cabinet.js` действия `updateSite`, `changeStatus`, `hideSite`, `restoreSite`, `loadHidden` и обновление списка после действий
- [X] T046 [US3] Доработать `resources/js/views/cabinet/CabinetSiteFormView.vue` для режима редактирования (загрузка `fetchSite(id)`, скрытие/показ read-only полей, недоступность для проданного) и создать `resources/js/views/cabinet/CabinetHiddenView.vue` с действием «Вернуть в список»
- [X] T047 [P] [US3] Vitest `tests/js/CabinetHiddenView.spec.js`: пустое состояние, возврат объявления, отсутствие кнопок редактирования

**Checkpoint**: US1–US3 работают независимо; управление объявлениями завершено.

---

## Phase 6: User Story 4 — Ответы на сообщения покупателей (Priority: P2)

**Goal**: продавец видит диалоги по своим объявлениям, читает историю, отвечает; входящие помечаются прочитанными; в шапке — число непрочитанных.

**Independent Test**: создать диалог от покупателя, открыть продавцом, ответить, проверить счётчики и режим «только чтение» для непубличного объявления.

### Tests for User Story 4

- [X] T048 [P] [US4] Feature-тест `tests/Feature/Cabinet/ConversationApiTest.php`: список только своих диалогов и сортировка `last_message_at desc`; чужой диалог → 404; открытие помечает входящие прочитанными и не трогает свои; ответ 1–2000 символов, пустой/2001 → 422; ответ в диалог по непубличному объявлению → 422; гость → 401
- [X] T049 [P] [US4] Feature-тест `tests/Feature/Cabinet/UnreadMessagesTest.php`: агрегат учитывает только чужие непрочитанные; 0 когда всё прочитано; уменьшается после открытия диалога; изоляция между пользователями
- [X] T050 [P] [US4] Vitest `tests/js/conversationsStore.spec.js`: загрузка списка, открытие (mark read), отправка, состояние read-only, ошибка отправки

### Implementation for User Story 4

- [X] T051 [US4] Создать `app/Http/Resources/ConversationResource.php` и `MessageResource.php` по `data-model.md` §7.4–7.5: `role`, `counterparty`, `lastMessage`, `unreadCount`, `isReadOnly`, `lastMessageAt`; сообщение — `isMine`, `readAt`
- [X] T052 [US4] Создать `app/Http/Controllers/Api/ConversationController.php`: `index()` через `forParticipant($user)` с `with(['site','buyer','seller','latestMessage'])`, `paginate(25)`; `show()` дополнительно вызывает `markReadFor($user)` и отдаёт `messages` по возрастанию `created_at`
- [X] T053 [US4] Создать `app/Http/Controllers/Api/MessageController.php` и `app/Http/Requests/StoreMessageRequest.php`: `body` `required|string|min:1|max:2000`, отправка через `Conversation::postMessage()`, `DomainException` → 422, ответ `201`
- [X] T054 [US4] Создать `app/Http/Controllers/Api/UnreadMessageController.php`: `{count}` через `$request->user()->unreadMessagesCount()`
- [X] T055 [P] [US4] Создать `resources/js/services/conversationService.js`: `fetchConversations`, `fetchConversation`, `sendMessage`, `fetchUnreadCount`
- [X] T056 [US4] Создать `resources/js/stores/conversations.js`: список, активный диалог, сообщения, `unreadCount`, действия загрузки/открытия/отправки
- [X] T057 [US4] Создать `resources/js/views/cabinet/CabinetMessagesView.vue`, `CabinetConversationView.vue` и компоненты `resources/js/components/messages/ConversationList.vue`, `ConversationThread.vue`, `MessageComposer.vue`, `ReadOnlyNotice.vue` по `ui-contract.md` §6
- [X] T058 [US4] Добавить бейдж непрочитанных в `resources/js/components/layout/SiteHeader.vue` и `refreshUnread()` в `resources/js/stores/session.js`; вызывать при инициализации и в `router.afterEach`
- [X] T059 [P] [US4] Vitest `tests/js/SiteHeaderUnread.spec.js`: число на аватаре, скрытие при 0, `99+`, сброс при выходе

**Checkpoint**: переписка со стороны продавца работает, счётчики корректны.

---

## Phase 7: User Story 5 — Переписка покупателя с продавцом (Priority: P2)

**Goal**: покупатель открывает окно «Связаться» на каталоге, отправляет первое сообщение, продолжает диалог в кабинете; каталог получает реальные данные.

**Independent Test**: отправить первое сообщение из каталога, увидеть диалог у обеих сторон, повторить отправку (новый диалог не создаётся), проверить гостя и свой лот.

### Tests for User Story 5

- [X] T060 [P] [US5] Feature-тест `tests/Feature/Cabinet/StartConversationTest.php`: первый `POST /conversations` создаёт диалог и сообщение; повторный использует тот же диалог; свой лот → 422; непубличное объявление → 422; пустой/длинный текст → 422; гость → 401; несуществующий `siteId` → 404
- [X] T061 [P] [US5] Feature-тест `tests/Feature/Catalog/CatalogApiTest.php`: только публично видимые лоты (`status = active`, `paid_until >= today`); фильтры цены/дохода/зон/проверки; «верхняя граница = и выше»; пагинация 25; лоты за пределами страницы; невалидные параметры нормализуются; зоны с `count` и порядком `ru, com, de`; пустой результат
- [X] T062 [P] [US5] Vitest `tests/js/ContactSellerDialog.spec.js`: гость видит кнопки входа; отправка текста; ошибка 422; окно закрывается после успеха; `Esc` и возврат фокуса
- [X] T063 [P] [US5] Обновить `tests/js/catalogService.spec.js` и `tests/js/ListingCard.spec.js` под числовой `id` и HTTP-сервис

### Implementation for User Story 5

- [X] T064 [US5] Создать `app/Http/Controllers/Api/CatalogController.php` и `app/Http/Resources/PublicSiteResource.php`: `index()` по правилам контракта `003` (публичная видимость, фильтры, «и выше», пагинация 25, сортировка `created_at desc, id asc`), `zones()` с группировкой по `domain_zone`
- [X] T065 [US5] Создать `app/Http/Requests/StartConversationRequest.php`: `siteId` `required|integer|exists:sites,id`, `body` `required|string|min:1|max:2000`
- [X] T066 [US5] Добавить `store()` в `app/Http/Controllers/Api/ConversationController.php`: поиск публично видимого объявления, `Conversation::startFor()`, `postMessage()`, `DomainException` → 422, ответ `201` с `ConversationResource`
- [X] T067 [US5] Перевести `resources/js/services/catalogService.js` с моков на `/api/v1/catalog` и `/api/v1/catalog/zones`, сохранив сигнатуры `fetchListings`/`fetchZones` и нормализацию параметров
- [X] T068 [US5] Создать `resources/js/components/catalog/ContactSellerDialog.vue` и подключить к `resources/js/views/CatalogView.vue` через `@contact`: textarea 0/2000, состояние гостя, сообщение для своего лота, сохранение фильтров и страницы после успеха
- [X] T069 [US5] Адаптировать кабинет для покупателя: в `ConversationResource` роль `buyer`, в `CabinetMessagesView.vue`/`ConversationThread.vue` — подписи «Продавец», пустое состояние покупателя и переход в каталог

**Checkpoint**: переписка работает в обе стороны, каталог отдаёт реальные лоты.

---

## Phase 8: User Story 6 — Отображение подписки (Priority: P3)

**Goal**: продавец видит «оплачено до», пометку «требует продления» (7 дней), историю платежей и пояснение об отсутствии оплаты в кабинете.

**Independent Test**: проверить границы порога на датах −1/0/+6/+7/+8 дней, историю платежей и отсутствие платёжных действий.

### Tests for User Story 6

- [X] T070 [P] [US6] Feature-тест `tests/Feature/Cabinet/SubscriptionDisplayTest.php`: `needsRenewal` на датах −1/0/+6/+7/+8 и при `null`; `paidUntil` в ответе; история платежей отсортирована новыми сначала; объявление с истёкшей подпиской отсутствует в каталоге и присутствует в кабинете; маршруты оплаты отсутствуют (405/404)

### Implementation for User Story 6

- [X] T071 [US6] Создать `app/Http/Resources/SubscriptionPaymentResource.php` (`id, amount:int, currency, periodMonths, status, statusLabel, paidAt, createdAt`)
- [X] T072 [US6] Дополнить `MySiteController@show` в `app/Http/Controllers/Api/MySiteController.php`: `$site->subscriptionPayments()->latest('created_at')->latest('id')->get()` → `payments` в `SiteDetail`
- [X] T073 [US6] Создать `resources/js/components/cabinet/PaymentHistory.vue` и расширить `SubscriptionBlock.vue`: дата, «Требует продления» (7 дней), «Скрыто из каталога», пустое состояние платежей, сообщение «Оплата появится в следующей версии», отсутствие платёжных кнопок
- [X] T074 [P] [US6] Vitest `tests/js/SubscriptionBlock.spec.js`: границы пометки, пустая история, отсутствие кнопок оплаты

**Checkpoint**: все истории работают; оплата отсутствует по решению спецификации.

---

## Phase 9: Polish & Cross-Cutting Concerns

- [X] T075 Прогнать `php artisan test --compact` и устранить падения (все `tests/Feature/**`)
- [X] T076 Прогнать `npm run test` и устранить падения (все `tests/js/**`)
- [X] T077 Прогнать `vendor/bin/pint --dirty --format agent` и закоммитить форматирование PHP-файлов
- [X] T078 Выполнить `npm run build`; проверить, что `/admin` открывается, `/up` отвечает, SPA-маршруты `/cabinet/*` не перехватывают API
- [ ] T079 Пройти ручные сценарии 5.1–5.9 из `specs/005-seller-cabinet/quickstart.md`
- [X] T080 Проверить безопасность: только `validated()` в контроллерах, отсутствие `v-html`, 404 на чужие ресурсы, CSRF на мутирующих запросах, `verified`/`user_id`/`hidden_at` не принимаются из запроса
- [X] T081 Проверить производительность списка: `with('categories')`, `withCount`, отсутствие N+1 в `CabinetSitesView.vue` и `ConversationList.vue`

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: без зависимостей
- **Foundational (Phase 2)**: зависит от Setup, блокирует все истории
- **US1 (Phase 3)**: после Foundational — даёт MVP вместе с US2
- **US2 (Phase 4)**: после Foundational; использует `SiteResource` и `SiteForm`; не зависит от US3+
- **US3 (Phase 5)**: после Foundational; использует `SiteResource`, `cabinetService`, `cabinet`-стор из US1–US2
- **US4 (Phase 6)**: после Foundational; не зависит от US2/US3 (диалоги создаются фабриками в тестах), но UI использует `CabinetLayout`
- **US5 (Phase 7)**: после US4 (общие ресурсы диалогов) и US2 (создание объявлений для ручной проверки); включает каталог-API
- **US6 (Phase 8)**: после US1 (карточка и `needsRenewal`)
- **Polish (Phase 9)**: после всех выбранных историй

### User Story Dependencies

- **US1 (P1)**: независима, MVP-основа
- **US2 (P1)**: независима после Foundational, дополняет MVP
- **US3 (P2)**: зависит от US1 (список/карточка) и US2 (форма) в части UI
- **US4 (P2)**: независима после Foundational; использует `CabinetLayout` из US1
- **US5 (P2)**: зависит от US4 (ресурсы диалогов) и каталог-API
- **US6 (P3)**: зависит от US1 (карточка)

### Within Each User Story

1. Тесты пишутся первыми и падают
2. Модели/правила → Form Request → контроллер → ресурс
3. Сервис → стор → представление/компонент
4. Проверка истории до перехода к следующей

### Parallel Opportunities

- T002 параллельно с T001, T003/T004 — последовательно (один файл `routes/web.php`)
- Foundational: T008–T011 параллельны; T005–T007 последовательны (модель зависит от миграций); T012 после T007
- US1: T014 и T015 параллельны; T017 параллелен T016; T024 параллелен T022
- US2: T025 и T026 параллельны; T029 и T031 параллельны; T033 параллелен T032
- US3: T035–T037 параллельны; T040 и T042 параллельны
- US4: T048–T050 параллельны; T055 параллелен T052
- US5: T060–T063 параллельны; T065 и T067 параллельны
- US6: T070 и T074 параллельны

---

## Parallel Example: User Story 1

```bash
# Тесты US1 одновременно:
Task: "Feature-тест списка в tests/Feature/Cabinet/SiteListTest.php"
Task: "Vitest сервиса и стора в tests/js/cabinetService.spec.js, tests/js/cabinetStore.spec.js"

# Реализация US1 параллельно по разным файлам:
Task: "index() в app/Http/Controllers/Api/MySiteController.php"
Task: "resources/js/services/cabinetService.js"
Task: "resources/js/components/layout/SiteHeader.vue"
```

## Parallel Example: User Story 5

```bash
Task: "Feature-тест старта переписки в tests/Feature/Cabinet/StartConversationTest.php"
Task: "Feature-тест каталога в tests/Feature/Catalog/CatalogApiTest.php"
Task: "Vitest окна в tests/js/ContactSellerDialog.spec.js"
```

---

## Implementation Strategy

### MVP First (US1 + US2)

1. Phase 1 Setup
2. Phase 2 Foundational
3. Phase 3 US1 → проверить кабинет и список
4. Phase 4 US2 → проверить создание объявления
5. **STOP и VALIDATE**: кабинет пригоден для продавца (просмотр + создание)

Промежуточный вариант MVP — только US1 (просмотр списка) для ранней демонстрации.

### Incremental Delivery

1. Foundational → US1 (просмотр) → демо
2. US2 (создание) → демо
3. US3 (статусы и скрытие) → демо
4. US4 (ответы продавца) → демо
5. US5 (покупатель + реальный каталог) → демо
6. US6 (подписка) → демо
7. Polish → релиз

### Parallel Team Strategy

- После Foundational: разработчик A — US1+US2 (кабинет), разработчик B — US4 (сообщения), разработчик C — каталог-API для US5
- US3 и US6 подключаются после US1/US2, US5 — после US4

---

## Notes

- Отклонение от T001: реестр shadcn-vue был недоступен (ошибка сети), поэтому `dialog`, `alert-dialog`, `input`, `textarea` добавлены вручную по образцу существующих компонентов; `select`/`form` не понадобились — тематики выбираются чекбоксами, статусы — существующим `DropdownMenu`.
- T079 (ручные сценарии `quickstart.md`) не выполнен в среде агента: вход через VK ID/Yandex ID требует реальных учётных записей провайдеров. Проверены автоматические тесты и smoke-запросы (`/cabinet/sites` → 200, `/api/v1/catalog` → JSON, гость → 401, `/admin` → 302, `/up` → 200).
- [P] — разные файлы, нет зависимостей от незавершённых задач
- Метки [US*] обеспечивают трассировку к `spec.md`
- Каждая история завершается независимой проверкой (`quickstart.md`)
- Тесты пишутся до реализации; после каждой задачи — узкий прогон
- Не добавлять платёжные эндпоинты: оплата вне объёма (FR-029)
- Не менять Orchid и не выдавать продавцам доступ в `/admin`