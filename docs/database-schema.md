# База даних DomMood

Проєкт орієнтований на невеликий український інтернет-магазин без мультимовності. Основна ціль схеми: не ускладнювати як маркетплейс, але мати нормальне e-commerce ядро для каталогу, замовлень, CRM, SEO й tracking.

## Принципи

- Ціни зберігаються в копійках у полях `*_cents`, щоб не залежати від floating point округлення.
- Покупці живуть у `customers`, а `users` використовуються для адмінів/менеджерів.
- Товар має базову ціну, але залишки ведуться на рівні `product_variants`.
- Замовлення зберігає snapshot товару в `order_items`, щоб історія не ламалась після редагування товару.
- SEO поля є безпосередньо в сутностях каталогу й контенту: `meta_title`, `meta_description`, `seo_text`, `canonical_url`.
- UTM attribution зберігається в `customers`, `carts`, `orders`.

## Каталог

```text
brands
categories
products
category_product
product_variants
product_images
attributes
attribute_values
product_attribute_values
product_variant_attribute_values
product_relations
```

Важливі можливості:
- дерево категорій через `categories.parent_id`;
- кілька категорій на товар через `category_product`;
- основна категорія товару через `products.primary_category_id`;
- варіанти товару з окремими SKU, цінами й залишками;
- фільтри через `attributes` / `attribute_values`;
- фото товару та фото конкретного варіанту;
- повʼязані товари для upsell/cross-sell.

## Продажі й CRM

```text
customers
carts
cart_items
orders
order_items
order_status_histories
```

Важливі можливості:
- checkout без обовʼязкового кабінету покупця;
- abandoned cart foundation через `carts`;
- історія статусів замовлення;
- customer LTV через `orders_count` і `total_spent_cents`;
- UTM attribution для аналітики й реклами.

## Контент, SEO, маркетинг

```text
content_pages
banners
promocodes
tracking_settings
```

Важливі можливості:
- службові сторінки: доставка, оплата, повернення;
- банери для головної, категорій і промо;
- промокоди з fixed/percent знижками;
- tracking settings для GA4, GTM, Meta Pixel/CAPI, TikTok Pixel, Google Ads.

## Наступний технічний шар

1. Admin CRUD для `categories`.
2. Admin CRUD для `products` без варіантів.
3. Варіанти, фото й атрибути товарів.
4. Публічний каталог і картка товару.
5. Кошик, checkout, order creation.
6. GA4/GTM/Meta events: `view_item`, `add_to_cart`, `begin_checkout`, `purchase`.
