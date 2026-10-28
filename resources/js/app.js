import { createApp } from 'vue';
import { createPinia } from 'pinia';

import App from '@/App.vue';
import router from '@/router';
import { useSessionStore } from '@/stores/session';

const pinia = createPinia();
const app = createApp(App).use(pinia).use(router);

const session = useSessionStore(pinia);

session.initialize().finally(() => {
    app.mount('#app');
});
