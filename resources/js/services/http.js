/**
 * Единый HTTP-клиент SPA (contracts/auth-api.yaml).
 *
 * Все запросы ходят с cookie-сессией (credentials: 'same-origin'),
 * заголовками X-CSRF-TOKEN / X-Requested-With и Accept: application/json.
 */
const CSRF_META_SELECTOR = 'meta[name="csrf-token"]';

let unauthorizedHandler = null;

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

    return meta?.getAttribute('content') ?? '';
}

/**
 * Обновить токен после logout, где сервер регенерирует CSRF (spa-auth-errors.md §3.4).
 *
 * @param {string} token
 * @returns {void}
 */
export function writeCsrfToken(token) {
    const meta = document.querySelector(CSRF_META_SELECTOR);

    if (meta) {
        meta.setAttribute('content', token);
    }
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
    const message = data?.message ?? `Ошибка запроса (${response.status})`;
    const error = new Error(message);

    error.status = response.status;
    error.code = code;
    error.data = data ?? null;

    throw error;
}

/**
 * @param {string} path
 * @param {object} [options]
 * @param {'GET' | 'POST' | 'PATCH' | 'DELETE'} [options.method]
 * @param {object} [options.body]
 * @param {Record<string, string>} [options.headers]
 * @returns {Promise<object | null>}
 */
async function request(path, { method = 'GET', body = null, headers = {} } = {}) {
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
export const fetchJson = (path) => request(path);

/**
 * @param {string} path
 * @param {object} [body]
 * @returns {Promise<object | null>}
 */
export const postJson = (path, body = {}) => request(path, { method: 'POST', body });

/**
 * @param {string} path
 * @param {object} [body]
 * @returns {Promise<object | null>}
 */
export const patchJson = (path, body = {}) => request(path, { method: 'PATCH', body });

/**
 * @param {string} path
 * @returns {Promise<object | null>}
 */
export const deleteJson = (path) => request(path, { method: 'DELETE' });

const http = {
    fetchJson,
    postJson,
    patchJson,
    deleteJson,
    readCsrfToken,
    writeCsrfToken,
};

export default http;