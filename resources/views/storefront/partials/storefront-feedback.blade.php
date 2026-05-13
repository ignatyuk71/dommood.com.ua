@php
    $storefrontToastMessage = session('cart_status');
@endphp

<div class="storefront-toast-region" data-storefront-toast-region aria-live="polite" aria-atomic="false"></div>

@if ($storefrontToastMessage)
    <script type="application/json" data-storefront-toast-payload>
        {!! json_encode([
            'type' => 'success',
            'message' => $storefrontToastMessage,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endif

<script>
    (() => {
        if (window.StorefrontFeedback) {
            return;
        }

        const toastRegion = document.querySelector('[data-storefront-toast-region]');
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

        if (!toastRegion) {
            return;
        }

        let toastCounter = 0;

        const toastIcons = {
            success: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>',
            error: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8v5"/><path d="M12 17h.01"/><path d="M10.3 4.5h3.4L21 18.5H3L10.3 4.5Z"/></svg>',
            info: '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 17v-5"/><path d="M12 8h.01"/><circle cx="12" cy="12" r="9"/></svg>',
        };

        const dismissToast = (toast) => {
            toast.classList.remove('is-visible');
            toast.classList.add('is-leaving');
            window.setTimeout(() => toast.remove(), 220);
        };

        const showToast = ({ message, type = 'success', duration = 3200 } = {}) => {
            if (!message) {
                return null;
            }

            const toast = document.createElement('div');
            const id = `storefront-toast-${Date.now()}-${toastCounter += 1}`;
            const safeType = ['success', 'error', 'info'].includes(type) ? type : 'info';

            toast.id = id;
            toast.className = `storefront-toast is-${safeType}`;
            toast.setAttribute('role', safeType === 'error' ? 'alert' : 'status');
            toast.innerHTML = `
                <span class="storefront-toast__icon">${toastIcons[safeType]}</span>
                <span class="storefront-toast__message"></span>
                <button type="button" class="storefront-toast__close" aria-label="Закрити повідомлення">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12"/><path d="M18 6 6 18"/></svg>
                </button>
            `;

            toast.querySelector('.storefront-toast__message').textContent = message;
            toast.querySelector('button')?.addEventListener('click', () => dismissToast(toast));
            toastRegion.appendChild(toast);

            requestAnimationFrame(() => toast.classList.add('is-visible'));

            if (duration > 0) {
                window.setTimeout(() => dismissToast(toast), duration);
            }

            return id;
        };

        const isVisible = (element) => {
            if (!element) {
                return false;
            }

            const rect = element.getBoundingClientRect();
            const styles = window.getComputedStyle(element);

            return rect.width > 0
                && rect.height > 0
                && styles.visibility !== 'hidden'
                && styles.display !== 'none';
        };

        const cartTarget = () => [
            ...document.querySelectorAll('[data-cart-target], .storefront-cart-link, .storefront-mobile-icon[aria-label="Кошик"], a[href$="/cart"]'),
        ].find(isVisible) ?? null;

        const animationSource = (form, submitter) => {
            const card = form.closest('.storefront-product-card');
            const productView = form.closest('.storefront-seo-card');

            return card?.querySelector('.storefront-product-card__media img, .storefront-product-card__media .storefront-image-placeholder')
                ?? productView?.querySelector('img, .storefront-image-placeholder')
                ?? submitter
                ?? form;
        };

        const pulseCart = (target) => {
            target.classList.remove('is-cart-pulse');
            void target.offsetWidth;
            target.classList.add('is-cart-pulse');
            window.setTimeout(() => target.classList.remove('is-cart-pulse'), 760);
        };

        const createFlyer = (source) => {
            const flyer = document.createElement('span');

            flyer.className = 'storefront-cart-flyer';
            flyer.setAttribute('aria-hidden', 'true');

            if (source instanceof HTMLImageElement && (source.currentSrc || source.src)) {
                flyer.classList.add('has-image');
                flyer.style.backgroundImage = `url("${source.currentSrc || source.src}")`;
            } else {
                flyer.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/><path d="M12 9v5"/><path d="M9.5 11.5h5"/></svg>';
            }

            return flyer;
        };

        const animateAddToCart = (form, submitter) => new Promise((resolve) => {
            const target = cartTarget();

            if (!target) {
                resolve(false);
                return;
            }

            pulseCart(target);

            if (reducedMotion.matches || typeof document.body.animate !== 'function') {
                resolve(true);
                return;
            }

            const source = animationSource(form, submitter);
            const sourceRect = source.getBoundingClientRect();
            const targetRect = target.getBoundingClientRect();
            const size = Math.min(82, Math.max(52, Math.min(sourceRect.width, sourceRect.height) * 0.58 || 58));
            const startX = sourceRect.left + sourceRect.width / 2 - size / 2;
            const startY = sourceRect.top + sourceRect.height / 2 - size / 2;
            const endX = targetRect.left + targetRect.width / 2 - size / 2;
            const endY = targetRect.top + targetRect.height / 2 - size / 2;
            const midX = startX + (endX - startX) * 0.52;
            const midY = Math.min(startY, endY) - Math.max(42, Math.abs(endX - startX) * 0.09);
            const flyer = createFlyer(source);
            const animationDuration = 1720;

            flyer.style.width = `${size}px`;
            flyer.style.height = `${size}px`;
            document.body.appendChild(flyer);

            const animation = flyer.animate([
                {
                    opacity: 1,
                    transform: `translate3d(${startX}px, ${startY}px, 0) scale(1)`,
                },
                {
                    offset: 0.58,
                    opacity: 1,
                    transform: `translate3d(${midX}px, ${midY}px, 0) scale(0.95)`,
                },
                {
                    opacity: 0.16,
                    transform: `translate3d(${endX}px, ${endY}px, 0) scale(0.42)`,
                },
            ], {
                duration: animationDuration,
                easing: 'cubic-bezier(0.18, 0.82, 0.2, 1)',
                fill: 'both',
            });
            let isFinished = false;
            const finish = () => {
                if (isFinished) {
                    return;
                }

                isFinished = true;
                flyer.remove();
                resolve(true);
            };

            animation.finished
                .catch(() => {})
                .finally(finish);
            window.setTimeout(finish, animationDuration + 220);
        });

        document.addEventListener('storefront:toast', (event) => {
            showToast(event.detail ?? {});
        });

        window.StorefrontFeedback = {
            showToast,
            animateAddToCart,
        };

        document.querySelectorAll('[data-storefront-toast-payload]').forEach((payload) => {
            try {
                showToast(JSON.parse(payload.textContent));
            } catch (error) {
                console.warn('Не вдалося показати storefront toast.', error);
            }
        });
    })();
</script>
