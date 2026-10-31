import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';

import CategoriesField from '@/components/catalog/CategoriesField.vue';

const CATEGORIES = [
    { id: 1, name: 'Авто-Мото', slug: 'avto-moto' },
    { id: 2, name: 'Бизнес и финансы', slug: 'biznes-i-finansy' },
    { id: 3, name: 'Компьютеры и IT', slug: 'kompyutery-i-it' },
];

/**
 * @param {object} [props]
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountField(props = {}) {
    return mount(CategoriesField, {
        attachTo: document.body,
        props: {
            modelValue: [],
            categories: CATEGORIES,
            ...props,
        },
    });
}

/**
 * @returns {import('@vue/test-utils').DOMWrapper}
 */
function triggerButton(wrapper) {
    return wrapper.find('button');
}

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper
 * @returns {Promise<void>}
 */
async function openMenu(wrapper) {
    await triggerButton(wrapper).trigger('click');
    await flushPromises();
    await nextTick();
}

describe('CategoriesField', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('показывает «Все тематики», когда ничего не выбрано', () => {
        const wrapper = mountField();

        expect(wrapper.text()).toContain('Тематики');
        expect(triggerButton(wrapper).text()).toBe('Все тематики');
    });

    it('перечисляет выбранные тематики в триггере', () => {
        const wrapper = mountField({ modelValue: ['avto-moto', 'kompyutery-i-it'] });

        expect(triggerButton(wrapper).text()).toBe('Авто-Мото, Компьютеры и IT');
    });

    it('добавляет тематику в модель при выборе в списке', async () => {
        const wrapper = mountField();

        await openMenu(wrapper);

        const items = [...document.querySelectorAll('[role="menuitemcheckbox"]')];
        const target = items.find((item) => item.textContent.includes('Авто-Мото'));

        expect(target).toBeTruthy();

        target.click();
        await nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual(['avto-moto']);
    });

    it('снимает выбор при повторном клике', async () => {
        const wrapper = mountField({ modelValue: ['avto-moto'] });

        await openMenu(wrapper);

        const items = [...document.querySelectorAll('[role="menuitemcheckbox"]')];
        const target = items.find((item) => item.textContent.includes('Авто-Мото'));

        target.click();
        await nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual([]);
    });

    it('сбрасывает выбор пунктом «Все тематики»', async () => {
        const wrapper = mountField({ modelValue: ['avto-moto'] });

        await openMenu(wrapper);

        const reset = [...document.querySelectorAll('[role="menuitem"]')].find((item) =>
            item.textContent.includes('Все тематики'),
        );

        reset.click();
        await nextTick();

        expect(wrapper.emitted('update:modelValue').at(-1)[0]).toEqual([]);
    });
});
