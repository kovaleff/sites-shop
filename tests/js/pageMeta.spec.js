import { applyListingMeta } from '@/lib/pageMeta';

/**
 * @param {string} name
 * @returns {string | null}
 */
function metaContent(name) {
    return document.head.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? null;
}

/**
 * @param {string} property
 * @returns {string | null}
 */
function propertyContent(property) {
    return document.head.querySelector(`meta[property="${property}"]`)?.getAttribute('content') ?? null;
}

describe('pageMeta', () => {
    const listing = {
        title: 'Магазин товаров для дома',
        domain: 'dom-shop.ru',
        price: 1250000,
        slug: '7-magazin',
        url: '/site/7-magazin',
    };

    beforeEach(() => {
        document.head.innerHTML = '';
        document.title = '';
    });

    it('обновляет заголовок и описание', () => {
        applyListingMeta(listing);

        expect(document.title).toContain('Магазин товаров для дома');
        expect(document.title).toContain('dom-shop.ru');
        expect(metaContent('description')).toContain('dom-shop.ru');
    });

    it('проставляет canonical и og:url', () => {
        applyListingMeta(listing);

        const canonical = document.head.querySelector('link[rel="canonical"]');

        expect(canonical?.getAttribute('href')).toBe(`${window.location.origin}/site/7-magazin`);
        expect(propertyContent('og:url')).toBe(`${window.location.origin}/site/7-magazin`);
    });

    it('переиспользует существующие теги вместо дублирования', () => {
        applyListingMeta(listing);
        applyListingMeta({ ...listing, title: 'Другой лот', slug: '8-drugoi', url: '/site/8-drugoi' });

        expect(document.head.querySelectorAll('meta[name="description"]')).toHaveLength(1);
        expect(document.head.querySelectorAll('link[rel="canonical"]')).toHaveLength(1);
        expect(document.title).toContain('Другой лот');
    });

    it('ничего не делает без объявления', () => {
        applyListingMeta(null);

        expect(document.title).toBe('');
    });
});
