import { fetchJson } from '@/services/http';

/**
 * @typedef {object} Category
 * @property {number} id
 * @property {string} name
 * @property {string} slug
 */

/**
 * @returns {Promise<Category[]>}
 */
export async function fetchCategories() {
    const data = await fetchJson('/api/v1/categories');

    return Array.isArray(data?.items) ? data.items : [];
}