<script setup>
import { computed } from 'vue';

import { Badge } from '@/components/ui/badge';

const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, required: true },
});

const variantClass = computed(() => {
    switch (props.status) {
        case 'pending':
            return 'border-warning/40 bg-warning/10 text-warning';
        case 'rejected':
            return 'border-destructive/40 bg-destructive/10 text-destructive';
        default:
            return 'border-border bg-secondary text-muted-foreground';
    }
});
</script>

<template>
    <Badge variant="outline" :class="variantClass">{{ label }}</Badge>
</template>
