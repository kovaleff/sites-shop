<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePaymentPolling } from '@/composables/usePaymentPolling';
import { formatRub } from '@/lib/format';
import {
    createPayment,
    fetchPaymentOptions,
    paymentErrorMessage,
    startPayment,
    startRobokassaPayment,
} from '@/services/paymentService';

const props = defineProps({
    open: { type: Boolean, default: false },
    site: { type: Object, default: null },
});

const emit = defineEmits(['update:open', 'paid']);

const QR_CONTAINER_ID = 'robokassa-qr-container';

const phase = ref('loading-options');
const options = ref(null);
const months = ref(1);
const receiptEmail = ref('');
const error = ref('');
const blockedMessage = ref('');
const payment = ref(null);
const paymentLink = ref('');
const secondsLeft = ref(0);
const submitting = ref(false);

let countdownTimer = null;

const polling = usePaymentPolling({
    onFinal: (finalPayment) => {
        payment.value = finalPayment;

        if (finalPayment.status === 'paid') {
            phase.value = 'success';
            emit('paid', finalPayment);

            return;
        }

        phase.value = finalPayment.status === 'requires_review' ? 'review' : finalPayment.status;
    },
});

const periods = computed(() => options.value?.periods ?? []);
const selected = computed(
    () => periods.value.find((period) => period.months === months.value) ?? periods.value[0] ?? null,
);
const total = computed(() => selected.value?.total ?? 0);
const basePrice = computed(() => options.value?.basePrice ?? 0);
const discountPercent = computed(() => selected.value?.discountPercent ?? 0);
const discountAmount = computed(() =>
    Math.max(0, Math.round(basePrice.value * months.value - total.value)),
);
const willBeHidden = computed(() => Boolean(options.value?.willBeHidden));
const targetStatusLabel = computed(() => options.value?.targetStatusLabel ?? '');
const activePayment = computed(() => options.value?.activePayment ?? null);
const canContinue = computed(() => activePayment.value?.status === 'pending');

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            await loadOptions();
        } else {
            reset();
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearCountdown();
});

/**
 * @returns {Promise<void>}
 */
async function loadOptions() {
    reset();

    if (!props.site) {
        return;
    }

    phase.value = 'loading-options';

    try {
        const data = await fetchPaymentOptions(props.site.id);
        options.value = data;
        months.value = data.periods?.[0]?.months ?? 1;
        receiptEmail.value = data.defaultReceiptEmail ?? '';
        blockedMessage.value = data.canPay ? '' : (data.blockedMessage ?? '');

        if (data.activePayment?.status === 'requires_review') {
            phase.value = 'review';
            payment.value = data.activePayment;

            return;
        }

        phase.value = 'form';
    } catch (requestError) {
        phase.value = 'error';
        error.value = paymentErrorMessage(requestError);
    }
}

/**
 * @returns {Promise<void>}
 */
async function submit() {
    if (!props.site || submitting.value) {
        return;
    }

    if (!receiptEmail.value.trim()) {
        error.value = 'Укажите email для чека.';

        return;
    }

    error.value = '';
    submitting.value = true;
    phase.value = 'creating';

    try {
        const response = await createPayment(props.site.id, {
            periodMonths: months.value,
            receiptEmail: receiptEmail.value.trim(),
        });

        payment.value = response?.data ?? null;
        await openQr(response?.robokassa ?? null);
    } catch (requestError) {
        error.value = paymentErrorMessage(requestError);
        phase.value = 'form';
    } finally {
        submitting.value = false;
    }
}

/**
 * @returns {Promise<void>}
 */
async function continuePayment() {
    if (!activePayment.value) {
        return;
    }

    error.value = '';

    try {
        const response = await startPayment(activePayment.value.id);
        payment.value = response?.data ?? null;
        await openQr(response?.robokassa ?? null);
    } catch (requestError) {
        error.value = paymentErrorMessage(requestError);
        phase.value = 'form';
        await loadOptions();
    }
}

/**
 * @param {object | null} robokassa
 * @returns {Promise<void>}
 */
async function openQr(robokassa) {
    if (!robokassa) {
        phase.value = 'error';
        error.value = paymentErrorMessage({ code: 'provider_unavailable' });

        return;
    }

    startCountdown(payment.value?.qrExpiresAt ?? null);
    phase.value = 'qr';

    try {
        const link = await startRobokassaPayment({
            scriptUrl: robokassa.scriptUrl,
            merchantLogin: robokassa.merchantLogin,
            outSum: robokassa.outSum,
            invId: robokassa.invId,
            receipt: robokassa.receipt,
            signature: robokassa.signature,
            email: receiptEmail.value.trim(),
            isTest: robokassa.isTest,
            qrContainerId: QR_CONTAINER_ID,
            onPaymentLink: (value) => {
                paymentLink.value = value;
            },
        });

        if (link) {
            paymentLink.value = link;
        }

        if (payment.value?.id) {
            polling.start(payment.value.id);
        }
    } catch (requestError) {
        error.value = paymentErrorMessage({ code: 'provider_unavailable', ...requestError });
    }
}

/**
 * @param {string | null} expiresAt
 * @returns {void}
 */
function startCountdown(expiresAt) {
    clearCountdown();

    if (!expiresAt) {
        secondsLeft.value = 0;

        return;
    }

    const deadline = new Date(expiresAt).getTime();

    const update = () => {
        secondsLeft.value = Math.max(0, Math.round((deadline - Date.now()) / 1000));

        if (secondsLeft.value <= 0) {
            clearCountdown();
            polling.stop();
            phase.value = 'cancelled';
        }
    };

    update();
    countdownTimer = setInterval(update, 1000);
}

function clearCountdown() {
    if (countdownTimer !== null) {
        clearInterval(countdownTimer);
        countdownTimer = null;
    }
}

/**
 * @returns {Promise<void>}
 */
async function checkStatus() {
    const result = await polling.checkNow();

    if (result?.status === 'paid') {
        phase.value = 'success';
        emit('paid', result);
    }
}

/**
 * @returns {void}
 */
function retry() {
    reset();
    phase.value = 'form';
}

function reset() {
    clearCountdown();
    polling.stop();
    phase.value = 'loading-options';
    options.value = null;
    payment.value = null;
    paymentLink.value = '';
    error.value = '';
    blockedMessage.value = '';
    secondsLeft.value = 0;
}

const remainingLabel = computed(() => {
    if (secondsLeft.value <= 0) {
        return '';
    }

    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = String(secondsLeft.value % 60).padStart(2, '0');

    return `${minutes}:${seconds}`;
});

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Продление подписки</DialogTitle>
                <DialogDescription v-if="site">
                    {{ site.domain }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="phase === 'loading-options'" class="flex flex-col gap-2 py-6">
                <p class="text-sm text-muted-foreground">Загружаем условия оплаты…</p>
            </div>

            <div v-else-if="phase === 'form'" class="flex flex-col gap-4">
                <p
                    v-if="blockedMessage"
                    class="rounded-md border border-destructive/40 bg-destructive/10 p-3 text-sm text-destructive"
                >
                    {{ blockedMessage }}
                </p>

                <template v-else>
                    <div class="flex flex-col gap-2">
                        <Label>Срок подписки</Label>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <button
                                v-for="period in periods"
                                :key="period.months"
                                type="button"
                                class="rounded-md border px-3 py-2 text-sm transition"
                                :class="
                                    months === period.months
                                        ? 'border-primary bg-primary/10 font-semibold'
                                        : 'border-border hover:border-primary/50'
                                "
                                @click="months = period.months"
                            >
                                {{ period.months }} мес.
                            </button>
                        </div>
                    </div>

                    <dl class="flex flex-col gap-1 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-muted-foreground">Базовая цена</dt>
                            <dd>{{ formatRub(basePrice) }} / мес</dd>
                        </div>
                        <div v-if="discountPercent > 0" class="flex justify-between">
                            <dt class="text-muted-foreground">Скидка</dt>
                            <dd>−{{ discountPercent }}%</dd>
                        </div>
                        <div v-if="discountPercent > 0" class="flex justify-between">
                            <dt class="text-muted-foreground">Экономия</dt>
                            <dd>−{{ formatRub(discountAmount) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-border pt-1 text-base font-semibold">
                            <dt>Итого</dt>
                            <dd>{{ formatRub(total) }}</dd>
                        </div>
                    </dl>

                    <div class="flex flex-col gap-2">
                        <Label for="payment-receipt-email">Email для чека</Label>
                        <Input
                            id="payment-receipt-email"
                            v-model="receiptEmail"
                            type="email"
                            autocomplete="email"
                            placeholder="name@example.com"
                        />
                        <p class="text-xs text-muted-foreground">
                            Чек придёт на этот адрес. Email аккаунта не изменится.
                        </p>
                    </div>

                    <div class="rounded-md border border-border bg-muted/40 p-3 text-sm">
                        <p>
                            После оплаты объявление станет:
                            <span class="font-semibold">{{ targetStatusLabel }}</span>.
                        </p>
                        <p v-if="willBeHidden" class="mt-1 text-xs text-muted-foreground">
                            Объявление останется скрытым из каталога. Вы сможете включить его в кабинете.
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-2 text-xs text-muted-foreground">
                        <span>Способ оплаты: СБП</span>
                        <span v-if="canContinue">Есть незавершённая попытка оплаты</span>
                    </div>

                    <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                    <p class="text-xs text-muted-foreground">
                        Нажимая «Перейти к оплате», вы принимаете условия
                        <a
                            href="/offer"
                            target="_blank"
                            rel="noopener"
                            class="text-primary underline underline-offset-2"
                        >публичной оферты</a>
                        и
                        <a
                            href="/privacy"
                            target="_blank"
                            rel="noopener"
                            class="text-primary underline underline-offset-2"
                        >политики конфиденциальности</a>.
                    </p>
                </template>
            </div>

            <div v-else-if="phase === 'creating'" class="py-6 text-center text-sm text-muted-foreground">
                Создаём счёт…
            </div>

            <div v-else-if="phase === 'qr'" class="flex flex-col items-center gap-3">
                <p class="text-sm text-muted-foreground">Отсканируйте QR-код в приложении банка</p>

                <div
                    :id="QR_CONTAINER_ID"
                    class="flex min-h-[260px] w-full items-center justify-center rounded-md border border-border p-2"
                >
                    <p v-if="!paymentLink" class="text-sm text-muted-foreground">
                        Получаем QR-код…
                    </p>
                </div>

                <a
                    v-if="paymentLink"
                    :href="paymentLink"
                    target="_blank"
                    rel="noopener"
                    class="break-all text-center text-sm text-primary underline"
                >
                    Открыть в банковском приложении
                </a>

                <p v-if="remainingLabel" class="text-sm text-muted-foreground">
                    QR действует ещё {{ remainingLabel }}
                </p>

                <p v-if="error" class="text-sm text-destructive">{{ error }}</p>

                <p class="text-sm" aria-live="polite">Ожидаем подтверждение оплаты…</p>

                <div class="flex gap-2">
                    <Button variant="outline" type="button" @click="checkStatus">Проверить статус</Button>
                    <Button variant="ghost" type="button" @click="close">Закрыть</Button>
                </div>
            </div>

            <div v-else-if="phase === 'success'" class="flex flex-col gap-3">
                <p class="text-base font-semibold">Оплата получена</p>
                <p class="text-sm text-muted-foreground">
                    Подписка продлена. Объявление перейдёт в статус «{{ targetStatusLabel }}».
                </p>
                <DialogFooter>
                    <Button type="button" @click="close">Готово</Button>
                </DialogFooter>
            </div>

            <div v-else-if="phase === 'review'" class="flex flex-col gap-3">
                <p class="text-base font-semibold">Платёж на проверке</p>
                <p class="text-sm text-muted-foreground">
                    Мы проверяем платёж вручную. Подписка обновится после решения администратора.
                </p>
                <DialogFooter>
                    <Button type="button" @click="close">Понятно</Button>
                </DialogFooter>
            </div>

            <div v-else-if="phase === 'cancelled'" class="flex flex-col gap-3">
                <p class="text-base font-semibold">Срок действия QR истёк</p>
                <p class="text-sm text-muted-foreground">Подписка не изменилась. Создайте новую попытку.</p>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="retry">Создать новую попытку</Button>
                    <Button type="button" variant="ghost" @click="close">Закрыть</Button>
                </DialogFooter>
            </div>

            <div v-else-if="phase === 'failed'" class="flex flex-col gap-3">
                <p class="text-base font-semibold">Оплата не прошла</p>
                <p class="text-sm text-muted-foreground">Подписка не изменилась. Попробуйте ещё раз.</p>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="retry">Повторить</Button>
                    <Button type="button" variant="ghost" @click="close">Закрыть</Button>
                </DialogFooter>
            </div>

            <div v-else-if="phase === 'error'" class="flex flex-col gap-3">
                <p class="text-sm text-destructive">{{ error }}</p>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="loadOptions">Повторить</Button>
                    <Button type="button" variant="ghost" @click="close">Закрыть</Button>
                </DialogFooter>
            </div>

            <DialogFooter v-if="phase === 'form' && !blockedMessage">
                <Button type="button" variant="ghost" @click="close">Отмена</Button>
                <Button v-if="canContinue" type="button" variant="outline" @click="continuePayment">
                    Продолжить оплату
                </Button>
                <Button type="button" :disabled="submitting" @click="submit">Перейти к оплате</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
