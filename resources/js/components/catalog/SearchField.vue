<script setup>
import { useId } from 'vue';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SEARCH_MAX_LENGTH, SEARCH_MIN_LENGTH } from '@/lib/catalogConfig';

defineProps({
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const searchId = useId();
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <Label
                :for="searchId"
                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
            >
                Поиск
            </Label>
            <span class="text-xs text-muted-foreground">
                Название или домен, минимум {{ SEARCH_MIN_LENGTH }} символа
            </span>
        </div>

        <Input
            :id="searchId"
            type="search"
            :model-value="modelValue"
            :maxlength="SEARCH_MAX_LENGTH"
            placeholder="Например, доставка или ferma-dostavka.ru"
            autocomplete="off"
            spellcheck="false"
            aria-label="Поиск по названию или домену"
            @update:model-value="emit('update:modelValue', String($event))"
        />
    </div>
</template>
