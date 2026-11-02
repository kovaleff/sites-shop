import { deleteJson, fetchJson, patchJson, postJson } from '@/services/http';

/**
 * @returns {Promise<object[]>}
 */
export async function fetchSavedSearches() {
    const data = await fetchJson('/api/v1/saved-searches');

    return Array.isArray(data?.data) ? data.data : [];
}

/**
 * @param {{ name?: string | null, frequency: string, filters: object }} payload
 * @returns {Promise<object | null>}
 */
export async function createSavedSearch(payload) {
    const data = await postJson('/api/v1/saved-searches', payload);

    return data?.data ?? null;
}

/**
 * @param {number} id
 * @param {{ name?: string | null, frequency?: string }} payload
 * @returns {Promise<object | null>}
 */
export async function updateSavedSearch(id, payload) {
    const data = await patchJson(`/api/v1/saved-searches/${id}`, payload);

    return data?.data ?? null;
}

/**
 * @param {number} id
 * @returns {Promise<void>}
 */
export async function deleteSavedSearch(id) {
    await deleteJson(`/api/v1/saved-searches/${id}`);
}
