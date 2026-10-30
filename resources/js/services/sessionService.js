import { fetchJson, postJson, writeCsrfToken } from '@/services/http';

export const VK_STATE_KEY = 'vk_oauth_state';
export const VK_VERIFIER_KEY = 'vk_oauth_verifier';
export const VK_REDIRECT_PATH = '/api/vk/callback';
export const VK_VERIFIER_COOKIE = 'vk_code_verifier';

/**
 * @typedef {object} SessionUser
 * @property {number} id
 * @property {string} displayName
 * @property {string | null} email
 * @property {string | null} avatarUrl
 * @property {'vk' | 'yandex' | null} provider
 */

/**
 * @typedef {object} SessionState
 * @property {boolean} authenticated
 * @property {SessionUser | null} user
 */

/**
 * @returns {SessionState}
 */
function guestSession() {
    return { authenticated: false, user: null };
}

/**
 * @param {unknown} payload
 * @returns {SessionState}
 */
function normalizeSession(payload) {
    if (!payload || typeof payload !== 'object') {
        return guestSession();
    }

    const user = payload.user;

    if (!payload.authenticated || !user || typeof user !== 'object') {
        return guestSession();
    }

    return {
        authenticated: true,
        user: {
            id: user.id,
            displayName: user.displayName ?? 'Покупатель',
            email: user.email ?? null,
            avatarUrl: user.avatarUrl ?? null,
            provider: user.provider ?? null,
        },
    };
}

/**
 * @returns {string}
 */
function randomToken() {
    const bytes = new Uint8Array(32);
    crypto.getRandomValues(bytes);

    return Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0')).join('');
}

/**
 * code_verifier нужен бэкенду при обмене кода, но в redirect_uri его класть нельзя:
 * адрес обязан совпадать с «Доверенным redirect URL» из кабинета VK ID.
 *
 * @param {string} codeVerifier
 * @returns {void}
 */
function storeVerifierCookie(codeVerifier) {
    const secure = window.location.protocol === 'https:';

    document.cookie = [
        `${VK_VERIFIER_COOKIE}=${codeVerifier}`,
        'path=/',
        'max-age=600',
        `samesite=${secure ? 'none' : 'lax'}`,
        ...(secure ? ['secure'] : []),
    ].join('; ');
}

/**
 * Текущее состояние сессии: гость или авторизованный пользователь.
 *
 * @returns {Promise<SessionState>}
 */
export async function fetchSession() {
    try {
        return normalizeSession(await fetchJson('/api/v1/user'));
    } catch {
        return guestSession();
    }
}

/**
 * Запуск входа через VK ID: PKCE + VKID SDK.
 *
 * @returns {Promise<void>}
 */
export async function startVkLogin() {
    const clientId = import.meta.env.VITE_VK_CLIENT_ID ?? '';

    if (!clientId) {
        throw Object.assign(new Error('Вход через VK ID пока не настроен'), {
            code: 'vk_not_configured',
        });
    }

    const state = randomToken();
    const codeVerifier = randomToken();

    sessionStorage.setItem(VK_STATE_KEY, state);
    sessionStorage.setItem(VK_VERIFIER_KEY, codeVerifier);
    storeVerifierCookie(codeVerifier);

    const VKID = await import('@vkid/sdk');

    VKID.Config.init({
        app: clientId,
        redirectUrl: `${window.location.origin}${VK_REDIRECT_PATH}`,
        state,
        codeVerifier,
        scope: 'offline',
    });

    await VKID.Auth.login();
}

/**
 * Запуск входа через Яндекс: серверный OAuth-поток.
 *
 * @returns {void}
 */
export function startYandexLogin() {
    window.location.assign('/api/ya/redirect');
}

/**
 * Обмен кода VK на сессию. Служебные данные передаются телом запроса,
 * чтобы не попадать в URL (contracts/spa-auth-errors.md §4.1).
 *
 * @param {{ code: string, deviceId?: string, codeVerifier?: string }} payload
 * @returns {Promise<SessionState>}
 */
export async function completeVkCallback({ code, deviceId = '', codeVerifier = '' }) {
    return normalizeSession(
        await postJson(VK_REDIRECT_PATH, {
            code,
            device_id: deviceId,
            code_verifier: codeVerifier,
        }),
    );
}

/**
 * Выход из аккаунта: сессия уничтожается, CSRF-токен обновляется.
 *
 * @returns {Promise<void>}
 */
export async function logout() {
    const data = await postJson('/api/v1/auth/logout');

    if (data?.csrfToken) {
        writeCsrfToken(data.csrfToken);
    }
}

/**
 * Очистить временные данные попытки входа VK.
 *
 * @returns {void}
 */
export function clearVkAttempt() {
    try {
        sessionStorage.removeItem(VK_STATE_KEY);
        sessionStorage.removeItem(VK_VERIFIER_KEY);
    } catch {
        /* noop: приватный режим может запрещать запись */
    }
}