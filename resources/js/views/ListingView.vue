<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { ArrowLeft, BadgeCheck, Globe, TrendingUp } from '@lucide/vue';

import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import FavoriteButton from '@/components/catalog/FavoriteButton.vue';
import ReportListingDialog from '@/components/catalog/ReportListingDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { consumeInitialState } from '@/lib/initialState';
import { applyListingMeta } from '@/lib/pageMeta';
import { formatDate, formatRub, formatRubMonthly } from '@/lib/format';
import { fetchListing } from '@/services/catalogService';
import { useFavoritesStore } from '@/stores/favorites';
import { useSessionStore } from '@/stores/session';

const route = useRoute();
const favorites = useFavoritesStore();
const session = useSessionStore();

const listing = ref(null);
const status = ref('loading');
const error = ref('');
const isContactOpen = ref(false);
const isReportOpen = ref(false);
const favoriteBusy = ref(false);

const isLoading = computed(() => status.value === 'loading');
const isError = computed(() => status.value === 'error');
const categories = computed(() => listing.value?.categories ?? []);
const sellerInitial = computed(
    () => (listing.value?.seller?.displayName?.charAt(0) || '?').toUpperCase(),
);
const isFavorite = computed(() =>
    listing.value ? favorites.isFavorite(listing.value.id) : false,
);

/**
 * @returns {Promise<void>}
 */
async function toggleFavorite() {
    if (!listing.value) {
        return;
    }

    if (!session.isAuthenticated) {
        toast.error('Войдите, чтобы добавить лот в избранное');

        return;
    }

    favoriteBusy.value = true;

    try {
        const added = await favorites.toggle(listing.value.id);

        toast.success(added ? 'Лот добавлен в избранное' : 'Лот убран из избранного');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось обновить избранное');
    } finally {
        favoriteBusy.value = false;
    }
}

/**
 * Гидратация из серверного состояния: страница отрисована без запроса к API.
 * При переходе внутри SPA состояния нет — грузим по слагу.
 *
 * @returns {Promise<void>}
 */
onMounted(async () => {
    if (session.isAuthenticated) {
        favorites.ensureLoaded().catch(() => {});
    }

    const slug = String(route.params.slug ?? '');
    const hydrated = consumeInitialState('listing');

    if (hydrated && hydrated.slug === slug) { 
        listing.value = hydrated;
        status.value = 'ready';

        return;
    }

    try {
        const data = await fetchListing(slug);

        if (!data) {
            status.value = 'error';
            error.value = 'Объявление не найдено или снято с публикации.';

            return;
        }

        listing.value = data;
        status.value = 'ready';
        applyListingMeta(data);
    } catch (requestError) {
        status.value = 'error';
        error.value =
            requestError?.status === 404
                ? 'Объявление не найдено или снято с публикации.'
                : (requestError?.message ?? 'Не удалось загрузить объявление.');
    }
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
            <Skeleton class="h-10 w-2/3 rounded-lg" />
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

        <template v-else-if="listing">
            <header class="mb-5 flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">
                        {{ listing.title }}
                    </h1>

                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <Globe class="size-4 shrink-0" aria-hidden="true" />
                        <a
                            :href="`https://${listing.domain}`"
                            target="_blank"
                            rel="nofollow noopener"
                            class="font-semibold text-primary hover:underline"
                        >
                            {{ listing.domain }}
                        </a>

                        <span v-if="listing.domainZone" aria-hidden="true">·</span>
                        <span v-if="listing.domainZone">.{{ listing.domainZone }}</span>

                        <Badge
                            v-if="listing.isVerified"
                            variant="outline"
                            class="border-verified/40 bg-verified/10 text-verified"
                        >
                            <BadgeCheck class="size-3.5" aria-hidden="true" />
                            Владение подтверждено
                        </Badge>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <FavoriteButton
                        :is-favorite="isFavorite"
                        :busy="favoriteBusy"
                        @toggle="toggleFavorite"
                    />
                    <Button type="button" @click="isContactOpen = true">Связаться с продавцом</Button>
                    <Button type="button" variant="ghost" @click="isReportOpen = true">
                        Пожаловаться
                    </Button>
                </div>
            </header>

            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                <Card class="flex flex-col gap-4 border-border bg-card p-5">
                    <h2 class="text-base font-bold text-foreground">Описание</h2>
                    <p class="whitespace-pre-line text-sm leading-relaxed text-muted-foreground">
                        {{ listing.description }}
                    </p>

                    <div v-if="categories.length > 0" class="flex flex-wrap gap-1.5">
                        <Badge v-for="category in categories" :key="category.id" variant="outline">
                            {{ category.name }}
                        </Badge>
                    </div>

                    <p v-if="listing.hasTrademark" class="text-xs text-muted-foreground">
                        Есть товарный знак
                    </p>
                </Card>

                <div class="flex flex-col gap-3">
                    <Card
                        v-if="listing.seller"
                        class="flex flex-col gap-2 border-border bg-card p-5"
                    >
                        <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Продавец
                        </span>

                        <RouterLink
                            :to="listing.seller.profileUrl ?? `/seller/${listing.seller.id}`"
                            class="flex items-center gap-2.5 rounded-lg outline-none hover:text-primary focus-visible:ring-2 focus-visible:ring-ring"
                        >
                            <span
                                class="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border bg-secondary text-sm font-extrabold text-primary"
                            >
                                <img
                                    v-if="listing.seller.avatarUrl"
                                    :src="listing.seller.avatarUrl"
                                    :alt="listing.seller.displayName"
                                    class="size-full object-cover"
                                />
                                <span v-else aria-hidden="true">{{ sellerInitial }}</span>
                            </span>

                            <span class="min-w-0 truncate text-sm font-semibold">
                                {{ listing.seller.displayName }}
                            </span>
                        </RouterLink>

                        <RouterLink
                            :to="listing.seller.profileUrl ?? `/seller/${listing.seller.id}`"
                            class="text-xs text-muted-foreground hover:text-primary hover:underline"
                        >
                            Все лоты продавца
                        </RouterLink>
                    </Card>

                    <Card class="flex flex-col gap-3 border-border bg-card p-5">
                        <div>
                            <span class="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Цена
                            </span>
                            <p class="text-2xl font-extrabold tabular-nums text-primary">
                                {{ formatRub(listing.price) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <TrendingUp class="size-4 text-verified" aria-hidden="true" />
                            <div>
                                <span class="block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    Доход
                                </span>
                                <span class="text-sm font-bold tabular-nums text-verified">
                                    {{ formatRubMonthly(listing.monthlyIncome) }}
                                </span>
                            </div>
                        </div>

                        <div v-if="listing.paybackMonths">
                            <span class="block text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Окупаемость
                            </span>
                            <span class="text-sm font-semibold text-foreground">
                                ≈ {{ listing.paybackMonths }} мес.
                            </span>
                        </div>

                        <p v-if="listing.publishedAt" class="text-xs text-muted-foreground">
                            Опубликовано {{ formatDate(listing.publishedAt) }}
                        </p>
                    </Card>
                </div>
            </div>

            <ContactSellerDialog v-model:open="isContactOpen" :listing="listing" />
            <ReportListingDialog v-model:open="isReportOpen" :listing="listing" />
        </template>
    </main>
</template>
