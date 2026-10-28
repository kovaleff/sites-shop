import { demoUser, guestSession, MOCK_SCENARIO_KEY, SESSION_STORAGE_KEY } from '@/mocks/session';

const LOGIN_LATENCY_MS = 600;

const PROVIDER_LABELS = {
    vk: 'VK ID',
    yandex: 'Яндекс ID',
};

/**
 * @returns {string | null}
 */
function readScenario() {
    try {
        return window.localStorage.getItem(MOCK_SCENARIO_KEY);
    } catch {
        return null;
    }
}

/**
 * @param {number} milliseconds
 * @returns {Promise<void>}
 */
function delay(milliseconds) {
    return new Promise((resolve) => {
        setTimeout(resolve, milliseconds);
    });
}

/**
 * @returns {string | null}
 */
function readStoredSession() {
    try {
        return window.localStorage.getItem(SESSION_STORAGE_KEY);
    } catch {
        return null;
    }
}

/**
 * @param {string} raw
 * @returns {{ id: string, displayName: string, avatarUrl: string | null, provider: string }}
 */
function parseStoredSession(raw) {
    const parsed = JSON.parse(raw);

    if (!parsed || typeof parsed.id !== 'string' || parsed.id.length === 0) {
        throw new Error('Invalid session');
    }

    return {
        id: parsed.id,
        displayName: parsed.displayName ?? 'Покупатель',
        avatarUrl: parsed.avatarUrl ?? null,
        provider: parsed.provider ?? 'vk',
    };
}

/**
 * @param {object} user
 * @returns {void}
 */
function storeSession(user) {
    try {
        window.localStorage.setItem(SESSION_STORAGE_KEY, JSON.stringify(user));
    } catch {
        /* noop: приватный режим может запрещать запись */
    }
}

/**
 * @returns {Promise<{ authenticated: boolean, user: object | null }>}
 */
export async function fetchSession() {
    const raw = readStoredSession();

    if (!raw) {
        return { ...guestSession };
    }

    try {
        return { authenticated: true, user: parseStoredSession(raw) };
    } catch {
        try {
            window.localStorage.removeItem(SESSION_STORAGE_KEY);
        } catch {
            /* noop */
        }

        return { ...guestSession };
    }
}

/**
 * @param {'vk' | 'yandex'} provider
 * @returns {Promise<{ authenticated: true, user: object }>}
 */
export async function login(provider) {
    await delay(LOGIN_LATENCY_MS);

    if (readScenario() === 'auth-error') {
        const label = PROVIDER_LABELS[provider] ?? 'провайдер';

        throw new Error(`Не удалось войти через ${label}. Попробуйте ещё раз.`);
    }

    const user = {
        ...demoUser,
        id: `usr_${provider}`,
        provider,
    };

    storeSession(user);

    return { authenticated: true, user };
}

/**
 * @returns {Promise<void>}
 */
export async function logout() {
    try {
        window.localStorage.removeItem(SESSION_STORAGE_KEY);
    } catch {
        /* noop */
    }
}
