import { createPinia, setActivePinia } from 'pinia';

import { MOCK_SCENARIO_KEY, SESSION_STORAGE_KEY } from '@/mocks/session';
import { createDefaultFilters } from '@/lib/catalogFilters';
import { useCatalogStore } from '@/stores/catalog';
import { useSessionStore } from '@/stores/session';

describe('session store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        window.localStorage.clear();
    });

    afterEach(() => {
        vi.useRealTimers();
        window.localStorage.clear();
    });

    it('удаляет повреждённую сессию и возвращает гостя', async () => {
        window.localStorage.setItem(SESSION_STORAGE_KEY, '{broken json');
        const store = useSessionStore();

        await store.initialize();

        expect(store.isAuthenticated).toBe(false);
        expect(store.user).toBeNull();
        expect(window.localStorage.getItem(SESSION_STORAGE_KEY)).toBeNull();
    });

    it('восстанавливает валидную сессию из localStorage', async () => {
        window.localStorage.setItem(
            SESSION_STORAGE_KEY,
            JSON.stringify({
                id: 'usr_demo',
                displayName: 'Демо Покупатель',
                avatarUrl: null,
                provider: 'vk',
            }),
        );

        const store = useSessionStore();
        await store.initialize();

        expect(store.isAuthenticated).toBe(true);
        expect(store.user.displayName).toBe('Демо Покупатель');
    });

    it('выставляет authError и не затрагивает каталог при auth-error', async () => {
        window.localStorage.setItem(MOCK_SCENARIO_KEY, 'auth-error');

        const catalog = useCatalogStore();
        catalog.applied = { ...createDefaultFilters(), zones: ['ru'], verifiedOnly: true };
        catalog.draft = { ...createDefaultFilters(), zones: ['ru'], verifiedOnly: true };
        catalog.page = 2;

        const store = useSessionStore();

        vi.useFakeTimers();
        const request = store.loginWithVk();
        await vi.advanceTimersByTimeAsync(700);
        const result = await request;

        expect(result.ok).toBe(false);
        expect(store.isAuthenticated).toBe(false);
        expect(store.authError).toBe('Не удалось войти через VK ID. Попробуйте ещё раз.');
        expect(catalog.applied.zones).toEqual(['ru']);
        expect(catalog.applied.verifiedOnly).toBe(true);
        expect(catalog.page).toBe(2);
    });

    it('игнорирует повторный вход во время выполнения', async () => {
        const store = useSessionStore();
        store.isLoggingIn = true;

        const result = await store.loginWithVk();

        expect(result).toEqual({ ok: false, error: null });
        expect(store.authError).toBeNull();
    });

    it('очищает пользователя и localStorage при выходе', async () => {
        const store = useSessionStore();
        store.user = { id: 'usr_demo', displayName: 'Демо Покупатель' };
        store.isAuthenticated = true;
        window.localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify(store.user));

        await store.logout();

        expect(store.user).toBeNull();
        expect(store.isAuthenticated).toBe(false);
        expect(window.localStorage.getItem(SESSION_STORAGE_KEY)).toBeNull();
    });
});
