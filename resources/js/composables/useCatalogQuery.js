import { watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { DEFAULT_ZONES } from '@/lib/catalogConfig';
import {
    normalizeFilters,
    normalizePage,
    parseFiltersFromQuery,
    serializeFiltersToQuery,
} from '@/lib/catalogFilters';
import { useCatalogStore } from '@/stores/catalog';

/**
 * Синхронизирует применённые фильтры каталога с query-параметрами URL.
 *
 * @param {(() => string[]) | string[]} [zonesSource]
 * @param {(() => string[]) | string[]} [categoriesSource]
 */
export function useCatalogQuery(
    zonesSource = () => DEFAULT_ZONES,
    categoriesSource = () => [],
) {
    const catalog = useCatalogStore();
    const route = useRoute();
    const router = useRouter();

    let syncedQueryKey = null;
    let hydrating = false;

    /**
     * @returns {string[]}
     */
    function availableZones() {
        const zones = typeof zonesSource === 'function' ? zonesSource() : zonesSource;

        if (!Array.isArray(zones) || zones.length === 0) {
            return [...DEFAULT_ZONES];
        }

        return zones.map((zone) => (typeof zone === 'string' ? zone : zone.value));
    }

    /**
     * Справочник тематик подгружается асинхронно, поэтому здесь возвращается
     * только то, что уже известно: пустой список означает «принять значения из URL».
     *
     * @returns {string[]}
     */
    function availableCategories() {
        const categories = typeof categoriesSource === 'function' ? categoriesSource() : categoriesSource;

        if (!Array.isArray(categories)) {
            return [];
        }

        return categories.map((category) =>
            typeof category === 'string' ? category : category.slug,
        );
    }

    /**
     * @param {object} filters
     * @param {number} page
     * @returns {Record<string, string>}
     */
    function buildQuery(filters, page) {
        return serializeFiltersToQuery(filters, page);
    }

    /**
     * @param {Record<string, string>} query
     * @returns {string}
     */
    function queryKey(query) {
        return JSON.stringify(Object.entries(query).sort(([left], [right]) => left.localeCompare(right)));
    }

    /**
     * @param {import('vue-router').RouteLocationNormalized} targetRoute
     * @returns {Promise<{ outOfRange: boolean, lastPage: number }>}
     */
    async function hydrateFromRoute(targetRoute = route) {
        const filters = parseFiltersFromQuery(
            targetRoute.query,
            availableZones(),
            availableCategories(),
        );
        const requestedPage = normalizePage(targetRoute.query.page);

        catalog.hydrateFilters(filters);

        hydrating = true;

        try {
            const result = await catalog.setPageFromUrl(requestedPage);

            if (result.outOfRange) {
                await router.replace({
                    name: 'catalog',
                    query: buildQuery(filters, result.lastPage),
                });

                await catalog.setPageFromUrl(result.lastPage);
            }

            syncedQueryKey = queryKey(buildQuery(catalog.applied, catalog.page));

            return result;
        } finally {
            hydrating = false;
        }
    }

    /**
     * @param {object} filters
     * @param {number} [page]
     * @returns {Promise<void>}
     */
    async function pushFilters(filters, page = 1) {
        const normalized = normalizeFilters(filters, availableZones(), availableCategories());
        const query = buildQuery(normalized, page);

        syncedQueryKey = queryKey(query);

        await router.push({ name: 'catalog', query });
    }

    /**
     * @returns {Promise<void>}
     */
    async function clearFilters() {
        syncedQueryKey = queryKey({});

        await router.push({ name: 'catalog', query: {} });
    }

    /**
     * @param {number} page
     * @param {object} [filters]
     * @returns {Promise<void>}
     */
    async function replacePage(page, filters = catalog.applied) {
        const query = buildQuery(filters, page);

        syncedQueryKey = queryKey(query);

        await router.replace({ name: 'catalog', query });
    }

    watch(
        () => route.query,
        async (query) => {
            if (hydrating || route.name !== 'catalog') {
                return;
            }

            const serialized = buildQuery(
                parseFiltersFromQuery(query, availableZones(), availableCategories()),
                normalizePage(query.page),
            );

            if (syncedQueryKey !== null && queryKey(serialized) === syncedQueryKey) {
                return;
            }

            await hydrateFromRoute(route);
        },
        { deep: true },
    );

    return {
        hydrateFromRoute,
        pushFilters,
        clearFilters,
        replacePage,
    };
}
