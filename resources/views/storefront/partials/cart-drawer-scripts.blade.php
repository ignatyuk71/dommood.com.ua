<script>
    (() => {
        if (window.__storefrontCartDrawerReady) {
            return;
        }

        window.__storefrontCartDrawerReady = true;

        const fallbackDrawerUrl = @json(route('cart.drawer'));
        let lastOpenButton = null;

        const getDrawer = () => document.querySelector('[data-cart-drawer]');
        const getDrawerUrl = () => getDrawer()?.dataset.cartDrawerUrl || fallbackDrawerUrl;

        const setPanelInert = (panel, isHidden) => {
            panel?.toggleAttribute('inert', isHidden);

            if (panel) {
                panel.inert = isHidden;
            }
        };

        const setCartState = (isOpen) => {
            const drawer = getDrawer();

            if (!drawer) {
                return;
            }

            if (isOpen) {
                setPanelInert(drawer, false);
            } else if (drawer.contains(document.activeElement)) {
                lastOpenButton?.focus({ preventScroll: true });
            }

            drawer.classList.toggle('is-open', isOpen);
            drawer.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            setPanelInert(drawer, !isOpen);
            document.body.classList.toggle('storefront-cart-open', isOpen);

            if (isOpen) {
                drawer.querySelector('[data-cart-drawer-panel]')?.focus({ preventScroll: true });
            }
        };

        const replaceDrawer = (html, isOpen = true) => {
            const currentDrawer = getDrawer();
            const template = document.createElement('template');

            template.innerHTML = html.trim();

            const nextDrawer = template.content.querySelector('[data-cart-drawer]');

            if (!currentDrawer || !nextDrawer) {
                return;
            }

            const wasOpen = currentDrawer.classList.contains('is-open');
            const shouldAnimateOpen = isOpen && !wasOpen;

            if (shouldAnimateOpen) {
                nextDrawer.classList.remove('is-open');
                nextDrawer.setAttribute('aria-hidden', 'true');
                nextDrawer.setAttribute('inert', '');
            }

            currentDrawer.replaceWith(nextDrawer);

            if (!shouldAnimateOpen) {
                setCartState(isOpen);
                return;
            }

            requestAnimationFrame(() => {
                requestAnimationFrame(() => setCartState(true));
            });
        };

        const loadDrawer = async (isOpen = true) => {
            const response = await fetch(getDrawerUrl(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (!response.ok) {
                throw new Error('Не вдалося завантажити кошик.');
            }

	            const data = await response.json();
	            updateCartIndicators(data.cart_summary);
	            replaceDrawer(data.drawer_html, isOpen);
	        };

        const firstErrorMessage = (payload) => {
            const errors = payload?.errors || {};
            const firstField = Object.keys(errors)[0];

            if (firstField && Array.isArray(errors[firstField]) && errors[firstField][0]) {
                return errors[firstField][0];
            }

            return payload?.message || 'Не вдалося оновити кошик. Спробуйте ще раз.';
        };

	        const showToast = (message, type = 'success') => {
	            if (!message) {
	                return;
	            }

	            window.StorefrontFeedback?.showToast({ message, type });
	        };

	        const quantityLabel = (quantity) => {
	            const absolute = Math.abs(quantity);
	            const lastTwo = absolute % 100;
	            const last = absolute % 10;

	            if (last === 1 && lastTwo !== 11) {
	                return `${quantity} товар`;
	            }

	            if (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) {
	                return `${quantity} товари`;
	            }

	            return `${quantity} товарів`;
	        };

	        const formatMoney = (amount, currency = 'UAH') => {
	            const value = new Intl.NumberFormat('uk-UA', {
	                maximumFractionDigits: Number(amount || 0) % 100 === 0 ? 0 : 2,
	                minimumFractionDigits: 0,
	            }).format(Number(amount || 0) / 100);

	            return currency === 'UAH' ? `${value} грн` : `${value} ${currency}`;
	        };

	        const updateFreeShippingProgress = (summary = {}) => {
	            document.querySelectorAll('[data-free-shipping-progress]').forEach((element) => {
	                const threshold = Math.max(1, Number(summary?.free_shipping_threshold_cents || element.dataset.freeShippingThreshold || 120000));
	                const total = Math.max(0, Number(summary?.total_cents ?? element.dataset.freeShippingCurrent ?? 0));
	                const remaining = Math.max(0, Number(summary?.free_shipping_remaining_cents ?? (threshold - total)));
	                const progress = Math.min(100, Math.max(0, Number(summary?.free_shipping_progress_percent ?? Math.floor((total / threshold) * 100))));
	                const label = summary?.free_shipping_label || (remaining > 0
	                    ? `Додайте ще ${formatMoney(remaining, summary?.currency || 'UAH')}, щоб отримати безкоштовну доставку`
	                    : 'Безкоштовна доставка доступна для цього замовлення');
	                const labelElement = element.querySelector('[data-free-shipping-label]');
	                const percentElement = element.querySelector('[data-free-shipping-percent]');

	                element.dataset.freeShippingThreshold = String(threshold);
	                element.dataset.freeShippingCurrent = String(total);
	                element.style.setProperty('--free-progress-percent', `${progress}%`);

	                if (labelElement) {
	                    labelElement.textContent = label;
	                }

	                if (percentElement) {
	                    percentElement.textContent = `${progress}%`;
	                    percentElement.hidden = true;
	                }
	            });
	        };

	        const updateCartIndicators = (summary = {}) => {
	            const quantityCount = Number(summary?.quantity_count ?? summary?.items_count ?? 0);
	            const isEmpty = summary?.is_empty ?? quantityCount <= 0;
	            const headerLabel = summary?.header_label || summary?.total_formatted || (isEmpty ? '' : quantityLabel(quantityCount));
	            const ariaLabel = summary?.aria_label || (isEmpty ? 'Кошик' : `${quantityLabel(quantityCount)} · ${headerLabel}`);
	            const badge = summary?.badge || (quantityCount > 99 ? '99+' : String(quantityCount));

	            document.querySelectorAll('[data-cart-summary]').forEach((element) => {
	                element.textContent = headerLabel;
	                element.hidden = isEmpty;
	            });

	            document.querySelectorAll('[data-cart-badge]').forEach((element) => {
	                element.textContent = badge;
	                element.hidden = isEmpty;
	            });

	            document.querySelectorAll('[data-cart-target]').forEach((element) => {
	                element.classList.toggle('has-cart-items', !isEmpty);
	                element.setAttribute('aria-label', isEmpty ? 'Кошик' : `Кошик: ${ariaLabel}`);
	            });

	            updateFreeShippingProgress(summary);
	        };

        const showDrawerError = async (message) => {
            await loadDrawer(true).catch(() => setCartState(true));
            showToast(message, 'error');
        };

        const pushAddToCartEvent = (form, formData) => {
            if (!form.matches('[data-cart-add]')) {
                return;
            }

            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: 'add_to_cart',
                ecommerce: {
                    items: [{
                        item_id: String(formData.get('product_id') || ''),
                        quantity: Number(formData.get('quantity') || 1),
                    }],
                },
            });
        };

        const formDataWithSubmitter = (form, submitter) => {
            try {
                return new FormData(form, submitter);
            } catch (error) {
                const formData = new FormData(form);

                if (submitter?.name) {
                    formData.append(submitter.name, submitter.value);
                }

                return formData;
            }
        };

        const submitButtons = (form, submitter) => [
            ...new Set([
                ...(submitter ? [submitter] : []),
                ...form.querySelectorAll('button[type="submit"], input[type="submit"]'),
            ]),
        ].filter((button) => button instanceof HTMLElement);

        const setSubmittingState = (form, submitter, isSubmitting) => {
            submitButtons(form, submitter).forEach((button) => {
                button.classList.toggle('is-cart-submitting', isSubmitting);

                if (isSubmitting) {
                    button.setAttribute('aria-busy', 'true');
                    button.setAttribute('disabled', 'disabled');
                } else if (button.isConnected) {
                    button.removeAttribute('aria-busy');
                    button.removeAttribute('disabled');
                }
            });

            form.querySelectorAll('[data-cart-variant-select]').forEach((select) => {
                if (isSubmitting) {
                    select.setAttribute('disabled', 'disabled');
                } else if (select.isConnected) {
                    select.removeAttribute('disabled');
                }
            });
        };

        const submitCartForm = async (form, submitter) => {
            if (form.dataset.cartRequestState === 'submitting') {
                return;
            }

            form.dataset.cartRequestState = 'submitting';
            const formData = formDataWithSubmitter(form, submitter);

            setSubmittingState(form, submitter, true);

	            try {
	                if (form.matches('[data-cart-add]')) {
	                    window.StorefrontFeedback?.animateAddToCart?.(form, submitter);
	                }

	                const response = await fetch(form.action, {
                    method: (form.getAttribute('method') || 'post').toUpperCase(),
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    await showDrawerError(firstErrorMessage(data));
                    return;
                }

	                pushAddToCartEvent(form, formData);
	                updateCartIndicators(data.cart_summary);
	                replaceDrawer(data.drawer_html, true);
	                showToast(data.status_message || 'Кошик оновлено.');
            } catch (error) {
                await showDrawerError('Кошик тимчасово не оновився. Перевірте зʼєднання і спробуйте ще раз.');
            } finally {
                delete form.dataset.cartRequestState;
                delete form.dataset.cartAnimationState;
                setSubmittingState(form, submitter, false);
            }
        };

        document.addEventListener('click', (event) => {
            const openButton = event.target.closest('[data-cart-open], [data-cart-target]');

            if (openButton) {
                event.preventDefault();
                lastOpenButton = openButton;
                loadDrawer(true).catch(() => setCartState(true));
                return;
            }

            const closeButton = event.target.closest('[data-cart-close]');

            if (closeButton) {
                event.preventDefault();
                setCartState(false);
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-cart-add], form[data-cart-action], form[data-cart-form]');

            if (!form) {
                return;
            }

            event.preventDefault();

            if (form.dataset.cartRequestState === 'submitting') {
                return;
            }

            submitCartForm(form, event.submitter || null);
        });

        document.addEventListener('change', (event) => {
            const select = event.target.closest('[data-cart-variant-select]');

            if (!select) {
                return;
            }

            const form = select.closest('form[data-cart-variant-form]');

            if (!form || form.dataset.cartRequestState === 'submitting') {
                return;
            }

            submitCartForm(form, null);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setCartState(false);
            }
        });
    })();
</script>
