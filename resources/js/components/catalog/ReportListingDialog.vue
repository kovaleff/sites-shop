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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { REPORT_REASONS, reportSite } from '@/services/reportService';
import { useSessionStore } from '@/stores/session';

const props = defineProps({
    open: { type: Boolean, default: false },
    listing: { type: Object, default: null },
});

const emit = defineEmits(['update:open']);

const session = useSessionStore();

const reason = ref(REPORT_REASONS[0].value);
const comment = ref('');
const error = ref('');
const sending = ref(false);

const canSend = computed(() => session.isAuthenticated && !sending.value && reason.value !== '');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        reason.value = REPORT_REASONS[0].value;
        comment.value = '';
        error.value = '';
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
        await reportSite(props.listing.id, { reason: reason.value, comment: comment.value });
        emit('update:open', false);
        toast.success('Жалоба отправлена. Спасибо за помощь!');
    } catch (requestError) {
        if (requestError?.status === 422) {
            error.value =
                requestError?.data?.errors?.site?.[0]
                ?? requestError?.data?.errors?.reason?.[0]
                ?? requestError?.message
                ?? 'Не удалось отправить жалобу';
        } else if (requestError?.status !== 401) {
            error.value = requestError?.message ?? 'Не удалось отправить жалобу';
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
                <DialogTitle>Пожаловаться на объявление</DialogTitle>
                <DialogDescription v-if="listing">
                    {{ listing.title }} · {{ listing.domain }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="!session.isAuthenticated" class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">
                    Войдите, чтобы отправить жалобу.
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

            <form v-else class="flex flex-col gap-3" novalidate @submit.prevent="submit">
                <div class="flex flex-col gap-1.5">
                    <Label for="report-reason">Причина</Label>
                    <select
                        id="report-reason"
                        v-model="reason"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option v-for="item in REPORT_REASONS" :key="item.value" :value="item.value">
                            {{ item.label }}
                        </option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="report-comment">Комментарий</Label>
                    <Textarea
                        id="report-comment"
                        v-model="comment"
                        rows="3"
                        maxlength="1000"
                        placeholder="Что не так с объявлением?"
                    />
                </div>

                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>

                <DialogFooter class="flex-row justify-end">
                    <Button type="button" variant="ghost" @click="emit('update:open', false)">
                        Отмена
                    </Button>
                    <Button type="submit" :disabled="!canSend">
                        {{ sending ? 'Отправляем…' : 'Отправить жалобу' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
