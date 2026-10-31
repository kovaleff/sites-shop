import {
    countActiveFilters,
    createDefaultFilters,
    normalizeFilters,
    normalizePage,
    parseFiltersFromQuery,
    serializeFiltersToQuery,
} from '@/lib/catalogFilters';

const ZONES = ['ru', 'com', 'de'];
const CATEGORIES = ['avto-moto', 'biznes-i-finansy', 'kompyutery-i-it'];

describe('createDefaultFilters', () => {
    it('возвращает значения по умолчанию', () => {
        expect(createDefaultFilters()).toEqual({
            priceMin: 0,
            priceMax: 10_000_000,
            incomeMin: 0,
            incomeMax: 1_000_000,
            zones: [],
            categories: [],
            verifiedOnly: false,
        });
    });
});

describe('parseFiltersFromQuery', () => {
    it('трактует нечисловую минимальную цену как 0', () => {
        expect(parseFiltersFromQuery({ price_min: 'abc' }, ZONES).priceMin).toBe(0);
    });

    it('трактует отрицательную минимальную цену как 0', () => {
        expect(parseFiltersFromQuery({ price_min: '-5' }, ZONES).priceMin).toBe(0);
    });

    it('ограничивает максимальную цену верхней границей', () => {
        expect(parseFiltersFromQuery({ price_max: '99999999' }, ZONES).priceMax).toBe(10_000_000);
    });

    it('сбрасывает обе границы цены, если минимум больше максимума', () => {
        const filters = parseFiltersFromQuery({ price_min: '2000000', price_max: '100000' }, ZONES);

        expect(filters.priceMin).toBe(0);
        expect(filters.priceMax).toBe(10_000_000);
    });

    it('отбрасывает неизвестные зоны', () => {
        expect(parseFiltersFromQuery({ zones: 'ru,xx' }, ZONES).zones).toEqual(['ru']);
    });

    it('трактует пустую строку зон как «Все зоны»', () => {
        expect(parseFiltersFromQuery({ zones: '' }, ZONES).zones).toEqual([]);
    });

    it('нормализует порядок зон', () => {
        expect(parseFiltersFromQuery({ zones: 'de,com,ru' }, ZONES).zones).toEqual(['ru', 'com', 'de']);
    });

    it('разбирает тематики из строки через запятую', () => {
        expect(
            parseFiltersFromQuery({ categories: 'kompyutery-i-it,avto-moto' }, ZONES, CATEGORIES)
                .categories,
        ).toEqual(['avto-moto', 'kompyutery-i-it']);
    });

    it('отбрасывает неизвестные тематики при известном справочнике', () => {
        expect(
            parseFiltersFromQuery({ categories: 'avto-moto,unknown' }, ZONES, CATEGORIES).categories,
        ).toEqual(['avto-moto']);
    });

    it('сохраняет тематики из URL, пока справочник не загружен', () => {
        expect(parseFiltersFromQuery({ categories: 'avto-moto,unknown' }, ZONES).categories).toEqual([
            'avto-moto',
            'unknown',
        ]);
    });

    it('трактует пустую строку тематик как «Все тематики»', () => {
        expect(parseFiltersFromQuery({ categories: '' }, ZONES, CATEGORIES).categories).toEqual([]);
    });

    it('включает проверку только для verified=1', () => {
        expect(parseFiltersFromQuery({ verified: '0' }, ZONES).verifiedOnly).toBe(false);
        expect(parseFiltersFromQuery({ verified: 'yes' }, ZONES).verifiedOnly).toBe(false);
        expect(parseFiltersFromQuery({ verified: '1' }, ZONES).verifiedOnly).toBe(true);
    });

    it('нормализует некорректный номер страницы', () => {
        expect(normalizePage(0)).toBe(1);
        expect(normalizePage(-1)).toBe(1);
        expect(normalizePage('x')).toBe(1);
        expect(normalizePage(3)).toBe(3);
    });
});

describe('serializeFiltersToQuery', () => {
    it('не пишет значения по умолчанию', () => {
        expect(serializeFiltersToQuery(createDefaultFilters(), 1)).toEqual({});
    });

    it('пишет только изменённые фильтры и страницу', () => {
        expect(
            serializeFiltersToQuery(
                {
                    priceMin: 50_000,
                    priceMax: 10_000_000,
                    incomeMin: 0,
                    incomeMax: 150_000,
                    zones: ['com', 'ru'],
                    categories: ['kompyutery-i-it', 'avto-moto'],
                    verifiedOnly: true,
                },
                2,
            ),
        ).toEqual({
            price_min: '50000',
            income_max: '150000',
            zones: 'ru,com',
            categories: 'avto-moto,kompyutery-i-it',
            verified: '1',
            page: '2',
        });
    });
});

describe('countActiveFilters', () => {
    it('возвращает 0 для значений по умолчанию', () => {
        expect(countActiveFilters(createDefaultFilters())).toBe(0);
    });

    it('считает каждый изменённый фильтр', () => {
        expect(
            countActiveFilters({
                priceMin: 0,
                priceMax: 10_000_000,
                incomeMin: 0,
                incomeMax: 150_000,
                zones: ['ru'],
                categories: CATEGORIES,
                verifiedOnly: false,
            }),
        ).toBe(3);

        expect(
            countActiveFilters({
                priceMin: 50_000,
                priceMax: 900_000,
                incomeMin: 10_000,
                incomeMax: 150_000,
                zones: ['ru'],
                categories: CATEGORIES,
                verifiedOnly: true,
            }),
        ).toBe(5);
    });

    it('считает диапазон 0…0 активным', () => {
        expect(
            countActiveFilters({
                ...createDefaultFilters(),
                priceMax: 0,
            }),
        ).toBe(1);
    });
});

describe('normalizeFilters', () => {
    it('ограничивает границы дохода настройками', () => {
        const filters = normalizeFilters(
            { incomeMin: -10, incomeMax: 5_000_000, verifiedOnly: '1' },
            ZONES,
        );

        expect(filters.incomeMin).toBe(0);
        expect(filters.incomeMax).toBe(1_000_000);
        expect(filters.verifiedOnly).toBe(true);
    });
});
