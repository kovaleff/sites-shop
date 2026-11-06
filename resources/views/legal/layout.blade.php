<!DOCTYPE html>
<html lang="ru" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#191a1c">

        <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('images/logo/sites-shop_logo_128.png') }}">
        <link rel="apple-touch-icon" sizes="512x512" href="{{ asset('images/logo/sites-shop_logo_512.png') }}">

        @include('partials.seo')

        @fonts

        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen flex-col bg-background text-foreground antialiased">
        <header class="border-b border-border/70">
            <div class="mx-auto flex w-full max-w-[1440px] items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('catalog') }}" class="flex min-w-0 items-center gap-2.5" aria-label="Sites-shop — на главную">
                    <img
                        src="{{ asset('images/logo/sites-shop_logo_128.png') }}"
                        alt="Логотип Sites-shop"
                        width="36"
                        height="36"
                        class="size-9 shrink-0 object-contain"
                    >
                    <span class="flex min-w-0 flex-col">
                        <span class="block truncate text-[17px] leading-tight font-bold tracking-tight">
                            Sites-<span class="text-primary">shop</span>
                        </span>
                        <span class="hidden text-[10px] tracking-[0.14em] text-muted-foreground uppercase sm:block">
                            Маркетплейс готовых сайтов
                        </span>
                    </span>
                </a>

                <nav class="flex items-center gap-4 text-sm" aria-label="Основное меню">
                    <a href="{{ route('catalog') }}" class="text-muted-foreground transition-colors hover:text-foreground">
                        Каталог
                    </a>
                    <a href="{{ route('pricing') }}" class="text-muted-foreground transition-colors hover:text-foreground">
                        Тарифы
                    </a>
                </nav>
            </div>
        </header>

        <main class="mx-auto w-full max-w-[1440px] flex-1 px-4 py-10 sm:px-6 lg:px-8">
            @yield('content')
        </main>

        <footer class="border-t border-border/70">
            <div class="mx-auto flex w-full max-w-[1440px] flex-col gap-3 px-4 py-6 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <p>© {{ date('Y') }} Sites-shop. Маркетплейс готовых сайтов.</p>

                <nav class="flex flex-wrap items-center gap-x-4 gap-y-2" aria-label="Юридическая информация">
                    <a href="{{ route('offer') }}" class="transition-colors hover:text-foreground">Публичная оферта</a>
                    <a href="{{ route('privacy') }}" class="transition-colors hover:text-foreground">Политика конфиденциальности</a>
                    <a href="{{ route('offer') }}#section-10" class="transition-colors hover:text-foreground">Реквизиты</a>
                    <a href="{{ route('pricing') }}" class="transition-colors hover:text-foreground">Тарифы</a>
                </nav>
            </div>
        </footer>
    </body>
</html>
