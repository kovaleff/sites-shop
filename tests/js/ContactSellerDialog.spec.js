import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';

import ContactSellerDialog from '@/components/catalog/ContactSellerDialog.vue';
import { startConversation } from '@/services/conversationService';
import { useSessionStore } from '@/stores/session';

vi.mock('@/services/conversationService', () => ({
    startConversation: vi.fn(),
    fetchUnreadCount: vi.fn().mockResolvedValue({ count: 0 }),
}));

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const LISTING = { id: 42, title: 'Магазин', domain: 'shop.ru' };

/**
 * @param {boolean} authenticated
 * @returns {object}
 */
function mountDialog(authenticated) {
    const pinia = createPinia();
    setActivePinia(pinia);

    const session = useSessionStore(pinia);

    if (authenticated) {
        session.user = { id: 1, displayName: 'Покупатель', avatarUrl: null, provider: 'vk' };
        session.isAuthenticated = true;
    }

    const wrapper = mount(ContactSellerDialog, {
        props: { open: true, listing: LISTING },
        attachTo: document.body,
        global: { plugins: [pinia] },
    });

    return { wrapper, session };
}

describe('ContactSellerDialog', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('гостю предлагает войти', async () => {
        mountDialog(false);
        await nextTick();

        expect(document.body.textContent).toContain('Войдите, чтобы написать продавцу');
    });

    it('показывает объявление', async () => {
        mountDialog(false);
        await nextTick();

        expect(document.body.textContent).toContain('Магазин');
        expect(document.body.textContent).toContain('shop.ru');
    });

    it('отправляет первое сообщение и закрывает окно', async () => {
        startConversation.mockResolvedValue({ data: { id: 7 } });

        const { wrapper } = mountDialog(true);
        await nextTick();

        const textarea = document.getElementById('contact-message-body');

        textarea.value = 'Здравствуйте';
        textarea.dispatchEvent(new Event('input'));
        await nextTick();

        const sendButton = [...document.body.querySelectorAll('button')].find((element) =>
            element.textContent.includes('Отправить'),
        );

        sendButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(startConversation).toHaveBeenCalledWith(42, 'Здравствуйте');
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('показывает ошибку сервера и сохраняет текст', async () => {
        startConversation.mockRejectedValue({
            status: 422,
            message: 'Нельзя начать переписку',
            data: { errors: { siteId: ['Нельзя начать переписку по этому объявлению.'] } },
        });

        mountDialog(true);
        await nextTick();

        const textarea = document.getElementById('contact-message-body');

        textarea.value = 'Здравствуйте';
        textarea.dispatchEvent(new Event('input'));
        await nextTick();

        const sendButton = [...document.body.querySelectorAll('button')].find((element) =>
            element.textContent.includes('Отправить'),
        );

        sendButton.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await flushPromises();

        expect(document.body.textContent).toContain('Нельзя начать переписку по этому объявлению.');
        expect(document.getElementById('contact-message-body').value).toBe('Здравствуйте');
    });
});