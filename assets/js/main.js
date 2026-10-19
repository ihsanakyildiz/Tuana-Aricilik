(function () {
    var root = document.documentElement;
    root.setAttribute("data-motion-ready", "1");
    var reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    if (reduceMotion) {
        document.querySelectorAll(".hero-video").forEach(function (video) {
            video.removeAttribute("autoplay");
            video.pause();
        });
    }
    document.querySelectorAll("[data-hero-slider]").forEach(function (deck) {
        var slides = Array.prototype.slice.call(deck.querySelectorAll("[data-slide]"));
        if (slides.length < 2) return;
        var effect = deck.getAttribute("data-effect") || "fade";
        var wait = Number(deck.getAttribute("data-interval")) || 6000;
        var autoplay = deck.getAttribute("data-autoplay") === "1" && !reduceMotion && effect !== "none";
        var index = 0;
        var timer = 0;
        function show(next) {
            index = (next + slides.length) % slides.length;
            slides.forEach(function (slide, i) {
                var on = i === index;
                slide.classList.toggle("is-on", on);
                if (on) {
                    slide.removeAttribute("inert");
                } else {
                    slide.setAttribute("inert", "");
                }
                slide.querySelectorAll("video").forEach(function (video) {
                    if (on && !reduceMotion) {
                        var play = video.play();
                        if (play && play.catch) play.catch(function () {});
                    } else {
                        video.pause();
                    }
                });
            });
            if (effect === "slide") {
                var track = deck.querySelector(".hero-track");
                if (track) track.style.transform = "translateX(" + (-index * 100) + "%)";
            }
            deck.querySelectorAll("[data-hero-dot]").forEach(function (dot) {
                if (dot.getAttribute("data-hero-dot") === String(index)) {
                    dot.setAttribute("aria-current", "true");
                } else {
                    dot.removeAttribute("aria-current");
                }
            });
        }
        function stop() { window.clearInterval(timer); timer = 0; }
        function start() {
            stop();
            if (!autoplay) return;
            timer = window.setInterval(function () { show(index + 1); }, wait);
        }
        var prev = deck.querySelector("[data-hero-prev]");
        var next = deck.querySelector("[data-hero-next]");
        if (prev) prev.addEventListener("click", function () { show(index - 1); start(); });
        if (next) next.addEventListener("click", function () { show(index + 1); start(); });
        deck.querySelectorAll("[data-hero-dot]").forEach(function (dot) {
            dot.addEventListener("click", function () {
                show(Number(dot.getAttribute("data-hero-dot")));
                start();
            });
        });
        deck.addEventListener("mouseenter", stop);
        deck.addEventListener("mouseleave", start);
        show(0);
        start();
    });
    var motionNodes = document.querySelectorAll("[data-anim]");
    var homeLead = !document.body.classList.contains("is-page");

    function revealAll(nodes) {
        nodes.forEach(function (el) { el.classList.add("is-in"); });
    }

    function placeFeatureOverlap() {
        if (!homeLead) return;
        document.querySelectorAll(".features").forEach(function (section) {
            var card = section.querySelector(".feature-card");
            if (!card) return;
            section.style.setProperty("--feature-overlap", Math.round(card.offsetHeight * 0.2) + "px");
        });
    }

    placeFeatureOverlap();
    window.addEventListener("resize", placeFeatureOverlap);

    if (!motionNodes.length || !root.classList.contains("motion-ok") || !("IntersectionObserver" in window)) {
        revealAll(motionNodes);
    } else {
        var eager = [];
        var later = [];
        motionNodes.forEach(function (el) {
            if (homeLead && el.closest(".hero, .features")) {
                eager.push(el);
            } else {
                later.push(el);
            }
        });
        if (eager.length) {
            window.requestAnimationFrame(function () {
                window.requestAnimationFrame(function () { revealAll(eager); });
            });
        }
        if (later.length) {
            var motionObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) {
                        if (entry.target.getAttribute("data-anim-once") === "0") {
                            entry.target.classList.remove("is-in");
                        }
                        return;
                    }
                    entry.target.classList.add("is-in");
                    if (entry.target.getAttribute("data-anim-once") !== "0") {
                        motionObserver.unobserve(entry.target);
                    }
                });
            }, { rootMargin: "0px 0px -12% 0px", threshold: 0.18 });
            later.forEach(function (el) { motionObserver.observe(el); });
        }
    }

    var header = document.getElementById("site-header");
    var toggle = document.querySelector(".nav-toggle");
    var nav = document.getElementById("site-nav");

    var banners = document.querySelectorAll("[data-banner]");
    var promos = document.querySelectorAll("[data-promo]");
    var bannerFrame = 0;

    function placePin(node, pinSelector, copyName, shiftName, copyTravel, shiftTravel) {
        var pin = node.querySelector(pinSelector);
        var view = window.innerHeight || 800;
        var travel = node.offsetHeight - (pin ? pin.offsetHeight : view);
        if (travel < 8) {
            node.style.setProperty(copyName, "0px");
            node.style.setProperty(shiftName, "0px");
            return;
        }
        var stick = pin ? parseFloat(window.getComputedStyle(pin).top) || 0 : 0;
        var passed = Math.min(Math.max(-(node.getBoundingClientRect().top - stick), 0), travel);
        var t = passed / travel;
        node.style.setProperty(copyName, ((0.5 - t) * copyTravel).toFixed(1) + "px");
        node.style.setProperty(shiftName, ((t - 0.5) * shiftTravel).toFixed(1) + "px");
    }

    function placeBanners() {
        bannerFrame = 0;
        if (reduceMotion || (!banners.length && !promos.length)) return;
        var view = window.innerHeight || 800;
        banners.forEach(function (banner) {
            placePin(banner, ".banner-pin", "--banner-copy", "--banner-shift", Math.min(170, view * 0.24), 48);
        });
        promos.forEach(function (promo) {
            placePin(promo, ".promo-pin", "--promo-copy", "--promo-shift", Math.min(210, view * 0.26), 36);
        });
    }

    function queueBanners() {
        if (bannerFrame) return;
        bannerFrame = window.requestAnimationFrame(placeBanners);
    }

    function onScroll() {
        if (header) header.classList.toggle("is-stuck", window.scrollY > 8);
        queueBanners();
    }

    onScroll();
    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", queueBanners);

    if (toggle && nav) {
        toggle.addEventListener("click", function () {
            var open = nav.classList.toggle("is-open");
            toggle.setAttribute("aria-expanded", open ? "true" : "false");
        });

        nav.querySelectorAll("a").forEach(function (link) {
            link.addEventListener("click", function () {
                nav.classList.remove("is-open");
                toggle.setAttribute("aria-expanded", "false");
            });
        });
    }

    var introDialog = document.getElementById("intro-dialog");

    function stopIntroVideo() {
        if (!introDialog) return;
        var frame = introDialog.querySelector("[data-youtube-frame]");
        var stage = introDialog.querySelector("[data-youtube-stage]");
        var copy = introDialog.querySelector("[data-intro-copy]");
        if (frame) frame.removeAttribute("src");
        if (stage) stage.hidden = true;
        if (copy) copy.hidden = false;
        introDialog.classList.remove("is-video");
    }

    if (introDialog) {
        introDialog.addEventListener("close", stopIntroVideo);
    }

    document.querySelectorAll("[data-modal]").forEach(function (trigger) {
        trigger.addEventListener("click", function (event) {
            if (!introDialog || typeof introDialog.showModal !== "function") return;
            event.preventDefault();
            var videoId = trigger.getAttribute("data-youtube") || "";
            var frame = introDialog.querySelector("[data-youtube-frame]");
            var stage = introDialog.querySelector("[data-youtube-stage]");
            var copy = introDialog.querySelector("[data-intro-copy]");
            if (frame && stage && /^[A-Za-z0-9_-]{11}$/.test(videoId)) {
                frame.src = "https://www.youtube-nocookie.com/embed/" + videoId + "?autoplay=1&rel=0";
                stage.hidden = false;
                if (copy) copy.hidden = true;
                introDialog.classList.add("is-video");
            } else {
                stopIntroVideo();
            }
            if (!introDialog.open) introDialog.showModal();
        });
    });

    var slider = document.querySelector("[data-slider]");
    if (slider) {
        var track = slider.querySelector(".review-track");
        var cards = track ? Array.prototype.slice.call(track.children) : [];
        var index = 0;

        function perView() {
            if (window.innerWidth < 720) return 1;
            if (window.innerWidth < 1020) return 2;
            return 3;
        }

        function maxIndex() {
            return Math.max(0, cards.length - perView());
        }

        function render() {
            if (!track || cards.length === 0) return;
            index = Math.min(index, maxIndex());
            var card = cards[0];
            var gap = 22;
            var shift = index * (card.getBoundingClientRect().width + gap);
            track.style.transform = "translateX(" + (-shift) + "px)";
        }

        var prev = slider.querySelector(".prev");
        var next = slider.querySelector(".next");
        if (prev) prev.addEventListener("click", function () { index = Math.max(0, index - 1); render(); });
        if (next) next.addEventListener("click", function () { index = Math.min(maxIndex(), index + 1); render(); });
        window.addEventListener("resize", render);
        render();

        var timer = null;
        function start() {
            if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) return;
            stop();
            timer = window.setInterval(function () {
                index = index >= maxIndex() ? 0 : index + 1;
                render();
            }, 6500);
        }
        function stop() {
            if (timer) window.clearInterval(timer);
            timer = null;
        }
        slider.addEventListener("mouseenter", stop);
        slider.addEventListener("mouseleave", start);
        start();
    }

    document.querySelectorAll("[data-service-slider]").forEach(function (root) {
        var track = root.querySelector(".service-track");
        var cards = track ? Array.prototype.slice.call(track.children) : [];
        if (!track || cards.length < 2) return;
        var wanted = Math.max(2, Math.min(6, Number(root.getAttribute("data-cols")) || 4));
        var effect = root.getAttribute("data-effect") || "slide";
        var groupStep = root.getAttribute("data-step") === "group";
        var loop = root.getAttribute("data-loop") === "1";
        var autoplay = root.getAttribute("data-autoplay") === "1" && !reduceMotion && effect !== "none";
        var pause = root.getAttribute("data-pause") !== "0";
        var wait = Number(root.getAttribute("data-interval")) || 5000;
        var index = 0;
        var timer = 0;
        var startX = 0;
        var dragged = false;

        function cols() {
            if (window.innerWidth < 720) return 1;
            if (window.innerWidth < 1020) return Math.min(wanted, 2);
            return wanted;
        }

        function stepSize() {
            return groupStep ? cols() : 1;
        }

        function maxIndex() {
            return Math.max(0, Math.ceil((cards.length - cols()) / stepSize()));
        }

        function paintDots() {
            var dots = root.querySelector("[data-service-dots]");
            if (!dots) return;
            var total = maxIndex() + 1;
            if (dots.childElementCount !== total) {
                dots.innerHTML = "";
                for (var i = 0; i < total; i += 1) {
                    var dot = document.createElement("button");
                    dot.type = "button";
                    dot.className = "service-dot";
                    dot.setAttribute("aria-label", (i + 1) + ". grup");
                    dot.addEventListener("click", function (event) {
                        var button = event.currentTarget;
                        index = Array.prototype.indexOf.call(dots.children, button);
                        show(false);
                        restart();
                    });
                    dots.appendChild(dot);
                }
            }
            Array.prototype.forEach.call(dots.children, function (dot, i) {
                if (i === index) dot.setAttribute("aria-current", "true");
                else dot.removeAttribute("aria-current");
            });
        }

        function show(wrapped) {
            index = loop ? ((index % (maxIndex() + 1)) + (maxIndex() + 1)) % (maxIndex() + 1) : Math.max(0, Math.min(index, maxIndex()));
            root.style.setProperty("--service-cols", String(cols()));
            var card = cards[0];
            var shift = index * stepSize() * (card.getBoundingClientRect().width + 18);
            var motion = !reduceMotion && !wrapped && effect !== "none";
            if (effect === "fade" && motion) {
                track.style.transition = "opacity .35s ease";
                track.style.opacity = "0";
                window.setTimeout(function () {
                    track.style.transition = "none";
                    track.style.transform = "translate3d(" + (-shift) + "px,0,0)";
                    track.style.opacity = "1";
                }, 350);
            } else {
                track.style.transition = motion && effect === "slide" ? "transform .55s ease" : "none";
                track.style.transform = "translate3d(" + (-shift) + "px,0,0)";
            }
            var prev = root.querySelector("[data-service-prev]");
            var next = root.querySelector("[data-service-next]");
            if (prev) prev.disabled = !loop && index <= 0;
            if (next) next.disabled = !loop && index >= maxIndex();
            paintDots();
        }

        function go(direction) {
            var nextIndex = index + direction;
            var wrapped = loop && (nextIndex > maxIndex() || nextIndex < 0);
            index = nextIndex;
            show(wrapped);
            restart();
        }

        function stop() { window.clearInterval(timer); timer = 0; }
        function restart() {
            stop();
            if (!autoplay || maxIndex() < 1) return;
            timer = window.setInterval(function () { go(1); }, wait);
        }

        var prev = root.querySelector("[data-service-prev]");
        var next = root.querySelector("[data-service-next]");
        if (prev) prev.addEventListener("click", function () { go(-1); });
        if (next) next.addEventListener("click", function () { go(1); });
        var viewport = root.querySelector(".service-viewport");
        if (viewport) {
            viewport.addEventListener("pointerdown", function (event) {
                if (event.pointerType === "mouse" && event.button !== 0) return;
                startX = event.clientX;
                dragged = false;
            });
            viewport.addEventListener("pointerup", function (event) {
                var delta = event.clientX - startX;
                if (Math.abs(delta) < 48) return;
                dragged = true;
                go(delta < 0 ? 1 : -1);
            });
            viewport.addEventListener("click", function (event) {
                if (!dragged) return;
                event.preventDefault();
                event.stopPropagation();
                dragged = false;
            }, true);
        }
        if (pause) {
            root.addEventListener("mouseenter", stop);
            root.addEventListener("mouseleave", restart);
        }
        window.addEventListener("resize", function () { show(true); });
        show(true);
        restart();
    });
})();
