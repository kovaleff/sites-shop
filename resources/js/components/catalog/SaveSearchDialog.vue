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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { serializeFiltersToPayload } from '@/lib/catalogFilters';
import { useSavedSearchesStore } from '@/stores/savedSearches';
import { useSessionStore } from '@/stores/session';

const props = defineProps({
    open: { type: Boolean, default: false },
    filters: { type: Object, required: true },
});

const emit = defineEmits(['update:open', 'saved']);

const session = useSessionStore();
const savedSearches = useSavedSearchesStore();

const name = ref('');
const frequency = ref('daily');
const error = ref('');
const sending = ref(false);

const canSubmit = computed(() => session.isAuthenticated && !sending.value);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return;
        }

        name.value = '';
        frequency.value = 'daily';
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
    error.value = '';
    sending.value = true;

    try {
        await savedSearches.create({
            name: name.value.trim() === '' ? null : name.value.trim(),
            frequency: frequency.value,
            filters: serializeFiltersToPayload(props.filters),
        });

        emit('saved');
        emit('update:open', false);
        toast.success('Подписка на фильтр сохранена');
    } catch (requestError) {
        error.value =
            requestError?.data?.errors?.['filters.price_min']?.[0]
            ?? requestError?.data?.errors?.frequency?.[0]
            ?? requestError?.message
            ?? 'Не удалось сохранить подписку';
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Подписаться на фильтр</DialogTitle>
                <DialogDescription>
                    Мы пришлём письмо, когда в каталоге появятся новые лоты,
                    подходящие под текущие фильтры.
                </DialogDescription>
            </DialogHeader>

            <div v-if="!session.isAuthenticated" class="flex flex-col gap-3">
                <p class="text-sm text-muted-foreground">
                    Войдите, чтобы сохранить фильтр и получать уведомления.
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
                    <Label for="saved-search-name">Название (необязательно)</Label>
                    <Input
                        id="saved-search-name"
                        v-model="name"
                        maxlength="100"
                        placeholder="Например, сайты до 500 000 ₽"
                    />
                </div>

                <div class="flex flex-col gap-1.5">
                    <Label for="saved-search-frequency">Как часто присылать</Label>
                    <select
                        id="saved-search-frequency"
                        v-model="frequency"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="daily">Раз в день</option>
                        <option value="weekly">Раз в неделю</option>
                    </select>
                </div>

                <p v-if="error" class="text-xs text-destructive">{{ error }}</p>

                <DialogFooter class="flex-row justify-end">
                    <Button type="button" variant="ghost" @click="emit('update:open', false)">
                        Отмена
                    </Button>
                    <Button type="submit" :disabled="!canSubmit">
                        {{ sending ? 'Сохраняем…' : 'Сохранить подписку' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
