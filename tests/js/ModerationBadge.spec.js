import { mount } from '@vue/test-utils';

import ModerationBadge from '@/components/cabinet/ModerationBadge.vue';

describe('ModerationBadge', () => {
    it('показывает объявление на проверке', () => {
        const wrapper = mount(ModerationBadge, {
            props: { status: 'pending', label: 'На проверке' },
        });

        expect(wrapper.text()).toBe('На проверке');
        expect(wrapper.classes().join(' ')).toContain('text-warning');
    });

    it('показывает отклонённое объявление', () => {
        const wrapper = mount(ModerationBadge, {
            props: { status: 'rejected', label: 'Отклонено' },
        });

        expect(wrapper.text()).toBe('Отклонено');
        expect(wrapper.classes().join(' ')).toContain('text-destructive');
    });
});
