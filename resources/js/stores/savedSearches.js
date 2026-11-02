import { defineStore } from 'pinia';

import {
    createSavedSearch,
    deleteSavedSearch,
    fetchSavedSearches,
    updateSavedSearch,
} from '@/services/savedSearchService';

export const useSavedSearchesStore = defineStore('savedSearches', {
    state: () => ({
        items: [],
        status: 'idle',
        error: null,
    }),

    actions: {
        /**
         * @returns {Promise<void>}
         */
        async load() {
            this.status = 'loading';
            this.error = null;

            try {
                this.items = await fetchSavedSearches();
                this.status = 'success';
            } catch (error) {
                this.status = 'error';
                this.error = error?.message ?? 'Не удалось загрузить подписки';
            }
        },

        /**
         * @param {{ name?: string | null, frequency: string, filters: object }} payload
         * @returns {Promise<object | null>}
         */
        async create(payload) {
            const search = await createSavedSearch(payload);

            if (search) {
                this.items = [search, ...this.items];
            }

            return search;
        },

        /**
         * @param {number} id
         * @param {string} frequency
         * @returns {Promise<void>}
         */
        async updateFrequency(id, frequency) {
            const updated = await updateSavedSearch(id, { frequency });
            const index = this.items.findIndex((item) => item.id === id);

            if (updated && index !== -1) {
                this.items.splice(index, 1, updated);
            }
        },

        /**
         * @param {number} id
         * @returns {Promise<void>}
         */
        async remove(id) {
            await deleteSavedSearch(id);
            this.items = this.items.filter((item) => item.id !== id);
        },

        /**
         * @returns {void}
         */
        reset() {
            this.items = [];
            this.status = 'idle';
            this.error = null;
        },
    },
});
