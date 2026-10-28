import { createPinia, setActivePinia } from 'pinia';

import { countActiveFilters, createDefaultFilters } from '@/lib/catalogFilters';
import { fetchListings } from '@/services/catalogService';
import { useCatalogStore } from '@/stores/catalog';

vi.mock('@/services/catalogService', () => ({
    fetchListings: vi.fn(),
}));

/**
 * @param {object} [overrides]
 * @returns {{ items: object[], meta: object }}
 */
function createResult(overrides = {}) {
    return {
        items: [],
        meta: {
            page: 1,
            perPage: 25,
            total: 0,
            lastPage: 1,
            from: null,
            to: null,
        },
        ...overrides,
    };
}

/**
 * @returns {{ promise: Promise<object>, resolve: (value: object) => void }}
 */
function deferred() {
    let resolve;

    const promise = new Promise((resolver) => {
        resolve = resolver;
    });

    return { promise, resolve };
}

describe('catalog store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();

        fetchListings.mockImplementation(async (query = {}) =>
            createResult({
                meta: {
                    page: query.page ?? 1,
                    perPage: 25,
                    total: 60,
                    lastPage: 3,
                    from: 1,
                    to: 25,
                },
            }),
        );
    });

    it('сбрасывает страницу в 1 при применении фильтров', async () => {
        const store = useCatalogStore();
        store.page = 2;

        await store.applyFilters({
            ...createDefaultFilters(),
            priceMax: 500_000,
        });

        expect(store.page).toBe(1);
        expect(store.applied.priceMax).toBe(500_000);
        expect(fetchListings).toHaveBeenCalledWith(expect.objectContaining({ page: 1, priceMax: 500_000 }));
    });

    it('обнуляет счётчик активных фильтров при сбросе', async () => {
        const store = useCatalogStore();
        store.applied = {
            ...createDefaultFilters(),
            zones: ['ru'],
            verifiedOnly: true,
        };

        await store.resetFilters();

        expect(countActiveFilters(store.applied)).toBe(0);
        expect(countActiveFilters(store.draft)).toBe(0);
        expect(store.page).toBe(1);
    });

    it('игнорирует устаревший ответ', async () => {
        const store = useCatalogStore();
        const first = deferred();
        const second = deferred();

        fetchListings.mockImplementationOnce(() => first.promise).mockImplementationOnce(() => second.promise);

        const firstLoad = store.load();
        const secondLoad = store.load();

        second.resolve(
            createResult({
                items: [{ id: 'second' }],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            }),
        );

        await secondLoad;

        first.resolve(
            createResult({
                items: [{ id: 'first' }],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            }),
        );

        await firstLoad;

        expect(store.items.map((item) => item.id)).toEqual(['second']);
        expect(store.status).toBe('success');
    });

    it('переходит в success при пустой выдаче', async () => {
        const store = useCatalogStore();
        fetchListings.mockResolvedValueOnce(createResult());

        await store.load();

        expect(store.status).toBe('success');
        expect(store.total).toBe(0);
        expect(store.items).toEqual([]);
    });

    it('сохраняет фильтры и черновик при ошибке', async () => {
        const store = useCatalogStore();
        const applied = { ...createDefaultFilters(), priceMax: 300_000 };
        const draft = { ...createDefaultFilters(), priceMax: 400_000 };

        store.applied = applied;
        store.draft = draft;

        fetchListings.mockRejectedValueOnce(new Error('Сеть недоступна'));

        await store.load();

        expect(store.status).toBe('error');
        expect(store.error).toBe('Сеть недоступна');
        expect(store.applied).toMatchObject(applied);
        expect(store.draft).toMatchObject(draft);
    });

    it('клампит переход по страницам', async () => {
        const store = useCatalogStore();
        store.lastPage = 3;

        await store.goToPage(0);
        expect(store.page).toBe(1);

        await store.goToPage(999);
        expect(store.page).toBe(3);
    });

    it('возвращает outOfRange для страницы за пределами диапазона', async () => {
        const store = useCatalogStore();
        fetchListings.mockResolvedValueOnce(
            createResult({
                meta: { page: 999, perPage: 25, total: 60, lastPage: 3, from: null, to: null },
            }),
        );

        const result = await store.setPageFromUrl(999);

        expect(result).toEqual({ outOfRange: true, lastPage: 3 });
        expect(store.items).toEqual([]);
        expect(store.error).toBeNull();
    });

    it('не помечает корректную страницу как outOfRange', async () => {
        const store = useCatalogStore();

        const result = await store.setPageFromUrl(2);

        expect(result.outOfRange).toBe(false);
        expect(store.page).toBe(2);
    });
});
