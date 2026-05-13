<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Статус оплати</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f5f4ff;
            color: #343241;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        main {
            width: min(520px, calc(100vw - 32px));
            border-radius: 16px;
            background: #fff;
            padding: 32px;
            box-shadow: 0 20px 60px rgba(61, 58, 101, 0.12);
        }

        h1 {
            margin: 0 0 12px;
            font-size: 28px;
        }

        p {
            margin: 0;
            color: #64748b;
            line-height: 1.6;
        }
    </style>
</head>
<body>
<main>
    <h1>Оплату прийнято в обробку</h1>
    <p>
        @if($orderNumber)
            Замовлення #{{ $orderNumber }}. 
        @endif
        Фінальний статус підтвердиться server callback від LiqPay. Після запуску storefront цю сторінку замінимо на нормальний thank-you екран.
    </p>
</main>
</body>
</html>
