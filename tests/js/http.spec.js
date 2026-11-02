import { postJson, readCsrfToken } from '@/services/http';

/**
 * @param {object | null} payload
 * @param {number} [status]
 * @returns {object}
 */
function jsonResponse(payload, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        text: async () => (payload === null ? '' : JSON.stringify(payload)),
        json: async () => payload,
    };
}

/**
 * @param {string} token
 * @returns {void}
 */
function setMetaToken(token) {
    document.head.innerHTML = `<meta name="csrf-token" content="${token}">`;
}

describe('http', () => {
    beforeEach(() => {
        setMetaToken('stale-token');
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.head.innerHTML = '';
    });

    it('перечитывает токен и повторяет запрос после 419', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse({ message: 'CSRF token mismatch.' }, 419))
            .mockResolvedValueOnce(jsonResponse({ csrfToken: 'fresh-token' }))
            .mockResolvedValueOnce(jsonResponse({ data: { id: 1 } }, 201));

        vi.stubGlobal('fetch', fetchMock);

        const result = await postJson('/api/v1/conversations/1/messages', { body: 'Привет' });

        expect(result).toEqual({ data: { id: 1 } });
        expect(fetchMock).toHaveBeenCalledTimes(3);

        const [, refreshOptions] = fetchMock.mock.calls[1];

        expect(String(fetchMock.mock.calls[1][0])).toBe('/api/v1/csrf-token');
        expect(refreshOptions.headers['X-CSRF-TOKEN']).toBeUndefined();

        const [, retryOptions] = fetchMock.mock.calls[2];

        expect(String(fetchMock.mock.calls[2][0])).toBe('/api/v1/conversations/1/messages');
        expect(retryOptions.method).toBe('POST');
        expect(retryOptions.headers['X-CSRF-TOKEN']).toBe('fresh-token');
        expect(JSON.parse(retryOptions.body)).toEqual({ body: 'Привет' });
        expect(readCsrfToken()).toBe('fresh-token');
    });

    it('не повторяет запрос бесконечно, если новый токен тоже отклонён', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(null, 419))
            .mockResolvedValueOnce(jsonResponse({ csrfToken: 'fresh-token' }))
            .mockResolvedValueOnce(jsonResponse(null, 419));

        vi.stubGlobal('fetch', fetchMock);

        await expect(postJson('/api/v1/conversations', { siteId: 1, body: 'Привет' })).rejects.toMatchObject({
            status: 419,
            message: 'Сессия устарела. Обновите страницу и попробуйте ещё раз.',
        });

        expect(fetchMock).toHaveBeenCalledTimes(3);
    });

    it('отдаёт исходную ошибку, если обновить токен не удалось', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(jsonResponse(null, 419))
            .mockRejectedValueOnce(new Error('Сеть недоступна'));

        vi.stubGlobal('fetch', fetchMock);

        await expect(postJson('/api/v1/conversations', { siteId: 1, body: 'Привет' })).rejects.toMatchObject({
            status: 419,
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });
});
