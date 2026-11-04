import {
    createPayment,
    fetchPaymentHistory,
    fetchPricing,
    paymentErrorMessage,
    startPayment,
} from '@/services/paymentService';

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

describe('paymentService', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
        vi.clearAllMocks();
    });

    it('запрашивает публичные тарифы', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ basePrice: 500, periods: [] }));
        vi.stubGlobal('fetch', fetchMock);

        await fetchPricing();

        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/pricing');
        expect(fetchMock.mock.calls[0][1].method).toBe('GET');
    });

    it('создаёт счёт методом POST', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } }, 201));
        vi.stubGlobal('fetch', fetchMock);

        await createPayment(7, { periodMonths: 3, receiptEmail: 'a@example.com' });

        const [url, options] = fetchMock.mock.calls[0];
        expect(String(url)).toBe('/api/v1/my/sites/7/payments');
        expect(options.method).toBe('POST');
        expect(JSON.parse(options.body)).toEqual({
            periodMonths: 3,
            receiptEmail: 'a@example.com',
        });
    });

    it('продолжает оплату по действующей попытке', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: {}, robokassa: {} }));
        vi.stubGlobal('fetch', fetchMock);

        await startPayment(42);

        const [url, options] = fetchMock.mock.calls[0];
        expect(String(url)).toBe('/api/v1/my/payments/42/start');
        expect(options.method).toBe('POST');
    });

    it('запрашивает историю платежей объявления', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [] }));
        vi.stubGlobal('fetch', fetchMock);

        await fetchPaymentHistory(7);

        expect(String(fetchMock.mock.calls[0][0])).toBe('/api/v1/my/sites/7/payments');
    });

    it('переводит коды ошибок в понятные тексты', () => {
        expect(paymentErrorMessage({ code: 'sold' })).toBe('Проданное объявление нельзя оплатить.');
        expect(paymentErrorMessage({ code: 'under_review' })).toContain('проверяется');
        expect(paymentErrorMessage({ code: 'domain_conflict' })).toContain('Домен уже занят');
        expect(paymentErrorMessage({ code: 'unknown', message: 'Серверная ошибка' })).toBe('Серверная ошибка');
    });
});
