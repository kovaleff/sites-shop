import { deleteJson, fetchJson, postJson } from '@/services/http';

/**
 * Идентификаторы всех избранных лотов пользователя.
 *
 * @returns {Promise<number[]>}
 */
export async function fetchFavoriteIds() {
    const data = await fetchJson('/api/v1/favorites/ids');

    return Array.isArray(data?.data) ? data.data.map(Number) : [];
}

/**
 * @param {number} [page]
 * @returns {Promise<{ data: object[], meta: object }>}
 */
export function fetchFavorites(page = 1) {
    return fetchJson(`/api/v1/favorites?page=${page}`);
}

/**
 * @param {number} siteId
 * @returns {Promise<object | null>}
 */
export function addFavorite(siteId) {
    return postJson(`/api/v1/favorites/${siteId}`);
}

/**
 * @param {number} siteId
 * @returns {Promise<object | null>}
 */
export function removeFavorite(siteId) {
    return deleteJson(`/api/v1/favorites/${siteId}`);
}
