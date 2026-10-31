import { useSessionStore } from '@/stores/session';
import { authErrorMessage } from '@/lib/authErrors';
import {
    clearVkAttempt,
    completeVkCallback,
    VK_STATE_KEY,
    VK_VERIFIER_KEY,
} from '@/services/sessionService';

/**
 * Разбор возврата после OAuth: параметры адресной строки → сообщение,
 * обмен кода VK → сессия (contracts/spa-auth-errors.md §4).
 *
 * Сообщение показывается один раз, параметры сразу удаляются из URL.
 */
export function useAuth() {
    const session = useSessionStore();

    /**
     * @param {string} [search]
     * @returns {string | null} код ошибки, если возврат был неуспешным
     */
    function handleAuthError(search = window.location.search) {
        const params = new URLSearchParams(search);
        const code = params.get('auth_error');

        if (!code) {
            return null;
        }

        session.authError = authErrorMessage(code);

        return code;
    }

    /**
     * Обмен кода VK на сессию при возврате навигацией.
     *
     * @param {string} [search]
     * @returns {Promise<{ handled: boolean, error: string | null }>}
     */
    async function handleVkReturn(search = window.location.search) {
        const params = new URLSearchParams(search);
        const code = params.get('code');
        const error = params.get('error');

        if (error) {
            clearVkAttempt();
            session.authError = authErrorMessage('vk_cancelled');

            return { handled: true, error: session.authError };
        }

        if (!code) {
            return { handled: false, error: null };
        }

        const expectedState = readSessionValue(VK_STATE_KEY);
        const returnedState = params.get('state');
        const codeVerifier = readSessionValue(VK_VERIFIER_KEY) ?? params.get('code_verifier') ?? '';

        clearVkAttempt();

        if (expectedState && returnedState && expectedState !== returnedState) {
            session.authError = authErrorMessage('vk_failed');

            return { handled: true, error: session.authError };
        }

        try {
            const result = await completeVkCallback({
                code,
                deviceId: params.get('device_id') ?? '',
                codeVerifier,
            });

            session.applySession(result);

            if (!result.authenticated) {
                session.authError = authErrorMessage('vk_failed');

                return { handled: true, error: session.authError };
            }

            return { handled: true, error: null };
        } catch (requestError) {
            session.authError = authErrorMessage(requestError?.code, requestError?.message);

            return { handled: true, error: session.authError };
        }
    }

    /**
     * Обработать возврат с любого провайдера.
     *
     * @param {string} [search]
     * @returns {Promise<{ error: string | null }>}
     */
    async function handleReturn(search = window.location.search) {
        const authErrorCode = handleAuthError(search);

        if (authErrorCode) {
            await session.initialize();

            return { error: session.authError };
        }

        const vk = await handleVkReturn(search);

        if (vk.handled) {
            return { error: vk.error };
        }

        return { error: null };
    }

    return { session, handleAuthError, handleVkReturn, handleReturn };
}

/**
 * @param {string} key
 * @returns {string | null}
 */
function readSessionValue(key) {
    try {
        return sessionStorage.getItem(key);
    } catch {
        return null;
    }
}

export default useAuth;