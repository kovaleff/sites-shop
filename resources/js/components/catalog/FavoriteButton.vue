<script setup>
import { Heart } from '@lucide/vue';

import { Button } from '@/components/ui/button';

defineProps({
    isFavorite: { type: Boolean, default: false },
    busy: { type: Boolean, default: false },
});

defineEmits(['toggle']);
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        :class="isFavorite ? 'text-destructive hover:text-destructive' : 'text-muted-foreground'"
        :aria-pressed="isFavorite ? 'true' : 'false'"
        :disabled="busy"
        :title="isFavorite ? 'Убрать из избранного' : 'В избранное'"
        @click="$emit('toggle')"
    >
        <Heart
            class="size-4"
            :class="isFavorite ? 'fill-current' : ''"
            aria-hidden="true"
        />
        <span class="sr-only">
            {{ isFavorite ? 'Убрать из избранного' : 'Добавить в избранное' }}
        </span>
    </Button>
</template>
