import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

import { usePaymentPolling } from '@/composables/usePaymentPolling';
import { fetchPayment } from '@/services/paymentService';

vi.mock('@/services/paymentService', () => ({
    fetchPayment: vi.fn(),
}));

/**
 * @param {object} [options]
 * @returns {{ wrapper: object, api: object }}
 */
function mountPolling(options = {}) {
    let api = null;

    const Component = defineComponent({
        setup() {
            api = usePaymentPolling({
                intervalMs: 1000,
                onFinal: options.onFinal,
            });

            return () => h('div');
        },
    });

    const wrapper = mount(Component);

    return { wrapper, api };
}

describe('usePaymentPolling', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.clearAllMocks();
    });

    it('опрашивает статус и останавливается на финальном статусе', async () => {
        fetchPayment.mockResolvedValue({ data: { id: 5, status: 'pending' } });

        const onFinal = vi.fn();
        const { api } = mountPolling({ onFinal });

        api.start(5);
        expect(api.isPolling.value).toBe(true);

        await vi.advanceTimersByTimeAsync(1000);

        expect(fetchPayment).toHaveBeenCalledWith(5);

        fetchPayment.mockResolvedValue({ data: { id: 5, status: 'paid' } });

        await vi.advanceTimersByTimeAsync(1000);

        expect(onFinal).toHaveBeenCalledWith(expect.objectContaining({ status: 'paid' }));
        expect(api.isPolling.value).toBe(false);
    });

    it('продолжает опрос при статусе «Требует проверки» и останавливается', async () => {
        fetchPayment.mockResolvedValue({ data: { id: 6, status: 'requires_review' } });

        const onFinal = vi.fn();
        const { api } = mountPolling({ onFinal });

        api.start(6);
        await vi.advanceTimersByTimeAsync(1000);

        expect(onFinal).toHaveBeenCalled();
        expect(api.isPolling.value).toBe(false);
    });

    it('замедляется после сетевой ошибки', async () => {
        fetchPayment.mockRejectedValue(new Error('network'));

        const { api } = mountPolling();

        api.start(7);
        await vi.advanceTimersByTimeAsync(1000);

        expect(fetchPayment).toHaveBeenCalledTimes(1);

        // После ошибки следующий опрос не раньше чем через удвоенный интервал.
        await vi.advanceTimersByTimeAsync(1000);
        expect(fetchPayment).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(1000);
        expect(fetchPayment).toHaveBeenCalledTimes(2);
    });

    it('прекращает опрос по stop()', async () => {
        fetchPayment.mockResolvedValue({ data: { id: 8, status: 'pending' } });

        const { api } = mountPolling();

        api.start(8);
        api.stop();

        await vi.advanceTimersByTimeAsync(5000);

        expect(fetchPayment).not.toHaveBeenCalled();
    });
});
