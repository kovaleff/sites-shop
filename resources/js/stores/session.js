import { defineStore } from 'pinia';

import { fetchSession, login, logout } from '@/services/sessionService';

export const useSessionStore = defineStore('session', {
    state: () => ({
        user: null,
        isAuthenticated: false,
        isLoggingIn: false,
        authError: null,
    }),

    actions: {
        /**
         * @returns {Promise<void>}
         */
        async initialize() {
            const session = await fetchSession();

            this.user = session.user;
            this.isAuthenticated = session.authenticated;
        },

        /**
         * @param {'vk' | 'yandex'} provider
         * @returns {Promise<{ ok: boolean, error: string | null }>}
         */
        async loginWithProvider(provider) {
            if (this.isLoggingIn) {
                return { ok: false, error: null };
            }

            this.isLoggingIn = true;
            this.authError = null;

            try {
                const session = await login(provider);

                this.user = session.user;
                this.isAuthenticated = true;

                return { ok: true, error: null };
            } catch (error) {
                this.authError = error?.message ?? 'Не удалось выполнить вход';

                return { ok: false, error: this.authError };
            } finally {
                this.isLoggingIn = false;
            }
        },

        /**
         * @returns {Promise<{ ok: boolean, error: string | null }>}
         */
        loginWithVk() {
            return this.loginWithProvider('vk');
        },

        /**
         * @returns {Promise<{ ok: boolean, error: string | null }>}
         */
        loginWithYandex() {
            return this.loginWithProvider('yandex');
        },

        /**
         * @returns {Promise<void>}
         */
        async logout() {
            await logout();

            this.user = null;
            this.isAuthenticated = false;
            this.authError = null;
        },
    },
});
