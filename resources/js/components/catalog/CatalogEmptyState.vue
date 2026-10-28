<script setup>
import { Button } from '@/components/ui/button';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    showReset: { type: Boolean, default: false },
    resetLabel: { type: String, default: 'Сбросить все' },
});

defineEmits(['reset']);
</script>

<template>
    <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card/60 px-6 py-14 text-center">
        <h2 class="text-lg font-bold text-foreground">{{ title }}</h2>
        <p v-if="description" class="mt-2 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <Button v-if="showReset" class="mt-5" variant="outline" @click="$emit('reset')">
            {{ resetLabel }}
        </Button>
    </div>
</template>
