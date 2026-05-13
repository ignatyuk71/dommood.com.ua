<div
    class="storefront-cart-drawer-shell"
    data-cart-drawer
    data-cart-drawer-url="{{ route('cart.drawer') }}"
    aria-hidden="true"
    inert
>
    <button type="button" class="storefront-cart-backdrop" data-cart-close aria-label="Закрити кошик"></button>
    <aside class="storefront-cart-panel" data-cart-drawer-panel role="dialog" aria-modal="true" aria-label="Кошик" tabindex="-1">
        <div class="storefront-cart-panel__head">
            <button type="button" data-cart-close aria-label="Закрити кошик">
                <svg viewBox="0 0 24 24"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <strong>Кошик</strong>
            <span aria-hidden="true"></span>
        </div>
        <section class="storefront-cart-empty">
            <p>Завантажуємо кошик...</p>
        </section>
    </aside>
</div>
