<script setup>
import { computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';
import { useRouter } from 'vue-router';
import { toast } from 'vue-sonner';
import { Plus } from '@lucide/vue';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import SiteCard from '@/components/cabinet/SiteCard.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useCabinetStore } from '@/stores/cabinet';

const router = useRouter();
const cabinet = useCabinetStore();
const { items, meta, status, error } = storeToRefs(cabinet);

const isLoading = computed(() => status.value === 'loading' || status.value === 'idle');
const isEmpty = computed(() => status.value === 'success' && items.value.length === 0);
const hasError = computed(() => status.value === 'error');

onMounted(() => {
    cabinet.load({ mode: 'visible', page: 1 });
});

/**
 * @param {object} site
 * @param {string} nextStatus
 * @returns {Promise<void>}
 */
async function changeStatus(site, nextStatus) {
    try {
        await cabinet.changeStatus(site.id, nextStatus);
        toast.success('Статус обновлён');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось изменить статус');
    }
}

/**
 * @param {object} site
 * @returns {Promise<void>}
 */
async function hideSite(site) {
    try {
        await cabinet.hide(site.id);
        toast.success('Объявление скрыто');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось скрыть объявление');
    }
}

/**
 * @param {object} site
 * @returns {void}
 */
function editSite(site) {
    router.push({ name: 'cabinet-site-edit', params: { id: site.id } });
}

/**
 * @param {number} page
 * @returns {void}
 */
function goToPage(page) {
    cabinet.load({ mode: 'visible', page });
}
</script>

<template>
    <CabinetLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-foreground">Мои объявления</h1>
                <p class="text-sm text-muted-foreground">
                    Управляйте объявлениями, статусами и подпиской.
                </p>
            </div>

            <Button type="button" @click="router.push({ name: 'cabinet-site-create' })">
                <Plus class="size-4" aria-hidden="true" />
                Добавить объявление
            </Button>
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
                    У вас пока нет объявлений. Разместите первый сайт на продажу.
                </p>
                <Button type="button" @click="router.push({ name: 'cabinet-site-create' })">
                    Добавить объявление
                </Button>
            </div>

            <template v-else>
                <p class="text-sm text-muted-foreground">
                    Показано {{ items.length }} из {{ meta.total }}
                </p>

                <SiteCard
                    v-for="site in items"
                    :key="site.id"
                    :site="site"
                    mode="visible"
                    @edit="editSite"
                    @change="changeStatus(site, $event)"
                    @hide="hideSite"
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