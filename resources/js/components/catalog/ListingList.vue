<script setup>
import ListingCard from '@/components/catalog/ListingCard.vue';
import { formatCount } from '@/lib/format';

defineProps({
    items: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    loading: { type: Boolean, default: false },
});

defineEmits(['contact']);
</script>

<template>
    <div id="catalog-list" class="scroll-mt-24" :aria-busy="loading ? 'true' : 'false'">
        <div class="mb-3 flex items-center justify-between gap-3">
            <p class="text-sm text-muted-foreground">
                Показано
                <strong class="font-semibold text-foreground">
                    {{ items.length }} из {{ formatCount(total) }}
                </strong>
                лотов
            </p>
        </div>

        <div class="grid grid-cols-1 gap-3">
            <ListingCard
                v-for="listing in items"
                :key="listing.id"
                :listing="listing"
                @contact="$emit('contact', $event)"
            />
        </div>
    </div>
</template>
