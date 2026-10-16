(function () {
    var roots = document.querySelectorAll("[data-address]");
    if (!roots.length) {
        return;
    }

    var fill = function (select, rows, placeholder) {
        var selected = select.value;
        select.replaceChildren();
        var empty = document.createElement("option");
        empty.value = "";
        empty.textContent = placeholder;
        select.appendChild(empty);
        rows.forEach(function (row) {
            var option = document.createElement("option");
            option.value = String(row.id);
            option.textContent = String(row.label || "");
            if (row.postal) {
                option.setAttribute("data-postal", String(row.postal));
            }
            if (option.value === selected) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        select.disabled = rows.length === 0;
        if (select.value !== selected) {
            select.value = "";
        }
        select.dispatchEvent(new Event("pick-refresh"));
    };

    var load = function (endpoint, parentId) {
        if (!parentId) {
            return Promise.resolve([]);
        }
        return fetch(endpoint + "?ust=" + encodeURIComponent(parentId), {
            headers: { "Accept": "application/json" },
            credentials: "same-origin"
        }).then(function (response) {
            if (!response.ok) {
                throw new Error("adres");
            }
            return response.json();
        }).then(function (data) {
            return Array.isArray(data) ? data : [];
        });
    };

    roots.forEach(function (root) {
        var endpoint = root.getAttribute("data-endpoint") || "";
        var city = root.querySelector("[data-level='il']");
        var district = root.querySelector("[data-level='ilce']");
        var place = root.querySelector("[data-level='yer']");
        var postal = root.querySelector("input[data-postal]");
        if (!endpoint || !city || !district || !place) {
            return;
        }

        [city, district, place].forEach(mountPick);

        var cityToken = 0;
        var districtToken = 0;

        var clearPlace = function (text) {
            fill(place, [], text);
            place.disabled = true;
        };

        city.addEventListener("change", function () {
            var token = ++cityToken;
            districtToken++;
            clearPlace("Önce ilçe seçin");
            district.disabled = true;
            fill(district, [], "Yükleniyor");
            load(endpoint, city.value).then(function (rows) {
                if (token !== cityToken) {
                    return;
                }
                fill(district, rows, rows.length ? "İlçe seçin" : "İlçe yok");
            }).catch(function () {
                if (token !== cityToken) {
                    return;
                }
                fill(district, [], "İlçeler alınamadı");
            });
        });

        district.addEventListener("change", function () {
            var token = ++districtToken;
            place.disabled = true;
            fill(place, [], "Yükleniyor");
            load(endpoint, district.value).then(function (rows) {
                if (token !== districtToken) {
                    return;
                }
                fill(place, rows, rows.length ? "Mahalle veya köy seçin" : "Kayıt yok");
            }).catch(function () {
                if (token !== districtToken) {
                    return;
                }
                fill(place, [], "Mahalleler alınamadı");
            });
        });

        place.addEventListener("change", function () {
            if (!postal) {
                return;
            }
            var option = place.options[place.selectedIndex];
            var code = option ? option.getAttribute("data-postal") || "" : "";
            if (/^\d{5}$/.test(code)) {
                postal.value = code;
            }
        });

        var form = root.closest("form") || root;
        var invoiceKind = root.querySelector("[name='invoice_kind']") || (form.getAttribute("data-invoice-bound") === "1" ? null : form.querySelector("[name='invoice_kind']"));
        var taxOffice = invoiceKind ? form.querySelector("[name='invoice_tax_office']") : null;
        if (invoiceKind && taxOffice) {
            form.setAttribute("data-invoice-bound", "1");
            var sameAddress = form.querySelector("#invoice-same");
            var syncInvoiceKind = function () {
                var corporate = invoiceKind.value === "kurumsal";
                taxOffice.disabled = !corporate;
                taxOffice.required = corporate;
                if (sameAddress) {
                    sameAddress.disabled = corporate;
                    if (corporate) {
                        sameAddress.checked = false;
                    }
                }
            };
            invoiceKind.addEventListener("change", function () {
                if (invoiceKind.value === "bireysel" && sameAddress) {
                    sameAddress.checked = true;
                }
                syncInvoiceKind();
            });
            syncInvoiceKind();
        }
    });

    function mountPick(select) {
        if (select.getAttribute("data-pick") === "1") {
            return;
        }
        select.setAttribute("data-pick", "1");
        select.classList.add("pick-native");
        select.tabIndex = -1;
        select.setAttribute("aria-hidden", "true");

        var pick = document.createElement("div");
        pick.className = "pick";
        var toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "pick-toggle";
        toggle.setAttribute("aria-haspopup", "listbox");
        toggle.setAttribute("aria-expanded", "false");
        var panel = document.createElement("div");
        panel.className = "pick-panel";
        panel.hidden = true;
        var search = document.createElement("input");
        search.type = "search";
        search.className = "pick-search";
        search.autocomplete = "off";
        search.spellcheck = false;
        search.placeholder = searchLabel(select);
        search.setAttribute("aria-label", searchLabel(select));
        var list = document.createElement("ul");
        list.className = "pick-list";
        list.setAttribute("role", "listbox");
        var empty = document.createElement("p");
        empty.className = "pick-empty";
        empty.hidden = true;
        empty.textContent = "Sonuç yok";
        panel.append(search, list, empty);
        pick.append(toggle, panel);
        select.parentNode.insertBefore(pick, select);

        var buttons = [];
        var active = -1;

        var refresh = function () {
            var selected = select.options[select.selectedIndex];
            var placeholder = select.options.length ? select.options[0].textContent : "Seçin";
            var hasValue = select.value !== "";
            toggle.textContent = hasValue && selected ? selected.textContent : placeholder;
            toggle.classList.toggle("is-empty", !hasValue);
            toggle.disabled = select.disabled;
            if (select.disabled) {
                close();
            }
            render(search.value);
        };

        var render = function (query) {
            var needle = fold(query);
            list.replaceChildren();
            buttons = [];
            Array.prototype.forEach.call(select.options, function (option) {
                if (option.value === "" || (needle !== "" && fold(option.textContent).indexOf(needle) === -1)) {
                    return;
                }
                var item = document.createElement("li");
                var button = document.createElement("button");
                button.type = "button";
                button.textContent = option.textContent;
                button.setAttribute("role", "option");
                button.setAttribute("data-value", option.value);
                if (option.value === select.value) {
                    button.classList.add("is-current");
                    button.setAttribute("aria-selected", "true");
                }
                button.addEventListener("click", function () {
                    choose(option.value);
                });
                item.appendChild(button);
                list.appendChild(item);
                buttons.push(button);
            });
            empty.hidden = buttons.length !== 0;
            var current = 0;
            buttons.forEach(function (button, index) {
                if (button.classList.contains("is-current")) {
                    current = index;
                }
            });
            highlight(buttons.length ? current : -1);
        };

        var highlight = function (index) {
            active = index;
            buttons.forEach(function (button, buttonIndex) {
                button.classList.toggle("is-on", buttonIndex === index);
            });
            var button = buttons[index];
            var row = button ? button.closest("li") : null;
            if (!row || panel.hidden) {
                return;
            }
            var top = row.offsetTop;
            var bottom = top + row.offsetHeight;
            if (top < list.scrollTop) {
                list.scrollTop = top;
            } else if (bottom > list.scrollTop + list.clientHeight) {
                list.scrollTop = bottom - list.clientHeight;
            }
        };

        var choose = function (value) {
            select.value = value;
            select.dispatchEvent(new Event("change", { bubbles: true }));
            close();
            refresh();
        };

        var open = function () {
            if (toggle.disabled) {
                return;
            }
            document.querySelectorAll(".pick-panel").forEach(function (other) {
                if (other !== panel) {
                    other.hidden = true;
                }
            });
            document.querySelectorAll(".pick-toggle[aria-expanded='true']").forEach(function (other) {
                if (other !== toggle) {
                    other.setAttribute("aria-expanded", "false");
                }
            });
            panel.hidden = false;
            toggle.setAttribute("aria-expanded", "true");
            search.value = "";
            render("");
            search.focus();
        };

        var close = function () {
            panel.hidden = true;
            toggle.setAttribute("aria-expanded", "false");
        };

        toggle.addEventListener("click", function () {
            if (panel.hidden) {
                open();
            } else {
                close();
            }
        });
        search.addEventListener("input", function () {
            render(search.value);
        });
        search.addEventListener("keydown", function (event) {
            if (event.key === "ArrowDown") {
                event.preventDefault();
                highlight(Math.min(active + 1, buttons.length - 1));
            } else if (event.key === "ArrowUp") {
                event.preventDefault();
                highlight(Math.max(active - 1, 0));
            } else if (event.key === "Enter") {
                event.preventDefault();
                if (buttons[active]) {
                    choose(buttons[active].getAttribute("data-value") || "");
                }
            } else if (event.key === "Escape") {
                event.preventDefault();
                close();
                toggle.focus();
            }
        });
        document.addEventListener("pointerdown", function (event) {
            if (!pick.contains(event.target)) {
                close();
            }
        });
        select.addEventListener("pick-refresh", refresh);
        select.addEventListener("invalid", function (event) {
            event.preventDefault();
            pick.classList.add("is-invalid");
            if (!toggle.disabled) {
                open();
            }
        });
        select.addEventListener("change", function () {
            pick.classList.remove("is-invalid");
        });
        refresh();
    }

    function searchLabel(select) {
        var level = select.getAttribute("data-level");
        if (level === "il") {
            return "İl ara";
        }
        if (level === "ilce") {
            return "İlçe ara";
        }
        if (level === "yer") {
            return "Mahalle veya köy ara";
        }
        return "Ara";
    }

    function fold(value) {
        return value.toLocaleLowerCase("tr-TR")
            .replace(/ı/g, "i")
            .replace(/ğ/g, "g")
            .replace(/ü/g, "u")
            .replace(/ş/g, "s")
            .replace(/ö/g, "o")
            .replace(/ç/g, "c");
    }
}());
