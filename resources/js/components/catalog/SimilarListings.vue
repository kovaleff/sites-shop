<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { Globe, TrendingUp } from '@lucide/vue';

import FavoriteButton from '@/components/catalog/FavoriteButton.vue';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRub, formatRubMonthly } from '@/lib/format';

const props = defineProps({
    items: { type: Array, default: () => [] },
    loading: { type: Boolean, default: false },
    favoriteIds: { type: Array, default: () => [] },
    favoriteBusyId: { type: Number, default: null },
});

defineEmits(['favorite']);

const isVisible = computed(() => props.loading || props.items.length > 0);

/**
 * @param {object} listing
 * @returns {boolean}
 */
function isFavorite(listing) {
    return props.favoriteIds.map(Number).includes(Number(listing.id));
}

/**
 * @param {object} listing
 * @returns {string}
 */
function zoneLabel(listing) {
    return listing.domainZone ? `.${listing.domainZone}` : '';
}

/**
 * @param {object} listing
 * @returns {object}
 */
function listingLink(listing) {
    return { name: 'listing', params: { slug: listing.slug ?? listing.id } };
}
</script>

<template>
    <section v-if="isVisible" class="mt-8" aria-labelledby="similar-listings-title">
        <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <h2 id="similar-listings-title" class="text-lg font-extrabold tracking-tight">
                Похожие лоты
            </h2>
            <p class="text-xs text-muted-foreground">Та же тематика и близкая цена</p>
        </div>

        <div
            v-if="loading"
            class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
            aria-hidden="true"
        >
            <Skeleton v-for="index in 3" :key="index" class="h-36 rounded-xl" />
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <Card
                v-for="listing in items"
                :key="listing.id"
                class="flex flex-col gap-2 border-border bg-card p-4 transition-colors hover:border-primary/40"
            >
                <div class="flex items-start justify-between gap-2">
                    <h3 class="line-clamp-2 text-sm leading-snug font-bold text-foreground">
                        <RouterLink
                            :to="listingLink(listing)"
                            class="hover:text-primary hover:underline"
                        >
                            {{ listing.title }}
                        </RouterLink>
                    </h3>

                    <FavoriteButton
                        :is-favorite="isFavorite(listing)"
                        :busy="favoriteBusyId === Number(listing.id)"
                        class="-mt-1 shrink-0"
                        @toggle="$emit('favorite', listing)"
                    />
                </div>

                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                    <Globe class="size-3.5 shrink-0" aria-hidden="true" />
                    <span class="truncate" :title="listing.domain">{{ listing.domain }}</span>
                    <template v-if="zoneLabel(listing)">
                        <span aria-hidden="true">·</span>
                        <span class="shrink-0">{{ zoneLabel(listing) }}</span>
                    </template>
                </div>

                <div class="mt-auto flex flex-wrap items-end justify-between gap-2 pt-1">
                    <span class="text-sm font-bold tabular-nums text-primary">
                        {{ formatRub(listing.price) }}
                    </span>
                    <span class="flex items-center gap-1 text-xs font-semibold tabular-nums text-verified">
                        <TrendingUp class="size-3.5 shrink-0" aria-hidden="true" />
                        {{ formatRubMonthly(listing.monthlyIncome) }}
                    </span>
                </div>
            </Card>
        </div>
    </section>
</template>
