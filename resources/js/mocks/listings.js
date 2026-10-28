const BRANDS = [
    'autozap',
    'medconsult',
    'sneakerdrop',
    'finforum',
    'ferma-dostavka',
    'domidei',
    'lumen',
    'teplo',
    'stroyka',
    'kinozal',
    'greenhouse',
    'travelbag',
    'petcare',
    'fitpro',
    'lawcheck',
    'eduflow',
    'craftbox',
    'autolife',
    'sportroom',
    'musiclab',
    'kidsclub',
];

const TITLES = [
    'Сервис доставки фермерских продуктов',
    'Онлайн-запись к врачам',
    'Магазин лимитированных кроссовок',
    'Закрытый форум инвесторов',
    'Агрегатор загородной недвижимости',
    'Платформа онлайн-курсов по дизайну',
    'Сервис подбора автосервисов',
    'Маркетплейс handmade-товаров',
    'Медиа о городских путешествиях',
    'Магазин товаров для дома',
    'Сервис аренды спортивного снаряжения',
    'B2B-каталог строительных материалов',
    'Приложение для планирования питания',
    'Площадка для поиска репетиторов',
    'Интернет-магазин зоотоваров',
    'Сервис юридических консультаций',
    'Клуб любителей настольных игр',
    'Магазин музыкальных инструментов',
    'Платформа для детских секций',
    'Сервис бронирования переговорных',
    'Каталог независимых кофеен',
];

const DESCRIPTIONS = [
    'Действующий проект с постоянными клиентами и настроенной рекламой.',
    'Стабильный трафик из поиска, настроена аналитика и CRM.',
    'Монетизация через подписку, средний чек 4 900 ₽.',
    'Собственная команда контента и партнёрская программа.',
    'Маркетплейс с прямыми контрактами поставщиков.',
    'Сервис бронирования с автоматическими напоминаниями.',
    'Нишевое медиа с ежемесячной рекламной выручкой.',
    'Интернет-магазин с отлаженной логистикой и складом.',
    'B2B-платформа с повторяющимися платежами.',
    'Проект под SEO: 120 информационных страниц в индексе.',
];

const LONG_TITLE =
    'Сервис доставки фермерских продуктов с подпиской, собственной логистикой и партнёрской программой для локальных хозяйств';

const LONG_DESCRIPTION =
    'Проект работает более четырёх лет: собственная логистика, склад и колл-центр, договоры с 38 локальными хозяйствами, повторные продажи составляют 62% выручки, настроены рекламные кампании и программа лояльности.';

const FAVICON_COLORS = [
    ['#4a8a3a', '#2a4f1f'],
    ['#c5482a', '#7a2818'],
    ['#c8952a', '#7a5418'],
    ['#2e6fd6', '#1a3a7a'],
    ['#b9c94a', '#6a7824'],
    ['#7d59b8', '#3a1a7a'],
    ['#2a9bb8', '#1a5a7a'],
    ['#b85f8e', '#5a2848'],
];

const MS_PER_DAY = 86_400_000;
const PUBLISHED_BASE = Date.UTC(2026, 9, 21, 12, 0, 0);

/**
 * @param {string} letter
 * @param {number} seed
 * @returns {string}
 */
function createFavicon(letter, seed) {
    const [from, to] = FAVICON_COLORS[seed % FAVICON_COLORS.length];

    const svg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="${from}"/><stop offset="1" stop-color="${to}"/></linearGradient></defs><rect width="64" height="64" rx="14" fill="url(#g)"/><text x="32" y="42" font-family="Inter,Arial,sans-serif" font-size="30" font-weight="700" fill="#ffffff" text-anchor="middle">${letter}</text></svg>`;

    return `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
}

/**
 * @param {number} index
 * @returns {string | null}
 */
function zoneFor(index) {
    if (index < 24) {
        return 'ru';
    }

    if (index < 45) {
        return 'com';
    }

    if (index < 57) {
        return 'de';
    }

    return null;
}

/**
 * @param {number} index
 * @param {string | null} zone
 * @returns {string}
 */
function domainFor(index, zone) {
    const brand = BRANDS[index % BRANDS.length];
    const suffix = index >= BRANDS.length ? `-${index + 1}` : '';

    return `${brand}${suffix}.${zone ?? 'online'}`;
}

export const listings = Array.from({ length: 63 }, (_, index) => {
    const zone = zoneFor(index);
    const domain = domainFor(index, zone);
    const isPublic = index < 60;

    let price = ((index * 137_000) % 9_500_000) + 120_000;

    if (index === 0) {
        price = 0;
    } else if (index === 58) {
        price = 12_000_000;
    } else if (index === 59) {
        price = 18_000_000;
    }

    let monthlyIncome = (index * 23_000) % 920_000;

    if (index === 0) {
        monthlyIncome = 0;
    } else if (index === 56) {
        monthlyIncome = 1_200_000;
    } else if (index === 57) {
        monthlyIncome = 1_500_000;
    }

    let description = DESCRIPTIONS[index % DESCRIPTIONS.length];

    if (index === 7 || index === 17) {
        description = null;
    } else if (index === 27) {
        description = '';
    } else if (index === 30) {
        description = LONG_DESCRIPTION;
    }

    const title = index === 30 ? LONG_TITLE : `${TITLES[index % TITLES.length]}${index >= TITLES.length ? ` ${index + 1}` : ''}`;

    const faviconUrl = [4, 14, 24, 34].includes(index) ? null : createFavicon(domain.charAt(0).toUpperCase(), index);

    const publishedAt = new Date(PUBLISHED_BASE - Math.floor((index * 120) / 62) * MS_PER_DAY).toISOString();

    return {
        id: `lst_${String(index + 1).padStart(4, '0')}`,
        title,
        domain,
        domainZone: zone,
        description,
        price,
        monthlyIncome,
        status: isPublic ? 'active' : 'paused',
        isVerified: index < 38,
        faviconUrl,
        publishedAt,
        isPublic,
    };
});

export default listings;
