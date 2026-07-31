(function (window, document) {
    'use strict';

    /**
     * GLOBALNY callback – wywoływany przez:
     * <inpost-geowidget onpoint="onpointselect">
     */
    window.onpointselect = function (event) {
        var p = (event && (event.detail || event.details)) ? (event.detail || event.details) : event;
        if (!p) return;

        var inputId   = document.getElementById('inpost_point_id');
        var inputName = document.getElementById('inpost_point_name');
        var inputAddr = document.getElementById('inpost_point_address');
        var selected  = document.getElementById('inpost-selected');
        var widget    = document.getElementById('inpost-geowidget');
        var changeBtn = document.getElementById('inpost-change');

        var id   = p.id   || p.name || '';
        var name = p.name || p.id   || '';
        var l1   = p.address && p.address.line1 ? p.address.line1 : '';
        var l2   = p.address && p.address.line2 ? p.address.line2 : '';
        var full = l1 + (l2 ? ', ' + l2 : '');

        if (inputId)   inputId.value   = id;
        if (inputName) inputName.value = name;
        if (inputAddr) inputAddr.value = full;

        if (selected) {
            selected.innerHTML =
                '<strong>Wybrany paczkomat:</strong> ' +
                name + ' (' + id + ')<br>' + full;
            selected.style.display = 'block';
        }

        if (widget) {
            widget.style.display = 'none';
        }
        if (changeBtn) {
            changeBtn.style.display = 'inline-block';
        }
    };

    function initOrderPage() {
        var inpostBox = document.getElementById('inpost-box');
        // jeśli brak elementów, to nie jesteśmy na page-order – kończymy
        if (!inpostBox) return;

        var radios          = document.querySelectorAll('.js-delivery-radio');
        var selectedBox     = document.getElementById('inpost-selected');
        var inputId         = document.getElementById('inpost_point_id');
        var inputName       = document.getElementById('inpost_point_name');
        var inputAddr       = document.getElementById('inpost_point_address');
        var widget          = document.getElementById('inpost-geowidget');
        var changeBtn       = document.getElementById('inpost-change');

        var deliveryPriceEl = document.getElementById('delivery-price');
        var orderTotalEl    = document.getElementById('order-total');
        var priceInput      = document.getElementById('price');
        var productsPriceEl = document.getElementById('products-price');

        function toggleInpostBox() {
            var checked = document.querySelector('.js-delivery-radio:checked');
            if (!checked) return;

            var val = parseInt(checked.value, 10);
            if (val === 2) {
                inpostBox.style.display = 'block';
                if (widget) widget.style.display = inputId && inputId.value ? 'none' : 'block';
                if (changeBtn) changeBtn.style.display = inputId && inputId.value ? 'inline-block' : 'none';
            } else {
                inpostBox.style.display = 'none';
                if (selectedBox) selectedBox.style.display = 'none';
                if (widget) widget.style.display = 'block';
                if (changeBtn) changeBtn.style.display = 'none';
                if (inputId)   inputId.value   = '';
                if (inputName) inputName.value = '';
                if (inputAddr) inputAddr.value = '';
            }
        }

        /**
         * Przelicza podsumowanie:
         *  - window.orderCartTotal (suma produktów),
         *  - window.orderDiscountPercent (rabat % z kodu — sesja po stronie PHP),
         *  - wybrana dostawa (data-price na radio).
         * Kwota jest ostatecznie przeliczana serwerowo przy zapisie zamówienia.
         */
        function updateSummary() {
            var cartTotal = (typeof window.orderCartTotal !== 'undefined')
                ? (parseFloat(window.orderCartTotal) || 0)
                : 0;

            var checked = document.querySelector('.js-delivery-radio:checked');
            if (!checked) return;

            var deliveryPrice = parseFloat(checked.getAttribute('data-price') || '0') || 0;

            // rabat liczony jak w PHP: round(products * percent / 100, 2)
            var discountPercent = parseFloat(window.orderDiscountPercent) || 0;
            var discountAmount  = discountPercent > 0
                ? Math.round(cartTotal * discountPercent) / 100
                : 0;

            var total = cartTotal - discountAmount + deliveryPrice;

            var formatter = new Intl.NumberFormat('pl-PL', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });

            if (productsPriceEl) {
                productsPriceEl.textContent = formatter.format(cartTotal) + ' zł';
            }

            var discountRow   = document.getElementById('discount-row');
            var discountPrice = document.getElementById('discount-price');
            if (discountRow) {
                discountRow.style.display = discountAmount > 0 ? '' : 'none';
            }
            if (discountPrice) {
                discountPrice.textContent = '-' + formatter.format(discountAmount) + ' zł';
            }

            if (deliveryPriceEl) {
                deliveryPriceEl.textContent = formatter.format(deliveryPrice) + ' zł';
            }
            if (orderTotalEl) {
                orderTotalEl.textContent = formatter.format(total) + ' zł';
            }
            if (priceInput) {
                priceInput.value = total.toFixed(2);
            }
        }

        /**
         * Kod rabatowy: walidacja przez plugins/shop-discount.php (sesja PHP).
         * Pusty kod = usunięcie rabatu.
         */
        function initDiscountCode() {
            var input    = document.getElementById('discount-code');
            var applyBtn = document.getElementById('discount-apply');
            var feedback = document.getElementById('discount-feedback');
            if (!input || !applyBtn) return;

            function applyDiscount() {
                var code      = input.value.trim();
                var csrfInput = document.querySelector('.pageContact__form input[name="csrf_token"]');

                var params = new URLSearchParams();
                params.set('action', code === '' ? 'remove' : 'apply');
                params.set('code', code);
                params.set('csrf_token', csrfInput ? csrfInput.value : '');

                applyBtn.disabled = true;

                fetch('/plugins/shop-discount.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params.toString(),
                    credentials: 'same-origin'
                })
                .then(function (r) {
                    // czytamy jako tekst, żeby przy błędzie serwera pokazać
                    // konkretną przyczynę (status HTTP), a nie ogólny komunikat
                    return r.text().then(function (body) {
                        var res = null;
                        try { res = JSON.parse(body); } catch (e) {}

                        if (!res) {
                            console.error('shop-discount: HTTP ' + r.status + ', odpowiedź nie-JSON:', body.slice(0, 500));
                            throw new Error('Błąd serwera (HTTP ' + r.status + ') — szczegóły w konsoli przeglądarki.');
                        }
                        return res;
                    });
                })
                .then(function (res) {
                    var ok = res && res.status === 'success';

                    window.orderDiscountPercent = ok ? (parseFloat(res.percent) || 0) : 0;

                    var codeLabel = document.getElementById('discount-code-label');
                    if (codeLabel) {
                        codeLabel.textContent = (ok && res.code) ? '(' + res.code + ')' : '';
                    }
                    if (feedback) {
                        feedback.textContent = res && res.message ? res.message : 'Wystąpił błąd. Spróbuj ponownie.';
                        feedback.className = 'orderDiscount__feedback ' + (ok ? 'is-success' : 'is-error');
                    }

                    updateSummary();
                })
                .catch(function (err) {
                    if (feedback) {
                        feedback.textContent = (err && err.message && err.message.indexOf('HTTP') !== -1)
                            ? err.message
                            : 'Wystąpił błąd połączenia. Spróbuj ponownie.';
                        feedback.className = 'orderDiscount__feedback is-error';
                    }
                })
                .finally(function () {
                    applyBtn.disabled = false;
                });
            }

            applyBtn.addEventListener('click', applyDiscount);

            // Enter w polu kodu nie może wysłać całego zamówienia
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applyDiscount();
                }
            });
        }

        // eksport funkcji do innych skryptów (np. AJAX z koszyka)
        window.orderUpdateSummary = updateSummary;

        window.orderHandleEmptyCart = function () {
            var form = document.querySelector('.pageContact__form');
            if (form) {
                form.style.display = 'none';
            }
            window.orderCartTotal = 0;
            updateSummary();
        };

        if (changeBtn) {
            changeBtn.addEventListener('click', function () {
                if (widget) widget.style.display = 'block';
                this.style.display = 'none';
            });
        }

        radios.forEach(function (r) {
            r.addEventListener('change', function () {
                toggleInpostBox();
                updateSummary();
            });
        });

        // startowo
        toggleInpostBox();
        initDiscountCode();
        updateSummary();
    }

    document.addEventListener('DOMContentLoaded', initOrderPage);
})(window, document);


 
// SHOP
$(document).ready(function () {

  function togglePaymentDetails() {
    const val = $('.js-payment-radio:checked').val();

    // ukryj wszystko
    $('.details_payment').removeClass('show');

    // pokaż właściwy blok
    $('.details_payment_' + val).addClass('show');
  }

  // start – przy odświeżeniu strony
  togglePaymentDetails();

  // zmiana metody płatności
  $(document).on('change', '.js-payment-radio', function () {
    togglePaymentDetails();
  });

});

