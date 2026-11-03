import { PAGE_SIZE } from '@/lib/catalogConfig';
import { fetchJson, postJson } from '@/services/http';

/**
 * @param {number} page
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta(page) {
    return { page, perPage: PAGE_SIZE, total: 0, lastPage: 1, from: null, to: null };
}

/**
 * Опубликованные отзывы продавца.
 *
 * @param {number | string} id
 * @param {number} [page]
 * @returns {Promise<{ items: object[], meta: object }>}
 */
export async function fetchSellerReviews(id, page = 1) {
    const safePage = Math.max(1, Number(page) || 1);
    const query = safePage > 1 ? `?page=${safePage}` : '';
    const data = await fetchJson(`/api/v1/sellers/${encodeURIComponent(id)}/reviews${query}`);

    return {
        items: Array.isArray(data?.data) ? data.data : [],
        meta: data?.meta ?? emptyMeta(safePage),
    };
}

/**
 * Проданные лоты, по которым текущий покупатель ещё не оставил отзыв.
 *
 * @returns {Promise<object[]>}
 */
export async function fetchPendingReviews() {
    const data = await fetchJson('/api/v1/reviews/pending');

    return Array.isArray(data?.data) ? data.data : [];
}

/**
 * Отзывы, написанные текущим пользователем.
 *
 * @returns {Promise<object[]>}
 */
export async function fetchMyReviews() {
    const data = await fetchJson('/api/v1/reviews/mine');

    return Array.isArray(data?.data) ? data.data : [];
}

/**
 * Оставить отзыв по проданному лоту.
 *
 * @param {number | string} siteId
 * @param {{ rating: number, comment?: string }} payload
 * @returns {Promise<object | null>}
 */
export async function submitReview(siteId, payload) {
    const data = await postJson(`/api/v1/sites/${encodeURIComponent(siteId)}/reviews`, payload);

    return data?.data ?? null;
}
