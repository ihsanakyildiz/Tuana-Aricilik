(function () {
    var toast = document.getElementById("admin-toast");
    var toastTimer = 0;

    function showToast(message) {
        if (!toast) {
            return;
        }
        toast.hidden = false;
        toast.textContent = message;
        toast.classList.add("is-on");
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(function () {
            toast.classList.remove("is-on");
            window.setTimeout(function () {
                if (!toast.classList.contains("is-on")) {
                    toast.hidden = true;
                }
            }, 200);
        }, 2400);
    }

    function closeMenus() {
        document.querySelectorAll("[data-menu].is-open").forEach(function (menu) {
            menu.classList.remove("is-open");
            var panel = menu.querySelector(".menu-panel");
            var button = menu.querySelector("button");
            if (panel) {
                panel.hidden = true;
            }
            if (button) {
                button.setAttribute("aria-expanded", "false");
            }
        });
    }

    document.querySelectorAll("[data-menu] > button").forEach(function (button) {
        button.addEventListener("click", function (event) {
            event.stopPropagation();
            var menu = button.parentElement;
            var willOpen = !menu.classList.contains("is-open");
            closeMenus();
            if (!willOpen) {
                return;
            }
            menu.classList.add("is-open");
            var panel = menu.querySelector(".menu-panel");
            if (panel) {
                panel.hidden = false;
            }
            button.setAttribute("aria-expanded", "true");
        });
    });

    document.querySelectorAll("[data-submenu]").forEach(function (button) {
        button.addEventListener("click", function () {
            var item = button.closest(".nav-item");
            var open = item.classList.toggle("is-open");
            button.setAttribute("aria-expanded", open ? "true" : "false");
        });
    });

    var sidebarToggle = document.querySelector("[data-sidebar-toggle]");
    var backdrop = document.querySelector("[data-sidebar-close]");
    if (sidebarToggle) {
        sidebarToggle.addEventListener("click", function () {
            if (window.innerWidth <= 1080) {
                var open = document.body.classList.toggle("is-drawer");
                if (backdrop) {
                    backdrop.hidden = !open;
                }
                return;
            }
            document.body.classList.toggle("is-collapsed");
        });
    }
    if (backdrop) {
        backdrop.addEventListener("click", function () {
            document.body.classList.remove("is-drawer");
            backdrop.hidden = true;
        });
    }

    document.addEventListener("click", function (event) {
        var soon = event.target.closest("[data-soon]");
        if (soon) {
            event.preventDefault();
            showToast(soon.getAttribute("data-soon") + " sıradaki adımda hazırlanacak.");
            closeMenus();
            return;
        }
        if (!event.target.closest("[data-menu]")) {
            closeMenus();
        }
    });

    document.addEventListener("keydown", function (event) {
        if (event.key !== "Escape") {
            return;
        }
        closeMenus();
        document.body.classList.remove("is-drawer");
        if (backdrop) {
            backdrop.hidden = true;
        }
    });

    var search = document.querySelector("[data-search]");
    if (search) {
        search.addEventListener("submit", function (event) {
            event.preventDefault();
            showToast("Arama, sayfalar bağlanınca çalışacak.");
        });
    }

    document.querySelectorAll("[data-refresh]").forEach(function (button) {
        button.addEventListener("click", function () {
            button.classList.remove("is-spin");
            window.requestAnimationFrame(function () {
                button.classList.add("is-spin");
            });
            window.setTimeout(function () {
                button.classList.remove("is-spin");
            }, 700);
        });
    });

    var clock = document.getElementById("admin-clock");
    if (clock) {
        var tick = function () {
            var now = new Date();
            clock.textContent = now.toLocaleTimeString("tr-TR", { hour12: false });
            var greet = document.querySelector("[data-greet]");
            if (greet) {
                var hour = now.getHours();
                greet.textContent = hour < 6 ? "İyi geceler," : (hour < 12 ? "Günaydın," : (hour < 18 ? "İyi günler," : "İyi akşamlar,"));
            }
        };
        tick();
        window.setInterval(tick, 1000);
    }

    var reportNode = document.getElementById("report-data");
    if (reportNode) {
        var report = JSON.parse(reportNode.textContent);
        document.querySelectorAll("[data-period]").forEach(function (tab) {
            tab.addEventListener("click", function () {
                var key = tab.getAttribute("data-period");
                var period = report[key];
                if (!period) {
                    return;
                }
                document.querySelectorAll("[data-period]").forEach(function (item) {
                    var on = item === tab;
                    item.classList.toggle("is-on", on);
                    item.setAttribute("aria-selected", on ? "true" : "false");
                });
                document.querySelectorAll("[data-chart]").forEach(function (chart) {
                    chart.classList.toggle("is-on", chart.getAttribute("data-chart") === key);
                });
                var countNode = document.querySelector("[data-report-count]");
                var revenueNode = document.querySelector("[data-report-revenue]");
                var ordersNode = document.querySelector("[data-report-orders]");
                var growthNode = document.querySelector("[data-report-growth]");
                var earningNode = document.querySelector("[data-report-earning]");
                var hintNode = document.querySelector("[data-report-hint]");
                if (countNode) {
                    countNode.textContent = "(" + period.count + ")";
                }
                if (revenueNode) {
                    revenueNode.textContent = period.revenue;
                }
                if (ordersNode) {
                    ordersNode.textContent = period.orders;
                }
                if (growthNode) {
                    growthNode.textContent = period.growth;
                }
                if (earningNode) {
                    earningNode.textContent = period.earning;
                }
                if (hintNode) {
                    hintNode.textContent = period.hint || "";
                }
            });
        });
    }

    var focal = document.querySelector("[data-focal]");
    if (focal) {
        var focalImage = focal.querySelector("img");
        var focalPin = focal.querySelector(".focal-pin");
        var focalX = document.getElementById("focal-x");
        var focalY = document.getElementById("focal-y");
        var placeFocal = function (x, y) {
            x = Math.max(0, Math.min(100, Math.round(x)));
            y = Math.max(0, Math.min(100, Math.round(y)));
            if (focalX) focalX.value = String(x);
            if (focalY) focalY.value = String(y);
            if (focalPin) {
                focalPin.style.left = x + "%";
                focalPin.style.top = y + "%";
            }
            if (focalImage) focalImage.style.objectPosition = x + "% " + y + "%";
        };
        focal.addEventListener("click", function (event) {
            var rect = focal.getBoundingClientRect();
            if (rect.width < 1 || rect.height < 1) return;
            placeFocal(((event.clientX - rect.left) / rect.width) * 100, ((event.clientY - rect.top) / rect.height) * 100);
        });
        [focalX, focalY].forEach(function (input) {
            if (!input) return;
            input.addEventListener("input", function () {
                placeFocal(Number(focalX.value), Number(focalY.value));
            });
        });
        if (focalImage) {
            focalImage.style.objectPosition = (focalX ? focalX.value : "50") + "% " + (focalY ? focalY.value : "45") + "%";
        }
        var heroFile = document.getElementById("hero-image");
        if (heroFile && focalImage) {
            heroFile.addEventListener("change", function () {
                var chosen = heroFile.files && heroFile.files[0];
                if (!chosen) return;
                var reader = new FileReader();
                reader.onload = function () {
                    if (typeof reader.result === "string") {
                        focalImage.hidden = false;
                        focalImage.src = reader.result;
                        var empty = focal.querySelector(".focal-empty");
                        if (empty) empty.hidden = true;
                    }
                };
                reader.readAsDataURL(chosen);
            });
        }
    }

    var overlay = document.getElementById("overlay");
    var overlayValue = document.getElementById("overlay-value");
    if (overlay && overlayValue) {
        overlay.addEventListener("input", function () {
            overlayValue.textContent = overlay.value;
        });
    }

    document.querySelectorAll("form[data-confirm]").forEach(function (form) {
        form.addEventListener("submit", function (event) {
            if (!window.confirm(form.getAttribute("data-confirm") || "Silinsin mi?")) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll(".file-pick input").forEach(function (input) {
        input.addEventListener("change", function () {
            var label = input.parentElement ? input.parentElement.querySelector(".file-name") : null;
            var files = input.files;
            if (label) {
                if (!files || files.length === 0) label.textContent = "Dosya seçilmedi";
                else if (files.length === 1) label.textContent = files[0].name;
                else label.textContent = String(files.length) + " dosya seçildi";
            }
        });
    });

    document.querySelectorAll("[data-preview]").forEach(function (stage) {
        var frame = stage.querySelector("iframe");
        if (!frame) {
            return;
        }
        var fit = function () {
            frame.style.transform = "scale(" + (stage.clientWidth / 1200) + ")";
        };
        fit();
        window.addEventListener("resize", fit);
    });

    var stack = document.querySelector("[data-card-stack]");
    var cardTemplate = document.getElementById("card-template");
    if (stack && cardTemplate) {
        var place = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) {
                return;
            }
            if (direction < 0) {
                stack.insertBefore(card, sibling);
            } else {
                stack.insertBefore(sibling, card);
            }
        };
        stack.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) {
                return;
            }
            var card = button.closest(".card-editor");
            if (!card) {
                return;
            }
            if (button.hasAttribute("data-card-up")) {
                place(card, -1);
            } else if (button.hasAttribute("data-card-down")) {
                place(card, 1);
            } else if (button.hasAttribute("data-card-remove")) {
                if (stack.querySelectorAll(".card-editor").length < 2) {
                    showToast("Son kart bölümde kalır.");
                    return;
                }
                card.remove();
            }
        });
        var addCard = document.querySelector("[data-card-add]");
        if (addCard) {
            addCard.addEventListener("click", function () {
                if (stack.querySelectorAll(".card-editor").length >= 6) {
                    showToast("Bir bölümde en fazla 6 kart olabilir.");
                    return;
                }
                stack.appendChild(cardTemplate.content.cloneNode(true));
            });
        }
    }

    var serviceStack = document.querySelector("[data-service-stack]");
    var serviceTemplate = document.getElementById("service-template");
    var serviceForm = document.querySelector("[data-service-form]");
    if (serviceStack && serviceTemplate) {
        var moveService = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) return;
            if (direction < 0) serviceStack.insertBefore(card, sibling);
            else serviceStack.insertBefore(sibling, card);
        };
        serviceStack.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var card = button.closest("[data-service-item]");
            if (!card) return;
            if (button.hasAttribute("data-service-up")) moveService(card, -1);
            else if (button.hasAttribute("data-service-down")) moveService(card, 1);
            else if (button.hasAttribute("data-service-remove")) {
                if (serviceStack.querySelectorAll("[data-service-item]").length < 2) {
                    showToast("Son hizmet kartı bölümde kalır.");
                    return;
                }
                card.remove();
            }
        });
        var addService = document.querySelector("[data-service-add]");
        if (addService) {
            addService.addEventListener("click", function () {
                if (serviceStack.querySelectorAll("[data-service-item]").length >= 24) {
                    showToast("Bir bölümde en fazla 24 hizmet olabilir.");
                    return;
                }
                serviceStack.appendChild(serviceTemplate.content.cloneNode(true));
            });
        }
    }
    if (serviceForm) {
        serviceForm.addEventListener("submit", function () {
            serviceForm.querySelectorAll("[data-service-item]").forEach(function (card) {
                var active = card.querySelector("[data-active]");
                var activeBox = card.querySelector("[data-active-box]");
                var clear = card.querySelector("[data-clear]");
                var clearBox = card.querySelector("[data-clear-box]");
                if (active && activeBox) active.value = activeBox.checked ? "1" : "0";
                if (clear) clear.value = clearBox && clearBox.checked ? "1" : "0";
            });
        });
        serviceForm.addEventListener("change", function (event) {
            var input = event.target;
            if (!input || input.type !== "file" || !input.closest(".file-pick")) return;
            var label = input.parentElement ? input.parentElement.querySelector(".file-name") : null;
            var file = input.files && input.files[0];
            if (label) label.textContent = file ? file.name : "Dosya seçilmedi";
        });
    }

    var whyStack = document.querySelector("[data-why-stack]");
    var whyTemplate = document.getElementById("why-template");
    var whyForm = document.querySelector("[data-why-form]");
    if (whyStack && whyTemplate) {
        var moveWhy = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) return;
            if (direction < 0) whyStack.insertBefore(card, sibling);
            else whyStack.insertBefore(sibling, card);
        };
        whyStack.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var card = button.closest("[data-why-item]");
            if (!card) return;
            if (button.hasAttribute("data-why-up")) moveWhy(card, -1);
            else if (button.hasAttribute("data-why-down")) moveWhy(card, 1);
            else if (button.hasAttribute("data-why-remove")) {
                if (whyStack.querySelectorAll("[data-why-item]").length < 2) {
                    showToast("Son madde bölümde kalır.");
                    return;
                }
                card.remove();
            }
        });
        var addWhy = document.querySelector("[data-why-add]");
        if (addWhy) {
            addWhy.addEventListener("click", function () {
                if (whyStack.querySelectorAll("[data-why-item]").length >= 8) {
                    showToast("Bir bölümde en fazla 8 madde olabilir.");
                    return;
                }
                whyStack.appendChild(whyTemplate.content.cloneNode(true));
            });
        }
    }
    if (whyForm) {
        whyForm.addEventListener("submit", function () {
            whyForm.querySelectorAll("[data-why-item]").forEach(function (card) {
                var active = card.querySelector("[data-active]");
                var activeBox = card.querySelector("[data-active-box]");
                if (active && activeBox) active.value = activeBox.checked ? "1" : "0";
            });
        });
    }

    var benefitStack = document.querySelector("[data-benefit-stack]");
    var benefitTemplate = document.getElementById("benefit-template");
    var benefitForm = document.querySelector("[data-benefit-form]");
    if (benefitStack && benefitTemplate) {
        var moveBenefit = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) return;
            if (direction < 0) benefitStack.insertBefore(card, sibling);
            else benefitStack.insertBefore(sibling, card);
        };
        benefitStack.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var card = button.closest("[data-benefit-item]");
            if (!card) return;
            if (button.hasAttribute("data-benefit-up")) moveBenefit(card, -1);
            else if (button.hasAttribute("data-benefit-down")) moveBenefit(card, 1);
            else if (button.hasAttribute("data-benefit-remove")) {
                if (benefitStack.querySelectorAll("[data-benefit-item]").length < 2) {
                    showToast("Son çubuk bölümde kalır.");
                    return;
                }
                card.remove();
            }
        });
        var addBenefit = document.querySelector("[data-benefit-add]");
        if (addBenefit) {
            addBenefit.addEventListener("click", function () {
                if (benefitStack.querySelectorAll("[data-benefit-item]").length >= 8) {
                    showToast("Bir bölümde en fazla 8 çubuk olabilir.");
                    return;
                }
                benefitStack.appendChild(benefitTemplate.content.cloneNode(true));
            });
        }
    }
    if (benefitForm) {
        benefitForm.addEventListener("submit", function () {
            benefitForm.querySelectorAll("[data-benefit-item]").forEach(function (card) {
                var active = card.querySelector("[data-active]");
                var activeBox = card.querySelector("[data-active-box]");
                if (active && activeBox) active.value = activeBox.checked ? "1" : "0";
            });
        });
    }

    var reviewStack = document.querySelector("[data-review-stack]");
    var reviewTemplate = document.getElementById("review-template");
    var reviewForm = document.querySelector("[data-review-form]");
    if (reviewStack && reviewTemplate) {
        var moveReview = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling) return;
            if (direction < 0) reviewStack.insertBefore(card, sibling);
            else reviewStack.insertBefore(sibling, card);
        };
        reviewStack.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var card = button.closest("[data-review-item]");
            if (!card) return;
            if (button.hasAttribute("data-review-up")) moveReview(card, -1);
            else if (button.hasAttribute("data-review-down")) moveReview(card, 1);
            else if (button.hasAttribute("data-review-remove")) {
                if (reviewStack.querySelectorAll("[data-review-item]").length < 2) {
                    showToast("Son yorum bölümde kalır.");
                    return;
                }
                card.remove();
            }
        });
        var addReview = document.querySelector("[data-review-add]");
        if (addReview) {
            addReview.addEventListener("click", function () {
                if (reviewStack.querySelectorAll("[data-review-item]").length >= 12) {
                    showToast("Bir bölümde en fazla 12 yorum olabilir.");
                    return;
                }
                reviewStack.appendChild(reviewTemplate.content.cloneNode(true));
            });
        }
    }
    if (reviewForm) {
        reviewForm.addEventListener("submit", function () {
            reviewForm.querySelectorAll("[data-review-item]").forEach(function (card) {
                var active = card.querySelector("[data-active]");
                var activeBox = card.querySelector("[data-active-box]");
                if (active && activeBox) active.value = activeBox.checked ? "1" : "0";
            });
        });
    }

    var postEditor = document.querySelector("[data-post-editor]");
    if (postEditor) {
        var postTitle = postEditor.querySelector("[data-post-title]");
        var postSlug = postEditor.querySelector("[data-post-slug]");
        var permalink = postEditor.querySelector("[data-permalink]");
        var permalinkBase = postEditor.getAttribute("data-permalink-base") || "";
        var slugify = function (value) {
            var map = { "ç": "c", "ğ": "g", "ı": "i", "ö": "o", "ş": "s", "ü": "u", "Ç": "c", "Ğ": "g", "İ": "i", "I": "i", "Ö": "o", "Ş": "s", "Ü": "u" };
            var text = String(value).replace(/[çğıöşüÇĞİIÖŞÜ]/g, function (ch) { return map[ch] || ch; });
            text = text.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-+|-+$/g, "");
            return text.slice(0, 64).replace(/-+$/g, "");
        };
        var paintLink = function () {
            if (!permalink) return;
            var value = postSlug ? postSlug.value.trim() : "";
            permalink.textContent = value ? permalinkBase + "/" + value : permalinkBase + "/";
        };
        if (postTitle && postSlug && postSlug.getAttribute("data-auto") === "1") {
            postTitle.addEventListener("input", function () {
                if (postSlug.getAttribute("data-touched") === "1") return;
                postSlug.setAttribute("data-filling", "1");
                postSlug.value = slugify(postTitle.value);
                postSlug.removeAttribute("data-filling");
                paintLink();
            });
        }
        if (postSlug) {
            postSlug.addEventListener("input", function () {
                if (postSlug.getAttribute("data-filling") !== "1") postSlug.setAttribute("data-touched", "1");
                paintLink();
            });
        }
        postEditor.querySelectorAll("[data-limit]").forEach(function (input) {
            var out = postEditor.querySelector('[data-count-for="' + input.id + '"]');
            var limit = input.getAttribute("data-limit") || "";
            var paintCount = function () {
                if (out) out.textContent = String(input.value.length) + "/" + limit;
            };
            input.addEventListener("input", paintCount);
            paintCount();
        });
        var richText = document.getElementById("post-body");
        if (richText && window.Jodit) {
            try {
            var rich = window.Jodit.make(richText, {
                language: "tr",
                height: 480,
                hidePoweredByJodit: true,
                toolbarSticky: false,
                toolbarAdaptive: false,
                showCharsCounter: true,
                showWordsCounter: true,
                askBeforePasteHTML: false,
                askBeforePasteFromWord: false,
                placeholder: richText.getAttribute("data-placeholder") || "Yazıyı buraya yazın",
                buttons: ["source", "|", "bold", "italic", "underline", "strikethrough", "|", "ul", "ol", "|", "fontsize", "paragraph", "brush", "|", "image", "table", "link", "|", "align", "indent", "outdent", "|", "undo", "redo", "|", "hr", "eraser", "fullsize"],
                uploader: {
                    url: richText.getAttribute("data-upload") || "",
                    format: "json",
                    method: "POST",
                    insertImageAsBase64URI: false,
                    imagesExtensions: ["jpg", "png", "jpeg", "gif", "webp"],
                    filesVariableName: function () { return "image"; },
                    headers: { "X-CSRF": richText.getAttribute("data-csrf") || "" },
                    prepareData: function (data) {
                        data.append("csrf", richText.getAttribute("data-csrf") || "");
                        return data;
                    },
                    isSuccess: function (resp) {
                        if (resp && resp.csrf) {
                            var field = postEditor.querySelector("[name=csrf]");
                            if (field) field.value = resp.csrf;
                            richText.setAttribute("data-csrf", resp.csrf);
                        }
                        return resp && !resp.error;
                    },
                    getMessage: function (resp) { return (resp && resp.msg) || "Görsel yüklenemedi."; },
                    process: function (resp) {
                        return {
                            files: resp.files || [],
                            baseurl: resp.baseurl || "",
                            isImages: resp.isImages || [],
                            error: resp.error || 0,
                            msg: resp.msg || ""
                        };
                    }
                }
            });
            postEditor.addEventListener("submit", function () {
                richText.value = rich.value;
            });
            } catch (error) {
                richText.removeAttribute("hidden");
            }
        }
        var status = postEditor.querySelector("#post-status");
        var saveButton = postEditor.querySelector(".post-publish button");
        var postKey = postEditor.querySelector("[name=post_key], [name=page_key]");
        if (status && saveButton) {
            status.addEventListener("change", function () {
                var editing = postKey && postKey.value !== "";
                if (status.value !== "publish") saveButton.textContent = "Taslağı kaydet";
                else saveButton.textContent = editing ? "Güncelle" : "Yayınla";
            });
        }
    }

    document.querySelectorAll("[data-grid-layout] select").forEach(function (select) {
        select.addEventListener("change", function () {
            var form = select.closest("form");
            if (form) {
                form.submit();
            }
        });
    });

    var gridBoard = document.querySelector("[data-grid-board]");
    if (gridBoard) {
        var gridForm = gridBoard.querySelector("[data-grid-arrange]");
        var gridDrag = null;
        var submitGrid = function () {
            if (!gridForm) {
                return;
            }
            var columns = [];
            gridBoard.querySelectorAll("[data-grid-column]").forEach(function (column) {
                var keys = [];
                column.querySelectorAll("[data-grid-chip]").forEach(function (chip) {
                    var key = chip.getAttribute("data-key");
                    if (key) {
                        keys.push(key);
                    }
                });
                columns.push(keys);
            });
            var field = gridForm.querySelector("[name=columns_json]");
            if (field) {
                field.value = JSON.stringify(columns);
            }
            gridForm.submit();
        };
        gridBoard.querySelectorAll("[data-grid-chip]").forEach(function (chip) {
            chip.addEventListener("dragstart", function (event) {
                gridDrag = chip;
                chip.classList.add("is-dragging");
                if (event.dataTransfer) {
                    event.dataTransfer.effectAllowed = "move";
                    event.dataTransfer.setData("text/plain", chip.getAttribute("data-key") || "");
                }
            });
            chip.addEventListener("dragend", function () {
                chip.classList.remove("is-dragging");
                gridBoard.querySelectorAll(".is-over").forEach(function (column) {
                    column.classList.remove("is-over");
                });
                gridDrag = null;
            });
        });
        gridBoard.querySelectorAll("[data-grid-column]").forEach(function (column) {
            column.addEventListener("dragover", function (event) {
                if (!gridDrag) {
                    return;
                }
                event.preventDefault();
                column.classList.add("is-over");
                var list = column.querySelector("[data-grid-list]");
                if (!list) {
                    return;
                }
                var after = null;
                column.querySelectorAll("[data-grid-chip]").forEach(function (chip) {
                    if (chip === gridDrag) {
                        return;
                    }
                    var box = chip.getBoundingClientRect();
                    if (event.clientY > box.top + box.height / 2) {
                        after = chip;
                    }
                });
                if (after) {
                    after.after(gridDrag);
                } else {
                    list.prepend(gridDrag);
                }
            });
            column.addEventListener("dragleave", function (event) {
                if (!column.contains(event.relatedTarget)) {
                    column.classList.remove("is-over");
                }
            });
            column.addEventListener("drop", function (event) {
                event.preventDefault();
                column.classList.remove("is-over");
                submitGrid();
            });
        });
    }

    var gallery = document.querySelector("[data-gallery]");
    if (gallery) {
        var moveShot = function (card, direction) {
            var sibling = direction < 0 ? card.previousElementSibling : card.nextElementSibling;
            if (!sibling || !sibling.hasAttribute("data-gallery-item")) return;
            if (direction < 0) gallery.insertBefore(card, sibling);
            else gallery.insertBefore(sibling, card);
        };
        gallery.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var card = button.closest("[data-gallery-item]");
            if (!card) return;
            if (button.hasAttribute("data-gallery-up")) moveShot(card, -1);
            else if (button.hasAttribute("data-gallery-down")) moveShot(card, 1);
            else if (button.hasAttribute("data-gallery-remove")) card.remove();
        });
    }

    var menuTree = document.querySelector("[data-menu-tree]");
    if (menuTree) {
        var menuForm = menuTree.closest("form");
        var menuTemplate = document.getElementById("menu-node-template");
        var menuEmpty = document.querySelector("[data-menu-empty]");
        var dragNode = null;
        var kindNames = {
            link: "Bağlantı",
            page: "Sayfa",
            post: "Yazı",
            product: "Ürün",
            product_category: "Ürün kategorisi",
            category: "Yazı kategorisi"
        };
        var levelNames = ["Üst", "Alt", "İç"];

        function depthOf(node) {
            return parseInt(node.getAttribute("data-depth") || "0", 10) || 0;
        }

        function setDepth(node, depth) {
            depth = Math.max(0, Math.min(2, depth));
            node.setAttribute("data-depth", String(depth));
            node.style.setProperty("--menu-depth", String(depth));
            var hidden = node.querySelector(".menu-depth");
            var level = node.querySelector(".menu-level");
            if (hidden) hidden.value = String(depth);
            if (level) level.textContent = levelNames[depth];
            if (depth !== 0) {
                var mega = node.querySelector("[data-menu-mega]");
                var megaValue = node.querySelector(".menu-mega-value");
                if (mega) mega.checked = false;
                if (megaValue) megaValue.value = "0";
            }
        }

        function blockOf(node) {
            var depth = depthOf(node);
            var nodes = [node];
            var next = node.nextElementSibling;
            while (next && next.classList.contains("menu-node") && depthOf(next) > depth) {
                nodes.push(next);
                next = next.nextElementSibling;
            }
            return nodes;
        }

        function legalizeAll() {
            var previous = -1;
            menuTree.querySelectorAll(".menu-node").forEach(function (node) {
                var depth = depthOf(node);
                var max = previous < 0 ? 0 : Math.min(2, previous + 1);
                if (depth > max) setDepth(node, max);
                previous = depthOf(node);
            });
        }

        function refreshEmpty() {
            if (menuEmpty) menuEmpty.hidden = menuTree.querySelector(".menu-node") !== null;
        }

        function moveBlock(node, direction) {
            var depth = depthOf(node);
            var block = blockOf(node);
            if (direction < 0) {
                var prev = node.previousElementSibling;
                while (prev && depthOf(prev) > depth) prev = prev.previousElementSibling;
                if (!prev || depthOf(prev) !== depth) return;
                block.forEach(function (item) { menuTree.insertBefore(item, prev); });
            } else {
                var next = block[block.length - 1].nextElementSibling;
                if (!next || depthOf(next) !== depth) return;
                var after = blockOf(next);
                var marker = after[after.length - 1].nextSibling;
                block.forEach(function (item) { menuTree.insertBefore(item, marker); });
            }
            legalizeAll();
        }

        function appendItem(data) {
            if (!menuTemplate) return;
            var fragment = menuTemplate.content.cloneNode(true);
            var node = fragment.querySelector(".menu-node");
            if (!node) return;
            node.setAttribute("data-kind", data.kind);
            node.querySelector(".menu-label").value = data.label;
            var url = node.querySelector(".menu-url");
            url.value = data.url;
            url.readOnly = data.kind !== "link";
            node.querySelector(".menu-kind").textContent = kindNames[data.kind] || "Bağlantı";
            node.querySelector("[name='item_kind[]']").value = data.kind;
            node.querySelector("[name='item_ref[]']").value = data.ref || "";
            setDepth(node, 0);
            menuTree.appendChild(node);
            refreshEmpty();
            node.scrollIntoView({ block: "nearest" });
        }

        menuTree.addEventListener("click", function (event) {
            var button = event.target.closest("button");
            if (!button) return;
            var node = button.closest(".menu-node");
            if (!node) return;
            if (button.hasAttribute("data-menu-remove")) {
                blockOf(node).forEach(function (item) { item.remove(); });
                refreshEmpty();
                return;
            }
            if (button.hasAttribute("data-menu-indent")) {
                var prev = node.previousElementSibling;
                if (!prev || depthOf(node) >= Math.min(2, depthOf(prev) + 1)) return;
                blockOf(node).forEach(function (item) { setDepth(item, depthOf(item) + 1); });
                legalizeAll();
                return;
            }
            if (button.hasAttribute("data-menu-outdent")) {
                if (depthOf(node) === 0) return;
                blockOf(node).forEach(function (item) { setDepth(item, depthOf(item) - 1); });
                legalizeAll();
                return;
            }
            if (button.hasAttribute("data-menu-up")) moveBlock(node, -1);
            if (button.hasAttribute("data-menu-down")) moveBlock(node, 1);
        });

        menuTree.addEventListener("change", function (event) {
            var box = event.target;
            var node = box.closest(".menu-node");
            if (!node) return;
            if (box.hasAttribute("data-menu-mega")) {
                node.querySelector(".menu-mega-value").value = box.checked ? "1" : "0";
            }
            if (box.hasAttribute("data-menu-blank")) {
                node.querySelector(".menu-blank-value").value = box.checked ? "1" : "0";
            }
        });

        menuTree.addEventListener("dragstart", function (event) {
            var handle = event.target.closest("[data-menu-drag]");
            if (!handle || !menuTree.contains(handle)) return;
            dragNode = handle.closest(".menu-node");
            if (!dragNode) return;
            dragNode.classList.add("is-dragging");
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", "menu");
        });

        menuTree.addEventListener("dragover", function (event) {
            if (!dragNode) return;
            var target = event.target.closest(".menu-node");
            if (!target || blockOf(dragNode).indexOf(target) !== -1) return;
            event.preventDefault();
            var block = blockOf(dragNode);
            var rect = target.getBoundingClientRect();
            if (event.clientY < rect.top + rect.height / 2) {
                block.forEach(function (item) { menuTree.insertBefore(item, target); });
            } else {
                var after = blockOf(target);
                var marker = after[after.length - 1].nextSibling;
                block.forEach(function (item) { menuTree.insertBefore(item, marker); });
            }
        });

        menuTree.addEventListener("drop", function (event) {
            if (!dragNode) return;
            event.preventDefault();
            legalizeAll();
        });

        menuTree.addEventListener("dragend", function () {
            if (dragNode) dragNode.classList.remove("is-dragging");
            dragNode = null;
            legalizeAll();
        });

        document.querySelectorAll("[data-menu-tab]").forEach(function (tab) {
            tab.addEventListener("click", function () {
                var id = tab.getAttribute("data-menu-tab");
                document.querySelectorAll("[data-menu-tab]").forEach(function (item) {
                    item.classList.toggle("is-on", item === tab);
                });
                document.querySelectorAll("[data-menu-source-panel]").forEach(function (panel) {
                    panel.classList.toggle("is-on", panel.getAttribute("data-menu-source-panel") === id);
                });
            });
        });

        document.querySelectorAll("[data-menu-filter]").forEach(function (filter) {
            filter.addEventListener("input", function () {
                var panel = filter.closest("[data-menu-source-panel]");
                var query = filter.value.trim().toLocaleLowerCase("tr");
                panel.querySelectorAll("[data-menu-choice]").forEach(function (choice) {
                    var text = (choice.getAttribute("data-search") || "").toLocaleLowerCase("tr");
                    choice.hidden = query !== "" && text.indexOf(query) === -1;
                });
            });
        });

        document.querySelectorAll("[data-menu-add]").forEach(function (button) {
            button.addEventListener("click", function () {
                var panel = button.closest("[data-menu-source-panel]");
                panel.querySelectorAll("input[type='checkbox']:checked").forEach(function (box) {
                    appendItem({
                        kind: box.getAttribute("data-kind") || "link",
                        ref: box.getAttribute("data-ref") || "",
                        label: box.getAttribute("data-label") || "",
                        url: box.getAttribute("data-url") || ""
                    });
                    box.checked = false;
                });
            });
        });

        var customAdd = document.querySelector("[data-menu-custom-add]");
        if (customAdd) {
            customAdd.addEventListener("click", function () {
                var wrap = customAdd.closest("[data-menu-custom]");
                var label = wrap.querySelector("[data-custom-label]");
                var href = wrap.querySelector("[data-custom-url]");
                var error = wrap.querySelector("[data-custom-error]");
                var name = label.value.trim();
                var address = href.value.trim();
                if (name === "" || address === "") {
                    error.hidden = false;
                    error.textContent = "Ad ve adres birlikte yazılmalı.";
                    return;
                }
                error.hidden = true;
                appendItem({ kind: "link", ref: "", label: name, url: address });
                label.value = "";
                href.value = "";
            });
        }

        if (menuForm) {
            menuForm.addEventListener("submit", function () {
                legalizeAll();
                menuTree.querySelectorAll(".menu-node").forEach(function (node) {
                    var mega = node.querySelector("[data-menu-mega]");
                    var blank = node.querySelector("[data-menu-blank]");
                    node.querySelector(".menu-mega-value").value = mega && mega.checked ? "1" : "0";
                    node.querySelector(".menu-blank-value").value = blank && blank.checked ? "1" : "0";
                    node.querySelector(".menu-depth").value = node.getAttribute("data-depth") || "0";
                });
            });
        }
    }

    var catalog = document.querySelector("[data-catalog]");
    if (catalog) {
        var master = catalog.querySelector("[data-check-all]");
        var boxes = catalog.querySelectorAll("[data-check]");
        var countNode = catalog.querySelector("[data-selected-count]");
        var syncMaster = function () {
            var checked = 0;
            boxes.forEach(function (box) {
                if (box.checked) {
                    checked++;
                }
            });
            if (master) {
                master.checked = boxes.length > 0 && checked === boxes.length;
                master.indeterminate = checked > 0 && checked < boxes.length;
            }
            if (countNode) {
                countNode.textContent = String(checked);
            }
        };
        if (master) {
            master.addEventListener("change", function () {
                boxes.forEach(function (box) {
                    box.checked = master.checked;
                });
                syncMaster();
            });
        }
        boxes.forEach(function (box) {
            box.addEventListener("change", syncMaster);
        });
    }
    document.querySelectorAll(".catalog-filters select[data-auto]").forEach(function (select) {
        select.addEventListener("change", function () {
            if (select.form) {
                select.form.submit();
            }
        });
    });

    document.querySelectorAll("[data-toggle-password]").forEach(function (button) {
        button.addEventListener("click", function () {
            var input = document.getElementById(button.getAttribute("data-toggle-password"));
            if (!input) {
                return;
            }
            var show = input.type === "password";
            input.type = show ? "text" : "password";
            button.setAttribute("aria-pressed", show ? "true" : "false");
            button.setAttribute("aria-label", show ? "Şifreyi gizle" : "Şifreyi göster");
        });
    });
}());
