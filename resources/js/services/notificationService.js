import { fetchJson, patchJson } from '@/services/http';

/**
 * @typedef {object} NotificationSetting
 * @property {string} value
 * @property {string} label
 * @property {string} description
 * @property {boolean} enabled
 */

/**
 * Настройки уведомлений текущего пользователя.
 *
 * @returns {Promise<{ data: NotificationSetting[], email: string | null }>}
 */
export function fetchNotificationSettings() {
    return fetchJson('/api/v1/notification-settings');
}

/**
 * Включить или выключить выбранные типы писем.
 *
 * @param {Record<string, boolean>} settings
 * @returns {Promise<{ data: NotificationSetting[], email: string | null }>}
 */
export function updateNotificationSettings(settings) {
    return patchJson('/api/v1/notification-settings', { settings });
}
