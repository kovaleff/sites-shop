import { flushPromises, mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { createMemoryHistory, createRouter } from 'vue-router';

import CabinetReviewsView from '@/views/cabinet/CabinetReviewsView.vue';
import { fetchMyReviews, fetchPendingReviews } from '@/services/reviewService';

vi.mock('@/services/reviewService', () => ({
    fetchPendingReviews: vi.fn(),
    fetchMyReviews: vi.fn(),
    submitReview: vi.fn(),
}));

const ROUTES = [
    { path: '/', name: 'catalog', component: { template: '<div />' } },
    { path: '/cabinet/sites', name: 'cabinet-sites', component: { template: '<div />' } },
    { path: '/cabinet/messages', name: 'cabinet-messages', component: { template: '<div />' } },
    { path: '/cabinet/hidden', name: 'cabinet-hidden', component: { template: '<div />' } },
    { path: '/cabinet/favorites', name: 'cabinet-favorites', component: { template: '<div />' } },
    { path: '/cabinet/searches', name: 'cabinet-saved-searches', component: { template: '<div />' } },
    { path: '/cabinet/reviews', name: 'cabinet-reviews', component: { template: '<div />' } },
    { path: '/cabinet/profile', name: 'cabinet-profile', component: { template: '<div />' } },
    {
        path: '/cabinet/settings/notifications',
        name: 'cabinet-notification-settings',
        component: { template: '<div />' },
    },
    { path: '/site/:slug', name: 'listing', component: { template: '<div />' } },
];

const ReviewFormStub = {
    name: 'ReviewFormDialog',
    props: ['open', 'listing'],
    template: '<div data-test="review-form" :data-listing="listing?.id" />',
};

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function pendingListing(overrides = {}) {
    return {
        id: 15,
        slug: '15-magazin',
        title: 'Магазин товаров для дома',
        domain: 'dom-shop.ru',
        seller: { id: 7, displayName: 'Иван Петров' },
        ...overrides,
    };
}

/**
 * @param {object} [overrides]
 * @returns {object}
 */
function review(overrides = {}) {
    return {
        id: 3,
        rating: 5,
        comment: 'Быстрая сделка, продавец на связи.',
        status: 'approved',
        statusLabel: 'Одобрено',
        isPublished: true,
        createdAt: '2026-10-25T10:00:00Z',
        publishedAt: '2026-10-26T10:00:00Z',
        author: { id: 42, displayName: 'Пётр Покупатель', avatarUrl: null },
        site: { id: 15, title: 'Магазин товаров для дома', domain: 'dom-shop.ru' },
        ...overrides,
    };
}

/**
 * @param {{ pending?: object[], reviews?: object[] }} [payload]
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountView(payload = {}) {
    fetchPendingReviews.mockResolvedValue(payload.pending ?? []);
    fetchMyReviews.mockResolvedValue(payload.reviews ?? []);

    const pinia = createPinia();
    setActivePinia(pinia);

    const router = createRouter({ history: createMemoryHistory(), routes: ROUTES });
    await router.push('/cabinet/reviews');
    await router.isReady();

    const wrapper = mount(CabinetReviewsView, {
        global: {
            plugins: [pinia, router],
            stubs: {
                ReviewFormDialog: ReviewFormStub,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

describe('CabinetReviewsView', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('показывает лоты, по которым можно оставить отзыв', async () => {
        const wrapper = await mountView({ pending: [pendingListing()] });

        expect(wrapper.text()).toContain('Можно оценить');
        expect(wrapper.text()).toContain('Магазин товаров для дома');
        expect(wrapper.text()).toContain('Иван Петров');
    });

    it('показывает написанные отзывы со статусом модерации', async () => {
        const wrapper = await mountView({ reviews: [review()] });

        expect(wrapper.text()).toContain('Мои отзывы');
        expect(wrapper.text()).toContain('Быстрая сделка, продавец на связи.');
        expect(wrapper.text()).toContain('Одобрено');
    });

    it('показывает пустое состояние без отзывов', async () => {
        const wrapper = await mountView();

        expect(wrapper.text()).toContain('Здесь появятся отзывы после покупки лота');
    });

    it('открывает форму отзыва с выбранным лотом', async () => {
        const wrapper = await mountView({ pending: [pendingListing()] });

        await wrapper
            .findAll('button')
            .find((button) => button.text().includes('Оставить отзыв'))
            .trigger('click');

        expect(wrapper.find('[data-test="review-form"]').attributes('data-listing')).toBe('15');
    });
});
