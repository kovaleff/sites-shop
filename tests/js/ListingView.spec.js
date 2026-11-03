import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import ListingView from '@/views/ListingView.vue';
import { fetchListing, fetchSimilarListings } from '@/services/catalogService';

vi.mock('@/services/catalogService', () => ({
    fetchListing: vi.fn(),
    fetchSimilarListings: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/site/:slug', name: 'listing', component: ListingView },
];

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function listingFixture(overrides = {}) {
    return {
        id: 1,
        slug: '1-osnovnoi-lot',
        url: '/site/1-osnovnoi-lot',
        title: 'Основной лот',
        domain: 'osnovnoi.ru',
        domainZone: 'ru',
        description: 'Описание основного лота.',
        price: 500_000,
        monthlyIncome: 40_000,
        paybackMonths: 13,
        hasTrademark: false,
        status: 'active',
        statusLabel: 'Активен',
        isAvailable: true,
        isFavorite: false,
        isVerified: true,
        faviconUrl: null,
        categories: [],
        seller: null,
        publishedAt: '2026-10-21T09:30:00Z',
        ...overrides,
    };
}

/**
 * @param {string} slug
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView(slug = '1-osnovnoi-lot') {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push(`/site/${slug}`);
    await router.isReady();

    const wrapper = mount(ListingView, {
        global: { plugins: [pinia, router] },
    });

    await flushPromises();

    return wrapper;
}

describe('ListingView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        delete window.__INITIAL_STATE__;
    });

    it('гидрирует лот и похожие лоты из серверного состояния', async () => {
        window.__INITIAL_STATE__ = {
            listing: listingFixture(),
            similar: [
                listingFixture({
                    id: 2,
                    slug: '2-pokhozhii-lot',
                    title: 'Похожий лот',
                    domain: 'pokhozhii.ru',
                }),
            ],
        };

        const wrapper = await mountView();

        expect(fetchListing).not.toHaveBeenCalled();
        expect(fetchSimilarListings).not.toHaveBeenCalled();
        expect(wrapper.text()).toContain('Основной лот');
        expect(wrapper.text()).toContain('Похожие лоты');
        expect(wrapper.text()).toContain('Похожий лот');
        expect(wrapper.find('a[href="/site/2-pokhozhii-lot"]').exists()).toBe(true);
    });

    it('загружает лот и похожие лоты по API без серверного состояния', async () => {
        fetchListing.mockResolvedValue(listingFixture());
        fetchSimilarListings.mockResolvedValue([
            listingFixture({ id: 2, slug: '2-pokhozhii-lot', title: 'Похожий лот' }),
        ]);

        const wrapper = await mountView();

        expect(fetchListing).toHaveBeenCalledWith('1-osnovnoi-lot');
        expect(fetchSimilarListings).toHaveBeenCalledWith('1-osnovnoi-lot');
        expect(wrapper.text()).toContain('Основной лот');
        expect(wrapper.text()).toContain('Похожий лот');
    });

    it('не показывает блок, если похожих лотов нет', async () => {
        fetchListing.mockResolvedValue(listingFixture());
        fetchSimilarListings.mockResolvedValue([]);

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Основной лот');
        expect(wrapper.text()).not.toContain('Похожие лоты');
    });

    it('не ломает карточку, если похожие лоты не загрузились', async () => {
        fetchListing.mockResolvedValue(listingFixture());
        fetchSimilarListings.mockRejectedValue(new Error('Ошибка'));

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Основной лот');
        expect(wrapper.text()).not.toContain('Похожие лоты');
    });

    it('перезагружает данные при переходе на другой лот', async () => {
        window.__INITIAL_STATE__ = { listing: listingFixture() };

        fetchListing.mockResolvedValue(
            listingFixture({
                id: 2,
                slug: '2-drugoi-lot',
                title: 'Другой лот',
                url: '/site/2-drugoi-lot',
            }),
        );
        fetchSimilarListings.mockResolvedValue([]);

        const wrapper = await mountView();

        await wrapper.vm.$router.push('/site/2-drugoi-lot');
        await flushPromises();

        expect(fetchListing).toHaveBeenCalledWith('2-drugoi-lot');
        expect(wrapper.text()).toContain('Другой лот');
    });
});
