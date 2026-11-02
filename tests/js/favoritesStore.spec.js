import { createPinia, setActivePinia } from 'pinia';

import {
    addFavorite,
    fetchFavoriteIds,
    fetchFavorites,
    removeFavorite,
} from '@/services/favoritesService';
import { useFavoritesStore } from '@/stores/favorites';

vi.mock('@/services/favoritesService', () => ({
    fetchFavoriteIds: vi.fn(),
    fetchFavorites: vi.fn(),
    addFavorite: vi.fn(),
    removeFavorite: vi.fn(),
}));

describe('favorites store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        fetchFavoriteIds.mockResolvedValue([]);
        addFavorite.mockResolvedValue(null);
        removeFavorite.mockResolvedValue(null);
    });

    it('загружает идентификаторы избранного один раз', async () => {
        fetchFavoriteIds.mockResolvedValue([3, 7]);

        const store = useFavoritesStore();

        await store.ensureLoaded();
        await store.ensureLoaded();

        expect(store.initialized).toBe(true);
        expect(store.isFavorite(7)).toBe(true);
        expect(store.isFavorite(9)).toBe(false);
        expect(fetchFavoriteIds).toHaveBeenCalledTimes(1);
    });

    it('добавляет лот оптимистично и отправляет запрос', async () => {
        const store = useFavoritesStore();
        await store.ensureLoaded();

        const isFavorite = await store.toggle(5);

        expect(isFavorite).toBe(true);
        expect(store.isFavorite(5)).toBe(true);
        expect(addFavorite).toHaveBeenCalledWith(5);
    });

    it('убирает лот из избранного и из загруженного списка', async () => {
        fetchFavoriteIds.mockResolvedValue([5]);
        const store = useFavoritesStore();
        await store.ensureLoaded();

        store.items = [{ id: 5, title: 'Лот' }];
        store.meta = { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 };

        const isFavorite = await store.toggle(5);

        expect(isFavorite).toBe(false);
        expect(store.isFavorite(5)).toBe(false);
        expect(store.items).toEqual([]);
        expect(store.meta.total).toBe(0);
        expect(removeFavorite).toHaveBeenCalledWith(5);
    });

    it('откатывает состояние при ошибке запроса', async () => {
        const store = useFavoritesStore();
        await store.ensureLoaded();

        addFavorite.mockRejectedValue(new Error('Сеть недоступна'));

        await expect(store.toggle(5)).rejects.toThrow('Сеть недоступна');

        expect(store.isFavorite(5)).toBe(false);
    });

    it('загружает список избранного с пагинацией', async () => {
        fetchFavoriteIds.mockResolvedValue([1]);
        fetchFavorites.mockResolvedValue({
            data: [{ id: 1 }, { id: 2 }],
            meta: { page: 1, perPage: 25, total: 2, lastPage: 1, from: 1, to: 2 },
        });

        const store = useFavoritesStore();
        await store.ensureLoaded();
        await store.loadFavorites(1);

        expect(store.items.map((item) => item.id)).toEqual([1, 2]);
        expect(store.meta.total).toBe(2);
        expect(store.isFavorite(2)).toBe(true);
        expect(store.status).toBe('success');
    });
});
