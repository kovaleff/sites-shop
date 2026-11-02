<script setup>
import { computed, nextTick, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue-sonner';
import { BookmarkPlus } from '@lucide/vue';

import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue';
import CatalogErrorState from '@/components/catalog/CatalogErrorState.vue';
import CatalogFilters from '@/components/catalog/CatalogFilters.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import CatalogSkeletons from '@/components/catalog/CatalogSkeletons.vue';
import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import ListingList from '@/components/catalog/ListingList.vue';
import ReportListingDialog from '@/components/catalog/ReportListingDialog.vue';
import SaveSearchDialog from '@/components/catalog/SaveSearchDialog.vue';
import { Button } from '@/components/ui/button';
import { useCatalogQuery } from '@/composables/useCatalogQuery';
import { countActiveFilters } from '@/lib/catalogFilters';
import { fallbackZones } from '@/mocks/zones';
import { fetchZones } from '@/services/catalogService';
import { fetchCategories } from '@/services/categoriesService';
import { useCatalogStore } from '@/stores/catalog';
import { useFavoritesStore } from '@/stores/favorites';
import { useSessionStore } from '@/stores/session';

const catalog = useCatalogStore();
const favorites = useFavoritesStore();
const session = useSessionStore();
const { applied, items, total, status, error, page, lastPage } = storeToRefs(catalog);

const zones = ref([]);
const zonesLoading = ref(true);
const categories = ref([]);
const categoriesLoading = ref(true);
const contactListing = ref(null);
const isContactOpen = ref(false);
const reportListing = ref(null);
const isReportOpen = ref(false);
const isSaveSearchOpen = ref(false);
const favoriteBusyId = ref(null);

const zoneValues = computed(() => zones.value.map((zone) => zone.value));
const categorySlugs = computed(() => categories.value.map((category) => category.slug));
const { hydrateFromRoute, pushFilters, replacePage, clearFilters } = useCatalogQuery(
    () => zoneValues.value,
    () => categorySlugs.value,
);

const activeCount = computed(() => countActiveFilters(applied.value));
const hasActiveFilters = computed(() => activeCount.value > 0);

const showSkeletons = computed(
    () => (status.value === 'loading' || status.value === 'idle') && items.value.length === 0,
);
const showError = computed(() => status.value === 'error');
const showEmpty = computed(() => status.value === 'success' && total.value === 0);
const showPagination = computed(() => status.value === 'success' && total.value > 0);

onMounted(async () => {
    await Promise.all([loadZones(), loadCategories()]);
    await hydrateFromRoute();
});

/**
 * @returns {Promise<void>}
 */
async function loadZones() {
    try {
        zones.value = await fetchZones();
    } catch {
        zones.value = fallbackZones.map((zone) => ({ ...zone, count: null }));
    } finally {
        zonesLoading.value = false;
    }
}

/**
 * Справочник тематик нужен только фильтру и форме объявления,
 * поэтому при ошибке каталог остаётся работоспособным без него.
 *
 * @returns {Promise<void>}
 */
async function loadCategories() {
    try {
        categories.value = await fetchCategories();
    } catch {
        categories.value = [];
    } finally {
        categoriesLoading.value = false;
    }
}

/**
 * @param {object} filters
 * @returns {Promise<void>}
 */
async function applyFilters(filters) {
    await catalog.applyFilters(filters);
    await replacePage(1, filters);
}

/**
 * @returns {Promise<void>}
 */
async function resetFilters() {
    await catalog.resetFilters();
    await clearFilters();
}

/**
 * @param {number} nextPage
 * @returns {Promise<void>}
 */
async function changePage(nextPage) {
    await catalog.goToPage(nextPage);
    await pushFilters(catalog.applied, catalog.page);
    await nextTick();

    document.getElementById('catalog-list')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/**
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

/**
 * @returns {void}
 */
function openSaveSearch() {
    isSaveSearchOpen.value = true;
}
</script>

<template>
    <main class="mx-auto w-full max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                    Каталог готовых сайтов
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Действующие проекты с прозрачной аналитикой и проверенными владельцами.
                </p>
            </div>

            <Button type="button" variant="outline" @click="openSaveSearch">
                <BookmarkPlus class="size-4" aria-hidden="true" />
                Подписаться на фильтр
            </Button>
        </div>

        <CatalogFilters
            class="mb-6"
            :applied="applied"
            :zones="zones"
            :zones-loading="zonesLoading"
            :categories="categories"
            :categories-loading="categoriesLoading"
            :active-count="activeCount"
            @apply="applyFilters"
            @reset="resetFilters"
        />

        <CatalogSkeletons v-if="showSkeletons" />

        <CatalogErrorState
            v-else-if="showError"
            :message="error"
            @retry="catalog.retry()"
            @reset="resetFilters"
        />

        <CatalogEmptyState
            v-else-if="showEmpty && !hasActiveFilters"
            title="Предложений пока нет"
            description="В каталоге пока нет активных лотов. Загляните немного позже."
        />

        <CatalogEmptyState
            v-else-if="showEmpty"
            title="Ничего не найдено"
            description="Попробуйте изменить параметры фильтра или сбросить их."
            show-reset
            @reset="resetFilters"
        />

        <template v-else>
            <ListingList
                :items="items"
                :total="total"
                :loading="status === 'loading'"
                :favorite-ids="favorites.ids"
                :favorite-busy-id="favoriteBusyId"
                @contact="handleContact"
                @report="handleReport"
                @favorite="toggleFavorite"
            />

            <CatalogPagination
                v-if="showPagination"
                :page="page"
                :last-page="lastPage"
                @update:page="changePage"
            />
        </template>
    </main>

    <ContactSellerDialog v-model:open="isContactOpen" :listing="contactListing" />
    <ReportListingDialog v-model:open="isReportOpen" :listing="reportListing" />
    <SaveSearchDialog v-model:open="isSaveSearchOpen" :filters="applied" />
</template>
