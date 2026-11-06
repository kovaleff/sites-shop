<script setup>
import 'vue-sonner/style.css';

import { onMounted } from 'vue';
import { toast } from 'vue-sonner';

import { Toaster } from '@/components/ui/sonner';
import SiteFooter from '@/components/layout/SiteFooter.vue';
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
    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <SiteHeader />
        <main class="flex-1">
            <RouterView />
        </main>
        <SiteFooter />
        <Toaster theme="dark" position="top-center" :rich-colors="true" />
    </div>
</template>