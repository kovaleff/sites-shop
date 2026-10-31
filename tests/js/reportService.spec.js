import { reportSite } from '@/services/reportService';

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

describe('reportService', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('отправляет жалобу на объявление', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 7, status: 'new' } }, 201));

        vi.stubGlobal('fetch', fetchMock);

        await reportSite(42, { reason: 'spam', comment: 'Реклама сторонних сервисов' });

        const [url, options] = fetchMock.mock.calls[0];

        expect(String(url)).toBe('/api/v1/sites/42/reports');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({
            reason: 'spam',
            comment: 'Реклама сторонних сервисов',
        });
    });

    it('не отправляет пустой комментарий', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 7 } }, 201));

        vi.stubGlobal('fetch', fetchMock);

        await reportSite(42, { reason: 'other', comment: '   ' });

        const [, options] = fetchMock.mock.calls[0];

        expect(JSON.parse(options.body)).toEqual({ reason: 'other', comment: null });
    });
});
