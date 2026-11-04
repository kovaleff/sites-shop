<script setup>
import { computed } from 'vue';

import { formatDate, formatRub } from '@/lib/format';

const props = defineProps({
    payments: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    error: { type: String, default: '' },
});

const emit = defineEmits(['continue', 'retry']);

const STATUS_STYLES = {
    pending: 'border-amber-400/40 bg-amber-400/10 text-amber-600',
    paid: 'border-emerald-400/40 bg-emerald-400/10 text-emerald-600',
    failed: 'border-destructive/40 bg-destructive/10 text-destructive',
    cancelled: 'border-border bg-secondary text-muted-foreground',
    requires_review: 'border-amber-500/40 bg-amber-500/10 text-amber-700',
};

const sorted = computed(() =>
    [...props.payments].sort((a, b) => new Date(b.createdAt ?? 0) - new Date(a.createdAt ?? 0)),
);

/**
 * @param {object} payment
 * @returns {string}
 */
function statusClass(payment) {
    return STATUS_STYLES[payment.status] ?? 'border-border bg-secondary text-muted-foreground';
}

/**
 * @param {object} payment
 * @returns {string}
 */
function termLabel(payment) {
    const months = Number(payment.periodMonths ?? 0);

    if (months <= 0) {
        return '';
    }

    const forms = ['месяц', 'месяца', 'месяцев'];
    const absolute = Math.abs(months) % 100;
    const last = absolute % 10;

    let form = forms[2];

    if (absolute > 10 && absolute < 20) {
        form = forms[2];
    } else if (last === 1) {
        form = forms[0];
    } else if (last >= 2 && last <= 4) {
        form = forms[1];
    }

    return `${months} ${form}`;
}

/**
 * @param {object} payment
 * @returns {string}
 */
function reasonLabel(payment) {
    const labels = {
        expired: 'Срок действия QR-кода истёк.',
        new_attempt: 'Создана новая попытка оплаты.',
        site_sold: 'Объявление продано, оплата не применена.',
        provider_error: 'Провайдер не подтвердил оплату.',
        amount_mismatch: 'Сумма не совпадает со счётом — платёж на проверке.',
        currency_mismatch: 'Валюта не совпадает со счётом — платёж на проверке.',
        method_mismatch: 'Способ оплаты не СБП — платёж на проверке.',
        domain_conflict: 'Домен занят другим объявлением — платёж на проверке.',
        site_not_payable: 'Объявление больше не принимает оплату — платёж на проверке.',
        rejected_by_admin: 'Платёж отклонён администратором.',
    };

    return labels[payment.failureReason] ?? labels[payment.reviewReason] ?? '';
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <h4 class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
            История платежей
        </h4>

        <p v-if="loading" class="text-xs text-muted-foreground">Загружаем историю…</p>

        <p v-else-if="error" class="text-xs text-destructive">{{ error }}</p>

        <p v-else-if="sorted.length === 0" class="text-xs text-muted-foreground">
            Платежей ещё не было.
        </p>

        <ul v-else class="flex flex-col gap-1.5">
            <li
                v-for="payment in sorted"
                :key="payment.id"
                class="flex flex-col gap-1 rounded-lg border border-border bg-card px-3 py-2 text-xs"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-semibold text-foreground">Счёт №{{ payment.invoiceNumber ?? payment.id }}</span>
                    <span class="rounded-full border px-2 py-0.5 font-semibold" :class="statusClass(payment)">
                        {{ payment.statusLabel }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-muted-foreground">
                    <span class="font-semibold text-foreground">{{ formatRub(payment.amount) }}</span>
                    <span v-if="termLabel(payment)">{{ termLabel(payment) }}</span>
                    <span>СБП</span>
                    <span v-if="payment.isTest">Тест</span>
                    <span>Создан: {{ formatDate(payment.createdAt) }}</span>
                    <span v-if="payment.paidAt">Оплачен: {{ formatDate(payment.paidAt) }}</span>
                </div>

                <div
                    v-if="payment.discountPercent > 0"
                    class="text-muted-foreground"
                >
                    Базовая цена {{ formatRub(payment.basePrice) }} · скидка {{ payment.discountPercent }}%
                </div>

                <p v-if="reasonLabel(payment)" class="text-muted-foreground">
                    {{ reasonLabel(payment) }}
                </p>

                <div v-if="payment.status === 'pending' || payment.status === 'failed' || payment.status === 'cancelled'" class="flex gap-2">
                    <button
                        v-if="payment.status === 'pending'"
                        type="button"
                        class="text-primary underline"
                        @click="emit('continue', payment)"
                    >
                        Продолжить оплату
                    </button>
                    <button
                        v-else
                        type="button"
                        class="text-primary underline"
                        @click="emit('retry', payment)"
                    >
                        Повторить
                    </button>
                </div>
            </li>
        </ul>
    </div>
</template>
