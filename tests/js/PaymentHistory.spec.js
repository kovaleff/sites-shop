import { mount } from '@vue/test-utils';

import PaymentHistory from '@/components/cabinet/PaymentHistory.vue';

const PAYMENTS = [
    {
        id: 1,
        invoiceNumber: 1,
        amount: 500,
        periodMonths: 1,
        status: 'paid',
        statusLabel: 'Оплачен',
        isTest: false,
        basePrice: 500,
        discountPercent: 0,
        paidAt: '2026-10-01T10:00:00+00:00',
        createdAt: '2026-10-01T09:00:00+00:00',
    },
    {
        id: 2,
        invoiceNumber: 2,
        amount: 1350,
        periodMonths: 3,
        status: 'cancelled',
        statusLabel: 'Отменён',
        isTest: false,
        basePrice: 500,
        discountPercent: 10,
        failureReason: 'expired',
        paidAt: null,
        createdAt: '2026-10-02T09:00:00+00:00',
    },
    {
        id: 3,
        invoiceNumber: 3,
        amount: 2550,
        periodMonths: 6,
        status: 'requires_review',
        statusLabel: 'Требует проверки',
        isTest: false,
        basePrice: 500,
        discountPercent: 15,
        reviewReason: 'amount_mismatch',
        paidAt: '2026-10-03T10:00:00+00:00',
        createdAt: '2026-10-03T09:00:00+00:00',
    },
];

describe('PaymentHistory', () => {
    it('показывает пустое состояние', () => {
        const wrapper = mount(PaymentHistory);

        expect(wrapper.text()).toContain('Платежей ещё не было');
    });

    it('сортирует платежи от новых к старым', () => {
        const wrapper = mount(PaymentHistory, { props: { payments: PAYMENTS } });

        const text = wrapper.text().replace(/\u00A0/g, ' ');
        const first = text.indexOf('Счёт №3');
        const second = text.indexOf('Счёт №2');
        const third = text.indexOf('Счёт №1');

        expect(first).toBeGreaterThan(-1);
        expect(first).toBeLessThan(second);
        expect(second).toBeLessThan(third);
    });

    it('показывает суммы, срок, способ и статусы', () => {
        const wrapper = mount(PaymentHistory, { props: { payments: PAYMENTS } });
        const text = wrapper.text().replace(/\u00A0/g, ' ');

        expect(text).toContain('1 350');
        expect(text).toContain('3 месяца');
        expect(text).toContain('СБП');
        expect(text).toContain('Оплачен');
        expect(text).toContain('Отменён');
        expect(text).toContain('Требует проверки');
    });

    it('показывает причину и действие для отменённой попытки', () => {
        const wrapper = mount(PaymentHistory, { props: { payments: PAYMENTS } });
        const text = wrapper.text().replace(/\u00A0/g, ' ');

        expect(text).toContain('Срок действия QR-кода истёк');
        expect(text).toContain('Повторить');
    });

    it('не показывает действие оплаты для завершённых платежей', () => {
        const wrapper = mount(PaymentHistory, { props: { payments: [PAYMENTS[0]] } });

        expect(wrapper.text()).not.toContain('Повторить');
        expect(wrapper.text()).not.toContain('Продолжить оплату');
    });

    it('показывает ошибку загрузки', () => {
        const wrapper = mount(PaymentHistory, {
            props: { error: 'Не удалось загрузить историю' },
        });

        expect(wrapper.text()).toContain('Не удалось загрузить историю');
    });

    it('корректно отображает старые записи без снимка', () => {
        const wrapper = mount(PaymentHistory, {
            props: {
                payments: [
                    {
                        id: 9,
                        invoiceNumber: 9,
                        amount: 300,
                        periodMonths: 1,
                        status: 'paid',
                        statusLabel: 'Оплачен',
                        basePrice: null,
                        discountPercent: 0,
                        createdAt: '2026-01-01T09:00:00+00:00',
                    },
                ],
            },
        });

        expect(wrapper.text()).toContain('Счёт №9');
        expect(wrapper.text()).toContain('300');
    });
});
