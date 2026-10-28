import { createRouter, createWebHistory } from 'vue-router';

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

router.afterEach((to) => {
    document.title = to.meta.title ? `${to.meta.title} — Sites-shop` : 'Sites-shop';
});

export default router;
