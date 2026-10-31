import { INCOME_BOUNDS, PAGE_SIZE, PRICE_BOUNDS } from '@/lib/catalogConfig';
import { sortCategories, sortZones } from '@/lib/catalogFilters';
import { fetchJson } from '@/services/http';

/**
 * @param {number} page
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta(page) {
    return { page, perPage: PAGE_SIZE, total: 0, lastPage: 1, from: null, to: null };
}

/**
 * @param {unknown} value
 * @param {number} fallback
 * @returns {number}
 */
function bound(value, fallback) {
    const number = typeof value === 'number' ? value : Number(value);

    return Number.isFinite(number) && number >= 0 ? Math.trunc(number) : fallback;
}

/**
 * @param {object} query
 * @returns {string}
 */
function buildQuery(query = {}) {
    const page = Math.max(1, bound(query.page, 1));
    const params = new URLSearchParams({
        price_min: String(bound(query.priceMin, PRICE_BOUNDS.min)),
        price_max: String(bound(query.priceMax, PRICE_BOUNDS.max)),
        income_min: String(bound(query.incomeMin, INCOME_BOUNDS.min)),
        income_max: String(bound(query.incomeMax, INCOME_BOUNDS.max)),
        page: String(page),
        sort: 'newest',
    });

    const zones = sortZones(
        (Array.isArray(query.zones) ? query.zones : [])
            .map((zone) => String(zone).trim().toLowerCase().replace(/^\./, ''))
            .filter(Boolean),
    );

    if (zones.length > 0) {
        params.set('zones', zones.join(','));
    }

    const categories = sortCategories(
        (Array.isArray(query.categories) ? query.categories : [])
            .map((slug) => String(slug).trim().toLowerCase())
            .filter(Boolean),
    );

    if (categories.length > 0) {
        params.set('categories', categories.join(','));
    }

    if (query.verifiedOnly === true) {
        params.set('verified', '1');
    }

    return params.toString();
}

/**
 * Страница публичного каталога (контракт 003).
 *
 * @param {object} [query]
 * @returns {Promise<{ items: object[], meta: object }>}
 */
export async function fetchListings(query = {}) {
    const data = await fetchJson(`/api/v1/catalog?${buildQuery(query)}`);

    return {
        items: Array.isArray(data?.items) ? data.items : [],
        meta: data?.meta ?? emptyMeta(1),
    };
}

/**
 * Доменные зоны публичных лотов.
 *
 * @returns {Promise<Array<{ value: string, label: string, count: number }>>}
 */
export async function fetchZones() {
    const data = await fetchJson('/api/v1/catalog/zones');

    return Array.isArray(data?.items) ? data.items : [];
}