export const SESSION_STORAGE_KEY = 'catalog:session';
export const MOCK_SCENARIO_KEY = 'catalog:mock-scenario';

export const guestSession = Object.freeze({
    authenticated: false,
    user: null,
});

export const demoUser = Object.freeze({
    id: 'usr_demo',
    displayName: 'Демо Покупатель',
    avatarUrl: null,
    provider: 'vk',
});
