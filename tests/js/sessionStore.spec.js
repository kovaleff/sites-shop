import { createPinia, setActivePinia } from 'pinia';

import { useSessionStore } from '@/stores/session';
import {
    fetchSession,
    logout as logoutRequest,
    startVkLogin,
    startYandexLogin,
} from '@/services/sessionService';

vi.mock('@/services/sessionService', () => ({
    fetchSession: vi.fn(),
    logout: vi.fn(),
    startVkLogin: vi.fn(),
    startYandexLogin: vi.fn(),
}));

const authenticatedSession = {
    authenticated: true,
    user: {
        id: 42,
        displayName: 'Иван Петров',
        email: 'ivan@example.com',
        avatarUrl: 'https://sun9.example/photo.jpg',
        provider: 'vk',
    },
};

describe('session store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('загружает авторизованную сессию с сервера', async () => {
        fetchSession.mockResolvedValue(authenticatedSession);

        const store = useSessionStore();
        await store.initialize();

        expect(fetchSession).toHaveBeenCalledTimes(1);
        expect(store.isAuthenticated).toBe(true);
        expect(store.user.displayName).toBe('Иван Петров');
    });

    it('остаётся гостем при пустой сессии', async () => {
        fetchSession.mockResolvedValue({ authenticated: false, user: null });

        const store = useSessionStore();
        await store.initialize();

        expect(store.isAuthenticated).toBe(false);
        expect(store.user).toBeNull();
    });

    it('запускает вход через VK', async () => {
        startVkLogin.mockResolvedValue();

        const store = useSessionStore();
        const result = await store.loginWithVk();

        expect(startVkLogin).toHaveBeenCalledTimes(1);
        expect(result).toEqual({ ok: true, error: null });
    });

    it('запускает редирект на серверный вход Яндекса', async () => {
        startYandexLogin.mockReturnValue();

        const store = useSessionStore();
        const result = await store.loginWithYandex();

        expect(startYandexLogin).toHaveBeenCalledTimes(1);
        expect(result).toEqual({ ok: true, error: null });
    });

    it('показывает сообщение о ненастроенном VK', async () => {
        startVkLogin.mockRejectedValue(
            Object.assign(new Error('technical detail'), { code: 'vk_not_configured' }),
        );

        const store = useSessionStore();
        const result = await store.loginWithVk();

        expect(result.ok).toBe(false);
        expect(store.authError).toBe('Вход через VK ID пока не настроен');
        expect(store.isAuthenticated).toBe(false);
    });

    it('не начинает вход, если пользователь уже авторизован', async () => {
        const store = useSessionStore();
        store.user = authenticatedSession.user;
        store.isAuthenticated = true;

        const result = await store.loginWithVk();

        expect(result).toEqual({ ok: false, error: null });
        expect(startVkLogin).not.toHaveBeenCalled();
    });

    it('игнорирует повторный вход во время выполнения', async () => {
        const store = useSessionStore();
        store.isLoggingIn = true;

        const result = await store.loginWithVk();

        expect(result).toEqual({ ok: false, error: null });
        expect(store.authError).toBeNull();
        expect(startVkLogin).not.toHaveBeenCalled();
    });

    it('очищает состояние при выходе', async () => {
        logoutRequest.mockResolvedValue();

        const store = useSessionStore();
        store.user = authenticatedSession.user;
        store.isAuthenticated = true;

        await store.logout();

        expect(logoutRequest).toHaveBeenCalledTimes(1);
        expect(store.user).toBeNull();
        expect(store.isAuthenticated).toBe(false);
    });

    it('возвращает гостя, даже если запрос выхода не удался', async () => {
        logoutRequest.mockRejectedValue(new Error('network down'));

        const store = useSessionStore();
        store.user = authenticatedSession.user;
        store.isAuthenticated = true;

        await store.logout();

        expect(store.user).toBeNull();
        expect(store.isAuthenticated).toBe(false);
    });
});