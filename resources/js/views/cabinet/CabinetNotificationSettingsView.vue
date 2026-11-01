<script setup>
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';

import CabinetLayout from '@/components/cabinet/CabinetLayout.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import {
    fetchNotificationSettings,
    updateNotificationSettings,
} from '@/services/notificationService';

const items = ref([]);
const email = ref(null);
const status = ref('idle');
const error = ref(null);
const saving = ref(null);

const isLoading = computed(() => status.value === 'idle' || status.value === 'loading');
const hasError = computed(() => status.value === 'error');

onMounted(load);

async function load() {
    status.value = 'loading';
    error.value = null;

    try {
        const payload = await fetchNotificationSettings();

        items.value = payload?.data ?? [];
        email.value = payload?.email ?? null;
        status.value = 'success';
    } catch (requestError) {
        error.value = requestError?.message ?? 'Не удалось загрузить настройки';
        status.value = 'error';
    }
}

/**
 * @param {object} item
 * @returns {Promise<void>}
 */
async function toggle(item) {
    const nextValue = !item.enabled;

    item.enabled = nextValue;
    saving.value = item.value;

    try {
        const payload = await updateNotificationSettings({ [item.value]: nextValue });

        items.value = payload?.data ?? items.value;
        email.value = payload?.email ?? null;
        toast.success(nextValue ? 'Уведомление включено' : 'Уведомление отключено');
    } catch (requestError) {
        item.enabled = !nextValue;
        toast.error(requestError?.message ?? 'Не удалось сохранить настройки');
    } finally {
        saving.value = null;
    }
}
</script>

<template>
    <CabinetLayout>
        <div>
            <h1 class="text-2xl font-extrabold text-foreground">Уведомления</h1>
            <p class="text-sm text-muted-foreground">
                Выберите, о чём присылать письма. Отключить любой тип можно и по ссылке
                из самого письма.
            </p>
        </div>

        <p v-if="email" class="text-sm text-muted-foreground">
            Письма приходят на <span class="font-medium text-foreground">{{ email }}</span>.
        </p>
        <p v-else-if="status === 'success'" class="text-sm text-muted-foreground">
            У аккаунта нет почты для писем: вход выполнен через VK или Яндекс без email.
            Такие уведомления не отправляются.
        </p>

        <div :aria-busy="isLoading ? 'true' : 'false'" class="flex flex-col gap-3">
            <template v-if="isLoading">
                <Skeleton v-for="index in 4" :key="index" class="h-20 w-full rounded-xl" />
            </template>

            <div
                v-else-if="hasError"
                class="flex flex-col items-start gap-2 rounded-xl border border-border p-6"
            >
                <p class="text-sm text-destructive">{{ error }}</p>
                <Button variant="outline" size="sm" @click="load">Повторить</Button>
            </div>

            <div v-else class="divide-y divide-border rounded-xl border border-border">
                <div
                    v-for="item in items"
                    :key="item.value"
                    class="flex items-start justify-between gap-4 p-4"
                >
                    <div class="flex flex-col gap-1">
                        <p class="text-sm font-medium text-foreground">{{ item.label }}</p>
                        <p class="text-sm text-muted-foreground">{{ item.description }}</p>
                    </div>
                    <Switch
                        :model-value="item.enabled"
                        :disabled="saving === item.value"
                        :aria-label="item.label"
                        @update:model-value="toggle(item)"
                    />
                </div>
            </div>
        </div>
    </CabinetLayout>
</template>
