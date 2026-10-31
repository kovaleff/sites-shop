<script setup>
import { computed } from 'vue';
import { ChevronDown } from '@lucide/vue';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';

const props = defineProps({
    modelValue: { type: Array, required: true },
    options: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    label: { type: String, required: true },
    allLabel: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const selectedOptions = computed(() =>
    props.options.filter((option) => props.modelValue.includes(option.value)),
);

const triggerLabel = computed(() => {
    if (props.modelValue.length === 0) {
        return props.allLabel;
    }

    if (selectedOptions.value.length === 0) {
        return `Выбрано: ${props.modelValue.length}`;
    }

    return selectedOptions.value.map((option) => option.label).join(', ');
});

/**
 * @param {string} value
 * @returns {boolean}
 */
function isSelected(value) {
    return props.modelValue.includes(value);
}

/**
 * @param {string} value
 * @returns {void}
 */
function toggle(value) {
    emit(
        'update:modelValue',
        isSelected(value)
            ? props.modelValue.filter((item) => item !== value)
            : [...props.modelValue, value],
    );
}

/**
 * @returns {void}
 */
function clear() {
    emit('update:modelValue', []);
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <span class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
            {{ label }}
        </span>

        <Skeleton v-if="loading" class="h-8 w-full rounded-md" />

        <DropdownMenu v-else>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-8 w-full justify-between gap-2 border-input bg-secondary/60 font-normal"
                    :aria-label="`${label}: ${triggerLabel}`"
                    :title="triggerLabel"
                >
                    <span class="truncate">{{ triggerLabel }}</span>
                    <ChevronDown class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="start" class="max-h-72 w-64">
                <DropdownMenuLabel>{{ label }}</DropdownMenuLabel>

                <DropdownMenuItem :disabled="modelValue.length === 0" @select.prevent="clear">
                    {{ allLabel }}
                </DropdownMenuItem>

                <DropdownMenuSeparator v-if="options.length > 0" />

                <DropdownMenuCheckboxItem
                    v-for="option in options"
                    :key="option.value"
                    :model-value="isSelected(option.value)"
                    @select.prevent="toggle(option.value)"
                >
                    {{ option.label }}
                </DropdownMenuCheckboxItem>

                <DropdownMenuItem v-if="options.length === 0" disabled>
                    Нет доступных вариантов
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

        <div class="h-4" aria-hidden="true" />
    </div>
</template>
