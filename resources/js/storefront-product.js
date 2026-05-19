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
    const tabGroups = Array.from(page.querySelectorAll('[data-product-tabs]'));
    const recentlyViewedSection = page.querySelector('[data-recently-viewed-section]');
    const recentlyViewedTrack = page.querySelector('[data-recently-viewed-track]');
    const recentlyViewedPrev = page.querySelector('[data-recently-viewed-prev]');
    const recentlyViewedNext = page.querySelector('[data-recently-viewed-next]');
    const colorLabel = page.querySelector('[data-product-color-label]');
    const addButton = page.querySelector('[data-product-add-button]');
    const addLabel = page.querySelector('[data-product-add-label]');
    const firstAvailableVariant = variants.find((variant) => variant.is_available !== false);
    const RECENTLY_VIEWED_KEY = 'dommood_recently_viewed_products';
    const RECENTLY_VIEWED_LIMIT = 12;
    let selectedVariantId = Number(variantField?.value || firstAvailableVariant?.id || variants[0]?.id || 0);

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
    const variantAvailable = (variant) => Boolean(variant) && variant.is_available !== false;
    const selectedColor = () => form?.querySelector('input[name="product_color"]:checked')?.value || '';
    const selectedSize = () => form?.querySelector('input[name="product_size"]:checked')?.value || '';

    const currentVariant = () => variants.find((variant) => Number(variant.id) === selectedVariantId) || variants[0] || null;

    const updateText = (selector, text) => {
        document.querySelectorAll(selector).forEach((element) => {
            element.textContent = text;
        });
    };

    const normalizeLocalUrl = (url) => {
        try {
            const parsedUrl = new URL(String(url || ''), window.location.origin);

            if (parsedUrl.origin !== window.location.origin) {
                return '#';
            }

            return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
        } catch (error) {
            return '#';
        }
    };

    const readRecentlyViewed = () => {
        try {
            const items = JSON.parse(window.localStorage.getItem(RECENTLY_VIEWED_KEY) || '[]');

            return Array.isArray(items)
                ? items.filter((item) => item && (item.id || item.url) && item.name)
                : [];
        } catch (error) {
            return [];
        }
    };

    const writeRecentlyViewed = (items) => {
        try {
            window.localStorage.setItem(RECENTLY_VIEWED_KEY, JSON.stringify(items.slice(0, RECENTLY_VIEWED_LIMIT)));
        } catch (error) {
            // localStorage може бути недоступним у приватному режимі.
        }
    };

    const sameViewedProduct = (firstProduct, secondProduct) => {
        const firstId = String(firstProduct?.id || '');
        const secondId = String(secondProduct?.id || '');

        if (firstId && secondId) {
            return firstId === secondId;
        }

        return normalizeLocalUrl(firstProduct?.url) === normalizeLocalUrl(secondProduct?.url);
    };

    const currentViewedProduct = () => ({
        id: String(product.id || ''),
        name: product.name || '',
        url: normalizeLocalUrl(product.url || window.location.href),
        image_url: product.image_url || '',
        image_alt: product.image_alt || product.name || '',
        price_cents: Number(product.base_price_cents || 0),
        old_price_cents: Number(product.base_old_price_cents || 0),
        currency,
        stock_status: product.stock_status || '',
        stock_status_label: product.stock_status_label || '',
        is_new: Boolean(product.is_new),
        is_bestseller: Boolean(product.is_bestseller),
        is_featured: Boolean(product.is_featured),
    });

    const appendText = (parent, tagName, className, text) => {
        const element = document.createElement(tagName);

        if (className) {
            element.className = className;
        }

        element.textContent = text;
        parent.append(element);

        return element;
    };

    const createRecentlyViewedCard = (item) => {
        const article = document.createElement('article');
        const href = normalizeLocalUrl(item.url);
        const hasDiscount = Number(item.old_price_cents || 0) > Number(item.price_cents || 0);

        article.className = 'storefront-product-card';
        article.setAttribute('role', 'listitem');

        if (item.id) {
            article.dataset.productId = String(item.id);
        }

        const mediaLink = document.createElement('a');
        mediaLink.className = 'storefront-product-card__media';
        mediaLink.href = href;

        if (item.image_url) {
            const image = document.createElement('img');
            image.src = item.image_url;
            image.alt = item.image_alt || item.name;
            image.loading = 'lazy';
            image.decoding = 'async';
            mediaLink.append(image);
        } else {
            appendText(mediaLink, 'span', 'storefront-image-placeholder', String(item.name || 'DM').slice(0, 2));
        }

        const badges = document.createElement('span');
        badges.className = 'storefront-product-badges';

        if (item.is_new) {
            appendText(badges, 'span', 'is-new', 'Новинка');
        }

        if (item.is_bestseller) {
            appendText(badges, 'span', 'is-hit', 'Хіт');
        }

        if (item.is_featured) {
            appendText(badges, 'span', 'is-top', 'Топ');
        }

        mediaLink.append(badges);
        article.append(mediaLink);

        const body = document.createElement('div');
        body.className = 'storefront-product-card__body';

        const titleWrap = document.createElement('div');
        const title = document.createElement('h3');
        const titleLink = document.createElement('a');
        titleLink.href = href;
        titleLink.textContent = item.name || '';
        title.append(titleLink);
        titleWrap.append(title);
        body.append(titleWrap);

        const footer = document.createElement('div');
        footer.className = 'storefront-product-card__footer';

        const price = document.createElement('div');
        price.className = 'storefront-product-price';
        appendText(price, 'span', '', money(Number(item.price_cents || 0), item.currency || currency));

        if (hasDiscount) {
            appendText(price, 'del', '', money(Number(item.old_price_cents || 0), item.currency || currency));
        }

        const stock = appendText(footer, 'span', 'storefront-stock', item.stock_status_label || '');
        stock.classList.toggle('is-muted', item.stock_status === 'out_of_stock');
        stock.classList.toggle('is-warning', item.stock_status === 'preorder');

        footer.prepend(price);
        body.append(footer);
        article.append(body);

        return article;
    };

    const updateRecentlyViewedControls = () => {
        if (!recentlyViewedTrack || !recentlyViewedPrev || !recentlyViewedNext) {
            return;
        }

        const canScroll = recentlyViewedTrack.scrollWidth > recentlyViewedTrack.clientWidth + 4;
        const isStart = recentlyViewedTrack.scrollLeft <= 2;
        const isEnd = recentlyViewedTrack.scrollLeft + recentlyViewedTrack.clientWidth >= recentlyViewedTrack.scrollWidth - 2;

        recentlyViewedPrev.hidden = !canScroll;
        recentlyViewedNext.hidden = !canScroll;
        recentlyViewedPrev.disabled = isStart;
        recentlyViewedNext.disabled = isEnd;
    };

    const renderRecentlyViewed = () => {
        if (!recentlyViewedSection || !recentlyViewedTrack) {
            return;
        }

        const currentProduct = currentViewedProduct();
        const viewedProducts = readRecentlyViewed();
        const visibleProducts = viewedProducts
            .filter((item) => !sameViewedProduct(item, currentProduct))
            .slice(0, RECENTLY_VIEWED_LIMIT);

        recentlyViewedTrack.replaceChildren(...visibleProducts.map(createRecentlyViewedCard));
        recentlyViewedSection.hidden = visibleProducts.length === 0;
        updateRecentlyViewedControls();

        writeRecentlyViewed([
            currentProduct,
            ...viewedProducts.filter((item) => !sameViewedProduct(item, currentProduct)),
        ]);
    };

    const updateDiscount = (priceCents, oldPriceCents) => {
        const hasDiscount = oldPriceCents > priceCents && priceCents > 0;

        document.querySelectorAll('[data-product-old-price]').forEach((element) => {
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

    const setPurchaseAvailability = (isAvailable) => {
        if (addButton) {
            addButton.disabled = !isAvailable;
        }

        if (addLabel) {
            addLabel.textContent = isAvailable ? 'У кошик' : 'Немає в наявності';
        }
    };

    const updateStock = (variant) => {
        const isAvailable = variantAvailable(variant);
        const label = isAvailable ? (variant.stock_status_label || product.stock_status_label || 'В наявності') : 'Немає в наявності';

        document.querySelectorAll('[data-product-stock]').forEach((element) => {
            const labelElement = element.querySelector('[data-product-stock-label]');

            if (labelElement) {
                labelElement.textContent = label;
            } else {
                element.textContent = label;
            }

            element.classList.toggle('is-muted', !isAvailable);
            element.classList.toggle('is-warning', isAvailable && product.stock_status === 'preorder');
        });

        setPurchaseAvailability(isAvailable);
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

    const initProductTabs = (container) => {
        const buttons = Array.from(container.querySelectorAll('[data-product-tab]'));
        const panels = Array.from(container.querySelectorAll('[data-product-tab-panel]'));

        if (buttons.length === 0 || panels.length === 0) {
            return;
        }

        const activateTab = (button, focus = false, emitEvent = false) => {
            const tabName = button.dataset.productTab;

            buttons.forEach((candidate) => {
                const isActive = candidate === button;

                candidate.setAttribute('aria-selected', isActive ? 'true' : 'false');
                candidate.tabIndex = isActive ? 0 : -1;
            });

            panels.forEach((panel) => {
                const isActive = panel.dataset.productTabPanel === tabName;

                panel.hidden = !isActive;
                panel.classList.toggle('is-active', isActive);
            });

            if (focus) {
                button.focus();
            }

            if (emitEvent) {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                    event: 'product_detail_tab_select',
                    tab_name: button.textContent.trim(),
                    item_id: String(currentVariant()?.sku || product.sku || product.id || ''),
                    item_name: product.name || '',
                });
            }
        };

        const activeButton = buttons.find((button) => button.getAttribute('aria-selected') === 'true') || buttons[0];

        activateTab(activeButton);

        buttons.forEach((button, index) => {
            button.addEventListener('click', () => activateTab(button, false, true));
            button.addEventListener('keydown', (event) => {
                const lastIndex = buttons.length - 1;
                let nextIndex = index;

                if (event.key === 'ArrowRight') {
                    nextIndex = index === lastIndex ? 0 : index + 1;
                } else if (event.key === 'ArrowLeft') {
                    nextIndex = index === 0 ? lastIndex : index - 1;
                } else if (event.key === 'Home') {
                    nextIndex = 0;
                } else if (event.key === 'End') {
                    nextIndex = lastIndex;
                } else {
                    return;
                }

                event.preventDefault();
                activateTab(buttons[nextIndex], true, true);
            });
        });
    };

    const initServiceAccordion = () => {
        const accordionItems = Array.from(page.querySelectorAll('.product-service-accordion details'));
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        accordionItems.forEach((details) => {
            const summary = details.querySelector('summary');
            const body = details.querySelector('.product-service-accordion__body');
            let animationTimer = null;

            if (!summary || !body) {
                return;
            }

            summary.addEventListener('click', (event) => {
                if (reduceMotion) {
                    return;
                }

                event.preventDefault();

                if (details.dataset.accordionState) {
                    return;
                }

                const finishAnimation = (state, onFinish) => {
                    const finish = (transitionEvent) => {
                        if (transitionEvent && transitionEvent.propertyName !== 'height') {
                            return;
                        }

                        window.clearTimeout(animationTimer);
                        body.removeEventListener('transitionend', finish);
                        onFinish();
                        delete details.dataset.accordionState;
                    };

                    details.dataset.accordionState = state;
                    body.addEventListener('transitionend', finish);
                    animationTimer = window.setTimeout(finish, 320);
                };

                if (details.open) {
                    body.style.height = `${body.scrollHeight}px`;
                    body.style.opacity = '1';
                    body.style.overflow = 'hidden';

                    finishAnimation('closing', () => {
                        details.open = false;
                        body.style.height = '';
                        body.style.opacity = '';
                        body.style.overflow = '';
                    });

                    window.requestAnimationFrame(() => {
                        body.style.height = '0px';
                        body.style.opacity = '0';
                    });

                    return;
                }

                details.open = true;
                body.style.height = '0px';
                body.style.opacity = '0';
                body.style.overflow = 'hidden';

                finishAnimation('opening', () => {
                    body.style.height = '';
                    body.style.opacity = '';
                    body.style.overflow = '';
                });

                window.requestAnimationFrame(() => {
                    body.style.height = `${body.scrollHeight}px`;
                    body.style.opacity = '1';
                });
            });
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

                return colorMatches && sizeMatches && variantAvailable(variant);
            });
        }

        if (!nextVariant) {
            nextVariant = variants.find((variant) => (!color || colorKey(variant) === color) && variantAvailable(variant))
                || variants.find((variant) => !color || colorKey(variant) === color)
                || firstAvailableVariant
                || variants[0];
        }

        selectedVariantId = Number(nextVariant.id);

        if (variantField) {
            variantField.value = String(nextVariant.id);
        }

        const price = Number(nextVariant.price_cents || product.base_price_cents || 0);
        const oldPrice = Number(nextVariant.old_price_cents || product.base_old_price_cents || 0);

        updateText('[data-product-price]', money(price));
        updateText('[data-product-sku]', nextVariant.sku || product.sku || '');
        updateStock(nextVariant);
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
    tabGroups.forEach(initProductTabs);
    initServiceAccordion();
    renderRecentlyViewed();

    recentlyViewedPrev?.addEventListener('click', () => {
        recentlyViewedTrack?.scrollBy({ left: -Math.max(240, recentlyViewedTrack.clientWidth * 0.85), behavior: 'smooth' });
    });

    recentlyViewedNext?.addEventListener('click', () => {
        recentlyViewedTrack?.scrollBy({ left: Math.max(240, recentlyViewedTrack.clientWidth * 0.85), behavior: 'smooth' });
    });

    recentlyViewedTrack?.addEventListener('scroll', updateRecentlyViewedControls, { passive: true });
    window.addEventListener('resize', updateRecentlyViewedControls);

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
                    window.requestAnimationFrame(() => {
                        dialog.focus({ preventScroll: true });
                    });
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

    document.querySelectorAll('[data-product-dialog-auto-open]').forEach((dialog) => {
        if (dialog instanceof HTMLDialogElement && !dialog.open) {
            dialog.showModal();
            window.requestAnimationFrame(() => {
                dialog.focus({ preventScroll: true });
            });
        }
    });

    form?.addEventListener('submit', (event) => {
        setQuantity(quantityInput?.value || 1);

        if (currentVariant() && !variantAvailable(currentVariant())) {
            event.preventDefault();
            event.stopImmediatePropagation();
            updateStock(currentVariant());
        }
    }, { capture: true });

    page.querySelectorAll('[data-product-unavailable-action]').forEach((action) => {
        action.addEventListener('click', () => {
            window.dataLayer = window.dataLayer || [];
            window.dataLayer.push({
                event: action.dataset.productUnavailableAction || 'out_of_stock_contact_click',
                item_id: String(currentVariant()?.sku || product.sku || product.id || ''),
                item_name: product.name || '',
                item_variant: currentVariant()?.label || currentVariant()?.name || '',
            });
        });
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
