import { fetchJson, postJson } from '@/services/http';

/**
 * @typedef {object} ConversationSummary
 * @property {number} id
 * @property {'buyer' | 'seller'} role
 * @property {object} site
 * @property {object} counterparty
 * @property {object | null} lastMessage
 * @property {number} unreadCount
 * @property {boolean} isReadOnly
 * @property {string | null} lastMessageAt
 */

/**
 * Диалоги текущего пользователя.
 *
 * @param {{ page?: number }} [query]
 * @returns {Promise<{ data: ConversationSummary[], meta: object }>}
 */
export function fetchConversations({ page = 1 } = {}) {
    return fetchJson(`/api/v1/conversations?page=${page}`);
}

/**
 * История диалога. Сервер помечает входящие прочитанными.
 *
 * @param {number} id
 * @returns {Promise<{ data: ConversationSummary, messages: object[] }>}
 */
export function fetchConversation(id) {
    return fetchJson(`/api/v1/conversations/${id}`);
}

/**
 * Начать или продолжить переписку по объявлению.
 *
 * @param {number} siteId
 * @param {string} body
 * @returns {Promise<{ data: ConversationSummary }>}
 */
export function startConversation(siteId, body) {
    return postJson('/api/v1/conversations', { siteId, body });
}

/**
 * Ответ в существующем диалоге.
 *
 * @param {number} conversationId
 * @param {string} body
 * @returns {Promise<{ data: object }>}
 */
export function sendMessage(conversationId, body) {
    return postJson(`/api/v1/conversations/${conversationId}/messages`, { body });
}

/**
 * Количество непрочитанных для бейджа.
 *
 * @returns {Promise<{ count: number }>}
 */
export function fetchUnreadCount() {
    return fetchJson('/api/v1/messages/unread');
}