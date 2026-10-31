<!DOCTYPE html>
<html lang="ru" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" type="image/png" sizes="128x128" href="{{ asset('images/logo/sites-shop_logo_128.png') }}">
        <link rel="apple-touch-icon" sizes="512x512" href="{{ asset('images/logo/sites-shop_logo_512.png') }}">

        <title>@yield('title', 'Sites-shop — маркетплейс готовых сайтов')</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <div id="app"></div>
    </body>
</html>
