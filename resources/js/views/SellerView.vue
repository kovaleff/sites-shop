<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    MapPin,
    PackageCheck,
    Star,
    Store,
} from '@lucide/vue';

import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import ReportListingDialog from '@/components/catalog/ReportListingDialog.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ListingList from '@/components/catalog/ListingList.vue';
import RatingStars from '@/components/reviews/RatingStars.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { consumeInitialState } from '@/lib/initialState';
import { applySellerMeta } from '@/lib/pageMeta';
import { formatCount, formatDate } from '@/lib/format';
import { fetchSeller } from '@/services/sellerService';
import { fetchSellerReviews } from '@/services/reviewService';
import { useFavoritesStore } from '@/stores/favorites';
import { useSessionStore } from '@/stores/session';

const route = useRoute();
const favorites = useFavoritesStore();
const session = useSessionStore();

const seller = ref(null);
const listings = ref([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const reviews = ref([]);
const reviewsMeta = ref({ page: 1, lastPage: 1, total: 0 });
const reviewsStatus = ref('loading');
const status = ref('loading');
const error = ref('');
const contactListing = ref(null);
const isContactOpen = ref(false);
const reportListing = ref(null);
const isReportOpen = ref(false);
const favoriteBusyId = ref(null);

const sellerId = computed(() => Number(route.params.id));
const isLoading = computed(() => status.value === 'loading');
const isError = computed(() => status.value === 'error');
const initial = computed(() => (seller.value?.displayName?.charAt(0) || '?').toUpperCase());
const hasRating = computed(() => Number(seller.value?.reviewsCount ?? 0) > 0);
const stats = computed(() => {
    const items = [
        {
            label: 'Опубликовано',
            value: formatCount(seller.value?.publishedSitesCount ?? 0),
            icon: Store,
            tone: 'text-primary',
        },
        {
            label: 'Продано',
            value: formatCount(seller.value?.soldSitesCount ?? 0),
            icon: PackageCheck,
            tone: 'text-verified',
        },
    ];

    if (hasRating.value) {
        items.push({
            label: 'Рейтинг',
            value: `${Number(seller.value?.ratingAvg ?? 0).toFixed(1).replace('.', ',')} · ${formatCount(seller.value?.reviewsCount ?? 0)}`,
            icon: Star,
            tone: 'text-amber-400',
        });
    }

    return items;
});

/**
 * Гидратация из серверного состояния: страница отрисована без запроса к API.
 *
 * @param {object | null} hydrated
 * @param {number} id
 * @returns {boolean}
 */
function hydrate(hydrated, id) {
    if (!hydrated?.data || Number(hydrated.data.id) !== id) {
        return false;
    }

    seller.value = hydrated.data;
    listings.value = Array.isArray(hydrated.listings) ? hydrated.listings : [];
    pagination.value = hydrated.meta ?? { page: 1, lastPage: 1, total: 0 };

    if (Array.isArray(hydrated.reviews)) {
        reviews.value = hydrated.reviews;
        reviewsMeta.value = hydrated.reviewsMeta ?? { page: 1, lastPage: 1, total: hydrated.reviews.length };
        reviewsStatus.value = 'ready';
    }

    status.value = 'ready';

    return true;
}

/**
 * Лента отзывов грузится отдельно: профиль остаётся доступным,
 * даже если отзывы не загрузились.
 *
 * @param {number} id
 * @param {number} page
 * @param {boolean} append
 * @returns {Promise<void>}
 */
async function loadReviews(id, page = 1, append = false) {
    if (!append) {
        reviewsStatus.value = 'loading';
    }

    try {
        const payload = await fetchSellerReviews(id, page);

        if (sellerId.value !== id) {
            return;
        }

        reviews.value = append ? [...reviews.value, ...payload.items] : payload.items;
        reviewsMeta.value = payload.meta;
        reviewsStatus.value = 'ready';
    } catch {
        if (sellerId.value !== id) {
            return;
        }

        if (append) {
            reviewsStatus.value = 'ready';

            return;
        }

        reviews.value = [];
        reviewsStatus.value = 'error';
    }
}

/**
 * @returns {Promise<void>}
 */
async function showMoreReviews() {
    const nextPage = Number(reviewsMeta.value.page ?? 1) + 1;

    if (nextPage > Number(reviewsMeta.value.lastPage ?? 1)) {
        return;
    }

    await loadReviews(sellerId.value, nextPage, true);
}

/**
 * @param {number} id
 * @param {number} page
 * @returns {Promise<void>}
 */
async function load(id, page = 1) {
    status.value = 'loading';
    error.value = '';

    try {
        const payload = await fetchSeller(id, page);

        if (!payload.seller) {
            status.value = 'error';
            error.value = 'Продавец не найден.';

            return;
        }

        seller.value = payload.seller;
        listings.value = payload.listings;
        pagination.value = payload.meta;
        status.value = 'ready';
        applySellerMeta(payload.seller);
        loadReviews(id, 1);
    } catch (requestError) {
        status.value = 'error';
        error.value =
            requestError?.status === 404
                ? 'Продавец не найден.'
                : (requestError?.message ?? 'Не удалось загрузить профиль продавца.');
    }
}

/**
 * @param {number} nextPage
 * @returns {Promise<void>}
 */
async function changePage(nextPage) {
    await load(sellerId.value, nextPage);

    document.getElementById('seller-listings')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
 * @param {object} listing
 * @returns {void}
 */
function handleContact(listing) {
    contactListing.value = listing;
    isContactOpen.value = true;
}

/**
 * @param {object} listing
 * @returns {void}
 */
function handleReport(listing) {
    reportListing.value = listing;
    isReportOpen.value = true;
}

/**
 * @param {object} listing
 * @returns {Promise<void>}
 */
async function toggleFavorite(listing) {
    if (!session.isAuthenticated) {
        toast.error('Войдите, чтобы добавить лот в избранное');

        return;
    }

    const id = Number(listing.id);
    favoriteBusyId.value = id;

    try {
        const isFavorite = await favorites.toggle(id);

        toast.success(isFavorite ? 'Лот добавлен в избранное' : 'Лот убран из избранного');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось обновить избранное');
    } finally {
        favoriteBusyId.value = null;
    }
}

onMounted(() => {
    if (session.isAuthenticated) {
        favorites.ensureLoaded().catch(() => {});
    }

    if (hydrate(consumeInitialState('seller'), sellerId.value)) {
        if (reviewsStatus.value === 'loading') {
            loadReviews(sellerId.value);
        }

        return;
    }

    load(sellerId.value);
});

watch(sellerId, (id) => {
    seller.value = null;
    listings.value = [];
    reviews.value = [];
    reviewsMeta.value = { page: 1, lastPage: 1, total: 0 };
    reviewsStatus.value = 'loading';
    load(id);
});
</script>

<template>
    <main class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <RouterLink
            :to="{ name: 'catalog' }"
            class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            К каталогу
        </RouterLink>

        <div v-if="isLoading" class="flex flex-col gap-3">
            <Skeleton class="h-40 w-full rounded-xl" />
            <Skeleton class="h-64 w-full rounded-xl" />
        </div>

        <div
            v-else-if="isError"
            class="flex flex-col items-start gap-3 rounded-xl border border-border p-6"
        >
            <p class="text-sm text-destructive">{{ error }}</p>
            <RouterLink :to="{ name: 'catalog' }">
                <Button variant="outline" size="sm">Перейти в каталог</Button>
            </RouterLink>
        </div>

        <template v-else-if="seller">
            <Card class="mb-6 flex flex-col gap-5 border-border bg-card p-5 sm:p-6">
                <div class="flex flex-wrap items-start gap-4">
                    <div
                        class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-secondary text-2xl font-extrabold text-primary"
                    >
                        <img
                            v-if="seller.avatarUrl"
                            :src="seller.avatarUrl"
                            :alt="seller.displayName"
                            class="size-full object-cover"
                        />
                        <span v-else aria-hidden="true">{{ initial }}</span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                                {{ seller.displayName }}
                            </h1>

                            <Badge
                                v-if="seller.isVerifiedSeller"
                                variant="outline"
                                class="border-verified/40 bg-verified/10 text-verified"
                            >
                                <BadgeCheck class="size-3.5" aria-hidden="true" />
                                Проверенный продавец
                            </Badge>

                            <RatingStars
                                v-if="hasRating"
                                :rating="Number(seller.ratingAvg ?? 0)"
                                :count="seller.reviewsCount"
                            />
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                            <span v-if="seller.registeredAt" class="inline-flex items-center gap-1.5">
                                <CalendarDays class="size-4" aria-hidden="true" />
                                На сайте с {{ formatDate(seller.registeredAt) }}
                            </span>

                            <span v-if="seller.city" class="inline-flex items-center gap-1.5">
                                <MapPin class="size-4" aria-hidden="true" />
                                {{ seller.city }}
                            </span>
                        </div>

                        <p
                            v-if="seller.bio"
                            class="mt-3 max-w-2xl text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                        >
                            {{ seller.bio }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3">
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="flex min-w-32 items-center gap-2 rounded-lg border border-border bg-secondary/40 px-3 py-2"
                    >
                        <component
                            :is="stat.icon"
                            class="size-4"
                            :class="stat.tone"
                            aria-hidden="true"
                        />

                        <div class="flex flex-col">
                            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {{ stat.label }}
                            </span>
                            <span class="text-sm font-bold tabular-nums text-foreground">
                                {{ stat.value }}
                            </span>
                        </div>
                    </div>
                </div>
            </Card>

            <section id="seller-listings" class="scroll-mt-24">
                <h2 class="mb-3 text-lg font-bold text-foreground">Лоты продавца</h2>

                <ListingList
                    v-if="listings.length > 0"
                    :items="listings"
                    :total="pagination.total ?? listings.length"
                    :loading="status === 'loading'"
                    :favorite-ids="favorites.ids"
                    :favorite-busy-id="favoriteBusyId"
                    @contact="handleContact"
                    @report="handleReport"
                    @favorite="toggleFavorite"
                />

                <p v-else class="rounded-xl border border-border p-6 text-sm text-muted-foreground">
                    У продавца пока нет опубликованных лотов.
                </p>

                <CatalogPagination
                    v-if="listings.length > 0"
                    :page="pagination.page ?? 1"
                    :last-page="pagination.lastPage ?? 1"
                    @update:page="changePage"
                />
            </section>

            <section id="seller-reviews" class="mt-8 scroll-mt-24">
                <div class="mb-3 flex flex-wrap items-end justify-between gap-2">
                    <h2 class="text-lg font-bold text-foreground">Отзывы покупателей</h2>

                    <RatingStars
                        v-if="hasRating"
                        :rating="Number(seller.ratingAvg ?? 0)"
                        :count="seller.reviewsCount"
                        size="md"
                    />
                </div>

                <div v-if="reviewsStatus === 'loading'" class="flex flex-col gap-3">
                    <Skeleton class="h-24 w-full rounded-xl" />
                </div>

                <p
                    v-else-if="reviewsStatus === 'error'"
                    class="rounded-xl border border-border p-6 text-sm text-muted-foreground"
                >
                    Не удалось загрузить отзывы.
                </p>

                <p
                    v-else-if="reviews.length === 0"
                    class="rounded-xl border border-border p-6 text-sm text-muted-foreground"
                >
                    Пока нет опубликованных отзывов — отзывы проходят проверку модератора.
                </p>

                <template v-else>
                    <div class="flex flex-col gap-3">
                        <Card
                            v-for="review in reviews"
                            :key="review.id"
                            class="flex flex-col gap-2 border-border bg-card p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="flex size-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-secondary text-xs font-extrabold text-primary"
                                    >
                                        <img
                                            v-if="review.author?.avatarUrl"
                                            :src="review.author.avatarUrl"
                                            :alt="review.author.displayName"
                                            class="size-full object-cover"
                                        />
                                        <span v-else aria-hidden="true">
                                            {{ (review.author?.displayName?.charAt(0) || '?').toUpperCase() }}
                                        </span>
                                    </span>

                                    <span class="text-sm font-semibold text-foreground">
                                        {{ review.author?.displayName ?? 'Покупатель' }}
                                    </span>

                                    <RatingStars :rating="review.rating" />
                                </div>

                                <span v-if="review.publishedAt" class="text-xs text-muted-foreground">
                                    {{ formatDate(review.publishedAt) }}
                                </span>
                            </div>

                            <p
                                v-if="review.comment"
                                class="text-sm leading-relaxed whitespace-pre-line text-muted-foreground"
                            >
                                {{ review.comment }}
                            </p>

                            <p v-if="review.site" class="text-xs text-muted-foreground">
                                Лот: {{ review.site.title }} · {{ review.site.domain }}
                            </p>
                        </Card>
                    </div>

                    <div
                        v-if="Number(reviewsMeta.page ?? 1) < Number(reviewsMeta.lastPage ?? 1)"
                        class="mt-3"
                    >
                        <Button variant="outline" size="sm" @click="showMoreReviews">
                            Показать ещё отзывы
                        </Button>
                    </div>
                </template>
            </section>

            <ContactSellerDialog v-model:open="isContactOpen" :listing="contactListing" />
            <ReportListingDialog v-model:open="isReportOpen" :listing="reportListing" />
        </template>
    </main>
</template>
