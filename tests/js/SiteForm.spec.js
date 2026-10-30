import { mount } from '@vue/test-utils';

import SiteForm from '@/components/cabinet/SiteForm.vue';

/**
 * @returns {object}
 */
function initial() {
    return {
        title: '',
        domain: '',
        description: '',
        price: '',
        monthlyRevenue: '',
        hasTrademark: false,
        categories: [],
    };
}

describe('SiteForm', () => {
    it('показывает ошибку поля из ответа сервера', () => {
        const wrapper = mount(SiteForm, {
            props: {
                initial: initial(),
                categories: [{ id: 1, name: 'Компьютеры и IT' }],
                errors: { title: ['Название должно быть от 3 до 255 символов.'] },
            },
        });

        expect(wrapper.text()).toContain('Название должно быть от 3 до 255 символов.');
    });

    it('показывает счётчик описания и обязательность тематик', () => {
        const wrapper = mount(SiteForm, {
            props: {
                initial: initial(),
                categories: [{ id: 1, name: 'Компьютеры и IT' }],
                errors: { categories: ['Выберите хотя бы одну тематику.'] },
            },
        });

        expect(wrapper.text()).toContain('0 / 5000 символов');
        expect(wrapper.text()).toContain('Выберите хотя бы одну тематику.');
        expect(wrapper.text()).toContain('Компьютеры и IT');
    });

    it('отправляет данные формы в camelCase', async () => {
        const wrapper = mount(SiteForm, {
            props: {
                initial: initial(),
                categories: [{ id: 1, name: 'Компьютеры и IT' }],
            },
        });

        await wrapper.find('#site-title').setValue('Магазин');
        await wrapper.find('#site-domain').setValue('example.ru');
        await wrapper.find('#site-description').setValue('а'.repeat(60));
        await wrapper.find('#site-price').setValue('1500');
        await wrapper.find('#site-revenue').setValue('250');
        await wrapper.find('input[type="checkbox"]').setValue(true);

        await wrapper.find('form').trigger('submit');

        expect(wrapper.emitted('submit')).toHaveLength(1);
        expect(wrapper.emitted('submit')[0][0]).toMatchObject({
            title: 'Магазин',
            domain: 'example.ru',
            price: 1500,
            monthlyRevenue: 250,
            categories: [1],
        });
    });

    it('блокирует кнопку сохранения во время отправки', () => {
        const wrapper = mount(SiteForm, {
            props: { initial: initial(), categories: [], submitting: true },
        });

        const submit = wrapper.find('button[type="submit"]');

        expect(submit.attributes('disabled')).toBeDefined();
        expect(submit.text()).toContain('Сохраняем');
    });
});