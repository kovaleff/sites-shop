import { createPinia, setActivePinia } from 'pinia';

import { changeSiteStatus, fetchMySites, hideSite, restoreSite } from '@/services/cabinetService';
import { useCabinetStore } from '@/stores/cabinet';

vi.mock('@/services/cabinetService', () => ({
    fetchMySites: vi.fn(),
    fetchSite: vi.fn(),
    createSite: vi.fn(),
    updateSite: vi.fn(),
    changeSiteStatus: vi.fn(),
    hideSite: vi.fn(),
    restoreSite: vi.fn(),
}));

/**
 * @param {object} [overrides]
 * @returns {{ data: object[], meta: object }}
 */
function listPayload(overrides = {}) {
    return {
        data: [],
        meta: { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null },
        ...overrides,
    };
}

describe('cabinet store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('загружает список объявлений', async () => {
        fetchMySites.mockResolvedValue(
            listPayload({
                data: [{ id: 1, title: 'Лот', status: 'active' }],
                meta: { page: 1, perPage: 25, total: 1, lastPage: 1, from: 1, to: 1 },
            }),
        );

        const store = useCabinetStore();

        await store.load({ mode: 'visible', page: 1 });

        expect(fetchMySites).toHaveBeenCalledWith({ page: 1, hidden: false });
        expect(store.items).toHaveLength(1);
        expect(store.meta.total).toBe(1);
        expect(store.status).toBe('success');
    });

    it('запрашивает скрытые объявления с флагом hidden', async () => {
        fetchMySites.mockResolvedValue(listPayload());

        const store = useCabinetStore();

        await store.load({ mode: 'hidden', page: 2 });

        expect(fetchMySites).toHaveBeenCalledWith({ page: 2, hidden: true });
        expect(store.mode).toBe('hidden');
    });

    it('сохраняет ошибку загрузки', async () => {
        fetchMySites.mockRejectedValue(new Error('Сеть недоступна'));

        const store = useCabinetStore();

        await store.load();

        expect(store.status).toBe('error');
        expect(store.error).toBe('Сеть недоступна');
    });

    it('обновляет объявление после смены статуса', async () => {
        fetchMySites.mockResolvedValue(
            listPayload({ data: [{ id: 7, status: 'active', statusLabel: 'Активен' }] }),
        );
        changeSiteStatus.mockResolvedValue({ data: { id: 7, status: 'inactive' } });

        const store = useCabinetStore();

        await store.load();
        await store.changeStatus(7, 'inactive');

        expect(store.items[0].status).toBe('inactive');
    });

    it('удаляет скрытое объявление из текущего списка', async () => {
        fetchMySites.mockResolvedValue(listPayload({ data: [{ id: 1 }, { id: 2 }] }));
        hideSite.mockResolvedValue({ data: { id: 1 } });

        const store = useCabinetStore();

        await store.load();
        await store.hide(1);

        expect(store.items.map((item) => item.id)).toEqual([2]);
    });

    it('удаляет возвращённое объявление из списка скрытых', async () => {
        fetchMySites.mockResolvedValue(listPayload({ data: [{ id: 5 }] }));
        restoreSite.mockResolvedValue({ data: { id: 5 } });

        const store = useCabinetStore();

        await store.load({ mode: 'hidden' });
        await store.restore(5);

        expect(store.items).toEqual([]);
    });
});