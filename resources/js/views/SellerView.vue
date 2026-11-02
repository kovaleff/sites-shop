<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import {
    ArrowLeft,
    BadgeCheck,
    CalendarDays,
    MapPin,
    PackageCheck,
    Store,
} from '@lucide/vue';

import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import ReportListingDialog from '@/components/catalog/ReportListingDialog.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ListingList from '@/components/catalog/ListingList.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { consumeInitialState } from '@/lib/initialState';
import { applySellerMeta } from '@/lib/pageMeta';
import { formatCount, formatDate } from '@/lib/format';
import { fetchSeller } from '@/services/sellerService';

const route = useRoute();

const seller = ref(null);
const listings = ref([]);
const pagination = ref({ page: 1, lastPage: 1, total: 0 });
const status = ref('loading');
const error = ref('');
const contactListing = ref(null);
const isContactOpen = ref(false);
const reportListing = ref(null);
const isReportOpen = ref(false);

const sellerId = computed(() => Number(route.params.id));
const isLoading = computed(() => status.value === 'loading');
const isError = computed(() => status.value === 'error');
const initial = computed(() => (seller.value?.displayName?.charAt(0) || '?').toUpperCase());
const stats = computed(() => [
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
]);

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
    status.value = 'ready';

    return true;
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

onMounted(() => {
    if (hydrate(consumeInitialState('seller'), sellerId.value)) {
        return;
    }

    load(sellerId.value);
});

watch(sellerId, (id) => {
    seller.value = null;
    listings.value = [];
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
                    @contact="handleContact"
                    @report="handleReport"
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

            <ContactSellerDialog v-model:open="isContactOpen" :listing="contactListing" />
            <ReportListingDialog v-model:open="isReportOpen" :listing="reportListing" />
        </template>
    </main>
</template>
