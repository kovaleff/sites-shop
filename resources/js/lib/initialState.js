/**
 * Данные, которые сервер положил в HTML для гидратации SPA.
 *
 * Страница лота отдаётся ListingPageController с готовыми метатегами и
 * `window.__INITIAL_STATE__`, поэтому первый рендер не ждёт запроса к API.
 *
 * @returns {object}
 */
export function readInitialState() {
    const state = window.__INITIAL_STATE__;

    return state && typeof state === 'object' ? state : {};
}

/**
 * Забирает значение из начального состояния и удаляет его, чтобы повторные
 * переходы не показывали устаревшие данные.
 *
 * @param {string} key
 * @returns {object | null}
 */
export function consumeInitialState(key) {
    const state = readInitialState();
    const value = state[key];

    if (!value || typeof value !== 'object') {
        return null;
    }

    delete state[key];

    return value;
}
