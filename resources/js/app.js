import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { watch } from 'vue';
import { toast } from 'vue-sonner';

import App from '@/App.vue';
import router from '@/router';
import { setUnauthorizedHandler } from '@/services/http';
import { useSessionStore } from '@/stores/session';

const pinia = createPinia();
const app = createApp(App).use(pinia).use(router);

const session = useSessionStore(pinia);

setUnauthorizedHandler(() => {
    session.handleUnauthorized();

    if (router.currentRoute.value.meta.requiresAuth) {
        toast.error('Войдите снова');
        router.push({ name: 'catalog' });
    }
});

// Счётчик непрочитанных обновляется при переходах, без живого опроса (FR-032).
let lastPath = router.currentRoute.value.fullPath;

watch(
    () => router.currentRoute.value.fullPath,
    (path) => {
        if (path === lastPath) {
            return;
        }

        lastPath = path;

        if (session.isAuthenticated) {
            session.refreshUnread();
        }
    },
);

session.initialize().finally(() => {
    app.mount('#app');
});