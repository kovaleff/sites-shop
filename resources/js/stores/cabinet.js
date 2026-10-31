import { defineStore } from 'pinia';

import {
    changeSiteStatus,
    fetchMySites,
    hideSite,
    restoreSite,
} from '@/services/cabinetService';

/**
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta() {
    return { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null };
}

export const useCabinetStore = defineStore('cabinet', {
    state: () => ({
        items: [],
        meta: emptyMeta(),
        mode: 'visible',
        status: 'idle',
        error: null,
    }),

    getters: {
        isLoading: (state) => state.status === 'loading',
        isEmpty: (state) => state.status === 'success' && state.items.length === 0,
        hasError: (state) => state.status === 'error',
    },

    actions: {
        /**
         * @param {{ mode?: 'visible' | 'hidden', page?: number }} [options]
         * @returns {Promise<void>}
         */
        async load({ mode = this.mode, page = 1 } = {}) {
            this.mode = mode;
            this.status = 'loading';
            this.error = null;

            try {
                const payload = await fetchMySites({ page, hidden: mode === 'hidden' });

                this.items = payload?.data ?? [];
                this.meta = payload?.meta ?? emptyMeta();
                this.status = 'success';
            } catch (error) {
                this.status = 'error';
                this.error = error?.message ?? 'Не удалось загрузить объявления';
            }
        },

        /**
         * @param {number} page
         * @returns {Promise<void>}
         */
        goToPage(page) {
            return this.load({ mode: this.mode, page });
        },

        /**
         * @param {number} id
         * @param {string} status
         * @returns {Promise<object>}
         */
        async changeStatus(id, status) {
            const payload = await changeSiteStatus(id, status);
            const site = payload?.data ?? null;

            if (site) {
                this.replace(site);
            }

            return site;
        },

        /**
         * @param {number} id
         * @returns {Promise<void>}
         */
        async hide(id) {
            await hideSite(id);
            this.items = this.items.filter((item) => item.id !== id);
        },

        /**
         * @param {number} id
         * @returns {Promise<void>}
         */
        async restore(id) {
            await restoreSite(id);
            this.items = this.items.filter((item) => item.id !== id);
        },

        /**
         * @param {object} site
         * @returns {void}
         */
        replace(site) {
            const index = this.items.findIndex((item) => item.id === site.id);

            if (index !== -1) {
                this.items.splice(index, 1, site);
            }
        },

        /**
         * @returns {void}
         */
        reset() {
            this.items = [];
            this.meta = emptyMeta();
            this.status = 'idle';
            this.error = null;
        },
    },
});