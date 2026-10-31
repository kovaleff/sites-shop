import { defineStore } from 'pinia';

import {
    fetchConversation,
    fetchConversations,
    sendMessage,
} from '@/services/conversationService';
import { useSessionStore } from '@/stores/session';

/**
 * @returns {{ page: number, perPage: number, total: number, lastPage: number, from: number|null, to: number|null }}
 */
function emptyMeta() {
    return { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null };
}

export const useConversationsStore = defineStore('conversations', {
    state: () => ({
        items: [],
        meta: emptyMeta(),
        status: 'idle',
        error: null,
        current: null,
        messages: [],
        currentStatus: 'idle',
        sending: false,
    }),

    getters: {
        isLoading: (state) => state.status === 'loading',
        isEmpty: (state) => state.status === 'success' && state.items.length === 0,
        hasError: (state) => state.status === 'error',
        isReadOnly: (state) => Boolean(state.current?.isReadOnly),
    },

    actions: {
        /**
         * @param {number} page
         * @returns {Promise<void>}
         */
        async load(page = 1) {
            this.status = 'loading';
            this.error = null;

            try {
                const payload = await fetchConversations({ page });

                this.items = payload?.data ?? [];
                this.meta = payload?.meta ?? emptyMeta();
                this.status = 'success';
            } catch (error) {
                this.status = 'error';
                this.error = error?.message ?? 'Не удалось загрузить сообщения';
            }
        },

        /**
         * @param {number} id
         * @returns {Promise<void>}
         */
        async open(id) {
            this.currentStatus = 'loading';
            this.error = null;

            try {
                const payload = await fetchConversation(id);

                this.current = payload?.data ?? null;
                this.messages = payload?.messages ?? [];
                this.currentStatus = 'success';

                const session = useSessionStore();
                await session.refreshUnread();
            } catch (error) {
                this.currentStatus = 'error';
                this.error = error?.message ?? 'Не удалось открыть диалог';
            }
        },

        /**
         * @param {string} body
         * @returns {Promise<object>}
         */
        async send(body) {
            if (!this.current) {
                throw new Error('Диалог не открыт');
            }

            this.sending = true;

            try {
                const payload = await sendMessage(this.current.id, body);

                if (payload?.data) {
                    this.messages.push(payload.data);
                }

                const session = useSessionStore();
                await session.refreshUnread();

                return payload?.data ?? null;
            } finally {
                this.sending = false;
            }
        },

        /**
         * @returns {void}
         */
        reset() {
            this.items = [];
            this.meta = emptyMeta();
            this.status = 'idle';
            this.error = null;
            this.current = null;
            this.messages = [];
            this.currentStatus = 'idle';
        },
    },
});