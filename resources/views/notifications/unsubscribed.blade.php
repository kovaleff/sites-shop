<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Уведомления отключены — Sites-shop</title>
    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: #f6f7f9;
            color: #1a1d21;
            font: 16px/1.6 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
        main {
            width: 100%;
            max-width: 480px;
            padding: 32px;
            border: 1px solid #e3e6ea;
            border-radius: 16px;
            background: #fff;
        }
        h1 { margin: 0 0 12px; font-size: 22px; line-height: 1.25; }
        p { margin: 0 0 12px; }
        a { color: #2563eb; }
    </style>
</head>
<body>
<main>
    <h1>Уведомления отключены</h1>
    <p>Мы больше не будем присылать письма «{{ $label }}».</p>
    <p>
        Остальные уведомления и их включение — в
        <a href="{{ $manageUrl }}">настройках кабинета</a>.
    </p>
    <p><a href="{{ url('/') }}">Вернуться в каталог</a></p>
</main>
</body>
</html>
