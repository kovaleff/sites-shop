import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';

import PaymentDialog from '@/components/cabinet/PaymentDialog.vue';
import { createPayment, fetchPaymentOptions } from '@/services/paymentService';

vi.mock('@/services/paymentService', async () => {
    const actual = await vi.importActual('@/services/paymentService');

    return {
        ...actual,
        fetchPaymentOptions: vi.fn(),
        createPayment: vi.fn(),
        startPayment: vi.fn(),
        startRobokassaPayment: vi.fn().mockResolvedValue('https://pay.robokassa.ru/abc'),
    };
});

vi.mock('vue-sonner', () => ({
    toast: Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
    }),
}));

const SITE = { id: 7, domain: 'example.ru', status: 'withdrawn', statusLabel: 'Снят с продажи' };

const OPTIONS = {
    canPay: true,
    basePrice: 500,
    periods: [
        { months: 1, discountPercent: 0, total: 500, perMonth: 500 },
        { months: 3, discountPercent: 10, total: 1350, perMonth: 450 },
        { months: 6, discountPercent: 15, total: 2550, perMonth: 425 },
        { months: 12, discountPercent: 25, total: 4500, perMonth: 375 },
    ],
    defaultReceiptEmail: 'seller@example.com',
    hasDeliverableEmail: true,
    targetStatus: 'active',
    targetStatusLabel: 'Активен',
    willBeHidden: false,
    activePayment: null,
    site: SITE,
};

function mountDialog() {
    const pinia = createPinia();
    setActivePinia(pinia);

    return mount(PaymentDialog, {
        props: { open: true, site: SITE },
        attachTo: document.body,
        global: { plugins: [pinia] },
    });
}

describe('PaymentDialog', () => {
    afterEach(() => {
        document.body.innerHTML = '';
        vi.clearAllMocks();
    });

    it('показывает цену, скидку и итог', async () => {
        fetchPaymentOptions.mockResolvedValue(OPTIONS);

        mountDialog();
        await flushPromises();
        await nextTick();

        const buttons = Array.from(document.querySelectorAll('button'));
        const threeMonths = buttons.find((button) => button.textContent.trim() === '3 мес.');

        threeMonths.click();
        await nextTick();

        const text = document.body.textContent.replace(/\u00A0/g, ' ');
        expect(text).toContain('Продление подписки');
        expect(text).toContain('Email для чека');
        expect(text).toContain('1 350');
        expect(text).toContain('Скидка');
    });

    it('подставляет email аккаунта', async () => {
        fetchPaymentOptions.mockResolvedValue(OPTIONS);

        mountDialog();
        await flushPromises();
        await nextTick();

        const input = document.getElementById('payment-receipt-email');
        expect(input.value).toBe('seller@example.com');
    });

    it('предупреждает о скрытом объявлении при целевом статусе «Неактивен»', async () => {
        fetchPaymentOptions.mockResolvedValue({
            ...OPTIONS,
            targetStatus: 'inactive',
            targetStatusLabel: 'Неактивен',
            willBeHidden: true,
        });

        mountDialog();
        await flushPromises();
        await nextTick();

        expect(document.body.textContent).toContain('останется скрытым из каталога');
    });

    it('показывает причину блокировки для проданного объявления', async () => {
        fetchPaymentOptions.mockResolvedValue({
            ...OPTIONS,
            canPay: false,
            blockedReason: 'sold',
            blockedMessage: 'Проданное объявление нельзя оплатить.',
        });

        mountDialog();
        await flushPromises();
        await nextTick();

        expect(document.body.textContent).toContain('Проданное объявление нельзя оплатить');
    });

    it('создаёт счёт и открывает QR', async () => {
        fetchPaymentOptions.mockResolvedValue(OPTIONS);
        createPayment.mockResolvedValue({
            data: { id: 42, status: 'pending', qrExpiresAt: new Date(Date.now() + 60000).toISOString() },
            robokassa: {
                merchantLogin: 'shop',
                outSum: '500',
                invId: 42,
                receipt: '{}',
                signature: 'sig',
                isTest: false,
                scriptUrl: 'https://auth.robokassa.ru/script.js',
            },
        });

        const wrapper = mountDialog();
        await flushPromises();
        await nextTick();

        const payButton = Array.from(document.querySelectorAll('button'))
            .find((button) => button.textContent.trim() === 'Перейти к оплате');

        payButton.click();
        await flushPromises();

        expect(createPayment).toHaveBeenCalledWith(7, {
            periodMonths: 1,
            receiptEmail: 'seller@example.com',
        });
        expect(document.body.textContent.replace(/\u00A0/g, ' ')).toContain('Ожидаем подтверждение оплаты');

        wrapper.unmount();
    });
});
