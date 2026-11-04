import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';

import PricingView from '@/views/PricingView.vue';
import { fetchPricing } from '@/services/paymentService';

vi.mock('@/services/paymentService', () => ({
    fetchPricing: vi.fn(),
}));

function mountView() {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(PricingView, {
        global: {
            plugins: [pinia],
            stubs: { RouterLink: { template: '<a><slot /></a>' } },
        },
    });
}

describe('PricingView', () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it('показывает цену, сроки и скидки', async () => {
        fetchPricing.mockResolvedValue({
            basePrice: 500,
            isActive: true,
            periods: [
                { months: 1, discountPercent: 0, total: 500, perMonth: 500 },
                { months: 3, discountPercent: 10, total: 1350, perMonth: 450 },
                { months: 12, discountPercent: 25, total: 4500, perMonth: 375 },
            ],
        });

        const wrapper = mountView();
        await flushPromises();

        const text = wrapper.text().replace(/\u00A0/g, ' ');
        expect(text).toContain('Тарифы');
        expect(text).toContain('1 месяц');
        expect(text).toContain('3 месяца');
        expect(text).toContain('12 месяцев');
        expect(text).toContain('−10%');
        expect(text).toContain('1 350');
        expect(text).toContain('500');
    });

    it('сообщает о недоступности оплаты при выключенном тарифе', async () => {
        fetchPricing.mockResolvedValue({ basePrice: 500, isActive: false, periods: [] });

        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.text().replace(/\u00A0/g, ' ')).toContain('Оплата временно недоступна');
    });

    it('показывает ошибку и кнопку повтора', async () => {
        fetchPricing.mockRejectedValue(new Error('Сеть недоступна'));

        const wrapper = mountView();
        await flushPromises();

        expect(wrapper.text()).toContain('Сеть недоступна');
        expect(wrapper.text()).toContain('Повторить');
    });
});
