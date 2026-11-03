<script setup>
import { computed } from 'vue';
import { Star } from '@lucide/vue';

const props = defineProps({
    rating: { type: Number, default: 0 },
    count: { type: Number, default: 0 },
    size: { type: String, default: 'sm' },
});

const normalized = computed(() => Math.min(5, Math.max(0, Number(props.rating) || 0)));
const filled = computed(() => Math.round(normalized.value));
const starClass = computed(() => (props.size === 'md' ? 'size-4' : 'size-3.5'));
const formatted = computed(() => normalized.value.toFixed(1).replace('.', ','));
const label = computed(() => `Рейтинг ${formatted.value} из 5`);
</script>

<template>
    <span class="inline-flex items-center gap-1.5">
        <span class="flex items-center gap-0.5" role="img" :aria-label="label">
            <Star
                v-for="index in 5"
                :key="index"
                :class="[
                    starClass,
                    index <= filled ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/35',
                ]"
                aria-hidden="true"
            />
        </span>

        <span v-if="normalized > 0" class="text-xs font-semibold tabular-nums text-foreground">
            {{ formatted }}
        </span>

        <span v-if="count > 0" class="text-xs text-muted-foreground">({{ count }})</span>
    </span>
</template>
