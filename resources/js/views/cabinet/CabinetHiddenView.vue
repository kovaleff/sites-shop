<script setup>
import { computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue-sonner';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import SiteCard from '@/components/cabinet/SiteCard.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCabinetStore } from '@/stores/cabinet';

const cabinet = useCabinetStore();
const { items, meta, status, error } = storeToRefs(cabinet);

const isLoading = computed(() => status.value === 'loading' || status.value === 'idle');
const isEmpty = computed(() => status.value === 'success' && items.value.length === 0);
const hasError = computed(() => status.value === 'error');

onMounted(() => {
    cabinet.load({ mode: 'hidden', page: 1 });
});

/**
 * @param {object} site
 * @returns {Promise<void>}
 */
async function restoreSite(site) {
    try {
        await cabinet.restore(site.id);
        toast.success('Объявление возвращено');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось вернуть объявление');
    }
}

/**
 * @param {number} page
 * @returns {void}
 */
function goToPage(page) {
    cabinet.load({ mode: 'hidden', page });
}
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">Скрытые объявления</h1>
            <p class="text-sm text-muted-foreground">
                Проданные объявления, скрытые из основного списка. История и переписка сохранены.
            </p>
        </div>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-3">
            <template v-if="isLoading">
                <Skeleton v-for="index in 2" :key="index" class="h-40 w-full rounded-xl" />
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
                class="rounded-xl border border-dashed border-border p-8 text-sm text-muted-foreground"
            >
                Скрытых объявлений нет.
            </div>

            <template v-else>
                <SiteCard
                    v-for="site in items"
                    :key="site.id"
                    :site="site"
                    mode="hidden"
                    @restore="restoreSite"
                />

                <CatalogPagination
                    :page="meta.page"
                    :last-page="meta.lastPage"
                    @update:page="goToPage"
                />
            </template>
        </div>
    </CabinetLayout>
</template>