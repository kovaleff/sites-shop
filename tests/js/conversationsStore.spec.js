import { createPinia, setActivePinia } from 'pinia';

import {
    fetchConversation,
    fetchConversations,
    fetchUnreadCount,
    sendMessage,
} from '@/services/conversationService';
import { useConversationsStore } from '@/stores/conversations';
import { useSessionStore } from '@/stores/session';

vi.mock('@/services/conversationService', () => ({
    fetchConversations: vi.fn(),
    fetchConversation: vi.fn(),
    sendMessage: vi.fn(),
    startConversation: vi.fn(),
    fetchUnreadCount: vi.fn(),
}));

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function listPayload(overrides = {}) {
    return {
        data: [],
        meta: { page: 1, perPage: 25, total: 0, lastPage: 1, from: null, to: null },
        ...overrides,
    };
}

describe('conversations store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
        fetchUnreadCount.mockResolvedValue({ count: 0 });

        const session = useSessionStore();
        session.isAuthenticated = true;
        session.user = { id: 1, displayName: 'Пользователь', avatarUrl: null, provider: 'vk' };
    });

    it('загружает диалоги', async () => {
        fetchConversations.mockResolvedValue(
            listPayload({ data: [{ id: 1, unreadCount: 2, role: 'seller' }] }),
        );

        const store = useConversationsStore();

        await store.load();

        expect(store.items).toHaveLength(1);
        expect(store.status).toBe('success');
    });

    it('сохраняет ошибку загрузки', async () => {
        fetchConversations.mockRejectedValue(new Error('Нет связи'));

        const store = useConversationsStore();

        await store.load();

        expect(store.status).toBe('error');
        expect(store.error).toBe('Нет связи');
    });

    it('открывает диалог и сохраняет сообщения', async () => {
        fetchConversation.mockResolvedValue({
            data: { id: 3, isReadOnly: false },
            messages: [{ id: 1, body: 'Привет', isMine: false }],
        });

        const store = useConversationsStore();

        await store.open(3);

        expect(fetchConversation).toHaveBeenCalledWith(3);
        expect(store.current.id).toBe(3);
        expect(store.messages).toHaveLength(1);
        expect(fetchUnreadCount).toHaveBeenCalled();
    });

    it('добавляет отправленное сообщение в тред', async () => {
        fetchConversation.mockResolvedValue({ data: { id: 3 }, messages: [] });
        sendMessage.mockResolvedValue({ data: { id: 10, body: 'Ответ', isMine: true } });

        const store = useConversationsStore();

        await store.open(3);
        await store.send('Ответ');

        expect(sendMessage).toHaveBeenCalledWith(3, 'Ответ');
        expect(store.messages).toHaveLength(1);
        expect(store.messages[0].body).toBe('Ответ');
    });

    it('отдаёт признак режима только для чтения', async () => {
        fetchConversation.mockResolvedValue({ data: { id: 4, isReadOnly: true }, messages: [] });

        const store = useConversationsStore();

        await store.open(4);

        expect(store.isReadOnly).toBe(true);
    });
});