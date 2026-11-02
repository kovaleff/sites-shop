<script setup>
import { computed, onMounted, ref } from 'vue';
import { storeToRefs } from 'pinia';
import { toast } from 'vue-sonner';
import { ExternalLink, Trash2 } from '@lucide/vue';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { formatDate } from '@/lib/format';
import { useSavedSearchesStore } from '@/stores/savedSearches';

const savedSearches = useSavedSearchesStore();
const { items, status, error } = storeToRefs(savedSearches);

const updatingId = ref(null);
const deletingId = ref(null);

const isLoading = computed(() => status.value === 'idle' || status.value === 'loading');
const isEmpty = computed(() => status.value === 'success' && items.value.length === 0);
const hasError = computed(() => status.value === 'error');

onMounted(() => {
    savedSearches.load();
});

/**
 * @param {object} search
 * @returns {string}
 */
function displayName(search) {
    return search.name && search.name.trim() !== '' ? search.name : 'Подписка на фильтр';
}

/**
 * @param {object} search
 * @param {Event} event
 * @returns {Promise<void>}
 */
async function changeFrequency(search, event) {
    const frequency = event.target.value;

    updatingId.value = search.id;

    try {
        await savedSearches.updateFrequency(search.id, frequency);
        toast.success('Частота писем обновлена');
    } catch (requestError) {
        event.target.value = search.frequency;
        toast.error(requestError?.message ?? 'Не удалось обновить частоту');
    } finally {
        updatingId.value = null;
    }
}

/**
 * @param {object} search
 * @returns {Promise<void>}
 */
async function remove(search) {
    deletingId.value = search.id;

    try {
        await savedSearches.remove(search.id);
        toast.success('Подписка удалена');
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось удалить подписку');
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <CabinetLayout>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-extrabold text-foreground">Подписки на фильтры</h1>
                <p class="text-sm text-muted-foreground">
                    Письма о новых лотах, подходящих под сохранённые условия каталога.
                </p>
            </div>

            <RouterLink :to="{ name: 'catalog' }">
                <Button type="button" variant="outline">Настроить в каталоге</Button>
            </RouterLink>
        </div>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-3">
            <template v-if="isLoading">
                <Skeleton v-for="index in 2" :key="index" class="h-28 w-full rounded-xl" />
            </template>

            <div
                v-else-if="hasError"
                class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
            >
                <p class="text-sm text-destructive">{{ error }}</p>
                <Button variant="outline" size="sm" @click="savedSearches.load()">Повторить</Button>
            </div>

            <div
                v-else-if="isEmpty"
                class="flex flex-col items-start gap-3 rounded-xl border border-dashed border-border p-8"
            >
                <p class="text-sm text-muted-foreground">
                    Подписок пока нет. Выставьте фильтры в каталоге и нажмите
                    «Подписаться на фильтр».
                </p>
                <RouterLink :to="{ name: 'catalog' }">
                    <Button type="button">Перейти в каталог</Button>
                </RouterLink>
            </div>

            <template v-else>
                <Card
                    v-for="search in items"
                    :key="search.id"
                    class="flex flex-col gap-3 border-border bg-card p-4"
                >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 class="text-base font-bold text-foreground">{{ displayName(search) }}</h2>
                        <p class="mt-1 text-sm text-muted-foreground">{{ search.summary }}</p>
                        <p v-if="search.lastNotifiedAt" class="mt-1 text-xs text-muted-foreground">
                            Последнее письмо: {{ formatDate(search.lastNotifiedAt) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <label class="text-xs text-muted-foreground" :for="`frequency-${search.id}`">
                            Частота
                        </label>
                        <select
                            :id="`frequency-${search.id}`"
                            class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                            :value="search.frequency"
                            :disabled="updatingId === search.id"
                            @change="changeFrequency(search, $event)"
                        >
                            <option value="daily">Раз в день</option>
                            <option value="weekly">Раз в неделю</option>
                        </select>

                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive hover:text-destructive"
                            :disabled="deletingId === search.id"
                            :aria-label="`Удалить подписку ${displayName(search)}`"
                            @click="remove(search)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </div>

                <RouterLink
                    :to="search.url"
                    class="inline-flex w-fit items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
                >
                    Открыть в каталоге
                    <ExternalLink class="size-3.5" aria-hidden="true" />
                </RouterLink>
            </Card>
            </template>
        </div>
    </CabinetLayout>
</template>
