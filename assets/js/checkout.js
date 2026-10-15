(function () {
    var root = document.querySelector("[data-checkout]");
    if (!root) {
        return;
    }

    var unit = parseInt(root.getAttribute("data-unit") || "0", 10);
    var qtyInput = root.querySelector("[name='qty']");
    var cargoInputs = root.querySelectorAll("[name='cargo']");
    var payInputs = root.querySelectorAll("[name='payment']");
    var cardFields = root.querySelectorAll("[data-card]");
    var cargoBox = root.querySelector("[data-cargo-box]");
    var subtotal = root.querySelector("[data-subtotal]");
    var cargoAmount = root.querySelector("[data-cargo-amount]");
    var total = root.querySelector("[data-total]");

    var money = function (cents) {
        var whole = Math.floor(Math.max(0, cents) / 100);
        var fraction = String(Math.max(0, cents) % 100).padStart(2, "0");
        return whole.toLocaleString("tr-TR") + "," + fraction + " TL";
    };

    var selected = function (inputs) {
        var value = "";
        Array.prototype.forEach.call(inputs, function (input) {
            if (input.checked && !input.disabled) {
                value = input.value;
            }
        });
        return value;
    };

    var sync = function () {
        var qty = 1;
        if (qtyInput) {
            qty = parseInt(qtyInput.value || "1", 10);
            if (!qty || qty < 1) {
                qty = 1;
            }
            var max = parseInt(qtyInput.getAttribute("max") || "99", 10);
            if (qty > max) {
                qty = max;
            }
        }
        var payment = selected(payInputs);
        var pickup = payment === "elden";
        var cargo = 0;
        Array.prototype.forEach.call(cargoInputs, function (input) {
            input.disabled = pickup;
            input.required = !pickup;
            if (!pickup && input.checked) {
                cargo = parseInt(input.getAttribute("data-price") || "0", 10) || 0;
            }
        });
        if (cargoBox) {
            cargoBox.hidden = pickup;
        }
        Array.prototype.forEach.call(cardFields, function (field) {
            var card = payment === "kart";
            field.disabled = !card;
            field.required = card;
        });
        var place = root.querySelector("[data-place]");
        if (place) {
            place.textContent = payment === "kart" ? "Ödemeye geç" : "Siparişi tamamla";
        }
        if (subtotal) {
            subtotal.textContent = money(unit * qty);
        }
        if (cargoAmount) {
            cargoAmount.textContent = money(pickup ? 0 : cargo);
        }
        if (total) {
            total.textContent = money(unit * qty + (pickup ? 0 : cargo));
        }
    };

    root.addEventListener("change", sync);
    root.addEventListener("input", sync);
    sync();
}());
