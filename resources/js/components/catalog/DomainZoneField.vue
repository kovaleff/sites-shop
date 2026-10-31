<script setup>
import { computed } from 'vue';

import MultiSelectField from '@/components/catalog/MultiSelectField.vue';

const props = defineProps({
    modelValue: { type: Array, required: true },
    zones: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const options = computed(() =>
    props.zones.map((zone) => ({ value: zone.value, label: zone.label })),
);
</script>

<template>
    <MultiSelectField
        :model-value="modelValue"
        :options="options"
        :loading="loading"
        label="Домен / зона"
        all-label="Все зоны"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
