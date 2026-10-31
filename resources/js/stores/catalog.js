import { defineStore } from 'pinia';

import { PAGE_SIZE } from '@/lib/catalogConfig';
import { createDefaultFilters, normalizePage } from '@/lib/catalogFilters';
import { fetchListings } from '@/services/catalogService';

/**
 * @param {ReturnType<typeof createDefaultFilters>} filters
 * @returns {ReturnType<typeof createDefaultFilters>}
 */
function cloneFilters(filters) {
    return {
        priceMin: filters.priceMin,
        priceMax: filters.priceMax,
        incomeMin: filters.incomeMin,
        incomeMax: filters.incomeMax,
        zones: [...filters.zones],
        categories: [...filters.categories],
        verifiedOnly: filters.verifiedOnly,
    };
}

export const useCatalogStore = defineStore('catalog', {
    state: () => ({
        applied: createDefaultFilters(),
        draft: createDefaultFilters(),
        page: 1,
        perPage: PAGE_SIZE,
        items: [],
        total: 0,
        lastPage: 1,
        status: 'idle',
        error: null,
        requestId: 0,
    }),

    actions: {
        /**
         * @returns {Promise<void>}
         */
        async load() {
            const requestId = this.requestId + 1;
            this.requestId = requestId;
            this.status = 'loading';
            this.error = null;

            try {
                const result = await fetchListings({
                    ...this.applied,
                    page: this.page,
                    perPage: this.perPage,
                });

                if (requestId !== this.requestId) {
                    return;
                }

                this.items = result.items;
                this.total = result.meta.total;
                this.lastPage = result.meta.lastPage;
                this.page = result.meta.page;
                this.status = 'success';
            } catch (error) {
                if (requestId !== this.requestId) {
                    return;
                }

                this.status = 'error';
                this.error = error?.message ?? 'Не удалось загрузить каталог';
            }
        },

        /**
         * @param {ReturnType<typeof createDefaultFilters>} filters
         * @returns {Promise<void>}
         */
        applyFilters(filters) {
            this.applied = cloneFilters(filters);
            this.draft = cloneFilters(filters);
            this.page = 1;

            return this.load();
        },

        /**
         * @param {ReturnType<typeof createDefaultFilters>} filters
         * @returns {void}
         */
        hydrateFilters(filters) {
            this.applied = cloneFilters(filters);
            this.draft = cloneFilters(filters);
        },

        /**
         * @returns {Promise<void>}
         */
        resetFilters() {
            const defaults = createDefaultFilters();

            this.applied = defaults;
            this.draft = createDefaultFilters();
            this.page = 1;

            return this.load();
        },

        /**
         * @param {ReturnType<typeof createDefaultFilters>} filters
         * @returns {void}
         */
        setDraft(filters) {
            this.draft = cloneFilters(filters);
        },

        /**
         * @returns {Promise<void>}
         */
        retry() {
            return this.load();
        },

        /**
         * @param {number} page
         * @returns {Promise<void>}
         */
        goToPage(page) {
            const target = Math.min(Math.max(normalizePage(page), 1), this.lastPage);

            this.page = target;

            return this.load();
        },

        /**
         * @param {number} page
         * @returns {Promise<{ outOfRange: boolean, lastPage: number }>}
         */
        async setPageFromUrl(page) {
            const target = normalizePage(page);
            this.page = target;

            await this.load();

            const outOfRange = this.total > 0 && target > this.lastPage;

            if (outOfRange) {
                this.items = [];
                this.status = 'success';
                this.error = null;
            }

            return { outOfRange, lastPage: this.lastPage };
        },
    },
});
