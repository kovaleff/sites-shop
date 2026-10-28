import { mount } from '@vue/test-utils';

import ListingCard from '@/components/catalog/ListingCard.vue';

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function createListing(overrides = {}) {
    return {
        id: 'lst_0001',
        title: 'Сервис доставки фермерских продуктов',
        domain: 'ferma-dostavka.ru',
        domainZone: 'ru',
        description: 'Действующий проект с постоянными клиентами.',
        price: 1_250_000,
        monthlyIncome: 85_000,
        status: 'active',
        isVerified: true,
        faviconUrl: 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg"></svg>',
        publishedAt: '2026-10-21T09:30:00Z',
        ...overrides,
    };
}

describe('ListingCard', () => {
    it('отображает обязательные поля лота', () => {
        const wrapper = mount(ListingCard, { props: { listing: createListing() } });

        expect(wrapper.text()).toContain('Сервис доставки фермерских продуктов');
        expect(wrapper.text()).toContain('ferma-dostavka.ru');
        expect(wrapper.text()).toContain('.ru');
        expect(wrapper.text()).toContain('Действующий проект');
        expect(wrapper.text()).toContain('1\u00A0250\u00A0000\u00A0₽');
        expect(wrapper.text()).toContain('85\u00A0000\u00A0₽/мес');
        expect(wrapper.text()).toContain('Активен');
        expect(wrapper.text()).toContain('Да');
        expect(wrapper.text()).toContain('Связаться');
    });

    it('показывает нулевые цену и доход', () => {
        const wrapper = mount(ListingCard, {
            props: { listing: createListing({ price: 0, monthlyIncome: 0 }) },
        });

        expect(wrapper.text()).toContain('0\u00A0₽');
        expect(wrapper.text()).toContain('0\u00A0₽/мес');
    });

    it('не ломает разметку без описания', () => {
        const wrapper = mount(ListingCard, {
            props: { listing: createListing({ description: null, isVerified: false }) },
        });

        expect(wrapper.find('.line-clamp-2').exists()).toBe(true);
        expect(wrapper.text()).toContain('Нет');
    });

    it('показывает первую букву домена при отсутствии иконки', () => {
        const wrapper = mount(ListingCard, {
            props: { listing: createListing({ faviconUrl: null }) },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.html()).toContain('>F<');
    });

    it('обрезает название и описание двумя строками', () => {
        const wrapper = mount(ListingCard, { props: { listing: createListing() } });

        expect(wrapper.findAll('.line-clamp-2').length).toBeGreaterThanOrEqual(2);
    });

    it('передаёт статус и проверку текстом', () => {
        const wrapper = mount(ListingCard, {
            props: { listing: createListing({ isVerified: false }) },
        });

        expect(wrapper.text()).toContain('Нет');
        expect(wrapper.text()).toContain('Активен');
    });

    it('эмитит contact без навигации', async () => {
        const listing = createListing();
        const wrapper = mount(ListingCard, { props: { listing } });

        await wrapper.findAll('button').find((button) => button.text().includes('Связаться')).trigger('click');

        expect(wrapper.emitted('contact')).toHaveLength(1);
        expect(wrapper.emitted('contact')[0][0]).toMatchObject({ id: listing.id });
    });
});
