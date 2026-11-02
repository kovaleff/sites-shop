/**
 * Обновление метатегов при клиентской навигации.
 *
 * Сервер отдаёт готовые метатеги на первой загрузке страницы лота. При переходе
 * внутри SPA серверный HTML не перезагружается, поэтому заголовок, описание
 * и canonical нужно обновить вручную — иначе в истории браузера и при шаринге
 * останутся данные предыдущей страницы.
 */

/**
 * @param {string} key
 * @param {string} value
 * @returns {void}
 */
function setMetaByName(key, value) {
    let element = document.head.querySelector(`meta[name="${key}"]`);

    if (!element) {
        element = document.createElement('meta');
        element.setAttribute('name', key);
        document.head.appendChild(element);
    }

    element.setAttribute('content', value);
}

/**
 * @param {string} key
 * @param {string} value
 * @returns {void}
 */
function setMetaByProperty(key, value) {
    let element = document.head.querySelector(`meta[property="${key}"]`);

    if (!element) {
        element = document.createElement('meta');
        element.setAttribute('property', key);
        document.head.appendChild(element);
    }

    element.setAttribute('content', value);
}

/**
 * @param {string} href
 * @returns {void}
 */
function setCanonical(href) {
    let link = document.head.querySelector('link[rel="canonical"]');

    if (!link) {
        link = document.createElement('link');
        link.setAttribute('rel', 'canonical');
        document.head.appendChild(link);
    }

    link.setAttribute('href', href);
}

/**
 * @param {object} listing
 * @returns {void}
 */
export function applyListingMeta(listing) {
    if (!listing) {
        return;
    }

    const price = new Intl.NumberFormat('ru-RU')
        .format(Number(listing.price) || 0)
        .replace(/\s/g, '\u00A0');

    const title = `${listing.title} — ${listing.domain} за ${price} ₽`;
    const description = `${listing.title} — сайт ${listing.domain} на продажу за ${price} ₽.`;
    const canonical = new URL(listing.url ?? `/site/${listing.slug}`, window.location.origin).href;

    document.title = title;

    setMetaByName('description', description);
    setMetaByName('twitter:title', title);
    setMetaByName('twitter:description', description);
    setMetaByProperty('og:title', title);
    setMetaByProperty('og:description', description);
    setMetaByProperty('og:url', canonical);
    setCanonical(canonical);
}

/**
 * Метатеги публичного профиля продавца при клиентской навигации.
 *
 * @param {object} seller
 * @returns {void}
 */
export function applySellerMeta(seller) {
    if (!seller) {
        return;
    }

    const title = `${seller.displayName} — продавец на Sites-shop`;
    const description =
        seller.bio
        || `Профиль продавца ${seller.displayName}: опубликованных лотов — ${seller.publishedSitesCount ?? 0}.`;
    const canonical = new URL(
        seller.profileUrl ?? `/seller/${seller.id}`,
        window.location.origin,
    ).href;

    document.title = title;

    setMetaByName('description', description);
    setMetaByName('twitter:title', title);
    setMetaByName('twitter:description', description);
    setMetaByProperty('og:title', title);
    setMetaByProperty('og:description', description);
    setMetaByProperty('og:url', canonical);
    setCanonical(canonical);
}
