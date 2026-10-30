import { mount } from '@vue/test-utils';

import CatalogFilters from '@/components/catalog/CatalogFilters.vue';
import { createDefaultFilters } from '@/lib/catalogFilters';

const ZONES = [
    { value: 'ru', label: '.ru' },
    { value: 'com', label: '.com' },
    { value: 'de', label: '.de' },
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

describe('CatalogFilters', () => {
    it('рендерит четыре поля и зоны из prop', () => {
        const wrapper = mountFilters();

        expect(wrapper.text()).toContain('Цена, ₽');
        expect(wrapper.text()).toContain('Домен / зона');
        expect(wrapper.text()).toContain('Доход, ₽/мес');
        expect(wrapper.text()).toContain('Проверен');
        expect(wrapper.text()).toContain('.ru');
        expect(wrapper.text()).toContain('.com');
        expect(wrapper.text()).toContain('.de');
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
            const zoneButton = wrapper
                .findAll('[data-slot="toggle-group-item"]')
                .find((button) => button.text() === '.ru');

            await wrapper.find('[role="switch"]').trigger('click');
            await vi.advanceTimersByTimeAsync(100);
            await zoneButton.trigger('click');
            await vi.advanceTimersByTimeAsync(300);

            const payloads = wrapper.emitted('apply');

            expect(payloads).toHaveLength(1);
            expect(payloads[0][0].verifiedOnly).toBe(true);
            expect(payloads[0][0].zones).toEqual(['ru']);
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

    it('группирует зоны с доступным названием', () => {
        const wrapper = mountFilters();

        expect(wrapper.find('[role="group"]').attributes('aria-label')).toBe('Доменные зоны');
        expect(wrapper.find('[data-slot="button"][aria-pressed="true"]').text()).toBe('Все зоны');
    });
});
