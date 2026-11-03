import { PAGE_SIZE } from '@/lib/catalogConfig';
import { fetchJson } from '@/services/http';

/**
 * @typedef {object} SellerProfile
 * @property {number} id
 * @property {string} displayName
 * @property {string | null} avatarUrl
 * @property {string | null} bio
 * @property {string | null} city
 * @property {string | null} registeredAt
 * @property {boolean} isVerifiedSeller
 * @property {number} publishedSitesCount
 * @property {number} soldSitesCount
 * @property {number | null} ratingAvg
 * @property {number} reviewsCount
 * @property {string} profileUrl
 */

/**
 * @param {number} page
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta(page) {
    return { page, perPage: PAGE_SIZE, total: 0, lastPage: 1, from: null, to: null };
}

/**
 * Публичный профиль продавца и его опубликованные лоты.
 *
 * @param {number | string} id
 * @param {number} [page]
 * @returns {Promise<{ seller: SellerProfile | null, listings: object[], meta: object }>}
 */
export async function fetchSeller(id, page = 1) {
    const safePage = Math.max(1, Number(page) || 1);
    const query = safePage > 1 ? `?page=${safePage}` : '';
    const data = await fetchJson(`/api/v1/sellers/${encodeURIComponent(id)}${query}`);

    return {
        seller: data?.data ?? null,
        listings: Array.isArray(data?.listings) ? data.listings : [],
        meta: data?.meta ?? emptyMeta(safePage),
    };
}
