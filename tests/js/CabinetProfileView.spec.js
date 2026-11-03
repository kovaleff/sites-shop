import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import CabinetProfileView from '@/views/cabinet/CabinetProfileView.vue';
import { fetchProfile, updateProfile } from '@/services/profileService';

vi.mock('@/services/profileService', () => ({
    fetchProfile: vi.fn(),
    updateProfile: vi.fn(),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/cabinet/sites', name: 'cabinet-sites', component: { template: '<div />' } },
    { path: '/cabinet/reviews', name: 'cabinet-reviews', component: { template: '<div />' } },
    { path: '/cabinet/messages', name: 'cabinet-messages', component: { template: '<div />' } },
    { path: '/cabinet/hidden', name: 'cabinet-hidden', component: { template: '<div />' } },
    { path: '/cabinet/favorites', name: 'cabinet-favorites', component: { template: '<div />' } },
    { path: '/cabinet/searches', name: 'cabinet-saved-searches', component: { template: '<div />' } },
    { path: '/cabinet/profile', name: 'cabinet-profile', component: { template: '<div />' } },
    {
        path: '/cabinet/settings/notifications',
        name: 'cabinet-notification-settings',
        component: { template: '<div />' },
    },
    { path: '/seller/:id', name: 'seller', component: { template: '<div />' } },
];

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function profileFixture(overrides = {}) {
    return {
        id: 7,
        displayName: 'Иван Петров',
        email: 'ivan@example.com',
        avatarUrl: null,
        bio: 'Продаю сайты с 2015 года.',
        city: 'Москва',
        profileUrl: '/seller/7',
        provider: 'vk',
        ...overrides,
    };
}

/**
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView() {
    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });

    await router.push('/cabinet/profile');
    await router.isReady();

    const wrapper = mount(CabinetProfileView, {
        global: { plugins: [pinia, router] },
    });

    await flushPromises();

    return wrapper;
}

describe('CabinetProfileView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('заполняет форму данными профиля', async () => {
        fetchProfile.mockResolvedValue(profileFixture());

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Иван Петров');
        expect(wrapper.text()).toContain('VK ID');
        expect(wrapper.find('#profile-name').element.value).toBe('Иван Петров');
        expect(wrapper.find('#profile-city').element.value).toBe('Москва');
        expect(wrapper.find('#profile-bio').element.value).toContain('Продаю сайты');
    });

    it('отправляет изменённые поля и чистит пустые значения', async () => {
        fetchProfile.mockResolvedValue(profileFixture());
        updateProfile.mockResolvedValue(profileFixture({ displayName: 'Пётр', city: null }));

        const wrapper = await mountView();

        await wrapper.find('#profile-name').setValue('Пётр');
        await wrapper.find('#profile-city').setValue('   ');
        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(updateProfile).toHaveBeenCalledWith({
            name: 'Пётр',
            bio: 'Продаю сайты с 2015 года.',
            city: null,
        });
    });

    it('показывает ошибки валидации сервера', async () => {
        fetchProfile.mockResolvedValue(profileFixture());
        updateProfile.mockRejectedValue(
            Object.assign(new Error('Ошибка валидации'), {
                status: 422,
                data: { errors: { name: ['Укажите имя, которое увидят покупатели.'] } },
            }),
        );

        const wrapper = await mountView();

        await wrapper.find('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Укажите имя, которое увидят покупатели.');
    });

    it('показывает ошибку загрузки и кнопку повтора', async () => {
        fetchProfile.mockRejectedValue(new Error('Сеть недоступна'));

        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Сеть недоступна');
        expect(wrapper.text()).toContain('Повторить');
    });
});
