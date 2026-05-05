# Структура проєкту DomMood

## Базовий стек

- Laravel 13 для backend, routing, API, черг, jobs і адмін-логіки.
- Vue 3 для інтерактивних компонентів: кошик, checkout, фільтри, quick view, admin UI.
- Bootstrap 5 як основа UI без Tailwind.
- MySQL для production, SQLite тільки для локального старту/швидких тестів.
- Vite для frontend build.

## Backend

```text
app/
  Domain/
    Catalog/      # товари, категорії, варіанти, ціни, залишки, фільтри
    Checkout/     # кошик, замовлення, доставка, оплати, промокоди
    Content/      # сторінки, банери, landing pages, SEO-блоки
    Customer/     # клієнти, адреси, профілі, історія замовлень
    Marketing/    # tracking, pixels, attribution, feeds, ads integrations
    Shared/       # спільні value objects, DTO, helpers доменного рівня
  Actions/        # короткі use-case класи для бізнес-операцій
  Data/           # DTO / data objects для запитів і відповідей
  Http/
    Controllers/  # тонкі контролери, без бізнес-логіки
    Requests/     # validation + authorization
    Resources/    # API resources
  Models/         # Eloquent-моделі
  Support/        # технічні helpers, інтеграції, форматери
```

## Frontend

```text
resources/
  css/app.css
  js/
    app.js
    bootstrap/    # axios, bootstrap init, глобальні browser adapters
    components/   # перевикористовувані Vue-компоненти
    composables/  # useCart, useTracking, useCatalogFilters
    pages/        # page-level Vue entry components, якщо потрібні
    stores/       # frontend state для кошика/checkout/admin
  views/
    layouts/      # Blade layouts
    partials/     # header, footer, SEO/meta partials
    pages/        # публічні сторінки
```

## Правила реалізації

- Контролери мають бути тонкими: request validation, виклик Action/Service, повернення view/json.
- Бізнес-логіка каталогу, checkout і tracking не повинна жити у Blade або Vue-компонентах.
- Усі таблиці для каталогу й замовлень проєктуємо з індексами під фільтри, slug, статуси, created_at.
- Для tracking одразу закладаємо GA4 events, GTM dataLayer, Meta Pixel/CAPI, TikTok Pixel і UTM attribution.
- SEO-дані мають бути частиною Content/Catalog моделей: title, description, canonical, robots, schema data.
- Bootstrap-компоненти адаптуємо під дизайн-систему через `resources/css/app.css`, без хаотичних inline-style.

## Перший рекомендований roadmap

1. Налаштувати `.env` під MySQL, cache, queue і mail.
2. Створити Catalog schema: categories, products, product_variants, product_images.
3. Додати Content/SEO основу: сторінки, meta tags, schema partials.
4. Побудувати перший frontend layout: header, footer, home, category, product page.
5. Додати tracking foundation: GTM container, dataLayer events, server-side order events.
