import {
    DEFAULT_SORT,
    DEFAULT_ZONES,
    INCOME_BOUNDS,
    PRICE_BOUNDS,
    SEARCH_MAX_LENGTH,
    SORT_OPTIONS,
} from '@/lib/catalogConfig';

const SORT_VALUES = new Set(SORT_OPTIONS.map((option) => option.value));

/**
 * @param {unknown} value
 * @param {number} fallback
 * @param {number} min
 * @param {number} max
 * @returns {number}
 */
function normalizeBound(value, fallback, min, max) {
    const number = typeof value === 'number' ? value : Number(value);

    if (!Number.isFinite(number)) {
        return fallback;
    }

    return Math.trunc(Math.min(Math.max(number, min), max));
}

/**
 * @param {string[]} zones
 * @returns {string[]}
 */
export function sortZones(zones) {
    const known = DEFAULT_ZONES;

    return [...new Set(zones)].sort((left, right) => {
        const leftIndex = known.indexOf(left);
        const rightIndex = known.indexOf(right);

        if (leftIndex !== -1 && rightIndex !== -1) {
            return leftIndex - rightIndex;
        }

        if (leftIndex !== -1) {
            return -1;
        }

        if (rightIndex !== -1) {
            return 1;
        }

        return left.localeCompare(right);
    });
}

/**
 * Приводит список из query-параметра (строка через запятую или массив) к массиву.
 *
 * @param {unknown} raw
 * @returns {unknown[]}
 */
function splitListValues(raw) {
    if (Array.isArray(raw)) {
        return raw;
    }

    if (typeof raw === 'string') {
        return raw.split(',');
    }

    return [];
}

/**
 * @param {string[]} categories
 * @param {string[]} [availableCategories]
 * @returns {string[]}
 */
export function sortCategories(categories, availableCategories = []) {
    const order = new Map(
        availableCategories.map((slug, index) => [String(slug).trim().toLowerCase(), index]),
    );

    return [...new Set(categories)].sort((left, right) => {
        const leftIndex = order.get(left) ?? -1;
        const rightIndex = order.get(right) ?? -1;

        if (leftIndex !== -1 && rightIndex !== -1) {
            return leftIndex - rightIndex;
        }

        if (leftIndex !== -1) {
            return -1;
        }

        if (rightIndex !== -1) {
            return 1;
        }

        return left.localeCompare(right);
    });
}

/**
 * @param {unknown} raw
 * @param {string[]} [availableZones]
 * @returns {string[]}
 */
function normalizeZones(raw, availableZones) {
    const available = new Set(
        (availableZones && availableZones.length ? availableZones : DEFAULT_ZONES).map((zone) =>
            String(zone).trim().toLowerCase().replace(/^\./, ''),
        ),
    );

    const zones = splitListValues(raw)
        .map((zone) => String(zone).trim().toLowerCase().replace(/^\./, ''))
        .filter((zone) => zone.length > 0 && available.has(zone));

    return sortZones(zones);
}

/**
 * Тематики приходят слагами. Если справочник ещё не загружен, значения из URL
 * сохраняются как есть, чтобы фильтр не терялся при прямом переходе по ссылке.
 *
 * @param {unknown} raw
 * @param {string[]} [availableCategories]
 * @returns {string[]}
 */
function normalizeCategories(raw, availableCategories) {
    const provided = [
        ...new Set(
            splitListValues(raw)
                .map((slug) => String(slug).trim().toLowerCase())
                .filter((slug) => slug.length > 0),
        ),
    ];

    if (!availableCategories || availableCategories.length === 0) {
        return provided;
    }

    const available = new Set(
        availableCategories.map((slug) => String(slug).trim().toLowerCase()),
    );

    return sortCategories(
        provided.filter((slug) => available.has(slug)),
        availableCategories,
    );
}

/**
 * @param {unknown} raw
 * @returns {boolean}
 */
function normalizeVerified(raw) {
    return raw === true || raw === 1 || raw === '1' || raw === 'true';
}

/**
 * Поисковая строка обрезается по краям и ограничивается сверху, чтобы не
 * раздувать URL и запрос. Минимум символов проверяет API.
 *
 * @param {unknown} raw
 * @returns {string}
 */
export function normalizeSearch(raw) {
    if (typeof raw !== 'string') {
        return '';
    }

    return raw.trim().slice(0, SEARCH_MAX_LENGTH);
}

/**
 * @param {unknown} raw
 * @returns {string}
 */
export function normalizeSort(raw) {
    const value = typeof raw === 'string' ? raw.trim() : '';

    return SORT_VALUES.has(value) ? value : DEFAULT_SORT;
}

/**
 * @returns {{
 *   priceMin: number,
 *   priceMax: number,
 *   incomeMin: number,
 *   incomeMax: number,
 *   zones: string[],
 *   categories: string[],
 *   verifiedOnly: boolean,
 *   search: string,
 *   sort: string,
 * }}
 */
export function createDefaultFilters() {
    return {
        priceMin: PRICE_BOUNDS.min,
        priceMax: PRICE_BOUNDS.max,
        incomeMin: INCOME_BOUNDS.min,
        incomeMax: INCOME_BOUNDS.max,
        zones: [],
        categories: [],
        verifiedOnly: false,
        search: '',
        sort: DEFAULT_SORT,
    };
}

/**
 * @param {object | null | undefined} raw
 * @param {string[]} [availableZones]
 * @param {string[]} [availableCategories]
 * @returns {ReturnType<typeof createDefaultFilters>}
 */
export function normalizeFilters(raw, availableZones, availableCategories) {
    const source = raw ?? {};

    let priceMin = normalizeBound(source.priceMin, PRICE_BOUNDS.min, PRICE_BOUNDS.min, PRICE_BOUNDS.max);
    let priceMax = normalizeBound(source.priceMax, PRICE_BOUNDS.max, PRICE_BOUNDS.min, PRICE_BOUNDS.max);

    if (priceMin > priceMax) {
        priceMin = PRICE_BOUNDS.min;
        priceMax = PRICE_BOUNDS.max;
    }

    let incomeMin = normalizeBound(source.incomeMin, INCOME_BOUNDS.min, INCOME_BOUNDS.min, INCOME_BOUNDS.max);
    let incomeMax = normalizeBound(source.incomeMax, INCOME_BOUNDS.max, INCOME_BOUNDS.min, INCOME_BOUNDS.max);

    if (incomeMin > incomeMax) {
        incomeMin = INCOME_BOUNDS.min;
        incomeMax = INCOME_BOUNDS.max;
    }

    return {
        priceMin,
        priceMax,
        incomeMin,
        incomeMax,
        zones: normalizeZones(source.zones, availableZones),
        categories: normalizeCategories(source.categories, availableCategories),
        verifiedOnly: normalizeVerified(source.verifiedOnly),
        search: normalizeSearch(source.search),
        sort: normalizeSort(source.sort),
    };
}

/**
 * @param {Record<string, unknown> | null | undefined} query
 * @param {string[]} [availableZones]
 * @param {string[]} [availableCategories]
 * @returns {ReturnType<typeof createDefaultFilters>}
 */
export function parseFiltersFromQuery(query, availableZones, availableCategories) {
    const source = query ?? {};

    return normalizeFilters(
        {
            priceMin: source.price_min,
            priceMax: source.price_max,
            incomeMin: source.income_min,
            incomeMax: source.income_max,
            zones: source.zones,
            categories: source.categories,
            verifiedOnly: source.verified,
            search: source.q,
            sort: source.sort,
        },
        availableZones,
        availableCategories,
    );
}

/**
 * Полный набор фильтров для API сохранённого поиска: в отличие от query-строки
 * здесь передаются и значения по умолчанию, чтобы фильтр был воспроизводим.
 *
 * @param {ReturnType<typeof createDefaultFilters>} filters
 * @returns {{
 *   price_min: number,
 *   price_max: number,
 *   income_min: number,
 *   income_max: number,
 *   zones: string[],
 *   categories: string[],
 *   verified: boolean,
 *   q: string | null,
 *   sort: string,
 * }}
 */
export function serializeFiltersToPayload(filters) {
    const normalized = normalizeFilters(filters, filters.zones, filters.categories);

    return {
        price_min: normalized.priceMin,
        price_max: normalized.priceMax,
        income_min: normalized.incomeMin,
        income_max: normalized.incomeMax,
        zones: normalized.zones,
        categories: normalized.categories,
        verified: normalized.verifiedOnly,
        q: normalized.search === '' ? null : normalized.search,
        sort: normalized.sort,
    };
}

/**
 * @param {ReturnType<typeof createDefaultFilters>} filters
 * @param {number} [page]
 * @returns {Record<string, string>}
 */
export function serializeFiltersToQuery(filters, page = 1) {
    const query = {};

    if (filters.priceMin !== PRICE_BOUNDS.min) {
        query.price_min = String(filters.priceMin);
    }

    if (filters.priceMax !== PRICE_BOUNDS.max) {
        query.price_max = String(filters.priceMax);
    }

    if (filters.incomeMin !== INCOME_BOUNDS.min) {
        query.income_min = String(filters.incomeMin);
    }

    if (filters.incomeMax !== INCOME_BOUNDS.max) {
        query.income_max = String(filters.incomeMax);
    }

    if (filters.zones.length > 0) {
        query.zones = sortZones(filters.zones).join(',');
    }

    if (filters.categories.length > 0) {
        query.categories = sortCategories(filters.categories).join(',');
    }

    if (filters.verifiedOnly) {
        query.verified = '1';
    }

    const search = normalizeSearch(filters.search);

    if (search !== '') {
        query.q = search;
    }

    const sort = normalizeSort(filters.sort);

    if (sort !== DEFAULT_SORT) {
        query.sort = sort;
    }

    const normalizedPage = normalizePage(page);

    if (normalizedPage > 1) {
        query.page = String(normalizedPage);
    }

    return query;
}

/**
 * @param {unknown} raw
 * @returns {number}
 */
export function normalizePage(raw) {
    const number = typeof raw === 'number' ? raw : Number.parseInt(String(raw ?? ''), 10);

    if (!Number.isFinite(number) || number < 1) {
        return 1;
    }

    return Math.trunc(number);
}

/**
 * @param {ReturnType<typeof createDefaultFilters>} filters
 * @returns {number}
 */
export function countActiveFilters(filters) {
    let count = 0;

    if (filters.priceMin !== PRICE_BOUNDS.min || filters.priceMax !== PRICE_BOUNDS.max) {
        count += 1;
    }

    if (filters.incomeMin !== INCOME_BOUNDS.min || filters.incomeMax !== INCOME_BOUNDS.max) {
        count += 1;
    }

    if (filters.zones.length > 0) {
        count += 1;
    }

    if (filters.categories.length > 0) {
        count += 1;
    }

    if (filters.verifiedOnly) {
        count += 1;
    }

    if (normalizeSearch(filters.search) !== '') {
        count += 1;
    }

    if (normalizeSort(filters.sort) !== DEFAULT_SORT) {
        count += 1;
    }

    return count;
}
