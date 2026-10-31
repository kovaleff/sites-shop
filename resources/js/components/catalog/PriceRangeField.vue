<script setup>
import { computed } from 'vue';

import { Slider } from '@/components/ui/slider';
import { formatRub } from '@/lib/format';

const props = defineProps({
    modelValue: { type: Array, required: true },
    min: { type: Number, required: true },
    max: { type: Number, required: true },
    step: { type: Number, required: true },
    label: { type: String, default: 'Цена, ₽' },
});

const emit = defineEmits(['update:modelValue']);

const value = computed(() => [props.modelValue[0] ?? props.min, props.modelValue[1] ?? props.max]);
const isUpperBound = computed(() => value.value[1] === props.max);

const thumbLabels = computed(() => ['Минимальная цена', 'Максимальная цена']);
const thumbValueTexts = computed(() => [formatRub(value.value[0]), formatRub(value.value[1])]);

/**
 * @param {number[]} next
 * @returns {void}
 */
function updateValue(next) {
    if (!Array.isArray(next) || next.length < 2) {
        return;
    }

    const nextMin = Math.min(Math.max(Number(next[0]), props.min), Number(next[1]));
    const nextMax = Math.max(Math.min(Number(next[1]), props.max), nextMin);

    emit('update:modelValue', [nextMin, nextMax]);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {{ label }}
        </span>

        <div class="flex h-8 items-center">
            <Slider
                :model-value="value"
                :min="min"
                :max="max"
                :step="step"
                :min-steps-between-thumbs="0"
                :thumb-labels="thumbLabels"
                :thumb-value-texts="thumbValueTexts"
                @update:model-value="updateValue"
            />
        </div>

        <div class="flex items-center justify-between gap-2 text-xs tabular-nums text-muted-foreground">
            <span class="text-primary">{{ formatRub(value[0]) }}</span>
            <span class="text-right">
                {{ formatRub(value[1]) }}<template v-if="isUpperBound"> и выше</template>
            </span>
        </div>
    </div>
</template>
