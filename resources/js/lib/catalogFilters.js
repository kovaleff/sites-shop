import { DEFAULT_ZONES, INCOME_BOUNDS, PRICE_BOUNDS } from '@/lib/catalogConfig';

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
 * @returns {{
 *   priceMin: number,
 *   priceMax: number,
 *   incomeMin: number,
 *   incomeMax: number,
 *   zones: string[],
 *   categories: string[],
 *   verifiedOnly: boolean,
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
        },
        availableZones,
        availableCategories,
    );
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

    return count;
}
