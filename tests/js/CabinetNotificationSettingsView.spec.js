import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import CabinetNotificationSettingsView from '@/views/cabinet/CabinetNotificationSettingsView.vue';
import {
    fetchNotificationSettings,
    updateNotificationSettings,
} from '@/services/notificationService';

vi.mock('@/services/notificationService', () => ({
    fetchNotificationSettings: vi.fn(),
    updateNotificationSettings: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const SETTINGS = [
    {
        value: 'messages',
        label: 'Новые сообщения',
        description: 'Письмо о новом сообщении в диалоге.',
        enabled: true,
    },
    {
        value: 'moderation',
        label: 'Решения модерации',
        description: 'Письмо об одобрении или отклонении.',
        enabled: true,
    },
];

/**
 * @returns {Array<object>}
 */
function settingsFixture() {
    return SETTINGS.map((item) => ({ ...item }));
}

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/cabinet/sites', name: 'cabinet-sites', component: { template: '<div />' } },
    { path: '/cabinet/hidden', name: 'cabinet-hidden', component: { template: '<div />' } },
    { path: '/cabinet/favorites', name: 'cabinet-favorites', component: { template: '<div />' } },
    { path: '/cabinet/searches', name: 'cabinet-saved-searches', component: { template: '<div />' } },
    { path: '/cabinet/messages', name: 'cabinet-messages', component: { template: '<div />' } },
    { path: '/cabinet/profile', name: 'cabinet-profile', component: { template: '<div />' } },
    {
        path: '/cabinet/settings/notifications',
        name: 'cabinet-notification-settings',
        component: { template: '<div />' },
    },
];

/**
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView() {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push('/cabinet/settings/notifications');
    await router.isReady();

    const wrapper = mount(CabinetNotificationSettingsView, {
        global: { plugins: [pinia, router] },
    });

    await flushPromises();

    return wrapper;
}

describe('CabinetNotificationSettingsView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('показывает типы уведомлений и адрес доставки', async () => {
        fetchNotificationSettings.mockResolvedValue({
            data: settingsFixture(),
            email: 'seller@example.com',
        });

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Новые сообщения');
        expect(wrapper.text()).toContain('Решения модерации');
        expect(wrapper.text()).toContain('seller@example.com');
        expect(wrapper.findAll('[role="switch"]')).toHaveLength(2);
    });

    it('отключает тип и сохраняет настройку', async () => {
        fetchNotificationSettings.mockResolvedValue({
            data: settingsFixture(),
            email: 'seller@example.com',
        });
        updateNotificationSettings.mockResolvedValue({
            data: [{ ...settingsFixture()[0], enabled: false }, settingsFixture()[1]],
            email: 'seller@example.com',
        });

        const wrapper = await mountView();

        await wrapper.findAll('[role="switch"]')[0].trigger('click');
        await flushPromises();

        expect(updateNotificationSettings).toHaveBeenCalledWith({ messages: false });
        expect(wrapper.text()).toContain('Новые сообщения');
    });

    it('возвращает переключатель в исходное состояние при ошибке', async () => {
        fetchNotificationSettings.mockResolvedValue({
            data: settingsFixture(),
            email: 'seller@example.com',
        });
        updateNotificationSettings.mockRejectedValue(new Error('Сервер недоступен'));

        const wrapper = await mountView();
        const switches = wrapper.findAll('[role="switch"]');

        expect(switches[0].attributes('data-state')).toBe('checked');

        await switches[0].trigger('click');
        await flushPromises();

        expect(wrapper.findAll('[role="switch"]')[0].attributes('data-state')).toBe('checked');
    });
});
