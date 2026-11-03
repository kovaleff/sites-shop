import { mount } from '@vue/test-utils';

import ListingCard from '@/components/catalog/ListingCard.vue';

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function createListing(overrides = {}) {
    return {
        id: 'lst_0001',
        slug: 'lst_0001-servis-dostavki',
        url: '/site/lst_0001-servis-dostavki',
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

/**
 * RouterLink заменяется ссылкой: тесту не нужен настоящий роутер,
 * но разметка `<a href>` должна остаться проверяемой.
 *
 * @param {object} listing
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountCard(listing) {
    return mount(ListingCard, {
        props: { listing },
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

describe('ListingCard', () => {
    it('отображает обязательные поля лота', () => {
        const wrapper = mountCard(createListing());

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
        const wrapper = mountCard(createListing({ price: 0, monthlyIncome: 0 }));

        expect(wrapper.text()).toContain('0\u00A0₽');
        expect(wrapper.text()).toContain('0\u00A0₽/мес');
    });

    it('не ломает разметку без описания', () => {
        const wrapper = mountCard(createListing({ description: null, isVerified: false }));

        expect(wrapper.find('.line-clamp-2').exists()).toBe(true);
        expect(wrapper.text()).toContain('Нет');
    });

    it('показывает первую букву домена при отсутствии иконки', () => {
        const wrapper = mountCard(createListing({ faviconUrl: null }));

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.html()).toContain('>F<');
    });

    it('обрезает название и описание двумя строками', () => {
        const wrapper = mountCard(createListing());

        expect(wrapper.findAll('.line-clamp-2').length).toBeGreaterThanOrEqual(2);
    });

    it('ведёт на страницу лота по слагу', () => {
        const wrapper = mountCard(createListing());

        const link = wrapper.find('h3 a');

        expect(link.exists()).toBe(true);
        expect(link.text()).toContain('Сервис доставки фермерских продуктов');
        expect(link.attributes('data-to')).toContain('lst_0001-servis-dostavki');
    });

    it('передаёт статус и проверку текстом', () => {
        const wrapper = mountCard(createListing({ isVerified: false }));

        expect(wrapper.text()).toContain('Нет');
        expect(wrapper.text()).toContain('Активен');
    });

    it('эмитит contact без навигации', async () => {
        const listing = createListing();
        const wrapper = mountCard(listing);

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Связаться'))
            .trigger('click');

        expect(wrapper.emitted('contact')).toHaveLength(1);
        expect(wrapper.emitted('contact')[0][0]).toMatchObject({ id: listing.id });
    });

    it('показывает ссылку на профиль продавца', () => {
        const wrapper = mountCard(
            createListing({
                seller: {
                    id: 7,
                    displayName: 'Иван Петров',
                    avatarUrl: null,
                    profileUrl: '/seller/7',
                },
            }),
        );

        const link = wrapper
            .findAll('a')
            .find((element) => element.text().includes('Иван Петров'));

        expect(link).toBeTruthy();
        expect(link.attributes('data-to')).toContain('/seller/7');
    });

    it('показывает рейтинг продавца, если отзывы есть', () => {
        const wrapper = mountCard(
            createListing({
                seller: {
                    id: 7,
                    displayName: 'Иван Петров',
                    avatarUrl: null,
                    ratingAvg: 4.5,
                    reviewsCount: 2,
                    profileUrl: '/seller/7',
                },
            }),
        );

        expect(wrapper.text()).toContain('4,5');
        expect(wrapper.text()).toContain('(2)');
    });

    it('не показывает блок продавца, если данных нет', () => {
        const wrapper = mountCard(createListing());

        expect(wrapper.text()).not.toContain('Иван Петров');
    });
});
