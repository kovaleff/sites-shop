import { createRouter, createWebHistory } from 'vue-router';
import { toast } from 'vue-sonner';

import { useSessionStore } from '@/stores/session';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'catalog',
            component: () => import('@/views/CatalogView.vue'),
            meta: { navKey: 'catalog', title: 'Каталог готовых сайтов' },
        },
        {
            path: '/how-it-works',
            name: 'how-it-works',
            component: () => import('@/views/HowItWorksView.vue'),
            meta: { navKey: 'how-it-works', title: 'Как это работает' },
        },
        {
            path: '/pricing',
            name: 'pricing',
            component: () => import('@/views/PricingView.vue'),
            meta: { navKey: 'pricing', title: 'Тарифы' },
        },
        {
            path: '/cabinet',
            redirect: { name: 'cabinet-sites' },
        },
        {
            path: '/cabinet/sites',
            name: 'cabinet-sites',
            component: () => import('@/views/cabinet/CabinetSitesView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Мои объявления',
                requiresAuth: true,
            },
        },
        {
            path: '/cabinet/sites/new',
            name: 'cabinet-site-create',
            component: () => import('@/views/cabinet/CabinetSiteFormView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Новое объявление',
                requiresAuth: true,
            },
        },
        {
            path: '/cabinet/sites/:id/edit',
            name: 'cabinet-site-edit',
            component: () => import('@/views/cabinet/CabinetSiteFormView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Редактирование объявления',
                requiresAuth: true,
            },
        },
        {
            path: '/cabinet/hidden',
            name: 'cabinet-hidden',
            component: () => import('@/views/cabinet/CabinetHiddenView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Скрытые объявления',
                requiresAuth: true,
            },
        },
        {
            path: '/cabinet/messages',
            name: 'cabinet-messages',
            component: () => import('@/views/cabinet/CabinetMessagesView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Сообщения',
                requiresAuth: true,
            },
        },
        {
            path: '/cabinet/messages/:id',
            name: 'cabinet-conversation',
            component: () => import('@/views/cabinet/CabinetConversationView.vue'),
            meta: {
                navKey: 'cabinet',
                title: 'Диалог',
                requiresAuth: true,
            },
        },
        {
            path: '/:pathMatch(.*)*',
            name: 'not-found',
            component: () => import('@/views/NotFoundView.vue'),
            meta: { navKey: null, title: 'Страница не найдена' },
        },
    ],
    scrollBehavior(to, from, savedPosition) {
        return savedPosition ?? { top: 0 };
    },
});

router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth) {
        return true;
    }

    const session = useSessionStore();

    if (!session.isInitialized) {
        await session.initialize();
    }

    if (!session.isAuthenticated) {
        toast.error('Войдите, чтобы открыть личный кабинет');

        return { name: 'catalog' };
    }

    return true;
});

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Sites-shop` : 'Sites-shop';
});

export default router;