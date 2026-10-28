import { fetchListings, fetchZones } from '@/services/catalogService';
import { listings } from '@/mocks/listings';
import { MOCK_SCENARIO_KEY } from '@/mocks/session';

const publicListings = listings.filter((listing) => listing.isPublic && listing.status === 'active');

/**
 * Собирает все страницы выдачи параллельно (сервис фиксирует размер страницы).
 *
 * @param {object} [overrides]
 * @returns {Promise<{ items: object[], meta: object }>}
 */
async function fetchAll(overrides = {}) {
    const results = await Promise.all(
        [1, 2, 3, 4].map((page) => fetchListings({ ...overrides, page })),
    );

    return {
        items: results.flatMap((result) => result.items),
        meta: results[0].meta,
    };
}

describe('catalogService.fetchZones', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('строит зоны по публичным лотам', async () => {
        const zones = await fetchZones();

        expect(zones).toEqual([
            { value: 'ru', label: '.ru', count: 24 },
            { value: 'com', label: '.com', count: 21 },
            { value: 'de', label: '.de', count: 12 },
        ]);
    });

    it('не включает зоны скрытых лотов и null-зону', async () => {
        const zones = await fetchZones();
        const total = zones.reduce((sum, zone) => sum + zone.count, 0);

        expect(total).toBe(57);
        expect(zones.map((zone) => zone.value)).not.toContain(null);
    });
});

describe('catalogService.fetchListings', () => {
    beforeEach(() => {
        window.localStorage.clear();
    });

    it('отдаёт только публичные активные лоты', async () => {
        const result = await fetchAll();

        expect(result.meta.total).toBe(60);
        expect(result.items).toHaveLength(60);
        expect(result.items.every((item) => item.status === 'active')).toBe(true);
        expect(result.items.every((item) => !('isPublic' in item))).toBe(true);
    });

    it('соблюдает границы цены включительно', async () => {
        const result = await fetchAll({ priceMin: 0, priceMax: 0 });

        expect(result.meta.total).toBe(1);
        expect(result.items[0].price).toBe(0);
    });

    it('трактует верхнюю границу цены как «и выше»', async () => {
        const result = await fetchAll({ priceMin: 0, priceMax: 10_000_000 });

        expect(result.items.some((item) => item.price > 10_000_000)).toBe(true);
        expect(result.meta.total).toBe(60);
    });

    it('трактует верхнюю границу дохода как «и выше»', async () => {
        const result = await fetchAll({ incomeMax: 1_000_000 });

        expect(result.items.some((item) => item.monthlyIncome > 1_000_000)).toBe(true);
    });

    it('фильтрует зоны по логике «ИЛИ»', async () => {
        const result = await fetchAll({ zones: ['ru', 'com'] });

        expect(result.meta.total).toBe(45);
        expect(result.items.every((item) => ['ru', 'com'].includes(item.domainZone))).toBe(true);
    });

    it('показывает лоты без зоны только при пустом выборе', async () => {
        const allZones = await fetchAll();
        const selectedZones = await fetchAll({ zones: ['ru', 'com', 'de'] });

        expect(allZones.items.some((item) => item.domainZone === null)).toBe(true);
        expect(selectedZones.items.some((item) => item.domainZone === null)).toBe(false);
        expect(selectedZones.meta.total).toBe(57);
    });

    it('фильтрует проверенные лоты', async () => {
        const result = await fetchAll({ verifiedOnly: true });

        expect(result.meta.total).toBe(38);
        expect(result.items.every((item) => item.isVerified === true)).toBe(true);
    });

    it('сортирует по дате публикации по убыванию', async () => {
        const result = await fetchAll();
        const dates = result.items.map((item) => item.publishedAt);

        expect(dates).toEqual([...dates].sort().reverse());
    });

    it('отдаёт не более 25 лотов на страницу без дублей', async () => {
        const first = await fetchListings({ page: 1 });
        const second = await fetchListings({ page: 2 });
        const third = await fetchListings({ page: 3 });

        expect(first.items).toHaveLength(25);
        expect(second.items).toHaveLength(25);
        expect(third.items).toHaveLength(10);

        const ids = new Set([...first.items, ...second.items, ...third.items].map((item) => item.id));

        expect(ids.size).toBe(60);
        expect(first.meta).toMatchObject({ page: 1, perPage: 25, total: 60, lastPage: 3, from: 1, to: 25 });
        expect(third.meta).toMatchObject({ page: 3, from: 51, to: 60 });
    });

    it('возвращает пустую страницу за пределами диапазона без ошибки', async () => {
        const result = await fetchListings({ page: 999 });

        expect(result.items).toEqual([]);
        expect(result.meta.total).toBe(60);
        expect(result.meta.lastPage).toBe(3);
        expect(result.meta.from).toBeNull();
        expect(result.meta.to).toBeNull();
    });

    it('поддерживает сценарий empty', async () => {
        window.localStorage.setItem(MOCK_SCENARIO_KEY, 'empty');

        const result = await fetchListings({ page: 2 });

        expect(result.items).toEqual([]);
        expect(result.meta.total).toBe(0);
        expect(result.meta.lastPage).toBe(1);
    });

    it('поддерживает сценарий error', async () => {
        window.localStorage.setItem(MOCK_SCENARIO_KEY, 'error');

        await expect(fetchListings()).rejects.toThrow('Не удалось загрузить каталог');
    });

    it('возвращает копии и не мутирует фикстуры', async () => {
        const first = await fetchListings();
        const originalTitle = publicListings[0].title;

        first.items[0].title = 'Изменено в тесте';

        const second = await fetchListings();

        expect(publicListings[0].title).toBe(originalTitle);
        expect(second.items[0].title).toBe(originalTitle);
    });
});
