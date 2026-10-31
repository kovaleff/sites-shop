<script setup>
import { computed, onMounted, watch } from 'vue';
import { storeToRefs } from 'pinia';
import { useRoute } from 'vue-router';
import { toast } from 'vue-sonner';
import { ArrowLeft } from '@lucide/vue';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import ConversationThread from '@/components/messages/ConversationThread.vue';
import MessageComposer from '@/components/messages/MessageComposer.vue';
import ReadOnlyNotice from '@/components/messages/ReadOnlyNotice.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useConversationsStore } from '@/stores/conversations';

const route = useRoute();
const conversations = useConversationsStore();
const { current, messages, currentStatus, error, sending } = storeToRefs(conversations);

const conversationId = computed(() => Number(route.params.id));
const isLoading = computed(() => currentStatus.value === 'loading' || currentStatus.value === 'idle');

function open() {
    conversations.open(conversationId.value);
}

onMounted(open);

watch(conversationId, () => {
    conversations.reset();
    open();
});

/**
 * @param {string} body
 * @returns {Promise<void>}
 */
async function send(body) {
    try {
        await conversations.send(body);
    } catch (requestError) {
        toast.error(requestError?.message ?? 'Не удалось отправить сообщение');
    }
}
</script>

<template>
    <CabinetLayout>
        <div class="flex flex-wrap items-center gap-2">
            <Button variant="ghost" size="sm" @click="$router.push({ name: 'cabinet-messages' })">
                <ArrowLeft class="size-4" aria-hidden="true" />
                Назад
            </Button>

            <span v-if="current" class="text-sm text-muted-foreground">
                {{ current.site?.title }} · {{ current.site?.domain }}
            </span>
        </div>

        <Skeleton v-if="isLoading" class="h-72 w-full rounded-xl" />

        <div
            v-else-if="currentStatus === 'error'"
            class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
        >
            <p class="text-sm text-destructive">{{ error }}</p>
            <Button variant="outline" size="sm" @click="open">Повторить</Button>
        </div>

        <div v-else class="flex max-w-3xl flex-col gap-4">
            <div class="rounded-xl border border-border p-4">
                <ConversationThread :messages="messages" />
            </div>

            <ReadOnlyNotice v-if="current?.isReadOnly" :status="current?.site?.status" />

            <MessageComposer
                v-else
                :sending="sending"
                @send="send"
            />
        </div>
    </CabinetLayout>
</template>