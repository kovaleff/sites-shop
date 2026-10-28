import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';
import { nextTick } from 'vue';

import SiteHeader from '@/components/layout/SiteHeader.vue';
import { useSessionStore } from '@/stores/session';
import { toast } from 'vue-sonner';

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' }, meta: { navKey: 'catalog' } },
    { path: '/how-it-works', name: 'how-it-works', component: { template: '<div />' }, meta: { navKey: 'how-it-works' } },
    { path: '/pricing', name: 'pricing', component: { template: '<div />' }, meta: { navKey: 'pricing' } },
];

/**
 * @param {string} path
 * @returns {Promise<import('vue-router').Router>}
 */
async function createTestRouter(path) {
    const router = createRouter({
        history: createMemoryHistory(),
        routes: ROUTES,
    });

    await router.push(path);
    await router.isReady();

    return router;
}

/**
 * @param {{ path?: string, authenticated?: boolean }} [options]
 */
async function mountHeader(options = {}) {
    const { path = '/', authenticated = false } = options;

    const pinia = createPinia();
    setActivePinia(pinia);

    const router = await createTestRouter(path);
    const session = useSessionStore(pinia);

    if (authenticated) {
        session.user = {
            id: 'usr_demo',
            displayName: 'Демо Покупатель',
            avatarUrl: null,
            provider: 'vk',
        };
        session.isAuthenticated = true;
    }

    const wrapper = mount(SiteHeader, {
        attachTo: document.body,
        global: { plugins: [pinia, router] },
    });

    return { wrapper, session, router, pinia };
}

afterEach(() => {
    document.body.innerHTML = '';
    vi.clearAllMocks();
});

describe('SiteHeader', () => {
    it('показывает ровно три пункта меню без «Помощь»', async () => {
        const { wrapper } = await mountHeader();

        const links = wrapper.find('nav[aria-label="Основное меню"]').findAll('a');

        expect(links.map((link) => link.text())).toEqual(['Каталог', 'Как это работает', 'Тарифы']);
        expect(wrapper.text()).not.toContain('Помощь');
    });

    it('определяет активный пункт по meta.navKey', async () => {
        const { wrapper } = await mountHeader({ path: '/pricing' });

        const links = wrapper.find('nav[aria-label="Основное меню"]').findAll('a');
        const active = links.find((link) => link.text() === 'Тарифы');

        expect(active.classes()).toContain('text-primary');
        expect(active.classes()).toContain('bg-primary/15');
    });

    it('показывает гостю две кнопки входа', async () => {
        const { wrapper } = await mountHeader();

        expect(wrapper.find('[aria-label="Войти через VK ID"]').exists()).toBe(true);
        expect(wrapper.find('[aria-label="Войти через Яндекс ID"]').exists()).toBe(true);
        expect(wrapper.find('[aria-label="Меню профиля"]').exists()).toBe(false);
    });

    it('вызывает loginWithVk и loginWithYandex по кнопкам', async () => {
        const { wrapper, session } = await mountHeader();
        const vkSpy = vi.spyOn(session, 'loginWithVk').mockResolvedValue({ ok: true, error: null });
        const yandexSpy = vi.spyOn(session, 'loginWithYandex').mockResolvedValue({ ok: true, error: null });

        await wrapper.find('[aria-label="Войти через VK ID"]').trigger('click');
        await wrapper.find('[aria-label="Войти через Яндекс ID"]').trigger('click');

        expect(vkSpy).toHaveBeenCalledTimes(1);
        expect(yandexSpy).toHaveBeenCalledTimes(1);
    });

    it('показывает аватар авторизованному пользователю', async () => {
        const { wrapper } = await mountHeader({ authenticated: true });

        expect(wrapper.find('[aria-label="Меню профиля"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('ДП');
        expect(wrapper.find('[aria-label="Войти через VK ID"]').exists()).toBe(false);
    });

    it('открывает меню профиля и показывает сообщение без перехода', async () => {
        const { wrapper, router } = await mountHeader({ authenticated: true });
        const pathBefore = router.currentRoute.value.fullPath;

        await wrapper.find('[aria-label="Меню профиля"]').trigger('pointerdown');
        await wrapper.find('[aria-label="Меню профиля"]').trigger('click');
        await nextTick();

        const item = [...document.body.querySelectorAll('[role="menuitem"]')].find((element) =>
            element.textContent.includes('Профиль'),
        );

        expect(item).toBeTruthy();

        item.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(toast).toHaveBeenCalled();
        expect(router.currentRoute.value.fullPath).toBe(pathBefore);
    });
});
