import { mount } from '@vue/test-utils';

import SiteMetrics from '@/components/cabinet/SiteMetrics.vue';

/**
 * @param {object} overrides
 * @returns {object}
 */
function site(overrides = {}) {
    return {
        viewsToday: 2,
        viewsWeek: 12,
        viewsMonth: 34,
        viewsTotal: 88,
        favoritesCount: 3,
        messagesCount: 5,
        unreadMessages: 0,
        ...overrides,
    };
}

/**
 * @param {object} props
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountMetrics(props) {
    return mount(SiteMetrics, {
        props,
        global: {
            stubs: {
                RouterLink: {
                    template: '<a><slot /></a>',
                },
            },
        },
    });
}

describe('SiteMetrics', () => {
    it('показывает просмотры за 7 и 30 дней, избранное и сообщения', () => {
        const wrapper = mountMetrics({ site: site() });

        expect(wrapper.text()).toContain('Просмотры:');
        expect(wrapper.text()).toContain('12 за 7 дней');
        expect(wrapper.text()).toContain('34 за 30 дней');
        expect(wrapper.text()).toContain('В избранном: 3');
        expect(wrapper.text()).toContain('Сообщения: 5');
        expect(wrapper.find('[title="Сегодня: 2 · Всего: 88"]').exists()).toBe(true);
    });

    it('выделяет непрочитанные сообщения ссылкой на диалоги', () => {
        const wrapper = mountMetrics({ site: site({ unreadMessages: 2 }) });

        expect(wrapper.text()).toContain('Сообщения: 5');
        expect(wrapper.text()).toContain('2 новых');
        expect(wrapper.find('a').exists()).toBe(true);
    });

    it('не показывает нулевые просмотры и избранное как отсутствующие данные', () => {
        const wrapper = mountMetrics({
            site: site({
                viewsToday: 0,
                viewsWeek: 0,
                viewsMonth: 0,
                viewsTotal: 0,
                favoritesCount: 0,
                messagesCount: 0,
            }),
        });

        expect(wrapper.text()).toContain('0 за 7 дней');
        expect(wrapper.text()).toContain('0 за 30 дней');
        expect(wrapper.text()).toContain('В избранном: 0');
        expect(wrapper.text()).toContain('Сообщения: 0');
    });
});
