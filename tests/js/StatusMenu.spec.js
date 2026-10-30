import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';

import StatusMenu from '@/components/cabinet/StatusMenu.vue';

/**
 * @param {string} status
 * @returns {Promise<import('@vue/test-utils').VueWrapper>}
 */
async function mountMenu(status) {
    const wrapper = mount(StatusMenu, {
        props: { status },
        attachTo: document.body,
    });

    await nextTick();

    return wrapper;
}

/**
 * @param {string} label
 * @returns {HTMLElement | undefined}
 */
function findMenuItem(label) {
    return [...document.body.querySelectorAll('[role="menuitem"]')].find((element) =>
        element.textContent.includes(label),
    );
}

describe('StatusMenu', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('не показывает меню для проданного объявления', async () => {
        const wrapper = await mountMenu('sold');

        expect(wrapper.find('button').exists()).toBe(false);
    });

    it('показывает допустимые переходы', async () => {
        const wrapper = await mountMenu('active');
        const trigger = wrapper.find('button');

        await trigger.trigger('pointerdown');
        await trigger.trigger('click');
        await nextTick();

        expect(findMenuItem('Неактивен')).toBeTruthy();
        expect(findMenuItem('Снят с продажи')).toBeTruthy();
        expect(findMenuItem('Продан')).toBeTruthy();
        expect(findMenuItem('Активен')).toBeFalsy();
    });

    it('отправляет смену статуса без подтверждения', async () => {
        const wrapper = await mountMenu('active');
        const trigger = wrapper.find('button');

        await trigger.trigger('pointerdown');
        await trigger.trigger('click');
        await nextTick();

        findMenuItem('Неактивен').dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.emitted('change')).toEqual([['inactive']]);
    });

    it('требует подтверждения перед продажей', async () => {
        const wrapper = await mountMenu('active');
        const trigger = wrapper.find('button');

        await trigger.trigger('pointerdown');
        await trigger.trigger('click');
        await nextTick();

        findMenuItem('Продан').dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(document.body.textContent).toContain('Отметить объявление проданным?');
        expect(wrapper.emitted('change')).toBeUndefined();

        const confirm = [...document.body.querySelectorAll('button')].find((element) =>
            element.textContent.includes('Отметить проданным'),
        );

        confirm.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();

        expect(wrapper.emitted('change')).toEqual([['sold']]);
    });
});