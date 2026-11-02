import { defineStore } from 'pinia';

import {
    addFavorite,
    fetchFavoriteIds,
    fetchFavorites,
    removeFavorite,
} from '@/services/favoritesService';

let loadingPromise = null;

/**
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta() {
    return { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null };
}

export const useFavoritesStore = defineStore('favorites', {
    state: () => ({
        ids: [],
        items: [],
        meta: emptyMeta(),
        status: 'idle',
        error: null,
        initialized: false,
    }),

    actions: {
        /**
         * Загрузить идентификаторы избранного один раз за сессию,
         * чтобы сердечки на карточках были заполнены без отдельного запроса на каждую.
         *
         * @returns {Promise<void>}
         */
        async ensureLoaded() {
            if (this.initialized) {
                return;
            }

            if (loadingPromise === null) {
                loadingPromise = fetchFavoriteIds()
                    .then((ids) => {
                        this.ids = ids;
                        this.initialized = true;
                    })
                    .finally(() => {
                        loadingPromise = null;
                    });
            }

            await loadingPromise;
        },

        /**
         * @param {number} siteId
         * @returns {boolean}
         */
        isFavorite(siteId) {
            return this.ids.includes(Number(siteId));
        },

        /**
         * Оптимистично переключает избранное и откатывает состояние при ошибке.
         *
         * @param {number} siteId
         * @returns {Promise<boolean>} новое состояние
         */
        async toggle(siteId) {
            await this.ensureLoaded();

            const id = Number(siteId);
            const wasFavorite = this.isFavorite(id);

            this.applyFavoriteState(id, !wasFavorite);

            try {
                if (wasFavorite) {
                    await removeFavorite(id);
                } else {
                    await addFavorite(id);
                }
            } catch (error) {
                this.applyFavoriteState(id, wasFavorite);

                throw error;
            }

            return !wasFavorite;
        },

        /**
         * @param {number} id
         * @param {boolean} isFavorite
         * @returns {void}
         */
        applyFavoriteState(id, isFavorite) {
            if (isFavorite) {
                if (!this.ids.includes(id)) {
                    this.ids = [...this.ids, id];
                }

                return;
            }

            if (!isFavorite) {
                const hadItem = this.items.some((item) => Number(item.id) === id);

                this.ids = this.ids.filter((value) => value !== id);
                this.items = this.items.filter((item) => Number(item.id) !== id);

                if (hadItem && this.meta.total > 0) {
                    this.meta.total -= 1;
                }
            }
        },

        /**
         * @param {number} [page]
         * @returns {Promise<void>}
         */
        async loadFavorites(page = 1) {
            this.status = 'loading';
            this.error = null;

            try {
                const payload = await fetchFavorites(page);

                this.items = payload?.data ?? [];
                this.meta = payload?.meta ?? emptyMeta();
                this.ids = [
                    ...new Set([
                        ...this.ids,
                        ...this.items.map((item) => Number(item.id)),
                    ]),
                ];
                this.status = 'success';
            } catch (error) {
                this.status = 'error';
                this.error = error?.message ?? 'Не удалось загрузить избранное';
            }
        },

        /**
         * @returns {void}
         */
        reset() {
            this.ids = [];
            this.items = [];
            this.meta = emptyMeta();
            this.status = 'idle';
            this.error = null;
            this.initialized = false;
        },
    },
});
