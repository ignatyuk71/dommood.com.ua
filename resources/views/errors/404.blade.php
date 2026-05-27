@php
    $storeName = 'DomMood';
    $categories = [];

    try {
        $categories = \App\Models\Category::query()
            ->orderBy('id')
            ->limit(6)
            ->get(['name', 'slug']);
    } catch (\Throwable) {
        // DB unavailable — page still renders without category links
    }
@endphp
<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,follow">
        <title>Сторінку не знайдено — {{ $storeName }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <meta name="theme-color" content="#29277f">
        <style>
            :root {
                --color-text: #171717;
                --color-muted: #667085;
                --color-soft: #fdf7f3;
                --color-wine: #7b1a25;
                --color-wine-dark: #64141e;
                --color-border: #dde3ea;
            }

            * { box-sizing: border-box; }

            body {
                background: var(--color-soft);
                color: var(--color-text);
                font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                line-height: 1.5;
            }

            .e404-header {
                padding: 1.5rem clamp(1rem, 4vw, 2.5rem);
                border-bottom: 1px solid var(--color-border);
                background: #fff;
            }

            .e404-header a {
                display: inline-block;
            }

            .e404-header img {
                display: block;
                height: 36px;
                width: auto;
            }

            .e404-main {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: clamp(2.5rem, 8vw, 5rem) clamp(1rem, 4vw, 2.5rem);
            }

            .e404-card {
                max-width: 640px;
                text-align: center;
            }

            .e404-code {
                color: var(--color-wine);
                font-size: clamp(4rem, 14vw, 8rem);
                font-weight: 900;
                line-height: 1;
                letter-spacing: -0.04em;
                margin: 0 0 1rem;
            }

            .e404-card h1 {
                font-size: clamp(1.4rem, 3vw, 1.9rem);
                font-weight: 800;
                line-height: 1.2;
                margin: 0 0 0.85rem;
                color: var(--color-text);
            }

            .e404-card p {
                color: var(--color-muted);
                font-size: 1rem;
                line-height: 1.7;
                margin: 0 0 2rem;
            }

            .e404-actions {
                display: flex;
                gap: 0.85rem;
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 2.5rem;
            }

            .e404-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 0.85rem 1.6rem;
                border-radius: 999px;
                font-weight: 600;
                font-size: 0.95rem;
                text-decoration: none;
                transition: background 0.15s, color 0.15s, border-color 0.15s;
            }

            .e404-btn--primary {
                background: var(--color-wine);
                color: #fff;
                border: 1px solid var(--color-wine);
            }

            .e404-btn--primary:hover {
                background: var(--color-wine-dark);
                border-color: var(--color-wine-dark);
            }

            .e404-btn--secondary {
                background: transparent;
                color: var(--color-text);
                border: 1px solid var(--color-border);
            }

            .e404-btn--secondary:hover {
                border-color: var(--color-text);
            }

            .e404-categories {
                border-top: 1px solid var(--color-border);
                padding-top: 1.75rem;
            }

            .e404-categories-title {
                color: var(--color-muted);
                font-size: 0.82rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                margin: 0 0 1rem;
            }

            .e404-categories ul {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                justify-content: center;
            }

            .e404-categories a {
                background: #fff;
                border: 1px solid var(--color-border);
                border-radius: 999px;
                color: var(--color-text);
                font-size: 0.875rem;
                padding: 0.45rem 0.95rem;
                text-decoration: none;
                transition: border-color 0.15s, color 0.15s;
            }

            .e404-categories a:hover {
                border-color: var(--color-wine);
                color: var(--color-wine);
            }

            .e404-footer {
                background: #fff;
                border-top: 1px solid var(--color-border);
                padding: 1.25rem clamp(1rem, 4vw, 2.5rem);
                text-align: center;
                color: var(--color-muted);
                font-size: 0.875rem;
            }

            .e404-footer a {
                color: var(--color-text);
                text-decoration: none;
                margin: 0 0.5rem;
            }

            .e404-footer a:hover {
                color: var(--color-wine);
            }
        </style>
    </head>
    <body>
        <header class="e404-header">
            <a href="{{ url('/') }}" aria-label="{{ $storeName }} — головна">
                <img src="{{ asset('brand/dom-mood-wordmark-black.webp') }}" alt="{{ $storeName }}" width="220" height="36">
            </a>
        </header>

        <main class="e404-main">
            <div class="e404-card">
                <div class="e404-code">404</div>
                <h1>Сторінку не знайдено</h1>
                <p>Можливо, ви перейшли за застарілим посиланням або помилилися в адресі. Ми зібрали кілька корисних шляхів, щоб не довелося повертатися.</p>

                <div class="e404-actions">
                    <a href="{{ url('/catalog') }}" class="e404-btn e404-btn--primary">Перейти до каталогу</a>
                    <a href="{{ url('/') }}" class="e404-btn e404-btn--secondary">На головну</a>
                </div>

                @if (count($categories) > 0)
                    <div class="e404-categories">
                        <p class="e404-categories-title">Популярні категорії</p>
                        <ul>
                            @foreach ($categories as $category)
                                <li>
                                    <a href="{{ url('/catalog/'.$category->slug) }}">{{ $category->name }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </main>

        <footer class="e404-footer">
            <a href="{{ url('/') }}">Головна</a>·
            <a href="{{ url('/catalog') }}">Каталог</a>·
            <a href="{{ url('/kontakty') }}">Контакти</a>·
            <a href="{{ url('/oplata-i-dostavka') }}">Доставка</a>
        </footer>
    </body>
</html>
