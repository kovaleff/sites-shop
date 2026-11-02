/**
 * Единый HTTP-клиент SPA (contracts/auth-api.yaml).
 *
 * Все запросы ходят с cookie-сессией (credentials: 'same-origin'),
 * заголовками X-CSRF-TOKEN / X-Requested-With и Accept: application/json.
 * Если сессия успела перегенерироваться, токен перечитывается при 419.
 */
const CSRF_META_SELECTOR = 'meta[name="csrf-token"]';
const CSRF_REFRESH_PATH = '/api/v1/csrf-token';

let unauthorizedHandler = null;
let csrfRefreshPromise = null;
let csrfTokenCache = null;

/**
 * Единый обработчик потери сессии: регистрируется в app.js,
 * чтобы http.js не зависел от стора и роутера.
 *
 * @param {(() => void) | null} handler
 * @returns {void}
 */
export function setUnauthorizedHandler(handler) {
    unauthorizedHandler = handler;
}

/**
 * @returns {string}
 */
export function readCsrfToken() {
    const meta = document.querySelector(CSRF_META_SELECTOR);

    if (meta) {
        return meta.getAttribute('content') ?? '';
    }

    return csrfTokenCache ?? '';
}

/**
 * Обновить токен после logout или перегенерации сессии (spa-auth-errors.md §3.4).
 *
 * @param {string} token
 * @returns {void}
 */
export function writeCsrfToken(token) {
    csrfTokenCache = token;

    const meta = document.querySelector(CSRF_META_SELECTOR);

    if (meta) {
        meta.setAttribute('content', token);
    }
}

/**
 * Запросить у сервера актуальный CSRF-токен текущей сессии и обновить мета-тег.
 *
 * Нужен, когда страница отрисована со старым токеном: сессия перегенерирована
 * входом в другой вкладке, страница восстановлена из bfcache или истекла.
 * Параллельные вызовы переиспользуют один запрос.
 *
 * @returns {Promise<string | null>}
 */
export function refreshCsrfToken() {
    if (csrfRefreshPromise) {
        return csrfRefreshPromise;
    }

    csrfRefreshPromise = fetch(CSRF_REFRESH_PATH, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => (response.ok ? response.json() : null))
        .then((data) => {
            if (typeof data?.csrfToken !== 'string' || data.csrfToken === '') {
                return null;
            }

            writeCsrfToken(data.csrfToken);

            return data.csrfToken;
        })
        .catch(() => null)
        .finally(() => {
            csrfRefreshPromise = null;
        });

    return csrfRefreshPromise;
}

/**
 * Бросить ошибку с кодом auth-ошибки и текстом из JSON-ответа.
 *
 * @param {Response} response
 * @param {object | null} data
 * @returns {never}
 */
function throwHttpError(response, data) {
    const code = data?.error ?? null;
    const message = data?.message
        ?? (response.status === 419
            ? 'Сессия устарела. Обновите страницу и попробуйте ещё раз.'
            : `Ошибка запроса (${response.status})`);
    const error = new Error(message);

    error.status = response.status;
    error.code = code;
    error.data = data ?? null;

    throw error;
}

/**
 * Выполнить запрос. При 419 токен перечитывается и запрос повторяется один раз,
 * поэтому устаревший мета-тег не превращается в ошибку для пользователя.
 *
 * @param {string} path
 * @param {{ method?: 'GET' | 'POST' | 'PATCH' | 'DELETE', body?: object | null, headers?: Record<string, string> }} options
 * @param {boolean} isRetry
 * @returns {Promise<object | null>}
 */
async function send(path, { method = 'GET', body = null, headers = {} } = {}, isRetry = false) {
    const response = await fetch(path, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-CSRF-TOKEN': readCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            ...(body ? { 'Content-Type': 'application/json' } : {}),
            ...headers,
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    let data = null;
    const text = await response.text();

    if (text) {
        try {
            data = JSON.parse(text);
        } catch {
            data = null;
        }
    }

    if (!response.ok) {
        if (response.status === 419 && !isRetry) {
            const token = await refreshCsrfToken();

            if (token !== null) {
                return send(path, { method, body, headers }, true);
            }
        }

        if (response.status === 401 && unauthorizedHandler) {
            unauthorizedHandler();
        }

        throwHttpError(response, data);
    }

    return data;
}

/**
 * @param {string} path
 * @returns {Promise<object | null>}
 */
export const fetchJson = (path) => send(path);

/**
 * @param {string} path
 * @param {object} [body]
 * @returns {Promise<object | null>}
 */
export const postJson = (path, body = {}) => send(path, { method: 'POST', body });

/**
 * @param {string} path
 * @param {object} [body]
 * @returns {Promise<object | null>}
 */
export const patchJson = (path, body = {}) => send(path, { method: 'PATCH', body });

/**
 * @param {string} path
 * @returns {Promise<object | null>}
 */
export const deleteJson = (path) => send(path, { method: 'DELETE' });

const http = {
    fetchJson,
    postJson,
    patchJson,
    deleteJson,
    readCsrfToken,
    refreshCsrfToken,
    writeCsrfToken,
};

export default http;