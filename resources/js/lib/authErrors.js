/**
 * Маппинг кодов ошибок авторизации на русские сообщения.
 * Источник: contracts/spa-auth-errors.md §2 (та же таблица, что и App\Enums\AuthError).
 *
 * @type {Record<string, string>}
 */
export const AUTH_ERROR_MESSAGES = {
    too_many_requests: 'Слишком много попыток входа. Попробуйте позже',
    not_configured: 'Вход через Яндекс пока не настроен',
    vk_not_configured: 'Вход через VK ID пока не настроен',
    cancelled: 'Вход через Яндекс отменён',
    vk_cancelled: 'Вход через VK ID отменён',
    state: 'Не удалось подтвердить вход через Яндекс. Попробуйте ещё раз',
    failed: 'Не удалось войти через Яндекс. Попробуйте ещё раз',
    vk_failed: 'Не удалось войти через VK ID. Попробуйте ещё раз',
};

/**
 * Человекочитаемое сообщение для кода из URL (?auth_error=) или из JSON-ответа.
 *
 * @param {string | null | undefined} code
 * @param {string} [fallback]
 * @returns {string}
 */
export function authErrorMessage(code, fallback = 'Не удалось войти. Попробуйте ещё раз') {
    if (code && code in AUTH_ERROR_MESSAGES) {
        return AUTH_ERROR_MESSAGES[code];
    }

    return fallback;
}

export default AUTH_ERROR_MESSAGES;