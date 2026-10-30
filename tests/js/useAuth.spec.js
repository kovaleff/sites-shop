import { createPinia, setActivePinia } from 'pinia';

import { useAuth } from '@/composables/useAuth';
import { useSessionStore } from '@/stores/session';
import { completeVkCallback, VK_STATE_KEY, VK_VERIFIER_KEY } from '@/services/sessionService';

vi.mock('@/services/sessionService', () => ({
    VK_STATE_KEY: 'vk_oauth_state',
    VK_VERIFIER_KEY: 'vk_oauth_verifier',
    VK_REDIRECT_PATH: '/api/vk/callback',
    VK_VERIFIER_COOKIE: 'vk_code_verifier',
    fetchSession: vi.fn(),
    logout: vi.fn(),
    startVkLogin: vi.fn(),
    startYandexLogin: vi.fn(),
    completeVkCallback: vi.fn(),
    clearVkAttempt: vi.fn(),
}));

const authenticatedSession = {
    authenticated: true,
    user: {
        id: 42,
        displayName: 'Иван Петров',
        email: 'ivan@example.com',
        avatarUrl: null,
        provider: 'vk',
    },
};

describe('useAuth', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        sessionStorage.clear();
    });

    it('показывает сообщение по коду auth_error и возвращает код', () => {
        const { handleAuthError, session } = useAuth();

        const code = handleAuthError('?auth_error=too_many_requests');

        expect(code).toBe('too_many_requests');
        expect(session.authError).toBe('Слишком много попыток входа. Попробуйте позже');
    });

    it('не реагирует на обычный адрес без параметров возврата', () => {
        const { handleAuthError, session } = useAuth();

        expect(handleAuthError('?page=2')).toBeNull();
        expect(session.authError).toBeNull();
    });

    it('сообщает об отмене входа через VK', async () => {
        const { handleVkReturn, session } = useAuth();

        const result = await handleVkReturn('?error=access_denied');

        expect(result).toEqual({ handled: true, error: 'Вход через VK ID отменён' });
        expect(session.authError).toBe('Вход через VK ID отменён');
        expect(completeVkCallback).not.toHaveBeenCalled();
    });

    it('не обменивает код при несовпадении state', async () => {
        sessionStorage.setItem(VK_STATE_KEY, 'expected-state');

        const { handleVkReturn, session } = useAuth();

        const result = await handleVkReturn('?code=abc&state=other-state');

        expect(result).toEqual({
            handled: true,
            error: 'Не удалось войти через VK ID. Попробуйте ещё раз',
        });
        expect(session.authError).toBe('Не удалось войти через VK ID. Попробуйте ещё раз');
        expect(completeVkCallback).not.toHaveBeenCalled();
    });

    it('обменивает код VK на сессию и сохраняет пользователя', async () => {
        sessionStorage.setItem(VK_STATE_KEY, 'same-state');
        sessionStorage.setItem(VK_VERIFIER_KEY, 'verifier-value');
        completeVkCallback.mockResolvedValue(authenticatedSession);

        const { handleVkReturn, session } = useAuth();

        const result = await handleVkReturn('?code=abc&state=same-state&device_id=device-1');

        expect(completeVkCallback).toHaveBeenCalledWith({
            code: 'abc',
            deviceId: 'device-1',
            codeVerifier: 'verifier-value',
        });
        expect(result).toEqual({ handled: true, error: null });
        expect(session.isAuthenticated).toBe(true);
        expect(session.user.displayName).toBe('Иван Петров');
    });

    it('показывает сообщение при превышении лимита на обмене кода', async () => {
        completeVkCallback.mockRejectedValue(
            Object.assign(new Error('Too Many Attempts.'), {
                status: 429,
                code: 'too_many_requests',
            }),
        );

        const { handleVkReturn, session } = useAuth();

        const result = await handleVkReturn('?code=abc');

        expect(result.error).toBe('Слишком много попыток входа. Попробуйте позже');
        expect(session.authError).toBe('Слишком много попыток входа. Попробуйте позже');
        expect(session.isAuthenticated).toBe(false);
    });

    it('не считает возвратом адрес без кода и ошибки', async () => {
        const { handleVkReturn } = useAuth();

        expect(await handleVkReturn('?utm=mail')).toEqual({ handled: false, error: null });
        expect(completeVkCallback).not.toHaveBeenCalled();
    });

    it('показывает ошибку из auth_error и не запускает обмен кода', async () => {
        const { handleReturn } = useAuth();

        const result = await handleReturn('?auth_error=state');

        expect(result.error).toBe('Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз');
        expect(completeVkCallback).not.toHaveBeenCalled();
    });
});