import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import SellerView from '@/views/SellerView.vue';
import { fetchSeller } from '@/services/sellerService';

vi.mock('@/services/sellerService', () => ({
    fetchSeller: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/site/:slug', name: 'listing', component: { template: '<div />' } },
    { path: '/seller/:id', name: 'seller', component: { template: '<div />' } },
];

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function sellerFixture(overrides = {}) {
    return {
        id: 7,
        displayName: 'Иван Петров',
        avatarUrl: null,
        bio: 'Продаю сайты с 2015 года.',
        city: 'Москва',
        registeredAt: '2024-03-01T10:00:00+00:00',
        isVerifiedSeller: true,
        publishedSitesCount: 2,
        soldSitesCount: 3,
        profileUrl: '/seller/7',
        ...overrides,
    };
}

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function listingFixture(overrides = {}) {
    return {
        id: 15,
        slug: '15-magazin',
        url: '/site/15-magazin',
        title: 'Магазин товаров для дома',
        domain: 'dom-shop.ru',
        domainZone: 'ru',
        description: 'Действующий проект.',
        price: 500_000,
        monthlyIncome: 40_000,
        status: 'active',
        isVerified: true,
        faviconUrl: null,
        publishedAt: '2026-10-21T09:30:00Z',
        seller: { id: 7, displayName: 'Иван Петров', avatarUrl: null, profileUrl: '/seller/7' },
        ...overrides,
    };
}

/**
 * @param {object} [payload]
 * @param {Error | null} [failure]
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView(payload = {}, failure = null) {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push('/seller/7');
    await router.isReady();

    if (failure) {
        fetchSeller.mockRejectedValue(failure);
    } else {
        fetchSeller.mockResolvedValue({
            seller: sellerFixture(),
            listings: [listingFixture()],
            meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            ...payload,
        });
    }

    const wrapper = mount(SellerView, {
        global: { plugins: [pinia, router] },
    });

    await flushPromises();

    return wrapper;
}

describe('SellerView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.__INITIAL_STATE__;
    });

    it('показывает профиль продавца, статистику и лоты', async () => {
        const wrapper = await mountView();

        expect(fetchSeller).toHaveBeenCalledWith(7, 1);
        expect(wrapper.text()).toContain('Иван Петров');
        expect(wrapper.text()).toContain('Проверенный продавец');
        expect(wrapper.text()).toContain('с 01.03.2024');
        expect(wrapper.text()).toContain('Москва');
        expect(wrapper.text()).toContain('Продаю сайты с 2015 года.');
        expect(wrapper.text()).toContain('Опубликовано');
        expect(wrapper.text()).toContain('Продано');
        expect(wrapper.text()).toContain('Магазин товаров для дома');
    });

    it('скрывает бейдж, если домены не подтверждены', async () => {
        const wrapper = await mountView({
            seller: sellerFixture({ isVerifiedSeller: false, bio: null, city: null }),
        });

        expect(wrapper.text()).not.toContain('Проверенный продавец');
        expect(wrapper.text()).not.toContain('Москва');
    });

    it('показывает пустое состояние без лотов', async () => {
        const wrapper = await mountView({
            listings: [],
            meta: { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null },
        });

        expect(wrapper.text()).toContain('У продавца пока нет опубликованных лотов');
    });

    it('гидрирует состояние из серверного HTML без запроса к API', async () => {
        window.__INITIAL_STATE__ = {
            seller: {
                data: sellerFixture(),
                listings: [listingFixture()],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            },
        };

        const wrapper = await mountView();

        expect(fetchSeller).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Иван Петров');
        expect(window.__INITIAL_STATE__.seller).toBeUndefined();
    });

    it('показывает ошибку для неизвестного продавца', async () => {
        const wrapper = await mountView(
            {},
            Object.assign(new Error('Not Found'), { status: 404 }),
        );

        expect(wrapper.text()).toContain('Продавец не найден');
    });
});
