# Структура проєкту DomMood

## Базовий стек

- Laravel 13 для backend, routing, API, черг, jobs і адмін-логіки.
- Vue 3 + Inertia для frontend-сторінок, auth, dashboard і майбутнього кабінету.
- Bootstrap 5 як основа UI для публічного сайту; Breeze auth scaffold використовує Tailwind-компоненти, які поступово адаптуємо під дизайн DomMood.
- MySQL для local і production. Для локального старту використовуємо MAMP MySQL `127.0.0.1:8889`.
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
    bootstrap.js  # axios, bootstrap init, глобальні browser adapters
    components/   # перевикористовувані Vue-компоненти
    composables/  # useCart, useTracking, useCatalogFilters
    pages/        # Inertia page-level Vue компоненти
    stores/       # frontend state для кошика/checkout/admin
  views/
    app.blade.php # Inertia root layout
```

## Правила реалізації

- Контролери мають бути тонкими: request validation, виклик Action/Service, повернення view/json.
- Бізнес-логіка каталогу, checkout і tracking не повинна жити у Blade або Vue-компонентах.
- Усі таблиці для каталогу й замовлень проєктуємо з індексами під фільтри, slug, статуси, created_at.
- Для tracking одразу закладаємо GA4 events, GTM dataLayer, Meta Pixel/CAPI, TikTok Pixel і UTM attribution.
- SEO-дані мають бути частиною Content/Catalog моделей: title, description, canonical, robots, schema data.
- Bootstrap-компоненти адаптуємо під дизайн-систему через `resources/css/app.css`, без хаотичних inline-style.

## Перший рекомендований roadmap

1. Налаштувати `.env` під MySQL, cache, queue і mail. Готово для local через MAMP MySQL.
2. Створити e-commerce schema: каталог, замовлення, клієнти, контент, tracking. Готово, деталі в `docs/database-schema.md`.
3. Побудувати admin layout для роботи менеджера.
4. Реалізувати Admin CRUD: color groups, size charts, categories, menus, products, product variants, images, attributes.
5. Побудувати storefront: header, footer, home, category, product page.
6. Додати checkout і tracking foundation: GTM container, dataLayer events, server-side order events.
