import { fetchJson } from '@/services/http';

/**
 * Публичные настройки SPA, которые включаются на сервере.
 *
 * @typedef {object} PublicConfig
 * @property {{ enabled: boolean, siteKey: string }} captcha
 */

/**
 * @returns {Promise<PublicConfig>}
 */
export function fetchPublicConfig() {
    return fetchJson('/api/v1/config');
}
