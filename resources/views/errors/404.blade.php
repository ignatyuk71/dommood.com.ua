<!DOCTYPE html>
<html lang="uk">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex,follow">
        <title>Сторінку не знайдено — {{ $storeName ?? 'DomMood' }}</title>
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">
        <meta name="theme-color" content="#29277f">
        @if (file_exists(public_path('hot')))
            @vite(['resources/css/storefront.css'])
        @else
            @include('storefront.partials.preload-stylesheet', ['href' => Vite::asset('resources/css/storefront.css')])
        @endif
        <style>
            .e404-section {
                background: var(--dm-color-soft);
                padding: clamp(3rem, 8vw, 6rem) 0;
            }

            .e404-card {
                margin: 0 auto;
                max-width: 640px;
                text-align: center;
            }

            .e404-code {
                color: var(--dm-color-wine);
                font-size: clamp(4.5rem, 14vw, 8rem);
                font-weight: 900;
                line-height: 1;
                letter-spacing: -0.04em;
                margin: 0 0 1rem;
            }

            .e404-card h1 {
                color: var(--dm-color-text);
                font-size: clamp(1.4rem, 3vw, 1.9rem);
                font-weight: 800;
                line-height: 1.2;
                margin: 0 0 0.85rem;
            }

            .e404-card p {
                color: var(--dm-color-muted);
                font-size: 1rem;
                line-height: 1.7;
                margin: 0 auto 2rem;
                max-width: 520px;
            }

            .e404-actions {
                display: flex;
                gap: 0.85rem;
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 2.75rem;
            }

            .e404-btn {
                align-items: center;
                border-radius: 999px;
                display: inline-flex;
                font-size: 0.95rem;
                font-weight: 600;
                justify-content: center;
                padding: 0.85rem 1.7rem;
                text-decoration: none;
                transition: background 0.15s, color 0.15s, border-color 0.15s;
            }

            .e404-btn--primary {
                background: var(--dm-color-wine);
                border: 1px solid var(--dm-color-wine);
                color: #fff;
            }

            .e404-btn--primary:hover {
                background: var(--dm-color-wine-dark);
                border-color: var(--dm-color-wine-dark);
            }

            .e404-btn--secondary {
                background: transparent;
                border: 1px solid var(--dm-color-border);
                color: var(--dm-color-text);
            }

            .e404-btn--secondary:hover {
                border-color: var(--dm-color-text);
            }

            .e404-categories {
                border-top: 1px solid var(--dm-color-border);
                padding-top: 1.75rem;
            }

            .e404-categories__title {
                color: var(--dm-color-muted);
                font-size: 0.78rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                margin: 0 0 1rem;
                text-transform: uppercase;
            }

            .e404-categories ul {
                display: flex;
                flex-wrap: wrap;
                gap: 0.55rem;
                justify-content: center;
                list-style: none;
                margin: 0;
                padding: 0;
            }

            .e404-categories a {
                background: #fff;
                border: 1px solid var(--dm-color-border);
                border-radius: 999px;
                color: var(--dm-color-text);
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
                text-decoration: none;
                transition: border-color 0.15s, color 0.15s;
            }

            .e404-categories a:hover {
                border-color: var(--dm-color-wine);
                color: var(--dm-color-wine);
            }
        </style>
        @include('storefront.partials.preconnect')
        @include('storefront.partials.google-analytics')
    </head>
    <body>
        <div class="storefront-page">
            @include('storefront.partials.site-header')

            <main>
                <section class="e404-section">
                    <div class="container">
                        <div class="e404-card">
                            <div class="e404-code">404</div>
                            <h1>Сторінку не знайдено</h1>
                            <p>Можливо, ви перейшли за застарілим посиланням або помилилися в адресі. Ми зібрали кілька корисних шляхів, щоб не довелося повертатися.</p>

                            <div class="e404-actions">
                                <a href="{{ url('/catalog') }}" class="e404-btn e404-btn--primary">Перейти до каталогу</a>
                                <a href="{{ url('/') }}" class="e404-btn e404-btn--secondary">На головну</a>
                            </div>

                            @if (! empty($popularCategories) && count($popularCategories) > 0)
                                <div class="e404-categories">
                                    <p class="e404-categories__title">Популярні категорії</p>
                                    <ul>
                                        @foreach ($popularCategories as $category)
                                            <li>
                                                <a href="{{ url('/catalog/'.$category->slug) }}">{{ $category->name }}</a>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
            </main>

            @include('storefront.partials.site-footer')
        </div>

        @include('storefront.partials.cart-drawer-root')
        @include('storefront.partials.storefront-ui-scripts')
        @include('storefront.partials.cart-drawer-scripts')
    </body>
</html>
