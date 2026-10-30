import { defineStore } from 'pinia';

import { authErrorMessage } from '@/lib/authErrors';
import {
    fetchSession,
    logout as logoutRequest,
    startVkLogin,
    startYandexLogin,
} from '@/services/sessionService';
import { fetchUnreadCount } from '@/services/conversationService';

export const useSessionStore = defineStore('session', {
    state: () => ({
        user: null,
        isAuthenticated: false,
        isInitialized: false,
        isLoggingIn: false,
        authError: null,
        unreadMessages: 0,
        csrfToken: null,
    }),

    actions: {
        /**
         * @param {{ authenticated: boolean, user: object | null }} session
         * @returns {void}
         */
        applySession(session) {
            this.user = session?.authenticated ? session.user : null;
            this.isAuthenticated = Boolean(session?.authenticated && session.user);

            if (!this.isAuthenticated) {
                this.unreadMessages = 0;
            }
        },

        /**
         * Загрузить состояние сессии с сервера при старте SPA.
         *
         * @returns {Promise<void>}
         */
        async initialize() {
            this.applySession(await fetchSession());
            this.isInitialized = true;

            if (this.isAuthenticated) {
                await this.refreshUnread();
            }
        },

        /**
         * Счётчик непрочитанных для бейджа в шапке (FR-032).
         * Ошибка запроса не ломает страницу — остаётся прежнее значение.
         *
         * @returns {Promise<void>}
         */
        async refreshUnread() {
            if (!this.isAuthenticated) {
                this.unreadMessages = 0;

                return;
            }

            try {
                const data = await fetchUnreadCount();

                this.unreadMessages = Number(data?.count ?? 0);
            } catch {
                /* noop: бейдж не критичен для работы страницы */
            }
        },

        /**
         * Реакция на 401: сессия считается потерянной без запроса к серверу.
         *
         * @returns {void}
         */
        handleUnauthorized() {
            this.user = null;
            this.isAuthenticated = false;
            this.unreadMessages = 0;
        },

        /**
         * @param {'vk' | 'yandex'} provider
         * @returns {Promise<{ ok: boolean, error: string | null }>}
         */
        async loginWithProvider(provider) {
            // Уже авторизован или попытка в процессе — новую не начинаем (FR-004).
            if (this.isAuthenticated || this.isLoggingIn) {
                return { ok: false, error: null };
            }

            this.isLoggingIn = true;
            this.authError = null;

            try {
                if (provider === 'vk') {
                    await startVkLogin();
                } else {
                    startYandexLogin();
                }

                return { ok: true, error: null };
            } catch (error) {
                this.authError = authErrorMessage(error?.code, error?.message);

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
            try {
                await logoutRequest();
            } catch {
                // Сессия на сервере могла уже истечь — интерфейс всё равно
                // возвращается в состояние гостя.
            }

            this.user = null;
            this.isAuthenticated = false;
            this.authError = null;
            this.unreadMessages = 0;
            this.csrfToken = null;
        },
    },
});