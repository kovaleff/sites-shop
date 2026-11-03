<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import { Clock, PenLine, Star } from '@lucide/vue';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import RatingStars from '@/components/reviews/RatingStars.vue';
import ReviewFormDialog from '@/components/reviews/ReviewFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/format';
import { fetchMyReviews, fetchPendingReviews } from '@/services/reviewService';

const status = ref('loading');
const error = ref('');
const pending = ref([]);
const reviews = ref([]);
const selectedListing = ref(null);
const isReviewOpen = ref(false);

const isLoading = computed(() => status.value === 'loading');
const hasError = computed(() => status.value === 'error');
const isEmpty = computed(
    () => status.value === 'ready' && pending.value.length === 0 && reviews.value.length === 0,
);

/**
 * @param {object} review
 * @returns {string}
 */
function statusClass(review) {
    if (review.status === 'approved') {
        return 'border-verified/40 bg-verified/10 text-verified';
    }

    if (review.status === 'rejected') {
        return 'border-destructive/40 bg-destructive/10 text-destructive';
    }

    return 'border-amber-400/40 bg-amber-400/10 text-amber-500';
}

/**
 * @returns {Promise<void>}
 */
async function load() {
    status.value = 'loading';
    error.value = '';

    try {
        const [pendingItems, myReviews] = await Promise.all([
            fetchPendingReviews(),
            fetchMyReviews(),
        ]);

        pending.value = pendingItems;
        reviews.value = myReviews;
        status.value = 'ready';
    } catch (requestError) {
        status.value = 'error';
        error.value = requestError?.message ?? 'Не удалось загрузить отзывы.';
    }
}

/**
 * @param {object} listing
 * @returns {void}
 */
function openReview(listing) {
    selectedListing.value = listing;
    isReviewOpen.value = true;
}

onMounted(load);
</script>

<template>
    <CabinetLayout>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-foreground">Отзывы</h1>
                <p class="text-sm text-muted-foreground">
                    Оцените прошедшую сделку и посмотрите свои отзывы о продавцах.
                </p>
            </div>
        </div>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-6">
            <template v-if="isLoading">
                <Skeleton v-for="index in 2" :key="index" class="h-28 w-full rounded-xl" />
            </template>

            <div
                v-else-if="hasError"
                class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
            >
                <p class="text-sm text-destructive">{{ error }}</p>
                <Button variant="outline" size="sm" @click="load">Повторить</Button>
            </div>

            <div
                v-else-if="isEmpty"
                class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-border p-8"
            >
                <p class="text-sm text-muted-foreground">
                    Здесь появятся отзывы после покупки лота: оценить продавца можно,
                    когда объявление получит статус «Продан».
                </p>
                <RouterLink :to="{ name: 'catalog' }">
                    <Button type="button">Перейти в каталог</Button>
                </RouterLink>
            </div>

            <template v-else>
                <section v-if="pending.length > 0" class="flex flex-col gap-3">
                    <h2 class="text-lg font-bold text-foreground">Можно оценить</h2>

                    <Card
                        v-for="listing in pending"
                        :key="listing.id"
                        class="flex flex-wrap items-center justify-between gap-3 border-border bg-card p-4"
                    >
                        <div class="min-w-0">
                            <RouterLink
                                :to="{ name: 'listing', params: { slug: listing.slug ?? listing.id } }"
                                class="text-sm font-bold text-foreground hover:text-primary hover:underline"
                            >
                                {{ listing.title }}
                            </RouterLink>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ listing.domain }}
                                <template v-if="listing.seller">
                                    · {{ listing.seller.displayName }}
                                </template>
                            </p>
                        </div>

                        <Button size="sm" @click="openReview(listing)">
                            <PenLine class="size-4" aria-hidden="true" />
                            Оставить отзыв
                        </Button>
                    </Card>
                </section>

                <section class="flex flex-col gap-3">
                    <h2 class="text-lg font-bold text-foreground">Мои отзывы</h2>

                    <p
                        v-if="reviews.length === 0"
                        class="rounded-xl border border-border p-6 text-sm text-muted-foreground"
                    >
                        Вы ещё не оставляли отзывов.
                    </p>

                    <Card
                        v-for="review in reviews"
                        :key="review.id"
                        class="flex flex-col gap-2 border-border bg-card p-4"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <RatingStars :rating="review.rating" size="md" />

                            <Badge variant="outline" :class="statusClass(review)">
                                <Clock v-if="review.status === 'pending'" class="size-3" aria-hidden="true" />
                                <Star v-else class="size-3" aria-hidden="true" />
                                {{ review.statusLabel }}
                            </Badge>
                        </div>

                        <p
                            v-if="review.comment"
                            class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                        >
                            {{ review.comment }}
                        </p>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground">
                            <span v-if="review.site" class="inline-flex items-center gap-1">
                                {{ review.site.title }}
                            </span>

                            <span v-if="review.createdAt">{{ formatDate(review.createdAt) }}</span>
                        </div>
                    </Card>
                </section>
            </template>
        </div>

        <ReviewFormDialog
            v-model:open="isReviewOpen"
            :listing="selectedListing"
            @submitted="load"
        />
    </CabinetLayout>
</template>
