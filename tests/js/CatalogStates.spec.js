import { mount } from '@vue/test-utils';

import CatalogEmptyState from '@/components/catalog/CatalogEmptyState.vue';
import CatalogErrorState from '@/components/catalog/CatalogErrorState.vue';

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper
 * @param {string} text
 * @returns {import('@vue/test-utils').DOMWrapper | undefined}
 */
function findButton(wrapper, text) {
    return wrapper.findAll('button').find((button) => button.text().includes(text));
}

describe('CatalogEmptyState', () => {
    it('показывает пустой каталог без кнопки', () => {
        const wrapper = mount(CatalogEmptyState, {
            props: { title: 'Предложений пока нет', description: 'Загляните позже.' },
        });

        expect(wrapper.text()).toContain('Предложений пока нет');
        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('показывает «Ничего не найдено» с кнопкой сброса', async () => {
        const wrapper = mount(CatalogEmptyState, {
            props: {
                title: 'Ничего не найдено',
                description: 'Измените фильтры.',
                showReset: true,
            },
        });

        expect(wrapper.text()).toContain('Ничего не найдено');

        await findButton(wrapper, 'Сбросить все').trigger('click');

        expect(wrapper.emitted('reset')).toHaveLength(1);
    });
});

describe('CatalogErrorState', () => {
    it('показывает сообщение и эмитит retry/reset', async () => {
        const wrapper = mount(CatalogErrorState, {
            props: { message: 'Не удалось загрузить каталог' },
        });

        expect(wrapper.text()).toContain('Не удалось загрузить каталог');

        await findButton(wrapper, 'Повторить').trigger('click');
        await findButton(wrapper, 'Сбросить все').trigger('click');

        expect(wrapper.emitted('retry')).toHaveLength(1);
        expect(wrapper.emitted('reset')).toHaveLength(1);
    });
});
