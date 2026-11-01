@php
    use App\Support\Seo\SeoMeta;

    $seo = $meta ?? SeoMeta::forCatalog()->toArray();
    $seoTitle = $seo['title'] ?? SeoMeta::SITE_NAME;
    $seoDescription = $seo['description'] ?? '';
    $seoCanonical = $seo['canonical'] ?? url()->current();
    $seoImage = $seo['image'] ?? url('images/logo/sites-shop_logo_512.png');
    $seoType = $seo['type'] ?? 'website';
    $seoIndexable = $seo['indexable'] ?? true;
    $seoJsonLd = $seo['jsonLd'] ?? null;
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoIndexable ? 'index, follow, max-image-preview:large' : 'noindex, follow' }}">
<link rel="canonical" href="{{ $seoCanonical }}">

<meta property="og:type" content="{{ $seoType }}">
<meta property="og:site_name" content="{{ SeoMeta::SITE_NAME }}">
<meta property="og:locale" content="ru_RU">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $seoTitle }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

@if ($seoJsonLd)
    <script type="application/ld+json">
        {!! json_encode($seoJsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endif
