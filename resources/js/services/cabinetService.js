import { deleteJson, fetchJson, patchJson, postJson } from '@/services/http';

/**
 * @typedef {object} Site
 * @property {number} id
 * @property {string} title
 * @property {string} domain
 * @property {string | null} domainZone
 * @property {string} status
 * @property {string} statusLabel
 * @property {number} price
 * @property {number} monthlyRevenue
 * @property {string | null} paidUntil
 * @property {boolean} needsRenewal
 * @property {boolean} isPubliclyVisible
 * @property {boolean} verified
 * @property {string} verificationFilename
 * @property {number} unreadMessages
 */

/**
 * @param {{ page?: number, hidden?: boolean }} [query]
 * @returns {Promise<{ data: Site[], meta: object }>}
 */
export function fetchMySites({ page = 1, hidden = false } = {}) {
    const params = new URLSearchParams({ page: String(page) });

    if (hidden) {
        params.set('hidden', '1');
    }

    return fetchJson(`/api/v1/my/sites?${params.toString()}`);
}

/**
 * @param {number} id
 * @returns {Promise<{ data: Site }>}
 */
export function fetchSite(id) {
    return fetchJson(`/api/v1/my/sites/${id}`);
}

/**
 * @param {object} payload
 * @returns {Promise<{ data: Site }>}
 */
export function createSite(payload) {
    return postJson('/api/v1/my/sites', payload);
}

/**
 * @param {number} id
 * @param {object} payload
 * @returns {Promise<{ data: Site }>}
 */
export function updateSite(id, payload) {
    return patchJson(`/api/v1/my/sites/${id}`, payload);
}

/**
 * @param {number} id
 * @param {string} status
 * @returns {Promise<{ data: Site }>}
 */
export function changeSiteStatus(id, status) {
    return patchJson(`/api/v1/my/sites/${id}/status`, { status });
}

/**
 * @param {number} id
 * @returns {Promise<{ data: Site }>}
 */
export function hideSite(id) {
    return postJson(`/api/v1/my/sites/${id}/hide`);
}

/**
 * @param {number} id
 * @returns {Promise<{ data: Site }>}
 */
export function restoreSite(id) {
    return deleteJson(`/api/v1/my/sites/${id}/hide`);
}