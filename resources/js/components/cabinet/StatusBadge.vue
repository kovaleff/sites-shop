<script setup>
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const variantClass = computed(() => {
    switch (props.status) {
        case 'active':
            return 'border-verified/40 bg-verified/10 text-verified';
        case 'inactive':
            return 'border-border bg-secondary text-muted-foreground';
        case 'withdrawn':
            return 'border-destructive/40 bg-destructive/10 text-destructive';
        case 'sold':
            return 'border-primary/40 bg-primary/10 text-primary';
        default:
            return 'border-border bg-secondary text-muted-foreground';
    }
});
</script>

<template>
    <Badge variant="outline" :class="variantClass">{{ label }}</Badge>
</template>