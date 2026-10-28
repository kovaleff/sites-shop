import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { defineComponent } from 'vue';
import { createMemoryHistory, createRouter } from 'vue-router';
import { flushPromises } from '@vue/test-utils';

import { useCatalogQuery } from '@/composables/useCatalogQuery';
import { createDefaultFilters } from '@/lib/catalogFilters';
import { fetchListings } from '@/services/catalogService';
import { useCatalogStore } from '@/stores/catalog';

vi.mock('@/services/catalogService', () => ({
    fetchListings: vi.fn(),
    fetchZones: vi.fn(),
}));

const DummyView = { template: '<div />' };

/**
 * @param {string} initialPath
 */
async function setup(initialPath = '/') {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({
        history: createMemoryHistory(),
        routes: [
            { path: '/', name: 'catalog', component: DummyView, meta: { navKey: 'catalog' } },
            { path: '/pricing', name: 'pricing', component: DummyView, meta: { navKey: 'pricing' } },
        ],
    });

    await router.push(initialPath);
    await router.isReady();

    let api;

    const Harness = defineComponent({
        setup() {
            api = useCatalogQuery(() => ['ru', 'com', 'de']);

            return () => null;
        },
    });

    mount(Harness, { global: { plugins: [pinia, router] } });

    return {
        api,
        router,
        catalog: useCatalogStore(pinia),
    };
}

describe('useCatalogQuery', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        fetchListings.mockImplementation(async (query = {}) => {
            const page = Number(query.page ?? 1);
            const total = 60;
            const lastPage = 3;
            const items = page > lastPage ? [] : [{ id: `page-${page}` }];

            return {
                items,
                meta: {
                    page,
                    perPage: 25,
                    total,
                    lastPage,
                    from: items.length ? 1 : null,
                    to: items.length ? 1 : null,
                },
            };
        });
    });

    it('разбирает query в стор и игнорирует неизвестные параметры', async () => {
        const { api, catalog } = await setup('/?price_min=50000&utm_source=test&page=2');

        await api.hydrateFromRoute();

        expect(catalog.applied.priceMin).toBe(50_000);
        expect(catalog.applied).not.toHaveProperty('utm_source');
        expect(catalog.page).toBe(2);
    });

    it('пишет нормализованный query через push', async () => {
        const { api, router } = await setup('/');

        await api.pushFilters({ ...createDefaultFilters(), priceMax: 500_000, zones: ['com', 'ru'] }, 2);
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({
            price_max: '500000',
            zones: 'ru,com',
            page: '2',
        });
    });

    it('очищает query при сбросе фильтров', async () => {
        const { api, router } = await setup('/?price_max=500000');

        await api.clearFilters();
        await flushPromises();

        expect(router.currentRoute.value.query).toEqual({});
    });

    it('заменяет страницу через replace', async () => {
        const { api, router } = await setup('/');

        const replaceSpy = vi.spyOn(router, 'replace');

        await api.replacePage(3, { ...createDefaultFilters(), zones: ['ru'] });
        await flushPromises();

        expect(replaceSpy).toHaveBeenCalledTimes(1);
        expect(router.currentRoute.value.query).toEqual({ zones: 'ru', page: '3' });
    });

    it('гидрирует состояние при изменении route.query и не дублирует запрос', async () => {
        const { api, router, catalog } = await setup('/');

        await api.hydrateFromRoute();
        await flushPromises();
        fetchListings.mockClear();

        await router.push({ name: 'catalog', query: { price_min: '100000' } });
        await flushPromises();

        expect(catalog.applied.priceMin).toBe(100_000);
        expect(fetchListings).toHaveBeenCalledTimes(1);

        await router.push({ name: 'catalog', query: { price_min: '100000', utm_source: 'x' } });
        await flushPromises();

        expect(fetchListings).toHaveBeenCalledTimes(1);
        expect(catalog.applied.priceMin).toBe(100_000);
    });

    it('заменяет page=999 на последнюю страницу без ошибки', async () => {
        const { api, router, catalog } = await setup('/?page=999');

        const result = await api.hydrateFromRoute();
        await flushPromises();

        expect(result.outOfRange).toBe(true);
        expect(router.currentRoute.value.query.page).toBe('3');
        expect(catalog.page).toBe(3);
        expect(catalog.status).toBe('success');
        expect(catalog.error).toBeNull();
    });

    it('сохраняет query и стор при переходе в заглушку и обратно', async () => {
        const { api, router, catalog } = await setup('/?price_min=50000&zones=ru');

        await api.hydrateFromRoute();
        await flushPromises();
        const callsAfterHydrate = fetchListings.mock.calls.length;

        await router.push({ name: 'pricing', query: router.currentRoute.value.query });
        await flushPromises();

        expect(catalog.applied.priceMin).toBe(50_000);
        expect(catalog.applied.zones).toEqual(['ru']);

        await router.push({ name: 'catalog', query: { price_min: '50000', zones: 'ru' } });
        await flushPromises();

        expect(catalog.applied.priceMin).toBe(50_000);
        expect(catalog.applied.zones).toEqual(['ru']);
        expect(fetchListings.mock.calls.length).toBe(callsAfterHydrate);
    });
});
