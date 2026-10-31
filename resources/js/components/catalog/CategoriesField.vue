<script setup>
import { computed } from 'vue';

import MultiSelectField from '@/components/catalog/MultiSelectField.vue';

const props = defineProps({
    modelValue: { type: Array, required: true },
    categories: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const options = computed(() =>
    props.categories.map((category) => ({ value: category.slug, label: category.name })),
);
</script>

<template>
    <MultiSelectField
        :model-value="modelValue"
        :options="options"
        :loading="loading"
        label="Тематики"
        all-label="Все тематики"
        @update:model-value="emit('update:modelValue', $event)"
    />
</template>
