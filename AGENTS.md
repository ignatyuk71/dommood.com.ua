# Інструкції для Codex у цьому проєкті

Мова комунікації: українська. Коментарі в коді також українською.

Стек: Laravel, Vue, MySQL, REST APIs, Bootstrap 5.

Архітектура:
- адмінка — окремий внутрішній модуль на Vue + Inertia під `/admin`;
- публічний сайт/storefront — server-rendered Laravel Blade + HTML/CSS/Bootstrap 5;
- Vue/JS у storefront використовувати тільки точково для інтерактивності: кошик, фільтри, вибір варіацій, пошук, quick checkout;
- критичний SEO і продажний контент storefront має рендеритися сервером у готовий HTML: товари, ціни, H1/H2, meta, canonical, breadcrumbs, schema.org, OpenGraph;
- storefront має бути максимально легким: мінімум зайвих скриптів, performance-first, Core Web Vitals і crawlability для Google/Merchant/Meta/TikTok;
- адмінка не має залежати від конкретного дизайну storefront, щоб публічний сайт можна було замінити без переробки CRM/e-commerce ядра.

Пріоритети:
- чиста архітектура й масштабованість;
- performance-first підхід;
- e-commerce growth, CRO, SEO і tracking;
- production-ready рішення без toy examples;
- мінімальні зміни HTML, якщо існуюча структура вже достатня;
- Laravel best practices: migrations, indexes, validation, authorization, structure;
- frontend на Bootstrap 5 з modern clean UI.

Для SEO, ads і analytics враховувати:
- GA4, GTM, events, pixels, UTM, attribution;
- Meta Ads, TikTok Ads, Google Ads;
- schema, headings, canonical, performance, content structure.
