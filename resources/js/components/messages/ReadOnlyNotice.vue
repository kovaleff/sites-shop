<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: null },
});

const reason = computed(() => {
    switch (props.status) {
        case 'inactive':
            return 'Объявление неактивно — переписка доступна только для чтения.';
        case 'withdrawn':
            return 'Объявление снято с продажи — переписка доступна только для чтения.';
        case 'sold':
            return 'Объявление продано — переписка доступна только для чтения.';
        default:
            return 'Подписка объявления истекла — переписка доступна только для чтения.';
    }
});
</script>

<template>
    <p class="rounded-lg border border-border bg-secondary px-3 py-2 text-xs text-muted-foreground">
        {{ reason }}
    </p>
</template>