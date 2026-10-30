import {
    changeSiteStatus,
    createSite,
    fetchMySites,
    fetchSite,
    hideSite,
    restoreSite,
    updateSite,
} from '@/services/cabinetService';

/**
 * @param {object} payload
 * @param {number} [status]
 * @returns {object}
 */
function jsonResponse(payload, status = 200) {
    return {
        ok: status >= 200 && status < 300,
        status,
        text: async () => JSON.stringify(payload),
    };
}

describe('cabinetService', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('запрашивает список своих объявлений', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [], meta: {} }));

        vi.stubGlobal('fetch', fetchMock);

        await fetchMySites({ page: 2, hidden: true });

        const [url, options] = fetchMock.mock.calls[0];
        expect(String(url)).toBe('/api/v1/my/sites?page=2&hidden=1');
        expect(options.method).toBe('GET');
    });

    it('создаёт объявление методом POST', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 3 } }, 201));

        vi.stubGlobal('fetch', fetchMock);

        await createSite({ title: 'Лот' });

        const [url, options] = fetchMock.mock.calls[0];
        expect(String(url)).toBe('/api/v1/my/sites');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({ title: 'Лот' });
    });

    it('отправляет PATCH при обновлении и смене статуса', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: {} }));

        vi.stubGlobal('fetch', fetchMock);

        await updateSite(3, { title: 'Новый' });
        await changeSiteStatus(3, 'sold');

        expect(fetchMock.mock.calls[0][1].method).toBe('PATCH');
        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/my/sites/3');
        expect(String(fetchMock.mock.calls[1][0])).toBe('/api/v1/my/sites/3/status');
        expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({ status: 'sold' });
    });

    it('скрывает и возвращает объявление', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: {} }));

        vi.stubGlobal('fetch', fetchMock);

        await hideSite(9);
        await restoreSite(9);

        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/my/sites/9/hide');
        expect(fetchMock.mock.calls[0][1].method).toBe('POST');
        expect(fetchMock.mock.calls[1][1].method).toBe('DELETE');
    });

    it('отдаёт ошибку валидации как объект', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                jsonResponse(
                    {
                        message: 'Ошибка валидации',
                        errors: { domain: ['Этот домен уже занят другим объявлением.'] },
                    },
                    422,
                ),
            ),
        );

        await expect(fetchSite(1)).rejects.toMatchObject({
            status: 422,
            data: { errors: { domain: ['Этот домен уже занят другим объявлением.'] } },
        });
    });
});