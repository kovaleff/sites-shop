import { mount } from '@vue/test-utils';

import SimilarListings from '@/components/catalog/SimilarListings.vue';

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function createListing(overrides = {}) {
    return {
        id: 15,
        slug: '15-magazin-tovarov',
        title: 'Магазин товаров для дома',
        domain: 'dom-shop.ru',
        domainZone: 'ru',
        price: 540_000,
        monthlyIncome: 45_000,
        ...overrides,
    };
}

/**
 * @param {object} [props]
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountListings(props = {}) {
    return mount(SimilarListings, {
        props: { items: [createListing()], ...props },
        global: {
            stubs: {
                RouterLink: {
                    props: ['to'],
                    template: '<a :data-to="JSON.stringify(to)"><slot /></a>',
                },
            },
        },
    });
}

describe('SimilarListings', () => {
    it('ничего не рисует без похожих лотов', () => {
        const wrapper = mountListings({ items: [] });

        expect(wrapper.find('section').exists()).toBe(false);
    });

    it('показывает заголовок и карточки похожих лотов', () => {
        const wrapper = mountListings({
            items: [
                createListing(),
                createListing({
                    id: 16,
                    slug: '16-ferma',
                    title: 'Сервис доставки фермерских продуктов',
                    domain: 'ferma-dostavka.com',
                    domainZone: 'com',
                    price: 1_250_000,
                    monthlyIncome: 85_000,
                }),
            ],
        });

        expect(wrapper.text()).toContain('Похожие лоты');
        expect(wrapper.text()).toContain('Магазин товаров для дома');
        expect(wrapper.text()).toContain('dom-shop.ru');
        expect(wrapper.text()).toContain('.ru');
        expect(wrapper.text()).toContain('540\u00A0000\u00A0₽');
        expect(wrapper.text()).toContain('45\u00A0000\u00A0₽/мес');
        expect(wrapper.text()).toContain('Сервис доставки фермерских продуктов');
        expect(wrapper.text()).toContain('.com');

        const links = wrapper.findAll('a');

        expect(links[0].attributes('data-to')).toContain('15-magazin-tovarov');
        expect(links[1].attributes('data-to')).toContain('16-ferma');
    });

    it('показывает заглушки во время загрузки', () => {
        const wrapper = mountListings({ items: [], loading: true });

        expect(wrapper.find('section').exists()).toBe(true);
        expect(wrapper.text()).toContain('Похожие лоты');
        expect(wrapper.findAll('.h-36')).toHaveLength(3);
    });

    it('эмитит favorite с выбранным лотом', async () => {
        const wrapper = mountListings();

        await wrapper.find('button').trigger('click');

        expect(wrapper.emitted('favorite')).toHaveLength(1);
        expect(wrapper.emitted('favorite')[0][0].id).toBe(15);
    });

    it('подсвечивает избранное и блокирует кнопку на время запроса', () => {
        const wrapper = mountListings({ favoriteIds: [15], favoriteBusyId: 15 });
        const button = wrapper.find('button');

        expect(button.attributes('aria-pressed')).toBe('true');
        expect(button.attributes('disabled')).toBeDefined();
    });
});
