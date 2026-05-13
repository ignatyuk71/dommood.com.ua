<script>
    (() => {
        const menu = document.querySelector('[data-mobile-menu]');
        const openButton = document.querySelector('[data-mobile-menu-open]');
        const closeButtons = document.querySelectorAll('[data-mobile-menu-close]');
        const searchPanel = document.querySelector('[data-mobile-search]');
        const searchOpenButton = document.querySelector('[data-mobile-search-open]');
        const searchCloseButton = document.querySelector('[data-mobile-search-close]');
        const submenuButtons = document.querySelectorAll('[data-mobile-submenu-toggle]');
        const desktopSubmenuLinks = document.querySelectorAll('[data-desktop-submenu-toggle]');
        const filterPanel = document.querySelector('[data-catalog-filters]');
        const filterOpenButtons = document.querySelectorAll('[data-catalog-filters-open]');
        const filterCloseButtons = document.querySelectorAll('[data-catalog-filters-close]');
        const priceForms = document.querySelectorAll('[data-catalog-price-filter]');

        const setPanelInert = (panel, isHidden) => {
            panel?.toggleAttribute('inert', isHidden);

            if (panel) {
                panel.inert = isHidden;
            }
        };

        const moveFocusOutside = (panel, fallbackButton) => {
            if (!panel?.contains(document.activeElement)) {
                return;
            }

            if (fallbackButton) {
                fallbackButton.focus({ preventScroll: true });
                return;
            }

            document.activeElement?.blur();
        };

        const setMenuState = (isOpen) => {
            if (!menu) {
                return;
            }

            if (isOpen) {
                setPanelInert(menu, false);
            } else {
                moveFocusOutside(menu, openButton);
            }

            menu.classList.toggle('is-open', isOpen);
            menu.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            setPanelInert(menu, !isOpen);
            document.body.classList.toggle('storefront-menu-open', isOpen);
        };

        const setSearchState = (isOpen) => {
            if (!searchPanel) {
                return;
            }

            if (isOpen) {
                setPanelInert(searchPanel, false);
            } else {
                moveFocusOutside(searchPanel, searchOpenButton);
            }

            searchPanel.classList.toggle('is-open', isOpen);
            searchPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            setPanelInert(searchPanel, !isOpen);
            document.body.classList.toggle('storefront-menu-open', isOpen);

            if (isOpen) {
                searchPanel.querySelector('input')?.focus();
            }
        };

        const setFilterState = (isOpen) => {
            if (!filterPanel) {
                return;
            }

            if (isOpen) {
                setPanelInert(filterPanel, false);
            } else {
                moveFocusOutside(filterPanel, filterOpenButtons[0] ?? null);
            }

            filterPanel.classList.toggle('is-open', isOpen);
            filterPanel.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
            setPanelInert(filterPanel, !isOpen);
            document.body.classList.toggle('storefront-menu-open', isOpen);
        };

        const closeDesktopSubmenus = (exceptItem = null) => {
            desktopSubmenuLinks.forEach((link) => {
                const item = link.closest('.storefront-desktop-menu__item');

                if (!item || item === exceptItem) {
                    return;
                }

                item.classList.remove('is-open');
                link.setAttribute('aria-expanded', 'false');
            });
        };

        openButton?.addEventListener('click', () => setMenuState(true));
        closeButtons.forEach((button) => button.addEventListener('click', () => setMenuState(false)));
        menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuState(false)));
        searchOpenButton?.addEventListener('click', () => setSearchState(true));
        searchCloseButton?.addEventListener('click', () => setSearchState(false));
        filterOpenButtons.forEach((button) => button.addEventListener('click', () => setFilterState(true)));
        filterCloseButtons.forEach((button) => button.addEventListener('click', () => setFilterState(false)));

        desktopSubmenuLinks.forEach((link) => {
            link.addEventListener('click', (event) => {
                const item = link.closest('.storefront-desktop-menu__item');

                if (!item) {
                    return;
                }

                event.preventDefault();

                const isExpanded = link.getAttribute('aria-expanded') === 'true';

                closeDesktopSubmenus(item);
                item.classList.toggle('is-open', !isExpanded);
                link.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
            });
        });

        document.addEventListener('click', (event) => {
            if (!event.target.closest('.storefront-category-nav')) {
                closeDesktopSubmenus();
            }
        });

        submenuButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const isExpanded = button.getAttribute('aria-expanded') === 'true';
                const panel = button.nextElementSibling;

                button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
                panel?.classList.toggle('is-open', !isExpanded);
            });
        });

        priceForms.forEach((form) => {
            const rangeFrom = form.querySelector('[data-price-range-from]');
            const rangeTo = form.querySelector('[data-price-range-to]');
            const inputFrom = form.querySelector('[data-price-input-from]');
            const inputTo = form.querySelector('[data-price-input-to]');
            let priceSubmitTimer = null;

            const submitPriceFilter = () => {
                if (!form.matches('[data-catalog-price-filter-auto]')) {
                    return;
                }

                window.clearTimeout(priceSubmitTimer);
                priceSubmitTimer = window.setTimeout(() => form.submit(), 420);
            };

            const syncPrice = (source) => {
                const min = Number(inputFrom?.min || rangeFrom?.min || 0);
                const max = Number(inputTo?.max || rangeTo?.max || 0);
                let from = Math.max(min, Math.min(Number(inputFrom?.value || rangeFrom?.value || min), max));
                let to = Math.max(min, Math.min(Number(inputTo?.value || rangeTo?.value || max), max));

                if (from > to) {
                    if (source === 'from') {
                        to = from;
                    } else {
                        from = to;
                    }
                }

                if (inputFrom) inputFrom.value = from;
                if (inputTo) inputTo.value = to;
                if (rangeFrom) rangeFrom.value = from;
                if (rangeTo) rangeTo.value = to;
            };

            rangeFrom?.addEventListener('input', () => {
                if (inputFrom) inputFrom.value = rangeFrom.value;
                syncPrice('from');
            });
            rangeTo?.addEventListener('input', () => {
                if (inputTo) inputTo.value = rangeTo.value;
                syncPrice('to');
            });
            rangeFrom?.addEventListener('change', submitPriceFilter);
            rangeTo?.addEventListener('change', submitPriceFilter);
            inputFrom?.addEventListener('input', () => syncPrice('from'));
            inputTo?.addEventListener('input', () => syncPrice('to'));
            inputFrom?.addEventListener('change', submitPriceFilter);
            inputTo?.addEventListener('change', submitPriceFilter);
            form.addEventListener('submit', () => syncPrice('to'));
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                setMenuState(false);
                setSearchState(false);
                setFilterState(false);
                closeDesktopSubmenus();
            }
        });
    })();
</script>
