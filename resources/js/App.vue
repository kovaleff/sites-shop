<script setup>
import 'vue-sonner/style.css';

import { onMounted } from 'vue';
import { toast } from 'vue-sonner';

import { Toaster } from '@/components/ui/sonner';
import SiteHeader from '@/components/layout/SiteHeader.vue';
import { useAuth } from '@/composables/useAuth';

const { handleReturn } = useAuth();

const RETURN_PARAMS = ['code', 'state', 'device_id', 'error', 'error_description', 'auth_error'];

/**
 * Убрать служебные параметры возврата, сохранив остальной query и hash.
 *
 * @returns {void}
 */
function cleanReturnParams() {
    const url = new URL(window.location.href);
    let changed = false;

    RETURN_PARAMS.forEach((param) => {
        if (url.searchParams.has(param)) {
            url.searchParams.delete(param);
            changed = true;
        }
    });

    if (changed) {
        const query = url.searchParams.toString();

        window.history.replaceState({}, '', `${url.pathname}${query ? `?${query}` : ''}${url.hash}`);
    }
}

onMounted(async () => {
    const { error } = await handleReturn();

    cleanReturnParams();

    if (error) {
        toast.error(error);
    }
});
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <SiteHeader />
        <RouterView />
        <Toaster theme="dark" position="top-center" :rich-colors="true" />
    </div>
</template>