(() => {
    const page = document.querySelector('[data-product-page]');
    const payloadElement = document.querySelector('[data-product-json]');

    if (!page || !payloadElement) {
        return;
    }

    const product = JSON.parse(payloadElement.textContent || '{}');
    const variants = Array.isArray(product.variants) ? product.variants : [];
    const currency = product.currency || 'UAH';
    const form = page.querySelector('[data-product-form]');
    const variantField = page.querySelector('[data-product-variant-field]');
    const quantityField = page.querySelector('[data-product-quantity-field]');
    const quantityInput = page.querySelector('[data-product-qty]');
    const galleryTrack = page.querySelector('[data-product-gallery-track]');
    const galleryItems = Array.from(page.querySelectorAll('[data-product-gallery-item]'));
    const dotButtons = Array.from(page.querySelectorAll('[data-product-dot]'));
    const colorLabel = page.querySelector('[data-product-color-label]');
    const mobileSubmitButton = document.querySelector('[data-product-mobile-submit]');
    let selectedVariantId = Number(variantField?.value || variants[0]?.id || 0);

    const money = (amount, nextCurrency = currency) => {
        const value = new Intl.NumberFormat('uk-UA', {
            maximumFractionDigits: amount % 100 === 0 ? 0 : 2,
            minimumFractionDigits: 0,
        }).format(Number(amount || 0) / 100);

        return nextCurrency === 'UAH' ? `${value} грн` : `${value} ${nextCurrency}`;
    };

    const normalized = (value) => String(value || '').trim().toLocaleLowerCase('uk-UA');
    const colorKey = (variant) => normalized(`${variant.color_name || ''}|${variant.color_hex || ''}`);
    const sizeKey = (variant) => normalized(variant.size || '');
    const selectedColor = () => form?.querySelector('input[name="product_color"]:checked')?.value || '';
    const selectedSize = () => form?.querySelector('input[name="product_size"]:checked')?.value || '';

    const currentVariant = () => variants.find((variant) => Number(variant.id) === selectedVariantId) || variants[0] || null;

    const updateText = (selector, text) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = text;
        });
    };

    const updateDiscount = (priceCents, oldPriceCents) => {
        const hasDiscount = oldPriceCents > priceCents && priceCents > 0;

        document.querySelectorAll('[data-product-old-price], [data-product-mobile-old-price]').forEach((element) => {
            element.hidden = !hasDiscount;
            element.textContent = money(oldPriceCents);
        });
    };

    const setActiveLabel = (input) => {
        const group = input?.closest('.product-color-options, .product-size-options');

        group?.querySelectorAll('label').forEach((label) => {
            label.classList.toggle('is-active', label.contains(input));
        });
    };

    const setQuantity = (value) => {
        const quantity = Math.max(1, Math.min(99, Number.parseInt(value, 10) || 1));

        if (quantityInput) {
            quantityInput.value = String(quantity);
        }

        if (quantityField) {
            quantityField.value = String(quantity);
        }
    };

    const setActiveGallery = (index) => {
        galleryItems.forEach((item) => {
            item.classList.toggle('is-active', Number(item.dataset.mediaIndex || 0) === index);
        });

        dotButtons.forEach((button) => {
            const isActive = Number(button.dataset.mediaIndex || 0) === index;

            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    };

    const showGalleryItem = (index) => {
        const item = galleryItems.find((galleryItem) => Number(galleryItem.dataset.mediaIndex || 0) === index);

        if (!item) {
            return;
        }

        setActiveGallery(index);
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
    };

    const initGalleryLightbox = () => {
        const photos = galleryItems
            .map((item) => ({
                index: Number(item.dataset.mediaIndex || 0),
                src: item.dataset.imageUrl || item.querySelector('img')?.currentSrc || item.querySelector('img')?.src || '',
                alt: item.dataset.imageAlt || item.querySelector('img')?.alt || product.name || 'Фото товару',
            }))
            .filter((photo) => photo.src);

        if (!photos.length) {
            return;
        }

        const icon = (path) => `<svg viewBox="0 0 24 24" aria-hidden="true"><path d="${path}"/></svg>`;
        const lightbox = document.createElement('div');
        lightbox.className = 'product-gallery-lightbox';
        lightbox.setAttribute('role', 'dialog');
        lightbox.setAttribute('aria-modal', 'true');
        lightbox.setAttribute('aria-label', 'Перегляд фото товару');
        lightbox.innerHTML = `
            <span class="product-gallery-lightbox__counter" data-gallery-lightbox-counter></span>
            <button type="button" class="product-gallery-lightbox__button product-gallery-lightbox__button--zoom" data-gallery-lightbox-zoom aria-label="Збільшити фото">
                ${icon('m21 21-4.35-4.35M11 6v10M6 11h10M19 11a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z')}
            </button>
            <button type="button" class="product-gallery-lightbox__button product-gallery-lightbox__button--close" data-gallery-lightbox-close aria-label="Закрити перегляд">
                ${icon('M18 6 6 18M6 6l12 12')}
            </button>
            <button type="button" class="product-gallery-lightbox__button product-gallery-lightbox__button--prev" data-gallery-lightbox-prev aria-label="Попереднє фото">
                ${icon('m15 18-6-6 6-6')}
            </button>
            <img class="product-gallery-lightbox__image" data-gallery-lightbox-image alt="">
            <button type="button" class="product-gallery-lightbox__button product-gallery-lightbox__button--next" data-gallery-lightbox-next aria-label="Наступне фото">
                ${icon('m9 6 6 6-6 6')}
            </button>
        `;
        document.body.appendChild(lightbox);

        const image = lightbox.querySelector('[data-gallery-lightbox-image]');
        const counter = lightbox.querySelector('[data-gallery-lightbox-counter]');
        const closeButton = lightbox.querySelector('[data-gallery-lightbox-close]');
        const zoomButton = lightbox.querySelector('[data-gallery-lightbox-zoom]');
        const previousButton = lightbox.querySelector('[data-gallery-lightbox-prev]');
        const nextButton = lightbox.querySelector('[data-gallery-lightbox-next]');
        let activePhotoIndex = 0;
        let restoreBodyOverflow = '';
        let touchStartX = null;

        const setZoom = (isZoomed) => {
            lightbox.classList.toggle('is-zoomed', isZoomed);
            zoomButton?.setAttribute('aria-label', isZoomed ? 'Зменшити фото' : 'Збільшити фото');
        };

        const renderPhoto = () => {
            const photo = photos[activePhotoIndex];

            if (!photo || !image || !counter) {
                return;
            }

            image.src = photo.src;
            image.alt = photo.alt;
            counter.textContent = `${activePhotoIndex + 1} / ${photos.length}`;
            setZoom(false);
            setActiveGallery(photo.index);
        };

        const movePhoto = (step) => {
            activePhotoIndex = (activePhotoIndex + step + photos.length) % photos.length;
            renderPhoto();
        };

        const openLightbox = (mediaIndex) => {
            const requestedIndex = photos.findIndex((photo) => photo.index === mediaIndex);
            activePhotoIndex = requestedIndex >= 0 ? requestedIndex : 0;
            restoreBodyOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            renderPhoto();
            lightbox.classList.add('is-open');
            closeButton?.focus({ preventScroll: true });
        };

        const closeLightbox = () => {
            if (!lightbox.classList.contains('is-open')) {
                return;
            }

            lightbox.classList.remove('is-open');
            document.body.style.overflow = restoreBodyOverflow;
            setZoom(false);
        };

        galleryItems.forEach((item) => {
            if (!item.dataset.imageUrl && !item.querySelector('img')) {
                return;
            }

            item.tabIndex = 0;
            item.setAttribute('role', 'button');
            item.setAttribute('aria-label', 'Відкрити фото товару');
            item.addEventListener('click', () => openLightbox(Number(item.dataset.mediaIndex || 0)));
            item.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ' ') {
                    return;
                }

                event.preventDefault();
                openLightbox(Number(item.dataset.mediaIndex || 0));
            });
        });

        closeButton?.addEventListener('click', closeLightbox);
        zoomButton?.addEventListener('click', () => setZoom(!lightbox.classList.contains('is-zoomed')));
        previousButton?.addEventListener('click', () => movePhoto(-1));
        nextButton?.addEventListener('click', () => movePhoto(1));
        image?.addEventListener('click', () => setZoom(!lightbox.classList.contains('is-zoomed')));

        lightbox.addEventListener('click', (event) => {
            if (event.target === lightbox) {
                closeLightbox();
            }
        });

        lightbox.addEventListener('touchstart', (event) => {
            touchStartX = event.touches[0]?.clientX ?? null;
        }, { passive: true });

        lightbox.addEventListener('touchend', (event) => {
            if (touchStartX === null) {
                return;
            }

            const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
            const diff = touchEndX - touchStartX;
            touchStartX = null;

            if (Math.abs(diff) < 45) {
                return;
            }

            movePhoto(diff > 0 ? -1 : 1);
        }, { passive: true });

        document.addEventListener('keydown', (event) => {
            if (!lightbox.classList.contains('is-open')) {
                return;
            }

            if (event.key === 'Escape') {
                closeLightbox();
            } else if (event.key === 'ArrowLeft') {
                movePhoto(-1);
            } else if (event.key === 'ArrowRight') {
                movePhoto(1);
            }
        });
    };

    const syncVariant = () => {
        if (variants.length === 0) {
            return;
        }

        const color = selectedColor();
        const size = selectedSize();
        const directVariant = Number(form?.querySelector('input[name="product_variant_choice"]:checked')?.value || 0);
        let nextVariant = directVariant ? variants.find((variant) => Number(variant.id) === directVariant) : null;

        if (!nextVariant) {
            nextVariant = variants.find((variant) => {
                const colorMatches = !color || colorKey(variant) === color;
                const sizeMatches = !size || sizeKey(variant) === size;

                return colorMatches && sizeMatches;
            });
        }

        if (!nextVariant) {
            nextVariant = variants.find((variant) => !color || colorKey(variant) === color) || variants[0];
        }

        selectedVariantId = Number(nextVariant.id);

        if (variantField) {
            variantField.value = String(nextVariant.id);
        }

        const price = Number(nextVariant.price_cents || product.base_price_cents || 0);
        const oldPrice = Number(nextVariant.old_price_cents || product.base_old_price_cents || 0);

        updateText('[data-product-price]', money(price));
        updateText('[data-product-mobile-price]', money(price));
        updateText('[data-product-sku]', nextVariant.sku || product.sku || '');
        updateText('[data-product-stock]', nextVariant.is_available === false ? 'Немає в наявності' : (product.stock_status_label || 'В наявності'));
        updateDiscount(price, oldPrice);

        if (colorLabel) {
            colorLabel.textContent = nextVariant.color_name || 'оберіть відтінок';
        }

        window.dataLayer = window.dataLayer || [];
        window.dataLayer.push({
            event: 'select_item_variant',
            ecommerce: {
                currency,
                value: price / 100,
                items: [{
                    item_id: String(nextVariant.sku || product.sku || product.id || ''),
                    item_name: product.name || '',
                    item_variant: nextVariant.label || nextVariant.name || '',
                    price: price / 100,
                    quantity: Number(quantityInput?.value || 1),
                }],
            },
        });
    };

    dotButtons.forEach((button) => {
        button.addEventListener('click', () => showGalleryItem(Number(button.dataset.mediaIndex || 0)));
    });

    if (galleryTrack && galleryItems.length > 1) {
        let scrollTimer = null;

        galleryTrack.addEventListener('scroll', () => {
            window.clearTimeout(scrollTimer);
            scrollTimer = window.setTimeout(() => {
                const trackLeft = galleryTrack.getBoundingClientRect().left;
                const nearestItem = galleryItems
                    .map((item) => ({
                        item,
                        distance: Math.abs(item.getBoundingClientRect().left - trackLeft),
                    }))
                    .sort((a, b) => a.distance - b.distance)[0]?.item;

                if (nearestItem) {
                    setActiveGallery(Number(nearestItem.dataset.mediaIndex || 0));
                }
            }, 80);
        }, { passive: true });
    }

    initGalleryLightbox();
    form?.querySelectorAll('input[name="product_color"], input[name="product_size"], input[name="product_variant_choice"]').forEach((input) => {
        input.addEventListener('change', () => {
            setActiveLabel(input);
            syncVariant();
        });
    });

    page.querySelector('[data-product-qty-minus]')?.addEventListener('click', () => {
        setQuantity(Number(quantityInput?.value || 1) - 1);
    });

    page.querySelector('[data-product-qty-plus]')?.addEventListener('click', () => {
        setQuantity(Number(quantityInput?.value || 1) + 1);
    });

    quantityInput?.addEventListener('input', () => setQuantity(quantityInput.value));
    quantityInput?.addEventListener('change', () => setQuantity(quantityInput.value));

    document.querySelectorAll('[data-product-dialog-open]').forEach((button) => {
        button.addEventListener('click', () => {
            const dialog = document.querySelector(`[data-product-dialog="${button.dataset.productDialogOpen}"]`);

            if (dialog instanceof HTMLDialogElement) {
                const currentDialog = button.closest('dialog');

                if (currentDialog instanceof HTMLDialogElement && currentDialog !== dialog && currentDialog.open) {
                    currentDialog.close();
                }

                if (!dialog.open) {
                    dialog.showModal();
                }
            }
        });
    });

    document.querySelectorAll('[data-product-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => {
            button.closest('dialog')?.close();
        });
    });

    document.querySelectorAll('[data-product-dialog]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    form?.addEventListener('submit', () => {
        setQuantity(quantityInput?.value || 1);
    }, { capture: true });

    mobileSubmitButton?.addEventListener('click', () => {
        if (!form) {
            return;
        }

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    });

    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: 'view_item',
        ecommerce: {
            currency,
            value: Number(currentVariant()?.price_cents || product.base_price_cents || 0) / 100,
            items: [{
                item_id: String(currentVariant()?.sku || product.sku || product.id || ''),
                item_name: product.name || '',
                price: Number(currentVariant()?.price_cents || product.base_price_cents || 0) / 100,
                quantity: 1,
            }],
        },
    });
})();
