import { mount } from '@vue/test-utils';

import CatalogPagination from '@/components/catalog/CatalogPagination.vue';
import { PaginationItem } from '@/components/ui/pagination';

/**
 * @param {number} page
 * @param {number} lastPage
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountPagination(page, lastPage) {
    return mount(CatalogPagination, { props: { page, lastPage } });
}

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper
 * @param {string} text
 * @returns {import('@vue/test-utils').DOMWrapper | undefined}
 */
function findControl(wrapper, text) {
    return wrapper.findAll('a, button').find((control) => control.text().includes(text));
}

describe('CatalogPagination', () => {
    it('не рендерится при одной странице', () => {
        const wrapper = mountPagination(1, 1);

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('делает «Назад» недоступной на первой странице', () => {
        const wrapper = mountPagination(1, 3);

        expect(findControl(wrapper, 'Назад').attributes('aria-disabled')).toBe('true');
        expect(findControl(wrapper, 'Вперёд').attributes('aria-disabled')).toBe('false');
    });

    it('делает «Вперёд» недоступной на последней странице', () => {
        const wrapper = mountPagination(3, 3);

        expect(findControl(wrapper, 'Вперёд').attributes('aria-disabled')).toBe('true');
    });

    it('помечает текущую страницу через aria-current', () => {
        const wrapper = mountPagination(2, 3);

        expect(wrapper.find('[aria-current="page"]').text()).toBe('2');
    });

    it('эмитит update:page при клике по номеру', async () => {
        const wrapper = mountPagination(1, 3);
        const secondPage = wrapper.findAllComponents(PaginationItem).find((item) => item.text() === '2');

        await secondPage.trigger('click');

        expect(wrapper.emitted('update:page')).toEqual([[2]]);
    });

    it('показывает эллипсис при большом числе страниц', () => {
        const wrapper = mountPagination(6, 12);

        expect(wrapper.find('[data-slot="pagination-ellipsis"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('1');
        expect(wrapper.text()).toContain('12');
    });

    it('использует nav с доступным названием', () => {
        const wrapper = mountPagination(1, 3);

        expect(wrapper.find('nav').attributes('aria-label')).toBe('Пагинация каталога');
    });

    it('подписывает управляющие кнопки и номера по-русски', () => {
        const wrapper = mountPagination(2, 3);
        const labels = wrapper.findAll('button').map((button) => button.attributes('aria-label'));

        expect(labels).toEqual([
            'Предыдущая страница',
            'Страница 1',
            'Страница 2',
            'Страница 3',
            'Следующая страница',
        ]);
    });
});
