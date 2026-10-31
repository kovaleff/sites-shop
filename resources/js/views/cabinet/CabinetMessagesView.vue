<script setup>
import { computed, onMounted } from 'vue';
import { storeToRefs } from 'pinia';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import ConversationList from '@/components/messages/ConversationList.vue';
import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useConversationsStore } from '@/stores/conversations';

const conversations = useConversationsStore();
const { items, meta, status, error } = storeToRefs(conversations);

const isLoading = computed(() => status.value === 'loading' || status.value === 'idle');
const isEmpty = computed(() => status.value === 'success' && items.value.length === 0);
const hasError = computed(() => status.value === 'error');

onMounted(() => {
    conversations.load(1);
});

/**
 * @param {number} page
 * @returns {void}
 */
function goToPage(page) {
    conversations.load(page);
}
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">Сообщения</h1>
            <p class="text-sm text-muted-foreground">
                Переписка по вашим объявлениям и объявлениям, которые вы нашли в каталоге.
            </p>
        </div>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-3">
            <template v-if="isLoading">
                <Skeleton v-for="index in 3" :key="index" class="h-20 w-full rounded-xl" />
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
                Сообщений пока нет. Они появятся, когда покупатели начнут писать по вашим объявлениям,
                или когда вы напишете продавцу из каталога.
            </div>

            <template v-else>
                <ConversationList :items="items" />

                <CatalogPagination
                    :page="meta.page"
                    :last-page="meta.lastPage"
                    @update:page="goToPage"
                />
            </template>
        </div>
    </CabinetLayout>
</template>