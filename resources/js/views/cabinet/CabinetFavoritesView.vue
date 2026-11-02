<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue-sonner';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import ListingList from '@/components/catalog/ListingList.vue';
import ReportListingDialog from '@/components/catalog/ReportListingDialog.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useFavoritesStore } from '@/stores/favorites';

const favorites = useFavoritesStore();
const { items, meta, status, error } = storeToRefs(favorites);

const contactListing = ref(null);
const isContactOpen = ref(false);
const reportListing = ref(null);
const isReportOpen = ref(false);
const favoriteBusyId = ref(null);

const isLoading = computed(() => status.value === 'idle' || status.value === 'loading');
const isEmpty = computed(() => status.value === 'success' && items.value.length === 0);
const hasError = computed(() => status.value === 'error');

onMounted(() => {
    favorites.loadFavorites(1);
});

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
    const id = Number(listing.id);
    favoriteBusyId.value = id;

    try {
        await favorites.toggle(id);
        toast.success('Лот убран из избранного');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось убрать лот из избранного');
    } finally {
        favoriteBusyId.value = null;
    }
}

/**
 * @param {number} page
 * @returns {void}
 */
function goToPage(page) {
    favorites.loadFavorites(page);
}
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">Избранное</h1>
            <p class="text-sm text-muted-foreground">
                Сохранённые лоты: следите за ценой и статусом объявления.
            </p>
        </div>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-3">
            <template v-if="isLoading">
                <Skeleton v-for="index in 3" :key="index" class="h-40 w-full rounded-xl" />
            </template>

            <div
                v-else-if="hasError"
                class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
            >
                <p class="text-sm text-destructive">{{ error }}</p>
                <Button variant="outline" size="sm" @click="goToPage(meta.page)">Повторить</Button>
            </div>

            <div
                v-else-if="isEmpty"
                class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-border p-8"
            >
                <p class="text-sm text-muted-foreground">
                    В избранном пока пусто. Нажмите на сердечко в каталоге, чтобы сохранить лот.
                </p>
                <RouterLink :to="{ name: 'catalog' }">
                    <Button type="button">Перейти в каталог</Button>
                </RouterLink>
            </div>

            <template v-else>
                <ListingList
                    :items="items"
                    :total="meta.total"
                    :favorite-ids="favorites.ids"
                    :favorite-busy-id="favoriteBusyId"
                    @contact="handleContact"
                    @report="handleReport"
                    @favorite="toggleFavorite"
                />

                <CatalogPagination
                    :page="meta.page"
                    :last-page="meta.lastPage"
                    @update:page="goToPage"
                />
            </template>
        </div>
    </CabinetLayout>

    <ContactSellerDialog v-model:open="isContactOpen" :listing="contactListing" />
    <ReportListingDialog v-model:open="isReportOpen" :listing="reportListing" />
</template>
