<script setup>
import { computed } from 'vue';

import { formatDate } from '@/lib/format';

const props = defineProps({
    paidUntil: { type: String, default: null },
    needsRenewal: { type: Boolean, default: false },
    isPubliclyVisible: { type: Boolean, default: false },
});

const paidLabel = computed(() =>
    props.paidUntil ? `Оплачено до ${formatDate(`${props.paidUntil}T00:00:00Z`)}` : 'Подписка не оплачена',
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-2 text-xs">
        <span class="text-muted-foreground">{{ paidLabel }}</span>

        <span
            v-if="needsRenewal"
            class="rounded-full border border-destructive/40 bg-destructive/10 px-2 py-0.5 font-semibold text-destructive"
        >
            Требует продления
        </span>

        <span
            v-if="!isPubliclyVisible"
            class="rounded-full border border-border bg-secondary px-2 py-0.5 text-muted-foreground"
        >
            Скрыто из каталога
        </span>
    </div>
</template>