<script setup>
import { formatDate, formatRub } from '@/lib/format';

defineProps({
    payments: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <h4 class="text-xs font-semibold tracking-wider text-muted-foreground uppercase">
            История платежей
        </h4>

        <p v-if="payments.length === 0" class="text-xs text-muted-foreground">
            Платежей ещё не было.
        </p>

        <ul v-else class="flex flex-col gap-1.5">
            <li
                v-for="payment in payments"
                :key="payment.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-border px-3 py-2 text-xs"
            >
                <span class="font-semibold text-foreground">{{ formatRub(payment.amount) }}</span>
                <span class="text-muted-foreground">{{ payment.periodMonths }} мес.</span>
                <span class="text-muted-foreground">{{ payment.statusLabel }}</span>
                <span class="text-muted-foreground">
                    {{ payment.paidAt ? formatDate(payment.paidAt) : formatDate(payment.createdAt) }}
                </span>
            </li>
        </ul>

        <p class="text-xs text-muted-foreground">Оплата подписки появится в следующей версии.</p>
    </div>
</template>