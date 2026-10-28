import { mount } from '@vue/test-utils';

import ListingList from '@/components/catalog/ListingList.vue';

const ITEMS = [
    {
        id: 'lst_0001',
        title: 'Первый лот',
        domain: 'first.ru',
        domainZone: 'ru',
        description: 'Описание первого лота',
        price: 100_000,
        monthlyIncome: 10_000,
        status: 'active',
        isVerified: true,
        faviconUrl: null,
        publishedAt: '2026-10-21T09:30:00Z',
    },
    {
        id: 'lst_0002',
        title: 'Второй лот',
        domain: 'second.com',
        domainZone: 'com',
        description: null,
        price: 200_000,
        monthlyIncome: 20_000,
        status: 'active',
        isVerified: false,
        faviconUrl: null,
        publishedAt: '2026-10-20T09:30:00Z',
    },
];

describe('ListingList', () => {
    it('показывает счётчик по items.length и total', () => {
        const wrapper = mount(ListingList, { props: { items: ITEMS, total: 60 } });

        expect(wrapper.text()).toContain('Показано');
        expect(wrapper.text()).toContain('2 из 60');
    });

    it('выставляет aria-busy при загрузке', () => {
        const wrapper = mount(ListingList, {
            props: { items: ITEMS, total: 60, loading: true },
        });

        expect(wrapper.find('#catalog-list').attributes('aria-busy')).toBe('true');
    });

    it('рендерит карточки по id', () => {
        const wrapper = mount(ListingList, { props: { items: ITEMS, total: 60 } });

        expect(wrapper.findAll('[data-slot="card"]')).toHaveLength(2);
        expect(wrapper.text()).toContain('Первый лот');
        expect(wrapper.text()).toContain('Второй лот');
    });

    it('сохраняет контейнер catalog-list', () => {
        const wrapper = mount(ListingList, { props: { items: ITEMS, total: 60 } });

        expect(wrapper.find('#catalog-list').exists()).toBe(true);
    });
});
