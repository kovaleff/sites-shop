import { fetchListings, fetchZones } from '@/services/catalogService';

/**
 * @param {object} payload
 * @param {number} [status]
 * @returns {object}
 */
function jsonResponse(payload, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        text: async () => JSON.stringify(payload),
    };
}

/**
 * @returns {object}
 */
function meta(overrides = {}) {
    return {
        page: 1,
        perPage: 25,
        total: 0,
        lastPage: 1,
        from: null,
        to: null,
        ...overrides,
    };
}

describe('catalogService.fetchListings', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('запрашивает каталог и отдаёт items с meta', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            jsonResponse({
                items: [{ id: 1, title: 'Лот' }],
                meta: meta({ total: 1, from: 1, to: 1 }),
            }),
        );

        vi.stubGlobal('fetch', fetchMock);

        const result = await fetchListings({ page: 2 });

        expect(result.items).toHaveLength(1);
        expect(result.items[0].id).toBe(1);
        expect(result.meta.total).toBe(1);

        const requested = String(fetchMock.mock.calls[0][0]);
        expect(requested).toContain('/api/v1/catalog?');
        expect(requested).toContain('page=2');
        expect(requested).toContain('sort=newest');
    });

    it('передаёт фильтры в snake_case и нормализует зоны', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ items: [], meta: meta() }));

        vi.stubGlobal('fetch', fetchMock);

        await fetchListings({
            priceMin: 1000,
            priceMax: 5000,
            incomeMin: 100,
            incomeMax: 900,
            zones: ['.RU', 'com'],
            verifiedOnly: true,
        });

        const requested = String(fetchMock.mock.calls[0][0]);
        expect(requested).toContain('price_min=1000');
        expect(requested).toContain('price_max=5000');
        expect(requested).toContain('income_min=100');
        expect(requested).toContain('income_max=900');
        expect(requested).toContain('zones=ru%2Ccom');
        expect(requested).toContain('verified=1');
    });

    it('подставляет значения по умолчанию при некорректных параметрах', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ items: [], meta: meta() }));

        vi.stubGlobal('fetch', fetchMock);

        await fetchListings({ priceMin: 'abc', page: 0, zones: 'ru' });

        const requested = String(fetchMock.mock.calls[0][0]);
        expect(requested).toContain('price_min=0');
        expect(requested).toContain('page=1');
        expect(requested).not.toContain('zones=');
    });

    it('возвращает пустой результат при неполном ответе', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({})));

        const result = await fetchListings();

        expect(result.items).toEqual([]);
        expect(result.meta.total).toBe(0);
    });

    it('пробрасывает сообщение об ошибке сервера', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(jsonResponse({ message: 'Не удалось загрузить каталог' }, 500)),
        );

        await expect(fetchListings()).rejects.toThrow('Не удалось загрузить каталог');
    });
});

describe('catalogService.fetchZones', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('отдаёт зоны из API', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                jsonResponse({
                    items: [
                        { value: 'ru', label: '.ru', count: 10 },
                        { value: 'com', label: '.com', count: 5 },
                    ],
                }),
            ),
        );

        const zones = await fetchZones();

        expect(zones).toEqual([
            { value: 'ru', label: '.ru', count: 10 },
            { value: 'com', label: '.com', count: 5 },
        ]);
    });

    it('возвращает пустой список при ошибке формата', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse({})));

        await expect(fetchZones()).resolves.toEqual([]);
    });
});