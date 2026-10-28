import {
    DEFAULT_ZONES,
    INCOME_BOUNDS,
    MOCK_LATENCY_MS,
    PAGE_SIZE,
    PRICE_BOUNDS,
    SLOW_LATENCY_MS,
} from '@/lib/catalogConfig';
import { normalizeFilters, normalizePage, sortZones } from '@/lib/catalogFilters';
import { listings } from '@/mocks/listings';
import { MOCK_SCENARIO_KEY } from '@/mocks/session';

/**
 * @returns {string | null}
 */
function readScenario() {
    try {
        return window.localStorage.getItem(MOCK_SCENARIO_KEY);
    } catch {
        return null;
    }
}

/**
 * @param {number} milliseconds
 * @returns {Promise<void>}
 */
function delay(milliseconds) {
    return new Promise((resolve) => {
        setTimeout(resolve, milliseconds);
    });
}

/**
 * @returns {Array<{ value: string, label: string, count: number }>}
 */
function deriveZones() {
    const counts = new Map();

    listings
        .filter((listing) => listing.isPublic && listing.status === 'active')
        .forEach((listing) => {
            if (!listing.domainZone) {
                return;
            }

            counts.set(listing.domainZone, (counts.get(listing.domainZone) ?? 0) + 1);
        });

    const known = DEFAULT_ZONES.filter((zone) => counts.has(zone));
    const extra = [...counts.keys()].filter((zone) => !DEFAULT_ZONES.includes(zone));

    return [...known, ...sortZones(extra)].map((zone) => ({
        value: zone,
        label: `.${zone}`,
        count: counts.get(zone),
    }));
}

/**
 * @param {object} listing
 * @returns {object}
 */
function toPublicListing(listing) {
    return {
        id: listing.id,
        title: listing.title,
        domain: listing.domain,
        domainZone: listing.domainZone,
        description: listing.description,
        price: listing.price,
        monthlyIncome: listing.monthlyIncome,
        status: listing.status,
        isVerified: listing.isVerified,
        faviconUrl: listing.faviconUrl,
        publishedAt: listing.publishedAt,
    };
}

/**
 * @param {number} page
 * @returns {{ items: object[], meta: { page: number, perPage: number, total: number, lastPage: number, from: null, to: null } }}
 */
function emptyResult(page) {
    return {
        items: [],
        meta: {
            page,
            perPage: PAGE_SIZE,
            total: 0,
            lastPage: 1,
            from: null,
            to: null,
        },
    };
}

/**
 * @param {object} query
 * @returns {Promise<{ items: object[], meta: object }>}
 */
export async function fetchListings(query = {}) {
    const scenario = readScenario();

    await delay(scenario === 'slow' ? SLOW_LATENCY_MS : MOCK_LATENCY_MS);

    if (scenario === 'error') {
        throw new Error('Не удалось загрузить каталог');
    }

    const page = normalizePage(query.page);

    if (scenario === 'empty') {
        return emptyResult(page);
    }

    const zoneValues = deriveZones().map((zone) => zone.value);
    const filters = normalizeFilters(
        {
            priceMin: query.priceMin,
            priceMax: query.priceMax,
            incomeMin: query.incomeMin,
            incomeMax: query.incomeMax,
            zones: query.zones,
            verifiedOnly: query.verifiedOnly,
        },
        zoneValues,
    );

    const filtered = listings
        .filter((listing) => listing.isPublic && listing.status === 'active')
        .filter((listing) => listing.price >= filters.priceMin)
        .filter((listing) =>
            filters.priceMax === PRICE_BOUNDS.max ? true : listing.price <= filters.priceMax,
        )
        .filter((listing) => listing.monthlyIncome >= filters.incomeMin)
        .filter((listing) =>
            filters.incomeMax === INCOME_BOUNDS.max
                ? true
                : listing.monthlyIncome <= filters.incomeMax,
        )
        .filter((listing) =>
            filters.zones.length > 0 ? filters.zones.includes(listing.domainZone) : true,
        )
        .filter((listing) => (filters.verifiedOnly ? listing.isVerified === true : true))
        .sort((left, right) => {
            const byDate = right.publishedAt.localeCompare(left.publishedAt);

            return byDate !== 0 ? byDate : left.id.localeCompare(right.id);
        });

    const total = filtered.length;
    const lastPage = Math.max(1, Math.ceil(total / PAGE_SIZE));
    const items = filtered.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).map(toPublicListing);

    const from = items.length === 0 ? null : (page - 1) * PAGE_SIZE + 1;
    const to = items.length === 0 ? null : from + items.length - 1;

    return {
        items,
        meta: {
            page,
            perPage: PAGE_SIZE,
            total,
            lastPage,
            from,
            to,
        },
    };
}

/**
 * @returns {Promise<Array<{ value: string, label: string, count: number }>>}
 */
export async function fetchZones() {
    const scenario = readScenario();

    await delay(scenario === 'slow' ? SLOW_LATENCY_MS : MOCK_LATENCY_MS);

    if (scenario === 'error') {
        throw new Error('Не удалось загрузить список зон');
    }

    return deriveZones().map((zone) => ({ ...zone }));
}
