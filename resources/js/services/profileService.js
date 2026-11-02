import { fetchJson, patchJson } from '@/services/http';

/**
 * @typedef {object} ProfileUser
 * @property {number} id
 * @property {string} displayName
 * @property {string | null} email
 * @property {string | null} avatarUrl
 * @property {string | null} bio
 * @property {string | null} city
 * @property {string} profileUrl
 * @property {'vk' | 'yandex' | null} provider
 */

/**
 * Профиль текущего пользователя для формы кабинета.
 *
 * @returns {Promise<ProfileUser | null>}
 */
export async function fetchProfile() {
    const data = await fetchJson('/api/v1/profile');

    return data?.user ?? null;
}

/**
 * Имя, описание и город, которые видны на публичной странице продавца.
 *
 * @param {{ name: string, bio: string | null, city: string | null }} payload
 * @returns {Promise<ProfileUser | null>}
 */
export async function updateProfile(payload) {
    const data = await patchJson('/api/v1/profile', payload);

    return data?.user ?? null;
}
