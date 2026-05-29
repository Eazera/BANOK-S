(function () {
    'use strict';

    var storageKey = 'banoks_online_cart';
    var cartAvailability = {};
    var availabilityTimer = null;
    var lastRenderedCartCount = null;
    var footerPreloaderTimer = null;
    var footerPreloaderHideTimer = null;

    function formatPeso(value) {
        return '\u20b1' + Number(value || 0).toFixed(2);
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function getFooterPreloader() {
        var preloader = document.querySelector('[data-banoks-route-preloader]');

        if (preloader) {
            return preloader;
        }

        preloader = document.createElement('div');
        preloader.className = 'banoks-route-preloader';
        preloader.setAttribute('data-banoks-route-preloader', '');
        preloader.setAttribute('data-route', 'menu');
        preloader.hidden = true;
        preloader.innerHTML = [
            '<section class="banoks-route-skeleton is-menu" aria-hidden="true">',
                '<div class="banoks-route-content">',
                    '<div class="banoks-route-header-dark">',
                        '<div class="banoks-route-header-row"><span></span><span></span></div>',
                        '<span class="banoks-route-search"></span>',
                    '</div>',
                    '<span class="banoks-route-curve"></span>',
                    '<span class="banoks-route-title"></span>',
                    '<div class="banoks-route-tabs"><span></span><span></span><span></span><span></span></div>',
                    '<div class="banoks-route-products">',
                        '<div class="banoks-route-product"><span></span><span></span><span></span><div><span></span><span></span></div></div>',
                        '<div class="banoks-route-product"><span></span><span></span><span></span><div><span></span><span></span></div></div>',
                        '<div class="banoks-route-product"><span></span><span></span><span></span><div><span></span><span></span></div></div>',
                        '<div class="banoks-route-product"><span></span><span></span><span></span><div><span></span><span></span></div></div>',
                    '</div>',
                    '<span class="banoks-route-floating-cart"></span>',
                '</div>',
                '<div class="banoks-route-footer"><div><span></span><span></span></div><div><span></span><span></span></div><div><span></span><span></span></div></div>',
            '</section>',
            '<section class="banoks-route-skeleton is-cart" aria-hidden="true">',
                '<div class="banoks-route-content">',
                    '<div class="banoks-route-cart-head"><span></span><span></span></div>',
                    '<div class="banoks-route-steps"><span></span><span></span><span></span></div>',
                    '<span class="banoks-route-toggle"></span>',
                    '<div class="banoks-route-cart-card">',
                        '<div class="banoks-route-cart-item"><span></span><span></span><div><span></span><span></span><span></span></div><span></span></div>',
                        '<div class="banoks-route-cart-item"><span></span><span></span><div><span></span><span></span><span></span></div><span></span></div>',
                        '<div class="banoks-route-cart-item"><span></span><span></span><div><span></span><span></span><span></span></div><span></span></div>',
                    '</div>',
                '</div>',
                '<div class="banoks-route-cart-footer"><span></span><span></span></div>',
            '</section>',
            '<section class="banoks-route-skeleton is-me" aria-hidden="true">',
                '<div class="banoks-route-content">',
                    '<div class="banoks-route-profile-card">',
                        '<div class="banoks-route-profile-top"><span></span><div><span></span><span></span><span></span></div></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                        '<div class="banoks-route-profile-row"><span></span><span></span></div>',
                    '</div>',
                '</div>',
                '<div class="banoks-route-footer"><div><span></span><span></span></div><div><span></span><span></span></div><div><span></span><span></span></div></div>',
            '</section>'
        ].join('');

        document.body.appendChild(preloader);
        return preloader;
    }

    function showFooterPreloader(route) {
        var preloader = getFooterPreloader();

        window.clearTimeout(footerPreloaderTimer);
        window.clearTimeout(footerPreloaderHideTimer);
        preloader.setAttribute('data-route', route || 'menu');
        preloader.hidden = false;
        preloader.classList.remove('is-hidden');
        document.documentElement.classList.add('banoks-is-route-loading');
        document.body.classList.add('banoks-is-route-loading');

        footerPreloaderHideTimer = window.setTimeout(hideFooterPreloader, 2500);
    }

    function hideFooterPreloader() {
        var preloader = document.querySelector('[data-banoks-route-preloader]');

        window.clearTimeout(footerPreloaderTimer);
        window.clearTimeout(footerPreloaderHideTimer);
        document.documentElement.classList.remove('banoks-is-route-loading');
        document.body.classList.remove('banoks-is-route-loading');

        if (preloader) {
            preloader.hidden = true;
            preloader.classList.add('is-hidden');
        }
    }

    function initFooterNavigationPreloader() {
        document.addEventListener('click', function (event) {
            var item = event.target.closest('[data-banoks-footer-nav]');
            var link;
            var route;
            var href;

            if (!item || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            link = item.matches('a[href]') ? item : item.querySelector('a[href]');
            if (!link) {
                return;
            }

            href = link.getAttribute('href');
            if (!href || href.charAt(0) === '#') {
                return;
            }

            if (link.href === window.location.href) {
                return;
            }

            route = item.getAttribute('data-banoks-footer-nav') || 'menu';
            showFooterPreloader(route);

            event.preventDefault();
            footerPreloaderTimer = window.setTimeout(function () {
                window.location.href = link.href;
            }, 90);
        }, true);

        window.addEventListener('pageshow', function () {
            hideFooterPreloader();
        });

        window.addEventListener('pagehide', hideFooterPreloader);
        window.addEventListener('load', hideFooterPreloader);
    }

    function getCart() {
        try {
            return JSON.parse(window.localStorage.getItem(storageKey)) || {};
        } catch (error) {
            return {};
        }
    }

    function saveCart(cart) {
        try {
            window.localStorage.setItem(storageKey, JSON.stringify(cart));
        } catch (error) {
            return;
        }
    }

    function createCartLineKey(prefix) {
        return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function getCartLineKey(item, fallbackKey) {
        return String(item.lineKey || item.cartKey || fallbackKey || item.id || '');
    }

    function getItemAddons(item) {
        return Array.isArray(item.addons) ? item.addons.filter(function (addon) {
            return addon && Number(addon.qty || 0) > 0;
        }) : [];
    }

    function setCartItemQty(id, qty, addonId) {
        var cart = getCart();
        id = String(id);

        if (!cart[id]) {
            return;
        }

        qty = Number(qty || 0);
        if (addonId) {
            cart[id].addons = getItemAddons(cart[id]).filter(function (addon) {
                if (String(addon.lineKey || addon.id) !== String(addonId)) {
                    return true;
                }

                if (qty > 0) {
                    addon.qty = qty;
                    return true;
                }

                return false;
            });
        } else if (qty <= 0) {
            delete cart[id];
        } else {
            cart[id].qty = qty;
        }

        saveCart(cart);
        renderCartButtons();
        scheduleCartAvailabilityRefresh();
    }

    function removeCartItem(id, addonId) {
        var cart = getCart();
        id = String(id);

        if (cart[id] && addonId) {
            cart[id].addons = getItemAddons(cart[id]).filter(function (addon) {
                return String(addon.lineKey || addon.id) !== String(addonId);
            });
            saveCart(cart);
            renderCartButtons();
            scheduleCartAvailabilityRefresh();
        } else if (cart[id]) {
            delete cart[id];
            saveCart(cart);
            renderCartButtons();
            scheduleCartAvailabilityRefresh();
        }
    }

    function addToCart(product) {
        var cart = getCart();
        var id = product.lineKey ? String(product.lineKey) : String(product.id);
        if (!cart[id]) {
            cart[id] = {
                id: id,
                productId: product.id,
                lineKey: id,
                name: product.name,
                price: Number(product.price || 0),
                description: product.description || '',
                image: product.image || '',
                qty: 0,
                addons: []
            };
        }
        cart[id].qty += Number(product.qty || 1);
        saveCart(cart);
        renderCartButtons();
    }

    function addCartGroup(product, addons) {
        var cart = getCart();
        var validAddons = (addons || []).filter(function (addon) {
            return addon && addon.id;
        });
        var lineKey = validAddons.length ? createCartLineKey('line') : String(product.id);

        if (!cart[lineKey]) {
            cart[lineKey] = {
                id: String(product.id),
                productId: String(product.id),
                lineKey: lineKey,
                name: product.name,
                price: Number(product.price || 0),
                description: product.description || '',
                image: product.image || '',
                qty: 0,
                addons: []
            };
        }

        cart[lineKey].qty += Number(product.qty || 1);
        cart[lineKey].addons = getItemAddons(cart[lineKey]).concat(validAddons.map(function (addon) {
            return {
                id: String(addon.id),
                productId: String(addon.id),
                lineKey: createCartLineKey('addon'),
                name: addon.name,
                price: Number(addon.price || 0),
                qty: Number(addon.qty || product.qty || 1)
            };
        }));

        saveCart(cart);
        renderCartButtons();
    }

    function addAddonsToCartLine(lineKey, addons, qty) {
        var cart = getCart();
        var target = cart[String(lineKey)];

        if (!target || !addons || !addons.length) {
            return;
        }

        target.addons = getItemAddons(target);
        addons.forEach(function (addon) {
            var existing = target.addons.filter(function (currentAddon) {
                return String(currentAddon.productId || currentAddon.id) === String(addon.id);
            })[0];

            if (existing) {
                existing.qty = Number(existing.qty || 0) + Number(qty || target.qty || 1);
            } else {
                target.addons.push({
                    id: String(addon.id),
                    productId: String(addon.id),
                    lineKey: createCartLineKey('addon'),
                    name: addon.name,
                    price: Number(addon.price || 0),
                    qty: Number(qty || target.qty || 1)
                });
            }
        });

        saveCart(cart);
        renderCartButtons();
        scheduleCartAvailabilityRefresh();
    }

    function getCartItems() {
        var cart = getCart();
        return Object.keys(cart).map(function (key) {
            var item = cart[key];
            if (item) {
                item.lineKey = getCartLineKey(item, key);
                item.productId = item.productId || item.id;
            }
            return item;
        }).filter(function (item) {
            return item && Number(item.qty) > 0;
        });
    }

    function getCartCount() {
        return getCartItems().reduce(function (sum, item) {
            return sum + Number(item.qty || 0);
        }, 0);
    }

    function getCartSubtotal() {
        return getCartItems().reduce(function (sum, item) {
            return sum + (Number(item.price || 0) * Number(item.qty || 0)) + getItemAddons(item).reduce(function (addonSum, addon) {
                return addonSum + (Number(addon.price || 0) * Number(addon.qty || 0));
            }, 0);
        }, 0);
    }

    function getSelectedCartSubtotal(page) {
        var total = 0;

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            if (checkbox && !checkbox.checked) {
                return;
            }
            if (itemNode.getAttribute('data-item-can-checkout') === '0') {
                return;
            }

            total += Number(itemNode.getAttribute('data-item-price') || 0) * Number(itemNode.getAttribute('data-item-qty') || 0);

            itemNode.querySelectorAll('[data-banoks-cart-addon]').forEach(function (addonNode) {
                total += Number(addonNode.getAttribute('data-addon-price') || 0) * Number(addonNode.getAttribute('data-addon-qty') || 0);
            });
        });

        return total;
    }

    function getSelectedCartCount(page) {
        var count = 0;

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            if (checkbox && !checkbox.checked) {
                return;
            }

            count += Number(itemNode.getAttribute('data-item-qty') || 0);
        });

        return count;
    }

    function getSelectedCartItems(page) {
        var items = [];

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            if (checkbox && !checkbox.checked) {
                return;
            }
            if (itemNode.getAttribute('data-item-can-checkout') === '0') {
                return;
            }

            items.push({
                id: itemNode.getAttribute('data-item-id'),
                qty: Number(itemNode.getAttribute('data-item-qty') || 0)
            });

            itemNode.querySelectorAll('[data-banoks-cart-addon]').forEach(function (addonNode) {
                items.push({
                    id: addonNode.getAttribute('data-addon-id'),
                    qty: Number(addonNode.getAttribute('data-addon-qty') || 0)
                });
            });
        });

        return items.filter(function (item) {
            return item.id && item.qty > 0;
        });
    }

    function removeSelectedCartItems(page) {
        var cart = getCart();

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            var lineKey = itemNode.getAttribute('data-line-key') || itemNode.getAttribute('data-item-id');
            if ((!checkbox || checkbox.checked) && lineKey && cart[lineKey]) {
                delete cart[lineKey];
            }
        });

        saveCart(cart);
        renderCartButtons();
    }

    function getFulfillmentType(page) {
        var active = page.querySelector('[data-banoks-service-option].is-active');
        return active ? active.getAttribute('data-banoks-service-option') : 'delivery';
    }

    function getSelectedPaymentMethod(page) {
        var selected = page.querySelector('[data-banoks-payment-method]:checked');
        return selected ? selected.value : 'cod';
    }

    function getSelectedPaymentLabel(page) {
        var selected = page.querySelector('[data-banoks-payment-method]:checked');
        var label = selected ? selected.closest('label') : null;
        var strong = label ? label.querySelector('strong') : null;

        return strong ? strong.textContent : 'Cash on Delivery';
    }

    function getSelectedAddressId(page) {
        var selected = page.querySelector('[name="banoks_checkout_address"]:checked');
        return selected ? selected.value : '';
    }

    function getSelectedAddressLabel(page) {
        var selected = page.querySelector('[name="banoks_checkout_address"]:checked');
        var label = selected ? selected.closest('label') : null;

        if (!label) {
            return '';
        }

        return Array.prototype.map.call(label.querySelectorAll('strong, small'), function (node) {
            return node.textContent;
        }).filter(Boolean).join(', ');
    }

    function setCheckoutMessage(page, message, isError) {
        var node = page.querySelector('[data-banoks-place-order-message]');
        if (!node) {
            if (message) {
                window.alert(message);
            }
            return;
        }

        node.hidden = !message;
        node.textContent = message || '';
        node.classList.toggle('is-error', !!isError);
        node.classList.toggle('is-success', !!message && !isError);
    }

    function setPlaceOrderLoading(page, isLoading) {
        var button = page.querySelector('[data-banoks-place-order]');
        if (!button) {
            return;
        }

        button.disabled = !!isLoading;
        button.classList.toggle('is-loading', !!isLoading);
        button.textContent = isLoading ? 'Processing...' : 'Place order';
    }

    function getAjaxConfig() {
        return window.banoksCustomerAuth || {};
    }

    function initThemeHeaderAutoHide() {
        var headers = document.querySelectorAll('.banoks-theme-header');
        var lastScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
        var ticking = false;
        var threshold = 3;
        var hideAfter = 18;

        if (!headers.length) {
            return;
        }

        function setHidden(isHidden) {
            headers.forEach(function (header) {
                header.classList.toggle('is-hidden', isHidden);
            });
        }

        function updateHeaderVisibility() {
            var currentScrollY = window.pageYOffset || document.documentElement.scrollTop || 0;
            var delta = currentScrollY - lastScrollY;

            if (currentScrollY <= 20) {
                setHidden(false);
                lastScrollY = currentScrollY;
                ticking = false;
                return;
            }

            if (Math.abs(delta) < threshold) {
                ticking = false;
                return;
            }

            if (delta > 0 && currentScrollY > hideAfter) {
                setHidden(true);
            } else if (delta < 0) {
                setHidden(false);
            }

            lastScrollY = currentScrollY;
            ticking = false;
        }

        window.addEventListener('scroll', function () {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(updateHeaderVisibility);
        }, { passive: true });

        window.addEventListener('focusin', function () {
            setHidden(false);
        });
    }

    function createFormData(data) {
        var formData = new FormData();
        Object.keys(data).forEach(function (key) {
            formData.set(key, data[key]);
        });
        return formData;
    }

    function paymongoRequest(path, publicKey, payload) {
        return window.fetch('https://api.paymongo.com/v1/' + path, {
            method: 'POST',
            headers: {
                'Authorization': 'Basic ' + window.btoa(publicKey + ':'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (data) {
                if (!response.ok) {
                    throw new Error(getPaymongoErrorMessage(data));
                }
                return data;
            });
        });
    }

    function getPaymongoErrorMessage(data) {
        if (data && data.errors && data.errors[0]) {
            return data.errors[0].detail || data.errors[0].message || 'Could not start GCash payment.';
        }

        return 'Could not start GCash payment.';
    }

    function startPaymongoGcash(page, checkoutData) {
        var paymongo = checkoutData.paymongo || {};
        var billing = paymongo.billing || {};
        var billingAttributes = {};

        if (billing.name) {
            billingAttributes.name = billing.name;
        }
        if (billing.email) {
            billingAttributes.email = billing.email;
        }
        if (billing.phone) {
            billingAttributes.phone = billing.phone;
        }

        return paymongoRequest('payment_methods', paymongo.publicKey, {
            data: {
                attributes: {
                    type: 'gcash',
                    billing: billingAttributes
                }
            }
        }).then(function (paymentMethodResponse) {
            var paymentMethodId = paymentMethodResponse && paymentMethodResponse.data ? paymentMethodResponse.data.id : '';
            if (!paymentMethodId) {
                throw new Error('Could not create GCash payment method.');
            }

            return paymongoRequest('payment_intents/' + encodeURIComponent(paymongo.paymentIntentId) + '/attach', paymongo.publicKey, {
                data: {
                    attributes: {
                        payment_method: paymentMethodId,
                        client_key: paymongo.clientKey,
                        return_url: paymongo.returnUrl
                    }
                }
            });
        }).then(function (intentResponse) {
            var attributes = intentResponse && intentResponse.data && intentResponse.data.attributes ? intentResponse.data.attributes : {};
            var redirectUrl = attributes.next_action && attributes.next_action.redirect ? attributes.next_action.redirect.url : '';

            if (redirectUrl) {
                window.location.href = redirectUrl;
                return;
            }

            if (attributes.status === 'succeeded') {
                removeSelectedCartItems(page);
                setCheckoutMessage(page, 'GCash payment confirmed. Your order has been placed.', false);
                return;
            }

            setCheckoutMessage(page, 'GCash payment is processing. We will confirm it shortly.', false);
        });
    }

    function submitCheckout(page) {
        var config = getAjaxConfig();
        var fulfillment = getFulfillmentType(page);
        var paymentMethod = getSelectedPaymentMethod(page);
        var selectedItems = getSelectedCartItems(page);
        var addressId = getSelectedAddressId(page);
        var formData;

        if (!config.ajaxUrl || !config.checkoutNonce) {
            setCheckoutMessage(page, 'Checkout is not available right now. Please try again.', true);
            return;
        }

        if (!selectedItems.length) {
            setCheckoutMessage(page, page.querySelector('[data-banoks-cart-item].is-selected-blocked') ? 'Uncheck out-of-stock items before checkout.' : 'Please select at least one item.', true);
            return;
        }

        if (page.querySelector('[data-banoks-cart-item].is-selected-blocked')) {
            setCheckoutMessage(page, 'Uncheck out-of-stock items before checkout.', true);
            return;
        }

        if (fulfillment === 'delivery' && !addressId) {
            setCheckoutMessage(page, 'Please select a delivery address.', true);
            return;
        }

        setCheckoutMessage(page, '', false);
        setPlaceOrderLoading(page, true);

        formData = createFormData({
            action: 'banoks_customer_checkout',
            nonce: config.checkoutNonce,
            fulfillment_type: fulfillment,
            payment_method: paymentMethod,
            address_id: addressId,
            items: JSON.stringify(selectedItems)
        });

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json().catch(function () {
                return { success: false, data: { message: 'Could not place your order.' } };
            });
        }).then(function (data) {
            if (!data || !data.success) {
                throw new Error((data && data.data && data.data.message) || 'Could not place your order.');
            }

            if (data.data && data.data.paymentMethod === 'gcash') {
                return startPaymongoGcash(page, data.data);
            }

            removeSelectedCartItems(page);
            setCheckoutMessage(page, 'Order placed successfully.', false);
        }).catch(function (error) {
            setCheckoutMessage(page, error && error.message ? error.message : 'Could not place your order.', true);
        }).finally(function () {
            setPlaceOrderLoading(page, false);
        });
    }

    function pollReturnedPayment(page, orderId) {
        var config = getAjaxConfig();
        var attempts = 0;

        if (!config.ajaxUrl || !config.paymentStatusNonce || !orderId) {
            return;
        }

        setCartView(page, 'checkout');
        setCheckoutMessage(page, 'GCash payment is processing. Waiting for confirmation...', false);

        function poll() {
            var formData = createFormData({
                action: 'banoks_customer_order_payment_status',
                nonce: config.paymentStatusNonce,
                order_id: orderId
            });

            attempts++;
            window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.json();
            }).then(function (data) {
                var paymentStatus = data && data.data ? data.data.paymentStatus : '';
                if (paymentStatus === 'paid') {
                    removeSelectedCartItems(page);
                    setCheckoutMessage(page, 'GCash payment confirmed. Your order has been placed.', false);
                    return;
                }

                if (paymentStatus === 'failed') {
                    setCheckoutMessage(page, (data.data && data.data.failureReason) || 'GCash payment failed. Please try again.', true);
                    return;
                }

                if (attempts < 10) {
                    window.setTimeout(poll, 3000);
                }
            }).catch(function () {
                if (attempts < 10) {
                    window.setTimeout(poll, 3000);
                }
            });
        }

        poll();
    }

    function openOrderConfirmModal(page) {
        var modal = page.querySelector('[data-banoks-order-confirm-modal]');
        var details = page.querySelector('[data-banoks-confirm-details]');
        var items = page.querySelector('[data-banoks-confirm-items]');
        var summary = page.querySelector('[data-banoks-confirm-summary]');
        var fulfillment = getFulfillmentType(page);
        var selectedAddress = fulfillment === 'delivery' ? getSelectedAddressLabel(page) : 'Manukan, Sunset Boulevard';
        var selectedItems = [];
        var subtotal = getSelectedCartSubtotal(page);
        var selectedAddressInput = page.querySelector('[name="banoks_checkout_address"]:checked');
        var deliveryFee = fulfillment === 'delivery' && subtotal > 0 && selectedAddressInput ? Number(selectedAddressInput.getAttribute('data-delivery-fee') || 0) : 0;
        var total = subtotal + deliveryFee;

        if (!modal || !details || !items || !summary) {
            submitCheckout(page);
            return;
        }

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            if ((checkbox && !checkbox.checked) || itemNode.getAttribute('data-item-can-checkout') === '0') {
                return;
            }

            selectedItems.push({
                name: itemNode.querySelector('[data-banoks-cart-name]') ? itemNode.querySelector('[data-banoks-cart-name]').textContent : 'Item',
                qty: Number(itemNode.getAttribute('data-item-qty') || 0),
                type: 'product',
                total: Number(itemNode.getAttribute('data-item-price') || 0) * Number(itemNode.getAttribute('data-item-qty') || 0)
            });

            itemNode.querySelectorAll('[data-banoks-cart-addon]').forEach(function (addonNode) {
                selectedItems.push({
                    name: addonNode.querySelector('.banoks-cart-addon-meta strong') ? addonNode.querySelector('.banoks-cart-addon-meta strong').textContent : 'Add-on',
                    qty: Number(addonNode.getAttribute('data-addon-qty') || 0),
                    type: 'addon',
                    total: Number(addonNode.getAttribute('data-addon-price') || 0) * Number(addonNode.getAttribute('data-addon-qty') || 0)
                });
            });
        });

        details.innerHTML = '<div><dt>Order Type</dt><dd>' + escapeHtml(fulfillment === 'pickup' ? 'Pick-up' : 'Delivery') + '</dd></div>' +
            '<div><dt>' + escapeHtml(fulfillment === 'pickup' ? 'Pick-up Location' : 'Delivery Address') + '</dt><dd>' + escapeHtml(selectedAddress || '-') + '</dd></div>' +
            '<div><dt>Payment</dt><dd>' + escapeHtml(getSelectedPaymentLabel(page)) + '</dd></div>';

        items.innerHTML = selectedItems.map(function (item) {
            return '<div class="' + (item.type === 'addon' ? 'is-addon' : '') + '"><span>' + (item.type === 'addon' ? '+ ' : '') + escapeHtml(item.name) + ' x ' + item.qty + '</span><strong>' + formatPeso(item.total) + '</strong></div>';
        }).join('');

        summary.innerHTML = '<div><dt>Subtotal</dt><dd>' + formatPeso(subtotal) + '</dd></div>' +
            '<div><dt>Delivery Fee</dt><dd>' + formatPeso(deliveryFee) + '</dd></div>' +
            '<div><dt>Total</dt><dd>' + formatPeso(total) + '</dd></div>';

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');

        var okButton = modal.querySelector('[data-banoks-confirm-ok]');
        if (okButton) {
            okButton.focus();
        }
    }

    function closeOrderConfirmModal(page) {
        var modal = page.querySelector('[data-banoks-order-confirm-modal]');
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
    }

    function renderCheckoutSummary(page) {
        var subtotal = getSelectedCartSubtotal(page);
        var selectedAddress = page.querySelector('[name="banoks_checkout_address"]:checked');
        var deliveryFee = getFulfillmentType(page) === 'delivery' && subtotal > 0 && selectedAddress ? Number(selectedAddress.getAttribute('data-delivery-fee') || 0) : 0;
        var discount = 0;
        var vat = 0;
        var total = Math.max(0, subtotal + deliveryFee + vat - discount);
        var fields = {
            '[data-banoks-summary-subtotal]': formatPeso(subtotal),
            '[data-banoks-summary-delivery]': deliveryFee ? String(deliveryFee) : '0',
            '[data-banoks-summary-discount]': String(discount),
            '[data-banoks-summary-vat]': vat ? String(vat) : '0',
            '[data-banoks-summary-total]': formatPeso(total),
            '[data-banoks-checkout-footer-total]': formatPeso(total)
        };

        Object.keys(fields).forEach(function (selector) {
            var node = page.querySelector(selector);
            if (node) {
                node.textContent = fields[selector];
            }
        });
    }

    function refreshCartAvailability() {
        var config = getAjaxConfig();
        var productMap = {};
        getCartItems().forEach(function (item) {
            productMap[String(item.productId || item.id)] = true;
            getItemAddons(item).forEach(function (addon) {
                productMap[String(addon.productId || addon.id)] = true;
            });
        });
        var productIds = Object.keys(productMap);
        var formData;

        if (!config.ajaxUrl || !config.cartAvailabilityNonce || !productIds.length) {
            cartAvailability = {};
            renderCartPages();
            return;
        }

        formData = createFormData({
            action: 'banoks_cart_item_availability',
            nonce: config.cartAvailabilityNonce,
            product_ids: JSON.stringify(productIds)
        });

        window.fetch(config.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            cartAvailability = data && data.success && data.data && data.data.items ? data.data.items : {};
            renderCartPages();
        }).catch(function () {
            cartAvailability = {};
            renderCartPages();
        });
    }

    function scheduleCartAvailabilityRefresh() {
        window.clearTimeout(availabilityTimer);
        availabilityTimer = window.setTimeout(refreshCartAvailability, 150);
    }

    function renderFulfillmentPanels(page) {
        var fulfillment = getFulfillmentType(page);
        var deliveryPanel = page.querySelector('[data-banoks-delivery-address-panel]');
        var pickupPanel = page.querySelector('[data-banoks-pickup-address-panel]');
        var addressHeading = page.querySelector('[data-banoks-address-heading]');
        var addAddressButton = page.querySelector('[data-banoks-add-address-toggle]');
        var addressForm = page.querySelector('[data-banoks-address-form]');
        var isPickup = fulfillment === 'pickup';

        if (deliveryPanel) {
            deliveryPanel.hidden = isPickup;
        }
        if (pickupPanel) {
            pickupPanel.hidden = !isPickup;
        }
        if (addressHeading) {
            addressHeading.textContent = isPickup ? 'Pick-up Location' : 'Delivery Address';
        }
        if (addAddressButton) {
            addAddressButton.hidden = isPickup;
        }
        if (addressForm && isPickup) {
            addressForm.hidden = true;
        }
    }

    function setCartView(page, view) {
        var cartView = page.querySelector('[data-banoks-cart-view]');
        var checkoutView = page.querySelector('[data-banoks-checkout-view]');
        var cartFooter = page.querySelector('[data-banoks-cart-footer]');
        var checkoutFooter = page.querySelector('[data-banoks-checkout-footer]');
        var title = page.querySelector('[data-banoks-cart-title]');
        var isCheckout = view === 'checkout';

        if (isCheckout && getSelectedCartCount(page) <= 0) {
            return;
        }

        if (cartView) {
            cartView.hidden = isCheckout;
        }
        if (checkoutView) {
            checkoutView.hidden = !isCheckout;
        }
        if (cartFooter) {
            cartFooter.hidden = isCheckout;
        }
        if (checkoutFooter) {
            checkoutFooter.hidden = !isCheckout;
        }
        if (title) {
            title.textContent = isCheckout ? 'Checkout' : 'Cart';
        }

        page.classList.toggle('is-checkout', isCheckout);
        page.querySelectorAll('[data-banoks-progress-step]').forEach(function (step) {
            var stepName = step.getAttribute('data-banoks-progress-step');
            step.classList.toggle('is-active', (isCheckout && stepName === 'checkout') || (!isCheckout && stepName === 'cart'));
            step.classList.toggle('is-complete', stepName === 'menu' || (isCheckout && stepName === 'cart'));
        });

        renderFulfillmentPanels(page);
        renderCheckoutSummary(page);
    }

    function setMeView(page, view, shouldScroll, orderId) {
        var mainView = page.querySelector('[data-banoks-me-main]');
        var ordersView = page.querySelector('[data-banoks-me-orders-view]');
        var ordersList = page.querySelector('[data-banoks-me-orders-list]');
        var detailViews = page.querySelectorAll('[data-banoks-me-order-detail]');
        var isDetail = view === 'order-detail';
        var isOrders = view === 'orders' || isDetail;
        var hasActiveDetail = false;

        if (!mainView || !ordersView) {
            return;
        }

        mainView.hidden = isOrders;
        ordersView.hidden = !isOrders;
        if (ordersList) {
            ordersList.hidden = isDetail;
        }
        detailViews.forEach(function (detail) {
            var isActive = isDetail && detail.getAttribute('data-banoks-me-order-detail') === String(orderId || '');
            detail.hidden = !isActive;
            hasActiveDetail = hasActiveDetail || isActive;
        });
        if (isDetail && !hasActiveDetail && ordersList) {
            ordersList.hidden = false;
            isDetail = false;
        }

        page.classList.toggle('is-order-detail', isDetail);
        page.classList.toggle('is-orders', isOrders);
        page.classList.toggle('is-main', !isOrders);

        if (shouldScroll && typeof page.scrollIntoView === 'function') {
            page.scrollIntoView({ block: 'start' });
        }
    }

    function initMePages() {
        document.querySelectorAll('[data-banoks-me-page]').forEach(function (page) {
            if (page.getAttribute('data-banoks-me-ready') === '1') {
                return;
            }

            page.setAttribute('data-banoks-me-ready', '1');
            setMeView(page, window.location.hash === '#banoks-me-orders' ? 'orders' : 'main', false);

            page.addEventListener('click', function (event) {
                var openButton = event.target.closest('[data-banoks-me-open]');
                var backButton = event.target.closest('[data-banoks-me-back]');
                var orderButton = event.target.closest('[data-banoks-me-order-id]');
                var ordersBackButton = event.target.closest('[data-banoks-me-orders-back]');

                if (openButton && page.contains(openButton)) {
                    event.preventDefault();
                    setMeView(page, openButton.getAttribute('data-banoks-me-open') || 'main', true);
                    return;
                }

                if (orderButton && page.contains(orderButton)) {
                    event.preventDefault();
                    setMeView(page, 'order-detail', true, orderButton.getAttribute('data-banoks-me-order-id'));
                    return;
                }

                if (ordersBackButton && page.contains(ordersBackButton)) {
                    event.preventDefault();
                    setMeView(page, 'orders', true);
                    return;
                }

                if (backButton && page.contains(backButton)) {
                    event.preventDefault();
                    setMeView(page, 'main', true);
                }
            });
        });
    }

    function triggerViewCartAddedAnimation() {
        document.querySelectorAll('.banoks-view-cart-button, .banoks-theme-floating-cart').forEach(function (node) {
            node.classList.remove('is-cart-added');
            void node.offsetWidth;
            node.classList.add('is-cart-added');

            window.setTimeout(function () {
                node.classList.remove('is-cart-added');
            }, 720);
        });
    }

    function renderCartButtons() {
        var count = getCartCount();
        var subtotal = getCartSubtotal();
        var shouldAnimate = lastRenderedCartCount !== null && count > lastRenderedCartCount;

        document.querySelectorAll('.banoks-cart-count').forEach(function (badge) {
            badge.textContent = String(count);
            badge.classList.toggle('has-items', count > 0);
            if (badge.closest('.banoks-cart-button-shortcode')) {
                badge.hidden = count <= 0;
            }
        });

        document.querySelectorAll('.banoks-floating-cart-button').forEach(function (button) {
            button.classList.toggle('has-items', count > 0);
        });

        document.querySelectorAll('.banoks-view-cart-button').forEach(function (button) {
            var total = button.querySelector('.banoks-view-cart-total');
            button.hidden = count <= 0;
            button.classList.toggle('has-items', count > 0);

            if (total) {
                total.textContent = formatPeso(subtotal);
            }
        });

        document.querySelectorAll('.banoks-theme-floating-cart').forEach(function (wrapper) {
            wrapper.classList.toggle('has-items', count > 0);
        });

        if (shouldAnimate && count > 0) {
            triggerViewCartAddedAnimation();
        }

        lastRenderedCartCount = count;
        renderCartPages();
    }

    function renderCartPageTotal(page) {
        var total = 0;
        var totalNode = page.querySelector('[data-banoks-cart-total]');
        var checkoutButton = page.querySelector('[data-banoks-checkout-button]');
        var selectedCount = 0;
        var selectedBlockedCount = 0;

        page.querySelectorAll('[data-banoks-cart-item]').forEach(function (itemNode) {
            var checkbox = itemNode.querySelector('[data-banoks-cart-select]');
            var selected = !checkbox || checkbox.checked;
            var blocked = itemNode.getAttribute('data-item-can-checkout') === '0';
            var price = Number(itemNode.getAttribute('data-item-price') || 0);
            var qty = Number(itemNode.getAttribute('data-item-qty') || 0);

            itemNode.classList.toggle('is-unselected', !selected);
            itemNode.classList.toggle('is-selected-blocked', selected && blocked);
            if (selected) {
                selectedCount += qty;
                if (blocked) {
                    selectedBlockedCount += 1;
                } else {
                    total += price * qty;
                }
            }
        });

        if (totalNode) {
            totalNode.textContent = formatPeso(total);
        }

        if (checkoutButton) {
            checkoutButton.classList.toggle('is-disabled', selectedCount <= 0 || selectedBlockedCount > 0);
            checkoutButton.setAttribute('aria-disabled', selectedCount <= 0 || selectedBlockedCount > 0 ? 'true' : 'false');
            checkoutButton.disabled = selectedCount <= 0 || selectedBlockedCount > 0;
        }

        renderCheckoutSummary(page);
    }

    function createAddressOption(address) {
        var label = document.createElement('label');
        var input = document.createElement('input');
        var text = document.createElement('span');
        var municipality = document.createElement('strong');
        var details = document.createElement('small');

        label.className = 'banoks-checkout-address-option';
        input.type = 'radio';
        input.name = 'banoks_checkout_address';
        input.value = String(address.id || '');
        input.setAttribute('data-delivery-area-id', String(address.deliveryAreaId || 0));
        input.setAttribute('data-delivery-fee', String(address.deliveryFee || 0));
        input.checked = true;

        municipality.textContent = address.municipality || 'Manukan';
        details.textContent = [address.barangay || '', address.sitio || ''].filter(Boolean).join(', ');

        text.appendChild(municipality);
        text.appendChild(details);
        label.appendChild(input);
        label.appendChild(text);

        return label;
    }

    function updateCartMinusButton(button, qty, label) {
        if (!button) {
            return;
        }

        if (Number(qty || 0) <= 1) {
            button.innerHTML = '<img src="' + escapeHtml((window.banoksCustomerAuth && window.banoksCustomerAuth.deleteIconUrl) || '') + '" alt="" aria-hidden="true">';
            button.setAttribute('aria-label', 'Remove ' + (label || 'item'));
            button.classList.add('is-delete-mode');
        } else {
            button.textContent = '-';
            button.setAttribute('aria-label', 'Decrease ' + (label || 'item') + ' quantity');
            button.classList.remove('is-delete-mode');
        }
    }

    function createCartAddonNode(lineKey, addon) {
        var addonNode = document.createElement('div');
        var meta = document.createElement('div');
        var name = document.createElement('strong');
        var controls = document.createElement('div');
        var stepper = document.createElement('div');
        var minus = document.createElement('button');
        var qty = document.createElement('span');
        var plus = document.createElement('button');
        var price = document.createElement('strong');
        var remove = document.createElement('button');
        var removeIcon = document.createElement('img');

        addonNode.className = 'banoks-cart-addon-item';
        addonNode.setAttribute('data-banoks-cart-addon', '');
        addonNode.setAttribute('data-line-key', lineKey);
        addonNode.setAttribute('data-addon-id', addon.productId || addon.id);
        addonNode.setAttribute('data-addon-line-key', addon.lineKey || addon.id);
        addonNode.setAttribute('data-addon-price', Number(addon.price || 0));
        addonNode.setAttribute('data-addon-qty', Number(addon.qty || 0));

        meta.className = 'banoks-cart-addon-meta';
        name.textContent = addon.name || 'Add-on';
        meta.appendChild(name);

        controls.className = 'banoks-cart-addon-controls';
        stepper.className = 'banoks-cart-qty-stepper banoks-cart-addon-stepper';

        minus.type = 'button';
        minus.setAttribute('data-banoks-cart-action', 'addon-minus');
        updateCartMinusButton(minus, addon.qty, addon.name || 'add-on');

        qty.textContent = String(Number(addon.qty || 0));

        plus.type = 'button';
        plus.textContent = '+';
        plus.setAttribute('data-banoks-cart-action', 'addon-plus');
        plus.setAttribute('aria-label', 'Increase ' + (addon.name || 'add-on') + ' quantity');

        stepper.appendChild(minus);
        stepper.appendChild(qty);
        stepper.appendChild(plus);

        price.textContent = formatPeso(Number(addon.price || 0) * Number(addon.qty || 0));

        remove.type = 'button';
        remove.className = 'banoks-cart-delete banoks-cart-addon-delete';
        remove.setAttribute('data-banoks-cart-action', 'addon-remove');
        remove.setAttribute('aria-label', 'Remove ' + (addon.name || 'add-on'));
        removeIcon.src = (window.banoksCustomerAuth && window.banoksCustomerAuth.deleteIconUrl) || '';
        removeIcon.alt = '';
        removeIcon.setAttribute('aria-hidden', 'true');
        remove.appendChild(removeIcon);

        controls.appendChild(stepper);
        controls.appendChild(price);
        controls.appendChild(remove);

        addonNode.appendChild(meta);
        addonNode.appendChild(controls);

        return addonNode;
    }

    function createCartAddonPicker(lineKey, addons, qty) {
        var picker = document.createElement('div');
        var options = document.createElement('div');
        var actions = document.createElement('div');
        var addButton = document.createElement('button');
        var closeButton = document.createElement('button');

        picker.className = 'banoks-cart-addon-picker-inner';
        options.className = 'banoks-cart-addon-picker-options';

        addons.forEach(function (addon) {
            var disabled = addon.canCheckout === false;
            var label = document.createElement('label');
            var input = document.createElement('input');
            var text = document.createElement('span');
            var name = document.createElement('strong');
            var meta = document.createElement('small');

            label.className = 'banoks-cart-addon-picker-option' + (disabled ? ' is-disabled' : '');
            input.type = 'checkbox';
            input.value = String(addon.id);
            input.setAttribute('data-addon-name', addon.name || 'Add-on');
            input.setAttribute('data-addon-price', Number(addon.price || 0));
            input.disabled = disabled;
            name.textContent = addon.name || 'Add-on';
            meta.textContent = disabled ? (addon.stockLabel || 'Out of Stock') : formatPeso(addon.price || 0);
            text.appendChild(name);
            text.appendChild(meta);
            label.appendChild(input);
            label.appendChild(text);
            options.appendChild(label);
        });

        actions.className = 'banoks-cart-addon-picker-actions';
        closeButton.type = 'button';
        closeButton.textContent = 'Back';
        closeButton.setAttribute('data-banoks-cart-action', 'close-addon-picker');
        addButton.type = 'button';
        addButton.textContent = '+ Addons';
        addButton.setAttribute('data-banoks-cart-action', 'confirm-addons');
        addButton.setAttribute('data-line-key', lineKey);
        addButton.setAttribute('data-addon-qty', Number(qty || 1));
        actions.appendChild(closeButton);
        actions.appendChild(addButton);

        picker.appendChild(options);
        picker.appendChild(actions);

        return picker;
    }

    function renderCartPages() {
        document.querySelectorAll('[data-banoks-cart-page]').forEach(function (page) {
            var items = getCartItems();
            var list = page.querySelector('[data-banoks-cart-items]');
            var empty = page.querySelector('[data-banoks-cart-empty]');
            var addMore = page.querySelector('[data-banoks-cart-add-more]');
            var template = document.getElementById('banoks-cart-item-template');

            if (!list || !template) {
                return;
            }

            list.innerHTML = '';
            if (empty) {
                empty.hidden = items.length > 0;
            }
            if (addMore) {
                addMore.hidden = items.length <= 0;
            }

            items.forEach(function (item) {
                var node = template.content.firstElementChild.cloneNode(true);
                var lineKey = getCartLineKey(item);
                var productId = String(item.productId || item.id);
                var addons = getItemAddons(item);
                var availability = cartAvailability[productId] || cartAvailability[Number(productId)] || null;
                var addonBlocked = addons.some(function (addon) {
                    var addonId = String(addon.productId || addon.id);
                    var addonAvailability = cartAvailability[addonId] || cartAvailability[Number(addonId)] || null;
                    return addonAvailability && addonAvailability.canCheckout === false;
                });
                var canCheckout = (!availability || availability.canCheckout !== false) && !addonBlocked;
                var warningText = availability && availability.reason ? availability.reason : 'This item is unavailable.';
                if (addonBlocked) {
                    warningText = 'Out of Stock.';
                }
                var image = node.querySelector('[data-banoks-cart-image]');
                var name = node.querySelector('[data-banoks-cart-name]');
                var description = node.querySelector('[data-banoks-cart-description]');
                var qty = node.querySelector('[data-banoks-cart-qty]');
                var minus = node.querySelector('[data-banoks-cart-minus]');
                var price = node.querySelector('[data-banoks-cart-price]');
                var checkbox = node.querySelector('[data-banoks-cart-select]');
                var stockWarning = node.querySelector('[data-banoks-cart-stock-warning]');
                var addonList = node.querySelector('[data-banoks-cart-addon-list]');
                var addAddonButton = node.querySelector('[data-banoks-cart-add-addon]');
                var addonPicker = node.querySelector('[data-banoks-cart-addon-picker]');
                var availableAddons = (window.banoksOnlineAddons && window.banoksOnlineAddons[productId]) ? window.banoksOnlineAddons[productId] : [];

                node.setAttribute('data-line-key', lineKey);
                node.setAttribute('data-item-id', productId);
                node.setAttribute('data-item-price', Number(item.price || 0));
                node.setAttribute('data-item-qty', Number(item.qty || 0));
                node.setAttribute('data-item-can-checkout', canCheckout ? '1' : '0');
                node.classList.toggle('is-stock-blocked', !canCheckout);

                if (image && item.image) {
                    image.innerHTML = '<img src="' + escapeHtml(item.image) + '" alt="">';
                }
                if (name) {
                    name.textContent = item.name || 'Item';
                }
                if (description) {
                    description.textContent = item.description || 'No description available.';
                }
                if (qty) {
                    qty.textContent = String(Number(item.qty || 0));
                }
                updateCartMinusButton(minus, item.qty, item.name || 'item');
                if (price) {
                    price.textContent = formatPeso(Number(item.price || 0) * Number(item.qty || 0));
                }
                if (checkbox) {
                    checkbox.setAttribute('aria-label', 'Select ' + (item.name || 'item'));
                }
                if (stockWarning) {
                    stockWarning.hidden = canCheckout;
                    stockWarning.textContent = canCheckout ? '' : warningText;
                }
                if (addonList) {
                    addonList.hidden = !addons.length;
                    addonList.innerHTML = '';
                    addons.forEach(function (addon) {
                        addonList.appendChild(createCartAddonNode(lineKey, addon));
                    });
                }
                if (addAddonButton) {
                    addAddonButton.hidden = !availableAddons.length;
                }
                if (addonPicker) {
                    addonPicker.hidden = true;
                    addonPicker.innerHTML = '';
                    if (availableAddons.length) {
                        addonPicker.appendChild(createCartAddonPicker(lineKey, availableAddons, item.qty));
                    }
                }

                list.appendChild(node);
            });

            renderCartPageTotal(page);
        });
    }

    document.querySelectorAll('.banoks-customer-auth').forEach(function (shell) {
        if (shell.getAttribute('data-banoks-auth-ready') === '1') {
            return;
        }

        shell.setAttribute('data-banoks-auth-ready', '1');
        document.body.classList.add('banoks-auth-page');

        function getAuthMessageParts() {
            return {
                modal: shell.querySelector('[data-banoks-auth-message-modal]'),
                text: shell.querySelector('[data-banoks-auth-message-text]')
            };
        }

        function banoksShowAuthModal(message) {
            var parts = getAuthMessageParts();
            if (!parts.modal || !parts.text) {
                window.alert(message);
                return;
            }

            parts.text.textContent = message;
            parts.modal.classList.add('is-open');
            parts.modal.setAttribute('aria-hidden', 'false');
            document.documentElement.classList.add('banoks-auth-modal-open');

            var closeButton = parts.modal.querySelector('[data-banoks-auth-message-close]');
            if (closeButton) {
                closeButton.focus();
            }
        }

        function closeAuthMessage() {
            var parts = getAuthMessageParts();
            if (!parts.modal) {
                return;
            }

            parts.modal.classList.remove('is-open');
            parts.modal.setAttribute('aria-hidden', 'true');
            document.documentElement.classList.remove('banoks-auth-modal-open');
        }

        function openRegisterPanel() {
            var panel = shell.querySelector('[data-banoks-register-modal]');
            if (!panel) {
                return;
            }

            panel.classList.add('is-open');
            panel.setAttribute('aria-hidden', 'false');
            panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

            var firstField = panel.querySelector('input, select, button');
            if (firstField) {
                firstField.focus();
            }
        }

        function closeRegisterPanel() {
            var panel = shell.querySelector('[data-banoks-register-modal]');
            var openButton = shell.querySelector('[data-banoks-open-register]');
            if (!panel) {
                return;
            }

            panel.classList.remove('is-open');
            panel.setAttribute('aria-hidden', 'true');
            if (openButton) {
                openButton.focus();
            }
        }

        function getField(form, name) {
            return form.querySelector('[name="' + name + '"]');
        }

        function getFieldValue(form, name) {
            var field = getField(form, name);
            return field ? String(field.value || '').trim() : '';
        }

        function validateAuthForm(form, type) {
            var required = type === 'register'
                ? ['full_name', 'username', 'contact_number', 'barangay', 'sitio', 'password', 'confirm_password']
                : ['identifier', 'password'];
            var i;

            for (i = 0; i < required.length; i++) {
                if (!getFieldValue(form, required[i])) {
                    return type === 'register' ? 'Please complete all required fields.' : 'Please enter your username and password.';
                }
            }

            if (type === 'register' && getFieldValue(form, 'password') !== getFieldValue(form, 'confirm_password')) {
                return 'Password and confirm password must match.';
            }

            if (type === 'register' && (!getField(form, 'privacy_agree') || !getField(form, 'privacy_agree').checked)) {
                return 'Please agree to the Data Privacy Policy before creating an account.';
            }

            return '';
        }

        function submitAuthForm(form) {
            var type = form.getAttribute('data-banoks-auth-form');
            var validation = validateAuthForm(form, type);
            var config = window.banoksCustomerAuth || {};
            var submitButton = form.querySelector('button[type="submit"]');
            var formData;

            if (validation) {
                banoksShowAuthModal(validation);
                return;
            }

            if (!config.ajaxUrl) {
                banoksShowAuthModal(type === 'register' ? 'Account creation failed. Please try again.' : 'Login failed. Please try again.');
                return;
            }

            formData = new FormData(form);
            formData.set('action', type === 'register' ? 'banoks_customer_register' : 'banoks_customer_login');
            formData.set('nonce', type === 'register' ? (config.registerNonce || '') : (config.loginNonce || ''));
            if (type === 'register') {
                formData.set('municipality', 'Manukan');
            }

            if (submitButton) {
                submitButton.disabled = true;
            }

            window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.json().catch(function () {
                    return { success: false, data: { message: 'Login failed. Please try again.' } };
                });
            }).then(function (data) {
                var message = data && data.data && data.data.message ? data.data.message : '';
                if (!data || !data.success) {
                    banoksShowAuthModal(message || (type === 'register' ? 'Account creation failed. Please try again.' : 'Invalid username or password.'));
                    return;
                }

                banoksShowAuthModal(message || (type === 'register' ? 'Account created successfully.' : 'Logged in successfully.'));
                if (type === 'login' || type === 'register') {
                    window.location.href = (data.data && data.data.redirectUrl) || config.mainPageUrl || window.location.href;
                    return;
                }
            }).catch(function () {
                banoksShowAuthModal(type === 'register' ? 'Account creation failed. Please try again.' : 'Login failed. Please try again.');
            }).finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
        }

        shell.addEventListener('click', function (event) {
            var target = event.target;
            var passwordToggle = target.closest('[data-banoks-password-toggle]');
            var socialButton = target.closest('[data-banoks-social-message]');

            if (target.closest('[data-banoks-open-register]')) {
                event.preventDefault();
                openRegisterPanel();
                return;
            }

            if (target.closest('[data-banoks-close-register]')) {
                event.preventDefault();
                closeRegisterPanel();
                return;
            }

            if (target.closest('[data-banoks-auth-message-close]')) {
                event.preventDefault();
                closeAuthMessage();
                return;
            }

            if (socialButton) {
                event.preventDefault();
                banoksShowAuthModal(socialButton.getAttribute('data-banoks-social-message') || 'Social login setup is coming next.');
                return;
            }

            if (passwordToggle) {
                var field = passwordToggle.closest('.banoks-auth-password-field');
                var input = field ? field.querySelector('input[type="password"], input[type="text"]') : null;
                var isHidden = input && input.type === 'password';
                if (!input) {
                    return;
                }

                input.type = isHidden ? 'text' : 'password';
                passwordToggle.classList.toggle('is-visible', isHidden);
                passwordToggle.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            }
        });

        shell.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-banoks-auth-form]');
            if (!form || !shell.contains(form)) {
                return;
            }

            event.preventDefault();
            submitAuthForm(form);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') {
                return;
            }

            if (shell.querySelector('[data-banoks-auth-message-modal].is-open')) {
                closeAuthMessage();
            } else if (shell.querySelector('[data-banoks-register-modal].is-open')) {
                closeRegisterPanel();
            }
        });
    });

    document.querySelectorAll('[data-banoks-menu]').forEach(function (menu) {
        var activeProduct = null;
        var shell = menu.closest('.banoks-online-shell') || document;
        var modal = shell.querySelector('#banoks-cart-modal');
        var modalTitle = shell.querySelector('#banoks-cart-modal-title');
        var modalPrice = shell.querySelector('#banoks-cart-modal-price');
        var modalImage = shell.querySelector('#banoks-cart-modal-image');
        var modalQty = shell.querySelector('#banoks-cart-modal-qty');
        var modalAddonList = shell.querySelector('#banoks-cart-addon-list');
        var addonMap = window.banoksOnlineAddons || {};
        var searchInputs = document.querySelectorAll('.banoks-theme-search-input');
        var emptySearch = null;

        function normalizeSearchText(value) {
            return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
        }

        function getMenuSearchQuery() {
            var query = '';

            searchInputs.forEach(function (input) {
                if (!query && input.value) {
                    query = input.value;
                }
            });

            return normalizeSearchText(query);
        }

        function getActiveMenuCategory() {
            var active = menu.querySelector('.banoks-menu-category-btn.is-active');
            return active ? active.getAttribute('data-category-filter') || 'all' : 'all';
        }

        function getMenuItemSearchText(item) {
            if (!item.getAttribute('data-search-text')) {
                item.setAttribute('data-search-text', normalizeSearchText(item.textContent + ' ' + (item.getAttribute('data-category') || '')));
            }

            return item.getAttribute('data-search-text') || '';
        }

        function getEmptySearchNode() {
            var grid = menu.querySelector('.banoks-menu-grid');

            if (emptySearch || !grid || !grid.parentNode) {
                return emptySearch;
            }

            emptySearch = document.createElement('p');
            emptySearch.className = 'banoks-menu-empty-search';
            emptySearch.hidden = true;
            emptySearch.textContent = 'No menu items match your search.';
            grid.parentNode.insertBefore(emptySearch, grid.nextSibling);

            return emptySearch;
        }

        function applyMenuFilters() {
            var query = getMenuSearchQuery();
            var category = getActiveMenuCategory();
            var visibleCount = 0;
            var emptyNode;

            menu.querySelectorAll('.banoks-menu-item').forEach(function (item) {
                var matchesSearch = !query || getMenuItemSearchText(item).indexOf(query) !== -1;
                var matchesCategory = !!query || category === 'all' || item.getAttribute('data-category') === category;
                var isVisible = matchesSearch && matchesCategory;

                item.hidden = !isVisible;
                if (isVisible) {
                    visibleCount += 1;
                }
            });

            emptyNode = getEmptySearchNode();
            if (emptyNode) {
                emptyNode.hidden = visibleCount > 0;
            }
        }

        function openCartModal(button) {
            var productId = button.getAttribute('data-product-id');
            activeProduct = {
                id: productId,
                name: button.getAttribute('data-product-name') || 'Product',
                price: parseFloat(button.getAttribute('data-product-price')) || 0,
                description: button.getAttribute('data-product-description') || '',
                image: button.getAttribute('data-product-image') || ''
            };
            var addons = addonMap[productId] || [];

            if (modalTitle) {
                modalTitle.textContent = activeProduct.name;
            }
            if (modalPrice) {
                modalPrice.textContent = formatPeso(activeProduct.price);
            }
            if (modalImage) {
                modalImage.innerHTML = activeProduct.image ? '<img src="' + escapeHtml(activeProduct.image) + '" alt="">' : '';
                modalImage.classList.toggle('has-image', !!activeProduct.image);
            }
            if (modalQty) {
                modalQty.value = '1';
            }
            if (modalAddonList) {
                if (!addons.length) {
                    modalAddonList.innerHTML = '<p class="banoks-cart-no-addons">No add-ons available for this item.</p>';
                } else {
                    modalAddonList.innerHTML = addons.map(function (addon) {
                        var disabled = addon.canCheckout === false;
                        return '<label class="banoks-cart-addon-option' + (disabled ? ' is-disabled' : '') + '">' +
                            '<input type="checkbox" data-addon-id="' + addon.id + '" data-addon-name="' + escapeHtml(addon.name) + '" data-addon-price="' + Number(addon.price || 0) + '" data-addon-image="' + escapeHtml(addon.image || '') + '"' + (disabled ? ' disabled' : '') + '>' +
                            '<span>' + escapeHtml(addon.name) + '<small>' + (disabled ? escapeHtml(addon.stockLabel || 'Out of Stock') : formatPeso(addon.price)) + '</small></span>' +
                            '</label>';
                    }).join('');
                }
            }
            if (modal) {
                modal.setAttribute('aria-hidden', 'false');
                modal.classList.add('is-open');
            }
        }

        function closeCartModal() {
            activeProduct = null;
            if (modal) {
                modal.setAttribute('aria-hidden', 'true');
                modal.classList.remove('is-open');
            }
        }

        menu.querySelectorAll('.banoks-menu-category-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                menu.querySelectorAll('.banoks-menu-category-btn').forEach(function (filterButton) {
                    filterButton.classList.toggle('is-active', filterButton === button);
                });

                applyMenuFilters();
            });
        });

        searchInputs.forEach(function (input) {
            if (input.getAttribute('data-banoks-search-ready') !== '1') {
                input.setAttribute('data-banoks-search-ready', '1');
                input.addEventListener('input', function () {
                    var value = input.value;

                    document.querySelectorAll('.banoks-theme-search-input').forEach(function (otherInput) {
                        if (otherInput !== input && otherInput.value !== value) {
                            otherInput.value = value;
                        }
                    });

                    document.querySelectorAll('[data-banoks-menu]').forEach(function (targetMenu) {
                        targetMenu.dispatchEvent(new CustomEvent('banoks-menu-search-change'));
                    });
                });
                input.addEventListener('search', function () {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                });
            }
        });

        menu.addEventListener('banoks-menu-search-change', applyMenuFilters);
        applyMenuFilters();

        menu.querySelectorAll('.banoks-add-cart-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                openCartModal(button);
            });
        });

        if (modal) {
            modal.addEventListener('click', function (event) {
                if (event.target === modal || event.target.classList.contains('banoks-cart-modal-close')) {
                    closeCartModal();
                }
            });
        }

        shell.querySelectorAll('.banoks-cart-qty-btn').forEach(function (button) {
            button.addEventListener('click', function () {
                if (!modalQty) {
                    return;
                }
                var value = parseInt(modalQty.value, 10) || 1;
                value = button.getAttribute('data-qty-action') === 'minus' ? Math.max(1, value - 1) : value + 1;
                modalQty.value = String(value);
            });
        });

        if (modalQty) {
            modalQty.addEventListener('keydown', function (event) {
                event.preventDefault();
            });
            modalQty.addEventListener('wheel', function (event) {
                event.preventDefault();
                modalQty.blur();
            });
            modalQty.addEventListener('paste', function (event) {
                event.preventDefault();
            });
            modalQty.addEventListener('beforeinput', function (event) {
                event.preventDefault();
            });
        }

        var confirmButton = shell.querySelector('#banoks-cart-confirm');
        if (confirmButton) {
            confirmButton.addEventListener('click', function () {
                var quantity = modalQty ? Math.max(1, parseInt(modalQty.value, 10) || 1) : 1;
                if (activeProduct) {
                    var selectedAddons = [];
                    if (modalAddonList) {
                        modalAddonList.querySelectorAll('input[type="checkbox"]:checked').forEach(function (addonInput) {
                            selectedAddons.push({
                                id: addonInput.getAttribute('data-addon-id'),
                                name: addonInput.getAttribute('data-addon-name'),
                                price: parseFloat(addonInput.getAttribute('data-addon-price')) || 0,
                                qty: quantity
                            });
                        });
                    }

                    addCartGroup({
                        id: activeProduct.id,
                        name: activeProduct.name,
                        price: activeProduct.price,
                        description: activeProduct.description,
                        image: activeProduct.image,
                        qty: quantity
                    }, selectedAddons);
                }
                closeCartModal();
            });
        }
    });

    document.querySelectorAll('[data-banoks-cart-page]').forEach(function (page) {
        page.addEventListener('click', function (event) {
            var serviceButton = event.target.closest('[data-banoks-service-option]');
            var actionButton = event.target.closest('[data-banoks-cart-action]');
            var goCheckout = event.target.closest('[data-banoks-go-checkout]');
            var goCart = event.target.closest('[data-banoks-go-cart]');
            var addAddressToggle = event.target.closest('[data-banoks-add-address-toggle]');
            var cancelAddress = event.target.closest('[data-banoks-cancel-address]');
            var placeOrder = event.target.closest('[data-banoks-place-order]');
            var headerBack = event.target.closest('[data-banoks-header-back]');
            var confirmBack = event.target.closest('[data-banoks-confirm-back]');
            var confirmOk = event.target.closest('[data-banoks-confirm-ok]');
            var itemNode;
            var itemId;
            var lineKey;
            var addonNode;
            var addonLineKey;
            var addonPicker;
            var selectedAddons;
            var qty;

            if (confirmBack && page.contains(confirmBack)) {
                event.preventDefault();
                closeOrderConfirmModal(page);
                return;
            }

            if (confirmOk && page.contains(confirmOk)) {
                event.preventDefault();
                closeOrderConfirmModal(page);
                submitCheckout(page);
                return;
            }

            if (headerBack && page.contains(headerBack)) {
                event.preventDefault();
                closeOrderConfirmModal(page);
                if (page.classList.contains('is-checkout')) {
                    setCartView(page, 'cart');
                } else {
                    window.location.href = page.getAttribute('data-menu-url') || '#';
                }
                return;
            }

            if (goCheckout && page.contains(goCheckout)) {
                event.preventDefault();
                setCartView(page, 'checkout');
                return;
            }

            if (goCart && page.contains(goCart)) {
                event.preventDefault();
                closeOrderConfirmModal(page);
                setCartView(page, 'cart');
                return;
            }

            if (addAddressToggle && page.contains(addAddressToggle)) {
                var form = page.querySelector('[data-banoks-address-form]');
                if (form) {
                    form.hidden = !form.hidden;
                }
                return;
            }

            if (cancelAddress && page.contains(cancelAddress)) {
                var addressForm = page.querySelector('[data-banoks-address-form]');
                if (addressForm) {
                    addressForm.hidden = true;
                    addressForm.reset();
                }
                return;
            }

            if (placeOrder && page.contains(placeOrder)) {
                event.preventDefault();
                if (page.querySelector('[data-banoks-cart-item].is-selected-blocked')) {
                    setCheckoutMessage(page, 'Uncheck out-of-stock items before checkout.', true);
                    return;
                }
                if (!getSelectedCartItems(page).length) {
                    setCheckoutMessage(page, 'Please select at least one item.', true);
                    return;
                }
                if (getFulfillmentType(page) === 'delivery' && !getSelectedAddressId(page)) {
                    setCheckoutMessage(page, 'Please select a delivery address.', true);
                    return;
                }
                openOrderConfirmModal(page);
                return;
            }

            if (serviceButton && page.contains(serviceButton)) {
                page.querySelectorAll('[data-banoks-service-option]').forEach(function (button) {
                    var active = button === serviceButton;
                    button.classList.toggle('is-active', active);
                    button.setAttribute('aria-checked', active ? 'true' : 'false');
                });
                renderFulfillmentPanels(page);
                renderCheckoutSummary(page);
                return;
            }

            if (!actionButton || !page.contains(actionButton)) {
                return;
            }

            itemNode = actionButton.closest('[data-banoks-cart-item]');
            if (!itemNode) {
                return;
            }

            lineKey = itemNode.getAttribute('data-line-key') || itemNode.getAttribute('data-item-id');
            addonNode = actionButton.closest('[data-banoks-cart-addon]');
            addonLineKey = addonNode ? addonNode.getAttribute('data-addon-line-key') : '';
            itemId = itemNode.getAttribute('data-item-id');
            qty = addonNode ? Number(addonNode.getAttribute('data-addon-qty') || 0) : Number(itemNode.getAttribute('data-item-qty') || 0);

            if (actionButton.getAttribute('data-banoks-cart-action') === 'plus') {
                setCartItemQty(lineKey, qty + 1);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'minus') {
                setCartItemQty(lineKey, qty - 1);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'remove') {
                removeCartItem(lineKey);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'addon-plus') {
                setCartItemQty(lineKey, qty + 1, addonLineKey);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'addon-minus') {
                setCartItemQty(lineKey, qty - 1, addonLineKey);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'addon-remove') {
                removeCartItem(lineKey, addonLineKey);
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'toggle-addons') {
                addonPicker = itemNode.querySelector('[data-banoks-cart-addon-picker]');
                if (addonPicker) {
                    addonPicker.hidden = !addonPicker.hidden;
                }
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'close-addon-picker') {
                addonPicker = itemNode.querySelector('[data-banoks-cart-addon-picker]');
                if (addonPicker) {
                    addonPicker.hidden = true;
                }
            } else if (actionButton.getAttribute('data-banoks-cart-action') === 'confirm-addons') {
                addonPicker = itemNode.querySelector('[data-banoks-cart-addon-picker]');
                selectedAddons = [];
                if (addonPicker) {
                    addonPicker.querySelectorAll('input[type="checkbox"]:checked').forEach(function (input) {
                        selectedAddons.push({
                            id: input.value,
                            name: input.getAttribute('data-addon-name') || 'Add-on',
                            price: Number(input.getAttribute('data-addon-price') || 0)
                        });
                    });
                    addonPicker.hidden = true;
                }
                addAddonsToCartLine(lineKey, selectedAddons, Number(actionButton.getAttribute('data-addon-qty') || qty || 1));
            }
        });

        page.addEventListener('change', function (event) {
            if (event.target.closest('[data-banoks-cart-select]')) {
                renderCartPageTotal(page);
            } else if (event.target.closest('[data-banoks-payment-method]') || event.target.closest('[name="banoks_checkout_address"]')) {
                renderCheckoutSummary(page);
            }
        });

        page.addEventListener('submit', function (event) {
            var form = event.target.closest('[data-banoks-address-form]');
            var config = window.banoksCustomerAuth || {};
            var submitButton;
            var formData;

            if (!form || !page.contains(form)) {
                return;
            }

            event.preventDefault();
            submitButton = form.querySelector('button[type="submit"]');

            if (!config.ajaxUrl || !config.addressNonce) {
                window.alert('Could not save delivery address. Please try again.');
                return;
            }

            formData = new FormData(form);
            formData.set('action', 'banoks_customer_add_address');
            formData.set('nonce', config.addressNonce);

            if (submitButton) {
                submitButton.disabled = true;
            }

            window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.json().catch(function () {
                    return { success: false, data: { message: 'Could not save delivery address.' } };
                });
            }).then(function (data) {
                var list = page.querySelector('[data-banoks-address-list]');
                var empty = page.querySelector('[data-banoks-no-address]');
                var address = data && data.data ? data.data.address : null;

                if (!data || !data.success || !address) {
                    window.alert((data && data.data && data.data.message) || 'Could not save delivery address.');
                    return;
                }

                if (list) {
                    list.querySelectorAll('input[name="banoks_checkout_address"]').forEach(function (input) {
                        input.checked = false;
                    });
                    if (empty) {
                        empty.remove();
                    }
                    list.appendChild(createAddressOption(address));
                }

                form.hidden = true;
                form.reset();
                renderCheckoutSummary(page);
                scheduleCartAvailabilityRefresh();
            }).catch(function () {
                window.alert('Could not save delivery address. Please try again.');
            }).finally(function () {
                if (submitButton) {
                    submitButton.disabled = false;
                }
            });
        });

        if (new URLSearchParams(window.location.search).get('banoks_paymongo_return') === '1') {
            pollReturnedPayment(page, new URLSearchParams(window.location.search).get('banoks_order_id') || '');
        }
    });

    initThemeHeaderAutoHide();
    initFooterNavigationPreloader();
    initMePages();
    renderCartButtons();
    scheduleCartAvailabilityRefresh();
})();
