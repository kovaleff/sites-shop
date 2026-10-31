<script setup>
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';

import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { MESSAGE_MAX } from '@/lib/cabinetConfig';
import { startConversation } from '@/services/conversationService';
import { useSessionStore } from '@/stores/session';

const props = defineProps({
    open: { type: Boolean, default: false },
    listing: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const session = useSessionStore();

const body = ref('');
const error = ref('');
const sending = ref(false);

const remaining = computed(() => MESSAGE_MAX - body.value.length);
const canSend = computed(
    () => session.isAuthenticated && !sending.value && body.value.trim().length > 0,
);

watch(
    () => props.open,
    async (isOpen) => {
        if (!isOpen) {
            return;
        }

        body.value = '';
        error.value = '';
        await Promise.resolve();

        document.getElementById('contact-message-body')?.focus();
    },
);

/**
 * @param {'vk' | 'yandex'} provider
 * @returns {Promise<void>}
 */
async function login(provider) {
    const result = provider === 'vk' ? await session.loginWithVk() : await session.loginWithYandex();

    if (!result.ok && result.error) {
        toast.error(result.error);
    }
}

/**
 * @returns {Promise<void>}
 */
async function submit() {
    if (!props.listing) {
        return;
    }

    error.value = '';
    sending.value = true;

    try {
        await startConversation(props.listing.id, body.value.trim());
        await session.refreshUnread();
        emit('update:open', false);
        toast.success('Сообщение отправлено');
    } catch (requestError) {
        if (requestError?.status === 422) {
            error.value =
                requestError?.data?.errors?.siteId?.[0]
                ?? requestError?.data?.errors?.body?.[0]
                ?? requestError?.message
                ?? 'Не удалось отправить сообщение';
        } else if (requestError?.status !== 401) {
            error.value = requestError?.message ?? 'Не удалось отправить сообщение';
        }
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Написать продавцу</DialogTitle>
                <DialogDescription v-if="listing">
                    {{ listing.title }} · {{ listing.domain }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="!session.isAuthenticated" class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">
                    Войдите, чтобы написать продавцу.
                </p>

                <div class="flex flex-wrap gap-2">
                    <Button type="button" :disabled="session.isLoggingIn" @click="login('vk')">
                        Войти через VK ID
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="session.isLoggingIn"
                        @click="login('yandex')"
                    >
                        Войти через Яндекс ID
                    </Button>
                </div>
            </div>

            <form v-else class="flex flex-col gap-2" novalidate @submit.prevent="submit">
                <Textarea
                    id="contact-message-body"
                    v-model="body"
                    rows="4"
                    :maxlength="MESSAGE_MAX"
                    placeholder="Здравствуйте! Интересует ваш сайт…"
                />

                <div class="flex items-center justify-between gap-2">
                    <span class="text-[11px] text-muted-foreground">
                        {{ remaining }} символов осталось
                    </span>

                    <DialogFooter class="flex-row justify-end">
                        <Button
                            type="button"
                            variant="ghost"
                            @click="emit('update:open', false)"
                        >
                            Отмена
                        </Button>
                        <Button type="submit" :disabled="!canSend">
                            {{ sending ? 'Отправляем…' : 'Отправить' }}
                        </Button>
                    </DialogFooter>
                </div>

                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>
            </form>
        </DialogContent>
    </Dialog>
</template>