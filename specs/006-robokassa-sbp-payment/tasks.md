---

description: "Task list for Robokassa SBP subscription payment"
---

# Tasks: Оплата подписки через СБП Robokassa

**Input**: Design documents from `/specs/006-robokassa-sbp-payment/`

**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/

**Tests**: Included as regression coverage because the repository rules (`AGENTS.md`) require tests for behavior changes. They are not strict TDD unless the team chooses to write them first.

**Organization**: Tasks are grouped by user story so each story is independently implementable and testable.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: `[US1]`, `[US2]`, `[US3]`, `[US4]`
- Every task includes an exact file path

## Path Conventions

Laravel monolith with Vue 3 SPA and Orchid admin:

- Backend: `app/`, `config/`, `database/`, `routes/`, `bootstrap/`
- Frontend: `resources/js/`
- Tests: `tests/Feature/`, `tests/js/`

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Connection config, CSRF, and rate limiters used by all payment stories.

- [x] T001 Add Robokassa connection config to `config/services.php`: merchant login, `password1`/`password2`, test login/passwords, test-mode flag, result/success/fail URLs, `sno`, `tax`, `payment_object`, script URL, all read via `env()`.
- [x] T002 [P] Document the `ROBOKASSA_*` variables in `.env.example` (production and test credentials, `ROBOKASSA_TEST_MODE`, result/success/fail URLs, fiscal settings).
- [x] T003 Exclude `api/v1/payments/robokassa/result` from CSRF in `bootstrap/app.php` (keep the existing `api/vk/callback` exclusion).
- [x] T004 [P] Register `create-payment`, `payment-status`, and `payment-callback` rate limiters in `app/Providers/AppServiceProvider.php` (5/min per user, 60/min per user, 120/min per IP).

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Schema, enums, models, factories, and shared payment services that every user story depends on.

**⚠️ CRITICAL**: No user story work can begin until this phase is complete.

- [x] T005 Create migration `database/migrations/2026_11_04_000001_create_subscription_settings_table.php` with `base_price decimal(10,2) unsigned default 0`, `periods json`, `discounts json`, `target_status string(16) default 'active'`, `qr_ttl_minutes unsignedSmallInteger default 15`, `is_active boolean default false`, timestamps, and a reversible `down()`.
- [x] T006 Create migration `database/migrations/2026_11_04_000002_add_robokassa_fields_to_subscription_payments_table.php` adding nullable `site_domain`, `base_price`, `base_amount`, `discount_percent default 0`, `discount_amount default 0`, `target_status`, `receipt_email`, `receipt_payload`, `qr_expires_at`, `provider`, `payment_method`, `provider_payload`, `failure_reason`, `review_reason`, `is_test default false`, plus indexes `site_id+status`, `status+qr_expires_at`, `site_domain`, `created_at`, `paid_at`, `payment_method`; `down()` drops indexes and columns.
- [x] T007 Create migration `database/migrations/2026_11_04_000003_create_subscription_payment_reviews_table.php` with `subscription_payment_id` (cascade), `admin_id` (restrict), `decision string(16)`, `comment text`, timestamps, index `subscription_payment_id+created_at`, and a reversible `down()`.
- [x] T008 [P] Extend `app/Enums/PaymentStatus.php` with `RequiresReview`, `isOpen()` (pending/requires_review), `isFinal()` compatibility, `label()`, and explicit transition guards (`pending→paid|cancelled|failed|requires_review`, `requires_review→paid|failed`, `cancelled|failed→paid`).
- [x] T009 [P] Create `app/Enums/PaymentMethod.php` with `Sbp` (`sbp`, label «СБП»).
- [x] T010 [P] Create `app/Enums/PaymentProvider.php` with `Robokassa` (`robokassa`).
- [x] T011 [P] Create `app/Enums/PaymentReviewDecision.php` with `Accepted` / `Rejected` and Russian labels.
- [x] T012 [P] Create `app/Models/SubscriptionSetting.php`: `current()` via `Cache::remember('subscription:settings', 60, …)`, `defaults()`, `periodOptions()`, `discountFor(int $months)`, casts for `periods`/`discounts`, cache invalidation on `saved`/`deleted`.
- [x] T013 [P] Extend `app/Models/SubscriptionPayment.php`: add new fields to `#[Fillable]`, casts (`provider_payload`/`receipt_payload` array, `qr_expires_at` datetime, `is_test` bool), `reviews()` / `latestReview()` relations, `isOpen()` / `requiresReview()` helpers, keep `markAsPaid()`/`markAsFailed()`/`markAsCancelled()` compatible.
- [x] T014 [P] Create `app/Models/SubscriptionPaymentReview.php` with `payment()` and `admin()` relations, `decision` cast, `comment` fillable.
- [x] T015 [P] Create `database/factories/SubscriptionSettingFactory.php` (base 500.00, periods `[1,3,6,12]`, discounts `{3:10,6:15,12:25}`, active) with states `inactive()`, `withoutDiscounts()`, `targetInactive()`, `shortQrTtl()`.
- [x] T016 [P] Create `database/factories/SubscriptionPaymentReviewFactory.php` with accepted/rejected states and an admin user.
- [x] T017 [P] Extend `database/factories/SubscriptionPaymentFactory.php` with snapshot/provider defaults (`site_domain`, `base_price`, `base_amount`, `discount_percent`, `discount_amount`, `target_status`, `receipt_email`, `receipt_payload`, `qr_expires_at`, `provider`, `payment_method`, `is_test`) and states `paid()`, `failed()`, `cancelled()`, `requiresReview()`, `test()`, `expired()`.
- [x] T018 Create `database/seeders/SubscriptionSettingSeeder.php` with production defaults (`base_price=0.00`, `periods=[1,3,6,12]`, `discounts={}`, `target_status=active`, `qr_ttl_minutes=15`, `is_active=false`).
- [x] T019 Update `app/Models/Site.php`: `acceptsPayments()` includes `ListingStatus::Withdrawn`; add `hasDomainConflictFor(ListingStatus $target): bool`, `applyPaidSubscription(int $months, ListingStatus $target): void` (conflict check, extend from `max(paid_until, today)`, clear `unpublish_reason`, set target status), and `latestPayment(): HasOne` (`latestOfMany`).
- [x] T020 Create `app/Services/Payments/SubscriptionPricing.php`: `quote(int $months)` returns `basePrice`, `baseAmount`, `discountPercent`, `discountAmount`, `total`; `gross = base_price * months`, `total = (int) round(gross * (100 - discount) / 100, 0, PHP_ROUND_HALF_UP)`; rejects periods outside settings and totals ≤ 0.
- [x] T021 Create `app/Services/Payments/RobokassaGateway.php`: `startPayload(SubscriptionPayment $payment)`, `receipt()`, `signature()`, `verifyCallback(array $payload)`, `scriptUrl()`, test-mode credential selection, and `outSum` as an integer-ruble string.
- [x] T022 [P] Update `tests/Feature/Models/SiteSubscriptionTest.php`: split `test_withdrawn_and_sold_sites_reject_payment` into withdrawn-accepts and sold-rejects tests; add `applyPaidSubscription` tests for target status, future `paid_until`, and domain conflict.
- [x] T023 [P] Update `tests/Feature/Orchid/SubscriptionPaymentReadOnlyTest.php` for the new fields/status and legacy rows without snapshots.
- [x] T024 Create `tests/Feature/Payments/SubscriptionPricingTest.php`: totals for 1/3/6/12 months, 0.5 rounds up, no discount for 1 month, invalid period rejected, zero total rejected, inactive tariff rejected.

**Checkpoint**: Schema, enums, models, factories, and shared services are ready; user stories can start.

---

## Phase 3: User Story 1 - Продление подписки объявления через СБП QR (Priority: P1) 🎯 MVP

**Goal**: Seller creates an invoice from the cabinet, gets an SBP QR/link, Robokassa confirms it, the subscription is extended and the listing moves to the admin-configured target status.

**Independent Test**: Create a listing without a paid subscription, open the cabinet, run a test SBP payment, send the provider confirmation, and verify `paid_until` grew by exactly the chosen term, the payment is «Оплачен», and the listing moved to the target status (back to catalog when «Активен» and moderation approved).

### Tests for User Story 1

- [x] T025 [P] [US1] Create `tests/Feature/Payments/PaymentInvoiceTest.php`: ownership 404, sold 422, withdrawn allowed, email prefill/required, single open attempt, previous pending cancelled, `requires_review` blocks a new invoice, domain conflict 422, snapshot fields, 201 response with `robokassa` payload.
- [x] T026 [P] [US1] Create `tests/Feature/Payments/PaymentCallbackTest.php`: valid signature, invalid signature 400, amount/currency/method mismatch → `requires_review`, idempotent duplicate, late payment, sold listing, domain conflict, unknown invoice, test-mode rejection in production, `paid_until` extension and target status, catalog visibility.
- [x] T027 [P] [US1] Create `tests/js/PaymentDialog.spec.js`: form fields, price/discount/total rendering, target-status warning, QR container and provider-script failure message, success state.
- [x] T028 [P] [US1] Create `tests/js/paymentService.spec.js`: pricing/options/create/status/start/history calls, payload shapes, and error-code mapping.

### Implementation for User Story 1

- [x] T029 [US1] Create `app/Http/Requests/StoreSubscriptionPaymentRequest.php` validating `periodMonths` (integer, in configured periods) and `receiptEmail` (`required|email:rfc|max:255`, trimmed/lower-cased, defaulted from a deliverable account email).
- [x] T030 [US1] Create `app/Services/Payments/PaymentInvoiceService.php`: transactional `lockForUpdate` on the site, owner/status/tariff/email/domain-conflict checks, cancel previous `pending`, block `requires_review`, persist snapshots and `qr_expires_at`, return the payment with Robokassa payload.
- [x] T031 [US1] Create `app/Services/Payments/PaymentFinalizer.php`: verify callback, lock payment and site, handle sold/domain-conflict/not-payable → `requires_review` with `paid_at`, otherwise `paid` + `provider_payload` + extension + target status; idempotent for `paid`.
- [x] T032 [P] [US1] Create `app/Http/Resources/PricingResource.php` (`basePrice`, `isActive`, `periods` with `months`, `discountPercent`, `total`, `perMonth`).
- [x] T033 [P] [US1] Create `app/Http/Resources/PaymentOptionsResource.php` (`canPay`, `blockedReason`, `blockedMessage`, `basePrice`, `periods`, `defaultReceiptEmail`, `hasDeliverableEmail`, `targetStatus`, `targetStatusLabel`, `willBeHidden`, `activePayment`, `site`).
- [x] T034 [P] [US1] Create `app/Http/Resources/RobokassaStartResource.php` (`merchantLogin`, `outSum`, `invId`, `receipt`, `signature`, `isTest`, `scriptUrl`).
- [x] T035 [P] [US1] Create `app/Http/Resources/SubscriptionPaymentSummaryResource.php` (`id`, `status`, `statusLabel`, `amount`, `periodMonths`, `qrExpiresAt`, `reviewReason`, `createdAt`).
- [x] T036 [US1] Update `app/Http/Resources/SubscriptionPaymentResource.php` with `invoiceNumber`, `siteDomain`, `currentSiteDomain`, `receiptEmail`, price/discount fields, `targetStatus`, `paymentMethod`, `isTest`, `qrExpiresAt`, `failureReason`, `reviewReason`, keeping existing keys.
- [x] T037 [US1] Update `app/Http/Resources/SiteResource.php` to expose `latestPayment` (summary) and `canRenew` for the list, keeping the existing detail `payments` collection.
- [x] T038 [US1] Create `app/Http/Controllers/Api/SitePaymentController.php` with `options`, `store`, and `start` actions (ownership via `findOrFail`, 409 when a payment cannot be continued).
- [x] T039 [US1] Create `app/Http/Controllers/Api/PaymentStatusController.php` returning the payment resource for polling.
- [x] T040 [US1] Create `app/Http/Controllers/Api/RobokassaResultController.php`: no session, `text/plain` `OK{InvId}` on accepted notifications, `400` on invalid signature or forbidden test callback.
- [x] T041 [US1] Register routes in `routes/web.php`: public `GET /api/v1/pricing`, auth group `GET /my/sites/{site}/payment-options`, `POST /my/sites/{site}/payments`, `GET /my/sites/{site}/payments`, `GET /my/payments/{payment}`, `POST /my/payments/{payment}/start`, and public `POST /api/v1/payments/robokassa/result` with `throttle:payment-callback`.
- [x] T042 [US1] Update `app/Http/Controllers/Api/MySiteController.php` to eager-load `latestPayment` for the list and keep `subscriptionPayments` for the detail.
- [x] T043 [US1] Create `resources/js/services/paymentService.js` with `fetchPricing`, `fetchPaymentOptions`, `createPayment`, `fetchPayment`, `startPayment`, and `fetchPaymentHistory`, normalizing error codes.
- [x] T044 [US1] Create `resources/js/components/cabinet/PaymentDialog.vue`: form step (term, base price, discount, total, receipt email, target status, hidden-listing warning), dynamic Robokassa script loading, QR container and payment link, success state.
- [x] T045 [US1] Update `resources/js/components/cabinet/SiteCard.vue` with «Продлить» for `active`/`inactive`/`withdrawn` and no action for `sold`.
- [x] T046 [US1] Update `resources/js/components/cabinet/SubscriptionBlock.vue` to refresh `paid_until`, «Требует продления», and hidden state after payment without a full reload.
- [x] T047 [US1] Update `resources/js/stores/cabinet.js` with `latestPayment`, `canRenew`, and a post-payment refresh action.
- [x] T048 [US1] Wire `PaymentDialog.vue` into `resources/js/views/cabinet/CabinetSitesView.vue` and open it from `SiteCard` events.

**Checkpoint**: US1 is fully functional — invoice, QR, callback, extension, and target status.

---

## Phase 4: User Story 2 - Контроль статуса оплаты и повторные попытки (Priority: P2)

**Goal**: Seller can reopen a pending QR, sees expiry/failure states, and retries safely without double charges.

**Independent Test**: Create a payment, close the dialog, reopen it from history, wait for expiry, verify cancellation and retry; separately verify a provider error and a repeated confirmation.

### Tests for User Story 2

- [x] T049 [P] [US2] Create `tests/Feature/Payments/PaymentExpiryTest.php`: `payments:expire-pending` cancels expired `pending`, lazy expiry in options/start/status, `failure_reason=expired`, `paid_until` unchanged, new attempt cancels the previous one.
- [x] T050 [P] [US2] Create `tests/js/usePaymentPolling.spec.js`: 4-second polling, stop on final statuses, pause when `document.hidden`, resume on `visibilitychange`, backoff on network errors.

### Implementation for User Story 2

- [x] T051 [US2] Create `app/Services/Payments/PaymentExpiryService.php` with `expirePending()`, `expireIfDue(SubscriptionPayment $payment)`, and reason codes (`expired`, `new_attempt`, `site_sold`).
- [x] T052 [US2] Create `app/Console/Commands/ExpirePendingPayments.php` with signature `payments:expire-pending`.
- [x] T053 [US2] Register `Schedule::command('payments:expire-pending')->everyMinute()->withoutOverlapping()` in `routes/console.php`.
- [x] T054 [US2] Apply lazy expiry in `app/Http/Controllers/Api/SitePaymentController.php` (`options`, `start`) and `app/Http/Controllers/Api/PaymentStatusController.php` (status) before returning data.
- [x] T055 [US2] Create `resources/js/composables/usePaymentPolling.js` with interval, hidden-tab pause, visibility resume, backoff, and max duration.
- [x] T056 [US2] Extend `resources/js/components/cabinet/PaymentDialog.vue` with polling, countdown, and `expired`/`failed`/`cancelled`/`requires_review`/retry/continue states.
- [x] T057 [US2] Map provider/network failures to the documented Russian messages in `resources/js/services/paymentService.js` and `PaymentDialog.vue` (FR-045), never exposing stack traces or secrets.

**Checkpoint**: US2 works independently — pending/expired/failed/retry flows are safe.

---

## Phase 5: User Story 3 - История платежей и подтверждение продления (Priority: P2)

**Goal**: Seller sees the full payment history and the subscription block refreshes after payment; expiry reminders respect the new date.

**Independent Test**: Create payments in «Ожидает оплаты», «Оплачен», «Не прошёл», «Отменён», open the listing card, verify display/sorting, and verify the subscription block updates after confirmation.

### Tests for User Story 3

- [x] T058 [P] [US3] Create `tests/js/PaymentHistory.spec.js`: newest-first order, all statuses, continue/retry actions, legacy records without snapshots, empty state, load error.
- [x] T059 [P] [US3] Update `tests/Feature/Cabinet/SubscriptionDisplayTest.php` for `latestPayment`, history endpoint, and post-payment `paid_until`/status refresh.

### Implementation for User Story 3

- [x] T060 [US3] Add the `index` (history) action to `app/Http/Controllers/Api/SitePaymentController.php`: last 50 payments, newest first, ownership enforced.
- [x] T061 [US3] Implement `resources/js/components/cabinet/PaymentHistory.vue`: invoice number, base price, discount, total, term, method «СБП», status, created/paid dates, reason, continue/retry actions.
- [x] T062 [US3] Wire `PaymentHistory.vue` into the cabinet listing card/detail flow in `resources/js/views/cabinet/CabinetSitesView.vue` and `resources/js/components/cabinet/SiteCard.vue`.
- [x] T063 [US3] Update `app/Notifications/SubscriptionExpiring.php` to link to the cabinet payment action and rely on the refreshed `paid_until` so reminders stop until the new threshold (FR-034).

**Checkpoint**: US3 works independently — history and subscription refresh are visible and accurate.

---

## Phase 6: User Story 4 - Тарифы и контроль платежей для администратора (Priority: P3)

**Goal**: Admin configures price/discounts/terms/target status, sees all payments with filters and sorting, reviews disputed payments, and the public pricing page shows the same values.

**Independent Test**: Change price/discount/target status and verify only new invoices change; filter all payments by several domains and sort by date; find a payment by invoice/domain; accept/reject a disputed payment with a comment and verify the seller sees the result.

### Tests for User Story 4

- [x] T064 [P] [US4] Create `tests/Feature/Orchid/SubscriptionSettingScreenTest.php`: save price/periods/discounts/target status/TTL/active flag, validation (period 1 required, discounts 0–99 for terms ≥ 3, total > 0), cache reset.
- [x] T065 [P] [US4] Create `tests/Feature/Payments/PaymentAdminListTest.php`: multi-domain filter (snapshot or current domain), status/date/method filters, invoice search, default newest-first and reversed sorting, 100+ rows.
- [x] T066 [P] [US4] Create `tests/Feature/Payments/PaymentReviewTest.php`: accept applies payment/extension/target status, reject sets `failed` without extension, mandatory 5–2000-char comment, audit row, seller-visible reason.
- [x] T067 [P] [US4] Create `tests/Feature/Payments/PricingPageTest.php`: public pricing matches cabinet settings and hides/announces inactive tariff.
- [x] T068 [P] [US4] Create `tests/js/PricingView.spec.js`: period cards, discount display, inactive state, CTA, loading/error.

### Implementation for User Story 4

- [x] T069 [US4] Create `app/Orchid/Screens/SubscriptionSetting/SubscriptionSettingScreen.php` with fields and validation for base price, periods, discounts, target status, QR TTL, and active flag.
- [x] T070 [US4] Register the settings screen in `routes/platform.php` and add the permission/menu item `platform.marketplace.subscription-settings` in `app/Orchid/PlatformProvider.php`.
- [x] T071 [P] [US4] Create `app/Orchid/Filters/PaymentDomainsFilter.php` normalizing comma/space/newline-separated domains and matching `site_domain` OR the current `sites.domain`.
- [x] T072 [P] [US4] Create `app/Orchid/Filters/PaymentStatusFilter.php` (multi-select of five statuses).
- [x] T073 [P] [US4] Create `app/Orchid/Filters/PaymentMethodFilter.php` (`sbp` / empty for legacy rows).
- [x] T074 [P] [US4] Create `app/Orchid/Filters/PaymentInvoiceFilter.php` (numeric invoice id search).
- [x] T075 [P] [US4] Create `app/Orchid/Filters/PaymentPaidAtFilter.php` (paid-at start/end range).
- [x] T076 [US4] Update `app/Orchid/Screens/SubscriptionPayment/SubscriptionPaymentListScreen.php` with invoice, snapshot/current domain, payer, amount, term, status, method, test flag, dates, filters, and `created_at`/`paid_at`/`amount` sorting (default newest first).
- [x] T077 [US4] Update `app/Orchid/Layouts/SubscriptionPayment/SubscriptionPaymentListLayout.php` with the new columns and eager loading to avoid N+1.
- [x] T078 [US4] Update `app/Orchid/Layouts/SubscriptionPayment/PaymentFiltersLayout.php` to include the domain/status/method/invoice/paid-at filters.
- [x] T079 [US4] Update `app/Orchid/Screens/SubscriptionPayment/SubscriptionPaymentShowScreen.php` with the snapshot, current state, review reason, review history, and the review form for `requires_review`.
- [x] T080 [US4] Update `app/Orchid/Layouts/SubscriptionPayment/SubscriptionPaymentShowLayout.php` with snapshot/provider fields and the review history block.
- [x] T081 [US4] Create `app/Orchid/Layouts/SubscriptionPayment/PaymentReviewLayout.php` with decision select and mandatory comment textarea.
- [x] T082 [US4] Create `app/Services/Payments/PaymentReviewService.php` with `accept()` and `reject()`: transactional status change, extension/target status on accept, `failed` without extension on reject, audit row, comment validation.
- [x] T083 [US4] Create `app/Notifications/PaymentRequiresReview.php` (type, mail, admin link).
- [x] T084 [US4] Dispatch `PaymentRequiresReview` to users with `platform.marketplace.payments` from `app/Services/Payments/PaymentFinalizer.php` when a payment enters `requires_review`; log a warning when there are no recipients.
- [x] T085 [US4] Create `app/Http/Controllers/Api/PricingController.php` returning `PricingResource` from `SubscriptionSetting::current()` and `SubscriptionPricing`.
- [x] T086 [US4] Register `GET /api/v1/pricing` in `routes/web.php` (public, outside the auth group).
- [x] T087 [US4] Replace the placeholder in `resources/js/views/PricingView.vue` with real pricing cards, discount/total display, inactive state, and cabinet CTA.

**Checkpoint**: US4 works independently — admin settings, payment filters, review, and public pricing are functional.

---

## Phase 7: Polish & Cross-Cutting Concerns

**Purpose**: Verification, formatting, security, and end-to-end validation.

- [x] T088 [P] Update `tests/Feature/Console/ScheduleTest.php` to assert `payments:expire-pending` is scheduled every minute.
- [x] T089 [P] Run `vendor/bin/pint --dirty --format agent` and fix formatting in all changed `app/`, `database/`, `routes/`, and `tests/` PHP files.
- [x] T090 [P] Run `php artisan test --compact tests/Feature/Payments tests/Feature/Orchid tests/Feature/Models` and fix failures.
- [x] T091 [P] Run `npm test -- tests/js` and fix failures in `tests/js/*.spec.js`.
- [x] T092 [P] Run `npm run build` and confirm `resources/js` compiles.
- [x] T093 Security audit across `config/services.php`, `app/Services/Payments/RobokassaGateway.php`, `app/Services/Payments/PaymentFinalizer.php`, and `app/Http/Controllers/Api/RobokassaResultController.php`: confirm `Пароль#1`/`Пароль#2`, signatures, and full receipts never appear in API responses, logs, or error messages; mask receipt email in logs.
- [x] T094 Execute the manual scenarios in `specs/006-robokassa-sbp-payment/quickstart.md` (success, callback emulation, mismatch, late payment, sold, domain conflict, double payment, expiry, test mode, admin filters, pricing page) and record results.

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies.
- **Foundational (Phase 2)**: Depends on Setup; blocks all user stories.
- **User Stories (Phase 3+)**: Depend on Foundational.
  - US1 is the MVP and should ship first.
  - US2 and US3 build on US1's payment service/controller and can run in parallel once US1 is stable.
  - US4 can start after Foundational for settings, but its payment review depends on US1's finalizer.
- **Polish (Phase 7)**: Depends on all desired stories.

### User Story Dependencies

- **US1 (P1)**: No dependency on other stories.
- **US2 (P2)**: Uses US1's `PaymentInvoiceService` and `SitePaymentController`; adds expiry/polling.
- **US3 (P2)**: Uses US1's payment resource and cabinet store; adds history/refresh.
- **US4 (P3)**: Uses Foundational settings/pricing and US1's finalizer; adds admin and public pricing.

### Within Each Story

- Tests may be written first and should fail before implementation.
- Models/services before endpoints; endpoints before UI; UI before integration.

### Parallel Opportunities

- Setup: T002 and T004 in parallel.
- Foundational: T008–T017 and T022–T023 in parallel; migrations T005–T007 are independent files.
- US1: tests T025–T028 in parallel; resources T032–T035 in parallel.
- US2: T049–T050 in parallel.
- US3: T058–T059 in parallel.
- US4: tests T064–T068 in parallel; filters T071–T075 in parallel.

---

## Parallel Example: User Story 1

```bash
# Tests (different files):
Task: "PaymentInvoiceTest.php"
Task: "PaymentCallbackTest.php"
Task: "PaymentDialog.spec.js"
Task: "paymentService.spec.js"

# API resources (different files):
Task: "PricingResource.php"
Task: "PaymentOptionsResource.php"
Task: "RobokassaStartResource.php"
Task: "SubscriptionPaymentSummaryResource.php"
```

## Parallel Example: User Story 4

```bash
# Admin filters (different files):
Task: "PaymentDomainsFilter.php"
Task: "PaymentStatusFilter.php"
Task: "PaymentMethodFilter.php"
Task: "PaymentInvoiceFilter.php"
Task: "PaymentPaidAtFilter.php"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup.
2. Complete Phase 2: Foundational.
3. Complete Phase 3: US1.
4. **STOP and VALIDATE**: test invoice, QR, callback, extension, and target status independently.
5. Deploy/demo if ready.

### Incremental Delivery

1. Setup + Foundational → foundation ready.
2. US1 → test → deploy (MVP).
3. US2 → test → deploy (safe retries).
4. US3 → test → deploy (history/refresh).
5. US4 → test → deploy (admin/pricing).
6. Polish → final validation.

### Parallel Team Strategy

- Team completes Setup + Foundational together.
- After Foundational:
  - Developer A: US1 (core payment).
  - Developer B: US4 settings/public pricing (independent of payment UI).
  - After US1: Developer C: US2; Developer D: US3.

---

## Notes

- `[P]` tasks touch different files and have no incomplete dependencies.
- `[US*]` labels map tasks to spec user stories.
- Every story ends at an independently testable checkpoint.
- Keep Robokassa secrets server-side; never commit real credentials.
- Run the narrowest tests while developing, then the full suite in Polish.
