import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import CabinetSitesView from '@/views/cabinet/CabinetSitesView.vue';
import { fetchMySites } from '@/services/cabinetService';

vi.mock('@/services/cabinetService', () => ({
    fetchMySites: vi.fn(),
    fetchSite: vi.fn(),
    createSite: vi.fn(),
    updateSite: vi.fn(),
    changeSiteStatus: vi.fn(),
    hideSite: vi.fn(),
    restoreSite: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/cabinet/sites', name: 'cabinet-sites', component: { template: '<div />' } },
    { path: '/cabinet/sites/new', name: 'cabinet-site-create', component: { template: '<div />' } },
    { path: '/cabinet/sites/:id/edit', name: 'cabinet-site-edit', component: { template: '<div />' } },
    { path: '/cabinet/hidden', name: 'cabinet-hidden', component: { template: '<div />' } },
    { path: '/cabinet/messages', name: 'cabinet-messages', component: { template: '<div />' } },
    { path: '/cabinet/messages/:id', name: 'cabinet-conversation', component: { template: '<div />' } },
    { path: '/cabinet/profile', name: 'cabinet-profile', component: { template: '<div />' } },
    {
        path: '/cabinet/settings/notifications',
        name: 'cabinet-notification-settings',
        component: { template: '<div />' },
    },
];

/**
 * @returns {Promise<import('vue-router').Router>}
 */
async function createTestRouter() {
    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push('/cabinet/sites');
    await router.isReady();

    return router;
}

/**
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView() {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = await createTestRouter();

    const wrapper = mount(CabinetSitesView, {
        global: { plugins: [pinia, router] },
    });

    await flushPromises();

    return wrapper;
}

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function listPayload(overrides = {}) {
    return {
        data: [],
        meta: { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null },
        ...overrides,
    };
}

describe('CabinetSitesView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('показывает пустое состояние с призывом создать объявление', async () => {
        fetchMySites.mockResolvedValue(listPayload());

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('У вас пока нет объявлений');
        expect(wrapper.text()).toContain('Добавить объявление');
    });

    it('показывает ошибку и кнопку повтора', async () => {
        fetchMySites.mockRejectedValue(new Error('Сеть недоступна'));

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Сеть недоступна');
        expect(wrapper.text()).toContain('Повторить');
    });

    it('показывает карточки объявлений с плашками', async () => {
        fetchMySites.mockResolvedValue(
            listPayload({
                data: [
                    {
                        id: 5,
                        title: 'Магазин товаров',
                        domain: 'shop.ru',
                        domainZone: 'ru',
                        status: 'active',
                        statusLabel: 'Активен',
                        price: 1000,
                        monthlyRevenue: 100,
                        hasTrademark: false,
                        verified: false,
                        verificationFilename: 'abc.txt',
                        paidUntil: null,
                        needsRenewal: true,
                        isPubliclyVisible: false,
                        unreadMessages: 2,
                        categories: [],
                    },
                ],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            }),
        );

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Магазин товаров');
        expect(wrapper.text()).toContain('Требует продления');
        expect(wrapper.text()).toContain('Скрыто из каталога');
        expect(wrapper.text()).toContain('Сообщения: 2');
    });

    it('показывает причину снятия объявления с публикации', async () => {
        const reason =
            'Подписка истекла 01.11.2026. Объявление снято с публикации — продлите подписку и включите его снова.';

        fetchMySites.mockResolvedValue(
            listPayload({
                data: [
                    {
                        id: 6,
                        title: 'Старый магазин',
                        domain: 'old-shop.ru',
                        domainZone: 'ru',
                        status: 'inactive',
                        statusLabel: 'Неактивен',
                        moderationStatus: 'approved',
                        moderationStatusLabel: 'Одобрено',
                        moderationNote: null,
                        unpublishReason: reason,
                        price: 1000,
                        monthlyRevenue: 100,
                        hasTrademark: false,
                        verified: true,
                        verificationFilename: 'abc.txt',
                        paidUntil: '2026-11-01',
                        needsRenewal: true,
                        isPubliclyVisible: false,
                        unreadMessages: 0,
                        categories: [],
                    },
                ],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            }),
        );

        const wrapper = await mountView();

        expect(wrapper.text()).toContain(reason);
    });
});
