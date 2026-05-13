<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $storeName }} — технічні роботи</title>
        <meta name="robots" content="noindex, nofollow">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <meta name="theme-color" content="#29277f">
        @if (file_exists(public_path('hot')))
            @vite('resources/css/storefront.css')
        @else
            <link rel="stylesheet" href="{{ Vite::asset('resources/css/storefront.css') }}">
        @endif
    </head>
    <body>
        <main class="storefront-maintenance">
            <section class="storefront-maintenance-card">
                <div class="storefront-maintenance-brand">
                    <p>Store status</p>
                    <h1>{{ $storeName }}</h1>
                    <span>Адмінка залишається доступною для команди, публічна частина тимчасово закрита.</span>
                </div>

                <div class="storefront-maintenance-content">
                    <p class="storefront-eyebrow mb-2">Технічні роботи</p>
                    <h2>Сайт тимчасово оновлюється</h2>
                    <p>{{ $message }}</p>

                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        @if ($supportEmail)
                            <a href="mailto:{{ $supportEmail }}" class="btn btn-dark">Написати нам</a>
                        @endif
                        @if ($canLogin)
                            <a href="{{ route('login') }}" class="btn btn-outline-dark">Вхід для команди</a>
                        @endif
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
