import { flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';

import CatalogFilters from '@/components/catalog/CatalogFilters.vue';
import { createDefaultFilters } from '@/lib/catalogFilters';

const ZONES = [
    { value: 'ru', label: '.ru' },
    { value: 'com', label: '.com' },
    { value: 'de', label: '.de' },
];

const CATEGORIES = [
    { id: 1, name: 'Авто-Мото', slug: 'avto-moto' },
    { id: 2, name: 'Компьютеры и IT', slug: 'kompyutery-i-it' },
];

/**
 * @param {object} [overrides]
 * @returns {import('@vue/test-utils').VueWrapper}
 */
function mountFilters(overrides = {}) {
    return mount(CatalogFilters, {
        props: {
            applied: createDefaultFilters(),
            zones: ZONES,
            zonesLoading: false,
            categories: CATEGORIES,
            categoriesLoading: false,
            activeCount: 0,
            ...overrides,
        },
    });
}

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper
 * @param {string} text
 * @returns {import('@vue/test-utils').DOMWrapper}
 */
function findButton(wrapper, text) {
    return wrapper.findAll('button').find((button) => button.text().includes(text));
}

/**
 * @param {import('@vue/test-utils').VueWrapper} wrapper
 * @param {string} triggerText
 * @returns {Promise<void>}
 */
async function openDropdown(wrapper, triggerText) {
    await findButton(wrapper, triggerText).trigger('click');
    await flushPromises();
    await nextTick();
}

describe('CatalogFilters', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('рендерит пять полей, зоны и тематики из prop', () => {
        const wrapper = mountFilters();

        expect(wrapper.text()).toContain('Цена, ₽');
        expect(wrapper.text()).toContain('Домен / зона');
        expect(wrapper.text()).toContain('Тематики');
        expect(wrapper.text()).toContain('Доход, ₽/мес');
        expect(wrapper.text()).toContain('Проверен');
        expect(wrapper.text()).toContain('Все зоны');
        expect(wrapper.text()).toContain('Все тематики');
    });

    it('скрывает счётчик при нуле и показывает фактическое число', async () => {
        const wrapper = mountFilters();

        expect(wrapper.text()).not.toContain('активных');

        await wrapper.setProps({ activeCount: 3 });

        expect(wrapper.text()).toContain('3 активных');
    });

    it('не показывает кнопку «Применить»', () => {
        const wrapper = mountFilters();

        expect(findButton(wrapper, 'Применить')).toBeUndefined();
    });

    it('эмитит apply после изменения фильтра без отдельной кнопки', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountFilters();

            await wrapper.find('[role="switch"]').trigger('click');
            await vi.advanceTimersByTimeAsync(300);

            const payload = wrapper.emitted('apply').at(-1)[0];

            expect(payload.verifiedOnly).toBe(true);
            expect(payload.priceMin).toBe(0);
            expect(payload.priceMax).toBe(10_000_000);
        } finally {
            vi.useRealTimers();
        }
    });

    it('объединяет быстрые изменения в один запрос', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountFilters();

            await wrapper.find('[role="switch"]').trigger('click');
            await vi.advanceTimersByTimeAsync(100);

            await openDropdown(wrapper, 'Все зоны');

            const zoneOption = [...document.querySelectorAll('[role="menuitemcheckbox"]')].find(
                (item) => item.textContent.trim() === '.ru',
            );

            zoneOption.click();
            await vi.advanceTimersByTimeAsync(300);

            const payloads = wrapper.emitted('apply');

            expect(payloads).toHaveLength(1);
            expect(payloads[0][0].verifiedOnly).toBe(true);
            expect(payloads[0][0].zones).toEqual(['ru']);
        } finally {
            vi.useRealTimers();
        }
    });

    it('эмитит apply с выбранной тематикой', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountFilters();

            await openDropdown(wrapper, 'Все тематики');

            const option = [...document.querySelectorAll('[role="menuitemcheckbox"]')].find((item) =>
                item.textContent.includes('Компьютеры и IT'),
            );

            option.click();
            await nextTick();
            await vi.advanceTimersByTimeAsync(300);

            const payload = wrapper.emitted('apply').at(-1)[0];

            expect(payload.categories).toEqual(['kompyutery-i-it']);
        } finally {
            vi.useRealTimers();
        }
    });

    it('эмитит reset и блокирует сброс при отсутствии активных фильтров', async () => {
        const wrapper = mountFilters();
        const resetButton = findButton(wrapper, 'Сбросить все');

        expect(resetButton.attributes('disabled')).toBeDefined();

        await wrapper.setProps({ activeCount: 2 });
        await resetButton.trigger('click');

        expect(wrapper.emitted('reset')).toHaveLength(1);
    });

    it('перезаписывает черновик при изменении applied без повторного apply', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountFilters();

            await wrapper.setProps({
                applied: {
                    ...createDefaultFilters(),
                    priceMin: 500_000,
                    verifiedOnly: false,
                },
            });
            await vi.advanceTimersByTimeAsync(300);

            expect(wrapper.emitted('apply')).toBeUndefined();

            await wrapper.find('[role="switch"]').trigger('click');
            await vi.advanceTimersByTimeAsync(300);

            const payload = wrapper.emitted('apply').at(-1)[0];

            expect(payload.priceMin).toBe(500_000);
            expect(payload.verifiedOnly).toBe(true);
        } finally {
            vi.useRealTimers();
        }
    });

    it('связывает подпись «Проверен» с переключателем', () => {
        const wrapper = mountFilters();
        const switchElement = wrapper.find('[role="switch"]');
        const label = wrapper.find(`label[for="${switchElement.attributes('id')}"]`);

        expect(label.exists()).toBe(true);
        expect(label.text()).toBe('Проверен');
    });

    it('подписывает обе ручки слайдеров ценами', () => {
        const wrapper = mountFilters();
        const thumbs = wrapper.findAll('[role="slider"]');

        expect(thumbs).toHaveLength(4);
        expect(thumbs.map((thumb) => thumb.attributes('aria-label'))).toEqual([
            'Минимальная цена',
            'Максимальная цена',
            'Минимальный доход',
            'Максимальный доход',
        ]);

        thumbs.forEach((thumb) => {
            expect(thumb.attributes('aria-valuetext')).toMatch(/₽/);
        });
    });

    it('показывает зоны в мультиселекте', async () => {
        const wrapper = mountFilters();

        await openDropdown(wrapper, 'Все зоны');

        const zoneLabels = [...document.querySelectorAll('[role="menuitemcheckbox"]')]
            .map((item) => item.textContent.trim())
            .filter((label) => label.startsWith('.'));

        expect(zoneLabels).toEqual(['.ru', '.com', '.de']);
        expect(findButton(wrapper, 'Все зоны').attributes('aria-label')).toBe(
            'Домен / зона: Все зоны',
        );
    });
});
