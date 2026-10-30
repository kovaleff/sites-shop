import { mount } from '@vue/test-utils';

import SubscriptionBlock from '@/components/cabinet/SubscriptionBlock.vue';

describe('SubscriptionBlock', () => {
    it('показывает дату оплаты', () => {
        const wrapper = mount(SubscriptionBlock, {
            props: {
                paidUntil: '2026-11-12',
                needsRenewal: false,
                isPubliclyVisible: true,
            },
        });

        expect(wrapper.text()).toContain('Оплачено до');
        expect(wrapper.text()).not.toContain('Требует продления');
        expect(wrapper.text()).not.toContain('Скрыто из каталога');
    });

    it('показывает пометку о продлении', () => {
        const wrapper = mount(SubscriptionBlock, {
            props: {
                paidUntil: '2026-11-02',
                needsRenewal: true,
                isPubliclyVisible: true,
            },
        });

        expect(wrapper.text()).toContain('Требует продления');
    });

    it('показывает скрытие из каталога и отсутствие оплаты', () => {
        const wrapper = mount(SubscriptionBlock, {
            props: {
                paidUntil: null,
                needsRenewal: true,
                isPubliclyVisible: false,
            },
        });

        expect(wrapper.text()).toContain('Подписка не оплачена');
        expect(wrapper.text()).toContain('Скрыто из каталога');
    });
});