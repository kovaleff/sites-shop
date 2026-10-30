import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import CabinetHiddenView from '@/views/cabinet/CabinetHiddenView.vue';
import { fetchMySites, restoreSite } from '@/services/cabinetService';

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
    { path: '/cabinet/hidden', name: 'cabinet-hidden', component: { template: '<div />' } },
    { path: '/cabinet/messages', name: 'cabinet-messages', component: { template: '<div />' } },
];

/**
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView() {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push('/cabinet/hidden');
    await router.isReady();

    const wrapper = mount(CabinetHiddenView, { global: { plugins: [pinia, router] } });

    await flushPromises();

    return wrapper;
}

describe('CabinetHiddenView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('запрашивает скрытые объявления', async () => {
        fetchMySites.mockResolvedValue({
            data: [],
            meta: { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null },
        });

        const wrapper = await mountView();

        expect(fetchMySites).toHaveBeenCalledWith({ page: 1, hidden: true });
        expect(wrapper.text()).toContain('Скрытых объявлений нет');
    });

    it('возвращает объявление в список', async () => {
        fetchMySites.mockResolvedValue({
            data: [
                {
                    id: 9,
                    title: 'Проданный лот',
                    domain: 'sold.ru',
                    status: 'sold',
                    statusLabel: 'Продан',
                    price: 0,
                    monthlyRevenue: 0,
                    hasTrademark: false,
                    verified: false,
                    verificationFilename: 'x.txt',
                    paidUntil: null,
                    needsRenewal: true,
                    isPubliclyVisible: false,
                    unreadMessages: 0,
                    categories: [],
                },
            ],
            meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
        });
        restoreSite.mockResolvedValue({ data: { id: 9 } });

        const wrapper = await mountView();

        const restoreButton = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Вернуть в список'));

        expect(restoreButton).toBeTruthy();

        await restoreButton.trigger('click');
        await flushPromises();

        expect(restoreSite).toHaveBeenCalledWith(9);
    });
});