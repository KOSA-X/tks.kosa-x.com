/* =========================================================
   APP CORE (cookies + helpers)
========================================================= */
(function (window, document, $) {
  "use strict";

  // Guard: bez jQuery nie inicjalizujemy skryptów, ale treść musi być widoczna
  // (.showUp jest ukryte w CSS do czasu animacji ScrollReveal)
  if (!$) {
    var revealAll = function () {
      document.querySelectorAll(".showUp").forEach(function (el) {
        el.style.visibility = "visible";
      });
    };
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", revealAll);
    } else {
      revealAll();
    }
    console.warn("jQuery nie został załadowany — inicjalizacja skryptów pominięta.");
    return;
  }

  const App = window.App || (window.App = {});

  // ---------- Cookies (jeden moduł dla całej strony) ----------
  App.Cookie = {
    get(name) {
      const cookies = document.cookie ? document.cookie.split("; ") : [];
      for (let i = 0; i < cookies.length; i++) {
        const p = cookies[i].split("=");
        const k = decodeURIComponent(p.shift());
        if (k === name) return decodeURIComponent(p.join("="));
      }
      return null;
    },

    set(name, value, opts = {}) {
      const path = opts.path || "/";
      const sameSite = (opts.sameSite || "Lax");
      const secure = (typeof opts.secure === "boolean") ? opts.secure : (location.protocol === "https:");

      let maxAge = 0;
      if (opts.days) maxAge = Math.max(1, parseInt(opts.days, 10) || 1) * 86400;
      else if (opts.seconds) maxAge = Math.max(1, parseInt(opts.seconds, 10) || 1);
      else maxAge = 31536000; // 1 rok domyślnie

      let c =
        encodeURIComponent(name) + "=" + encodeURIComponent(String(value)) +
        "; path=" + path +
        "; max-age=" + maxAge +
        "; samesite=" + sameSite;

      if (secure) c += "; secure";
      document.cookie = c;
    },

    del(name, opts = {}) {
      this.set(name, "", { ...opts, seconds: 1 });
    }
  };

  // Kompatybilność z Twoimi starymi funkcjami (cookies consent itp.)
  window.WHCreateCookie = function (name, value, days) {
    App.Cookie.set(name, value, { days: days || 1 });
  };
  window.WHReadCookie = function (name) {
    return App.Cookie.get(name);
  };

  // ---------- Helpers ----------
  App.hasFancybox = () => typeof window.Fancybox !== "undefined";
  App.hasScrollReveal = () => typeof window.ScrollReveal !== "undefined";
  App.hasLenis = () => typeof window.Lenis !== "undefined";

  /* =========================================================
     1) Smooth scroll + Parallax (Lenis) + body resize sync
  ========================================================= */
  function initLenisParallax() {
        if (!App.hasLenis()) return;

        const lenis = new Lenis({
          duration: 0,
          easing: (t) => t,
          smooth: true,
          smoothTouch: false
        });

        // ekspozycja na zewnątrz - żeby inne moduły mogły wywołać resize
        App.lenis = lenis;

        const parallaxItems = [
          { selector: ".imageParallax", scale: 1.1, strength: 0.1, max: 100, inverse: true, target: "img" },
          { selector: ".contentParallax", scale: 1, strength: 0.1, max: 30, inverse: true },
          { selector: ".contentParallaxInverse", scale: 1, strength: 0.1, max: 20, inverse: false }
        ];

        let cachedItems = [];
        let cacheTimer = null;

        function buildCache() {
          cachedItems = [];
          const scrollY = window.scrollY;

          parallaxItems.forEach(({ selector, scale, strength, max, inverse, target }) => {
            $(selector).each(function () {
              const $el = $(this);
              const $target = target ? $el.find(target) : $el;
              const rect = this.getBoundingClientRect();
              const elTop = rect.top + scrollY;
              const elHeight = rect.height;

              cachedItems.push({
                $target, scale, strength, max, inverse,
                elTop, elHeight
              });
            });
          });
        }

        function scheduleRebuildCache() {
          clearTimeout(cacheTimer);
          cacheTimer = setTimeout(buildCache, 200);
        }

        // Funkcja pełnego resyncu: cache parallaxu + Lenis limit
        function fullResync() {
          if (lenis && typeof lenis.resize === "function") {
            lenis.resize();
          }
          buildCache();
        }

        let resyncTimer = null;
        function scheduleFullResync() {
          clearTimeout(resyncTimer);
          resyncTimer = setTimeout(fullResync, 100);
        }

        // Start
        buildCache();

        // Resize okna
        window.addEventListener('resize', scheduleRebuildCache);

        // Lazy loaded image -> pełny resync (wysokość body się zmieniła)
        $(document).on('lazyLoaded', scheduleFullResync);

        // Po pełnym załadowaniu strony -> ostateczny resync
        window.addEventListener('load', function () {
          setTimeout(fullResync, 50);
        });

        // ResizeObserver na body - łapie KAŻDĄ zmianę wysokości
        // (lazy images, owl-carousel init, accordion, tabs, dynamiczny content)
        if (typeof ResizeObserver !== "undefined") {
          const ro = new ResizeObserver(scheduleFullResync);
          ro.observe(document.body);
        }

        function updateParallax(scrollY) {
          if (!cachedItems.length) return;
          const winH = window.innerHeight;

          cachedItems.forEach(({ $target, scale, strength, max, inverse, elTop, elHeight }) => {
            const elCenter = elTop + elHeight / 2;
            const viewportCenter = scrollY + winH / 2;
            const dist = elCenter - viewportCenter;
            const movement = Math.max(-max, Math.min(max, (inverse ? -dist : dist) * strength));
            $target.css("transform", `scale(${scale}) translateY(${movement}px)`);
          });
        }

        function raf(time) {
          lenis.raf(time);
          updateParallax(window.scrollY);
          requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);
      }

  /* =========================================================
     2) Menu (scroll-lock + hamburger)
  ========================================================= */
  function initMenu() {
    const $body = $("body");
    const $html = $("html");
    const $pageBody = $("body");

    const isMenuOpen = () => $(".mainHeader").hasClass("mainHeaderOpen");

    function setScrollLock(active) {
      if (active) {
        if ($html.hasClass("scroll-lock")) return;
        const docEl = document.documentElement;
        const scrollbarWidth = window.innerWidth - docEl.clientWidth;
        if (scrollbarWidth > 0) $html.css("padding-right", scrollbarWidth + "px");
        $html.addClass("scroll-lock");
        $pageBody.addClass("scroll-lock");
        $body.addClass("blurEffect");
      } else {
        if (!$html.hasClass("scroll-lock")) return;
        $html.removeClass("scroll-lock").css("padding-right", "");
        $pageBody.removeClass("scroll-lock");
        $body.removeClass("blurEffect");
      }
    }

    function setMenu(open) {
      $(".mainHeader").toggleClass("mainHeaderOpen", open);
      setScrollLock(open);
    }

    $(document).on("click", ".menuHamburger", function () {
      setMenu(!isMenuOpen());
    });

    $(document).on("mousedown", function (e) {
      const $t = $(e.target);
      if (isMenuOpen() && !$t.closest(".mainHeader__menu, .menuHamburger").length) {
        setMenu(false);
      }
    });

    $(document).on("keydown", function (e) {
      if (e.key === "Escape" && isMenuOpen()) setMenu(false);
    });
  }

  /* =========================================================
     3) Fancybox binds (jeden init)
  ========================================================= */

  // --- Reele Instagram: autoplay + stop/zerowanie wideo w popupie ----------
  // Fancybox otwiera inline-modal (#cardInsta-{id}) PRZENOSZĄC ten sam węzeł
  // <video> do widocznego overlay-a i chowając go z powrotem (display:none) przy
  // zamknięciu. Zamiast zgadywać nazwy zdarzeń Fancyboxa (część nie odpala się na
  // czas — węzeł wraca do modala zanim zdarzenie doleci, stąd dźwięk "w tle" po
  // zamknięciu) obserwujemy REALNĄ widoczność każdego <video>:
  //   widoczne (popup otwarty, slajd bieżący) → play() z dźwiękiem,
  //   niewidoczne (popup zamknięty / sąsiedni slajd karuzeli) → pauza + zerowanie.
  // currentTime=0 (nie sama pauza) → brak dźwięku w tle i brak nakładania rolek.
  function initInstaReelVideos() {
    const videos = document.querySelectorAll("video.cardInsta__video");
    if (!videos.length || !("IntersectionObserver" in window)) return;

    const io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        const v = entry.target;
        const visible = entry.isIntersecting && entry.intersectionRatio >= 0.5;
        if (visible) {
          v.loop = true;
          v.muted = false;
          if (!v.paused && !v.ended) return; // już gra — nie restartuj
          const p = v.play();
          if (p && typeof p.catch === "function") {
            p.catch(function (err) {
              // AbortError = play() przerwane kolejnym wywołaniem — nie wyciszaj.
              if (err && err.name === "AbortError") return;
              // realna blokada autoplay z dźwiękiem → odtwórz wyciszone
              v.muted = true;
              v.play().catch(function () {});
            });
          }
        } else {
          try { v.pause(); v.currentTime = 0; } catch (e) {}
        }
      });
    }, { threshold: [0, 0.5, 1] });

    videos.forEach(function (v) { io.observe(v); });
  }

  function initFancyboxBinds() {
    if (!App.hasFancybox()) return;

    Fancybox.bind("[data-fancybox-slide-up]", {
      mainClass: "fb-slide-up",
      dragToClose: false,
      backdropClick: true,
      contentClick: false,
      Carousel: {
        dragFree: false,
        friction: 0.92,
        Panzoom: { touch: false }
      }
    });

    Fancybox.bind("[data-fancybox]:not([data-fancybox^='instafeed'])", {
      dragToClose: false,
      backdropClick: true,
      closeButton: true,
      contentClick: false,
      Carousel: {
        Panzoom: { touch: false }
      }
    });

    // InstaFeed: dedykowana grupa (data-fancybox="instafeed-{konto}") z własną
    // klasą główną fb-instafeed do scope'owania captionu. Reużywa istniejącej
    // instancji Fancyboxa — NIE jest to druga inicjalizacja.
    Fancybox.bind("[data-fancybox^='instafeed']", {
      mainClass: "fb-instafeed",
      dragToClose: false,
      backdropClick: true,
      closeButton: true,
      contentClick: false,
      // Reels: lokalny mp4 odtwarzany w Fancyboxie (typ html5video z widgetu).
      Html5video: {
        autoplay: true,
        format: "video/mp4"
      },
      Carousel: {
        Panzoom: { touch: false }
      }
    });
  }

  /* =========================================================
     4) POPUPS (auto Fancybox + cookie per popup)
     - zamknięcie tylko: X lub [data-popup-close]/#popup-close
     - blok klik w tło (backdrop)
  ========================================================= */
  function initPopups() {
    const popups = Array.from(document.querySelectorAll(".js-popup-widget"));
    if (!popups.length || !App.hasFancybox()) return;

    const PREFIX = "popup_seen_";
    const closeSel = "[data-popup-close], #popup-close, .popupWidget__close";
    let openedEl = null;

    const getId = (el) => el.dataset.popupId || (el.id || "").replace("popup-widget-", "");
    const isSeen = (el) => !!App.Cookie.get(PREFIX + getId(el));
    const markSeen = (el) => {
      const id = getId(el);
      if (!id) return;
      const days = parseInt(el.dataset.cookieDays || "14", 10) || 14;
      App.Cookie.set(PREFIX + id, "1", { days });
    };

    // zamknięcie buttonem -> zawsze zapis cookie
    document.addEventListener("click", function (e) {
        const btn = e.target.closest(closeSel);
        if (!btn) return;

        const popup = btn.closest(".js-popup-widget");
        if (popup) markSeen(popup); // zapis cookie od razu na klik

        const href = btn.getAttribute("href");
        const isRealLink =
            btn.tagName === "A" &&
            href &&
            href !== "#" &&
            !href.startsWith("javascript:");

        // LINK: zamknij + przejdź na URL
        if (isRealLink) {
            e.preventDefault();

            const url = btn.href || href; // btn.href daje pełny URL
            if (App.hasFancybox()) Fancybox.close();

            setTimeout(() => {
                if (btn.target === "_blank") window.open(url, "_blank");
                else window.location.assign(url);
            }, 10);

            return;
        }

        // BUTTON / inne: tylko zamknij
        e.preventDefault();
        if (App.hasFancybox()) Fancybox.close();
    }, true);


    // blok klik w backdrop jeśli popup otwarty (niezależnie od opcji fancybox)
    document.addEventListener("click", function (e) {
      if (!openedEl) return;

      const isBackdrop = e.target?.classList?.contains("fancybox__backdrop") || e.target?.closest?.(".fancybox__backdrop");
      if (isBackdrop) {
        e.preventDefault();
        e.stopPropagation();
      }
    }, true);

    function open(el) {
      openedEl = el;
      const onClose = () => { markSeen(el); openedEl = null; };

      Fancybox.show([{ src: "#" + el.id, type: "inline" }], {
        dragToClose: false,
        closeButton: true,
        contentClick: false,
        keyboard: false,
        Carousel: { Panzoom: { touch: false } },
        on: { close: onClose, closing: onClose, closed: onClose, destroy: onClose }
      });
    }

    // otwórz pierwszy niewidziany
    for (const el of popups) {
      if (!isSeen(el)) { open(el); break; }
    }
  }

  /* =========================================================
     5) Dropdown
  ========================================================= */
  function initDropdown() {
    $(document).on("click", ".dropdown__button", function (e) {
      e.stopPropagation();

      const $dropdown = $(this).closest(".dropdown");
      const isOpen = $dropdown.hasClass("open");

      $(".dropdown").removeClass("open").find(".dropdown__button").removeClass("selected");

      if (!isOpen) {
        $dropdown.addClass("open");
        $(this).addClass("selected");
      }
    });

    $(document).on("click", function () {
      $(".dropdown").removeClass("open").find(".dropdown__button").removeClass("selected");
    });
  }

  /* =========================================================
     6) Tabs
  ========================================================= */
  function initTabs() {
    const FADE_MS = 180;
    const HEIGHT_MS = 220;

    function getTargetItem($tabs, target) {
      return $tabs.find("#tabs-" + target);
    }

    function measureHeight($el) {
      if ($el.is(":visible")) return $el.outerHeight(true);

      const $clone = $el.clone(true, true)
        .css({
          position: "absolute",
          visibility: "hidden",
          display: "block",
          left: -99999,
          top: 0,
          width: $el.parent().width()
        })
        .appendTo($el.parent());

      const h = $clone.outerHeight(true);
      $clone.remove();
      return h;
    }

    function initTabsOne($tabs) {
      const $links = $tabs.find(".tabs__nav a");
      const $content = $tabs.find(".tabs__content");
      const $items = $tabs.find(".tabs__item");

      if ($links.filter(".selected").length === 0) $links.first().addClass("selected");

      const target = $links.filter(".selected").data("target");
      const $active = getTargetItem($tabs, target);

      $items.hide();
      $active.show();
      $content.css("height", "auto");
    }

    $(".tabs").each(function () { initTabsOne($(this)); });

    $(document).on("click", ".tabs .tabs__nav a", function (e) {
      e.preventDefault();

      const $link = $(this);
      const $tabs = $link.closest(".tabs");
      const $links = $tabs.find(".tabs__nav a");
      const $content = $tabs.find(".tabs__content");
      const $items = $tabs.find(".tabs__item");

      if ($link.hasClass("selected")) return;

      const target = $link.data("target");
      const $next = getTargetItem($tabs, target);
      if (!$next.length) return;

      const $current = $items.filter(":visible");
      const currentH = $content.outerHeight();
      const nextH = measureHeight($next);

      $links.removeClass("selected");
      $link.addClass("selected");

      $content.stop(true, true).css("height", currentH);

      $current.stop(true, true).fadeOut(FADE_MS, function () {
        $content.stop(true, true).animate({ height: nextH }, HEIGHT_MS);

        $next.stop(true, true).fadeIn(FADE_MS, function () {
          setTimeout(function () {
            if ($next.is(":visible")) $content.css("height", "auto");
          }, Math.max(FADE_MS, HEIGHT_MS));
        });
      });
    });
  }

  /* =========================================================
     7) Theme + Font (cookies)
  ========================================================= */
  function initThemeAndFont() {
    function applyTheme(theme) {
      const $body = $("body");
      if (theme !== "dark" && theme !== "contrast") theme = "light";

      $body.removeClass("theme-dark theme-contrast");
      if (theme !== "light") $body.addClass("theme-" + theme);

      $("#theme-switch-checkbox").prop("checked", theme === "dark");
      $("#contrast-switch-checkbox").prop("checked", theme === "contrast");

      App.Cookie.set("theme", theme);
    }

    function applyFont(size) {
      const isLarge = (size === "large");
      $("body").toggleClass("theme-font", isLarge);
      $("#font-size-switch-checkbox").prop("checked", isLarge);
      App.Cookie.set("fontSize", isLarge ? "large" : "normal");
    }

    applyTheme(App.Cookie.get("theme") || "light");
    applyFont(App.Cookie.get("fontSize") || "normal");

    $("#theme-switch-checkbox").on("change", function () {
      applyTheme(this.checked ? "dark" : "light");
    });

    $("#contrast-switch-checkbox").on("change", function () {
      applyTheme(this.checked ? "contrast" : "light");
    });

    $("#font-size-switch-checkbox").on("change", function () {
      applyFont(this.checked ? "large" : "normal");
    });
  }

    $('input[type="date"]').on('blur change', function() {
        $(this).addClass('touched');
    });

 

  /* =========================================================
     8) UI Misc (forms, hover, accordion, scroll effects, reveal)
  ========================================================= */
  function initUiMisc() {
    // password toggle
    $(document).on("click", ".togglePassword", function () {
      const $input = $(this).siblings(".form-control");
      $input.attr("type", $input.attr("type") === "password" ? "text" : "password");
    });

    // kod pocztowy
    $(document).on("input", ".city-code", function () {
      let v = $(this).val().replace(/[^0-9]/g, "");
      if (v.length > 2) v = v.slice(0, 2) + "-" + v.slice(2, 6);
      $(this).val(v);
    });

    $(document).on("keypress", ".city-code", function (e) {
      if (!/[0-9]/.test(e.key) || $(this).val().replace(/\D/g, "").length >= 6) e.preventDefault();
    });

    // video autoplay + opacity on scroll
    const video = $(".videoHeader__background").get(0);
    if (video && video.paused) {
      video.play().catch((err) => console.log("Autoplay zablokowany:", err));
    }
      
      $(function() {
    setTimeout(function() {
        $('.mainPage__header-bg').addClass('header-revealed');
    }, 100);
});
      

    // hover submenu blur (desktop)
    function initHoverEffect() {
      if ($(window).width() > 1200) {
        $(".mainHeader .menu_item_submenu").off("mouseenter mouseleave").hover(
          function () {
            $(this).addClass("hover");
            $(".mainBody, .videoHeader__background").addClass("blurEffect");
          },
          function () {
            $(this).removeClass("hover");
            $(".mainBody, .videoHeader__background").removeClass("blurEffect");
          }
        );
      } else {
        $(".mainHeader .menu_item_submenu").off("mouseenter mouseleave");
      }
    }
    initHoverEffect();
    $(window).on("resize", initHoverEffect);

    // menu mobile arrows
    $(document).on("click", ".card__arrow", function () {
      $(this).toggleClass("open");
      $(this).parent().parent().toggleClass("open");
    });

    // accordion init: otwórz pierwszą w każdej liście jeśli brak open
    $(".accordionList").each(function () {
      const $list = $(this);
      if ($list.find(".accordionItem__title.open").length === 0) {
        $list.find("li:first-child .accordionItem__content").show();
        $list.find("li:first-child .accordionItem__title").addClass("open");
      }
    });

    // accordion click (w obrębie danej listy)
    $(document).on("click", ".accordionItem__title", function () {
      const $header = $(this);
      const $content = $header.next(".accordionItem__content");
      const $list = $header.closest(".accordionList");

      if ($content.is(":visible")) {
        $content.slideUp();
        $header.removeClass("open");
      } else {
        $list.find(".accordionItem__content:visible").slideUp();
        $list.find(".accordionItem__title.open").removeClass("open");
        $content.slideDown();
        $header.addClass("open");
      }
    });

    // submenu toggle
    $(document).on("click", ".menu_arrow", function () {
      const id = $(this).attr("data-menu");
      $("#submenu_" + id).toggleClass("open");
    });

    $(".submenu_list .submenu_item.selected").parent().parent().parent().addClass("selected");
    $(".submenu_list .submenu_item.selected").parent().parent().addClass("open");

    // scrollTo
    $(document).on("click", ".scrollTo", function (e) {
      e.preventDefault();
      const id = $(this).attr("data-id");
      const $t = $("#section-" + id);
      if ($t.length) {
        $("html, body").animate({ scrollTop: $t.offset().top }, 1000);
      }
    });

    // faqItemCollapse
    $(document).on("click", ".faqItemCollapse", function (e) {
      e.preventDefault();
      const id = $(this).attr("data-id");
      $(".faqItem").removeClass("open");
      $(".faqItem-" + id).addClass("open");
    });

    // Scroll handlers (1 raf)
    let ticking = false;
    function onScroll() {
      if (ticking) return;
      ticking = true;

      requestAnimationFrame(() => {
        const y = $(document).scrollTop();

        $(".mainHeader").toggleClass("scroll", y > 100);

        const opacity = Math.max(0.7 - (y * 0.001), 0);
        $(".videoHeader__background").css("opacity", opacity);
//        $(".mainPage__header-bg::before").css("opacity", opacity);

        $(".footerUp, .mobileFooter").toggleClass("show", y > 100);

        ticking = false;
      });
    }
    $(document).on("scroll", onScroll);
    onScroll();

    // ScrollReveal (z poszanowaniem prefers-reduced-motion)
    const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    if (App.hasScrollReveal() && !prefersReducedMotion) {
      ScrollReveal().reveal(".showUp", {
        delay: 200,
        duration: 500,
        distance: '10px',
          origin: 'bottom',
           easing: 'ease-out',
        reset: true,
          opacity: 0,
        scale: 1
      });
    } else {
      // bez animacji — elementy .showUp muszą być widoczne
      document.querySelectorAll(".showUp").forEach(function (el) {
        el.style.visibility = "visible";
      });
    }
  }

  // (usunięte) Kaskadowe wejście kart przy scrollu — przy kilkunastu tysiącach
  // produktów opóźniało wyświetlanie listy. Karty pokazują się od razu.

  /* =========================================================
     9) Cart (AJAX + Fancybox cart) z obsługą filtrów produktu
  ========================================================= */
  function initCart() {
    const CART_URL = "/zamowienie";

    function openCartFancybox() {
      if (!App.hasFancybox()) return;
      Fancybox.show([{ src: "#cart-widget", type: "inline" }], {
        mainClass: "fb-slide-up",
        dragToClose: false,
        backdropClick: true,
        contentClick: false,
        Carousel: {
          dragFree: false,
          friction: 0.92,
          Panzoom: { touch: false }
        }
      });
    }

    function recalcCartTotals() {
      const $wrapper = $(".cartListWrapper").first();
      if (!$wrapper.length) return;

      let productsTotal = 0;
      let hasItems = false;

      $wrapper.find(".cartItem").each(function () {
        hasItems = true;

        const $priceEl = $(this).find(".cartItem__info .cartItem__price");
        let txt = ($priceEl.text() || "");
        txt = txt.replace(/\s/g, "").replace(/[^\d,\.]/g, "").replace(",", ".");
        const val = parseFloat(txt);
        if (!isNaN(val)) productsTotal += val;
      });

      if (!hasItems) {
        window.orderCartTotal = 0;
        if (typeof window.orderHandleEmptyCart === "function") window.orderHandleEmptyCart();
        return;
      }

      window.orderCartTotal = productsTotal;
      if (typeof window.orderUpdateSummary === "function") window.orderUpdateSummary();
    }

    function refreshCartList(doneCb) {
      const $wrappers = $(".cartListWrapper");
      if (!$wrappers.length) return (typeof doneCb === "function") && doneCb();

      $.get(window.location.href, function (html) {
        const $tmp = $("<div>").append($.parseHTML(html, document, true));
        const $freshWrappers = $tmp.find(".cartListWrapper");

        $wrappers.each(function (i) {
          const $current = $(this);
          const $fresh = $freshWrappers.eq(i);
          if ($fresh.length) $current.empty().append($fresh.children());
        });

        recalcCartTotals();
        if (typeof doneCb === "function") doneCb();
      });
    }

    function findProductContainer($btn) {
      let $container = $btn.closest(".productPage, .productCard, .pageShop__item, .productMore__content");

      if (!$container.length) {
        $container = $btn.closest("[data-product-id]");
      }

      if (!$container.length) {
        const productId = $btn.data("product-id");
        $container = $('.productConfig[data-product-id="' + productId + '"]').closest(".productPage, .productCard, .pageShop__item, .productMore__content");
      }

      return $container;
    }

    function getProductFilters($btn) {
      const productId = $btn.data("product-id");
      let filters = {};

      const btnFilters = $btn.data("filters");
      if (btnFilters && typeof btnFilters === "object" && Object.keys(btnFilters).length > 0) {
        return btnFilters;
      }

      const $container = findProductContainer($btn);
      let $productConfig = null;

      if ($container.length) {
        $productConfig = $container.find('.productConfig[data-product-id="' + productId + '"]');
      }

      if (!$productConfig || !$productConfig.length) {
        $productConfig = $('.productConfig[data-product-id="' + productId + '"]');
      }

      if ($productConfig && $productConfig.length) {
        $productConfig.find(".product-config-select").each(function () {
          const key = $(this).data("filter-key");
          const val = $(this).val();
          if (key && val) {
            filters[key] = val;
          }
        });

        $productConfig.find(".product-config-radio:checked").each(function () {
          const key = $(this).data("filter-key");
          const val = $(this).val();
          if (key && val) {
            filters[key] = val;
          }
        });

        $productConfig.find(".product-config-input").each(function () {
          const key = $(this).data("filter-key");
          const val = $(this).val();
          if (key && val) {
            filters[key] = val;
          }
        });
      }

      return filters;
    }

    function areFiltersComplete($btn) {
      const productId = $btn.data("product-id");

      const $container = findProductContainer($btn);
      let $productConfig = null;

      if ($container.length) {
        $productConfig = $container.find('.productConfig[data-product-id="' + productId + '"]');
      }

      if (!$productConfig || !$productConfig.length) {
        $productConfig = $('.productConfig[data-product-id="' + productId + '"]');
      }

      if (!$productConfig || !$productConfig.length) {
        return true;
      }

      if (!$productConfig.hasClass("productConfig--has-selects")) {
        return true;
      }

      let complete = true;

      $productConfig.find(".product-config-select").each(function () {
        if (!$(this).val()) {
          complete = false;
          return false;
        }
      });

      if (complete) {
        const radioGroups = {};
        $productConfig.find(".product-config-radio").each(function () {
          const key = $(this).data("filter-key");
          if (!radioGroups[key]) radioGroups[key] = false;
          if ($(this).is(":checked")) radioGroups[key] = true;
        });
        for (const key in radioGroups) {
          if (!radioGroups[key]) {
            complete = false;
            break;
          }
        }
      }

      return complete;
    }

    function updateAddToCartButton($btn) {
      const isComplete = areFiltersComplete($btn);
      const $container = findProductContainer($btn);
      const $message = $container.find(".productConfig__message");

      // UWAGA: przycisku NIE blokujemy przez disabled — element disabled nie
      // emituje zdarzenia click, więc nie dałoby się pokazać komunikatu
      // o brakujących opcjach. Stan sygnalizuje klasa .button-disabled.
      if (isComplete) {
        $btn.removeClass("button-disabled");
        if ($message.length) {
          $message.hide();
        }
        $container.find(".formCheckBox.is-invalid, .product-config-select.is-invalid").removeClass("is-invalid");
      } else {
        $btn.addClass("button-disabled");
      }
    }

    function showFilterMessage($btn) {
      const $container = findProductContainer($btn);
      const $message = $container.find(".productConfig__message");
      if ($message.length) {
        $message.show();
      }
    }

    function initProductConfigButtons() {
      $(".add-to-cart").each(function () {
        const $btn = $(this);
        if ($btn.hasClass("button-active")) return;

        updateAddToCartButton($btn);
      });

      $(document).off("change.productConfig").on("change.productConfig", ".product-config-select", function () {
        const $select = $(this);
        const $productConfig = $select.closest(".productConfig");
        const productId = $productConfig.data("product-id");

        if ($select.val()) {
          $select.removeClass("is-invalid");
        }

        const $container = $productConfig.closest(".productPage, .productCard, .pageShop__item, .productMore__content, [data-product-id]");
        let $btn = $container.find('.add-to-cart[data-product-id="' + productId + '"]');

        if (!$btn.length) {
          $btn = $('.add-to-cart[data-product-id="' + productId + '"]');
        }

        if ($btn.length) {
          updateAddToCartButton($btn);
        }
      });

      $(document).off("change.productConfigRadio").on("change.productConfigRadio", ".product-config-radio", function () {
        const $radio = $(this);
        const $productConfig = $radio.closest(".productConfig");
        const productId = $productConfig.data("product-id");

        // wybór w grupie zdejmuje jej podświetlenie błędu
        $radio.closest(".formCheckBox").removeClass("is-invalid");

        const $container = $productConfig.closest(".productPage, .productCard, .pageShop__item, .productMore__content, [data-product-id]");
        let $btn = $container.find('.add-to-cart[data-product-id="' + productId + '"]');

        if (!$btn.length) {
          $btn = $('.add-to-cart[data-product-id="' + productId + '"]');
        }

        if ($btn.length) {
          updateAddToCartButton($btn);
        }
      });
    }

    function updateCart(productId, action, filters = {}, redirect = false, $btn = null, doneCb = null) {
      if ($btn) $btn.prop("disabled", true);

      const postData = {
        product_id: productId,
        action: action,
        filters: JSON.stringify(filters)
      };

      $.post("/plugins/cart.php", postData, function (res) {
        if (res && res.status === "success") {
          if (redirect) {
            window.location.href = CART_URL;
          } else {
            refreshCartList(function () {
              if (typeof doneCb === "function") doneCb(res);
            });
          }
        } else {
          console.error("Błąd koszyka:", res && res.message ? res.message : "Nieznany błąd");
        }
      }, "json")
        .fail(function () { console.error("Błąd komunikacji z koszykiem."); })
        .always(function () { if ($btn) $btn.prop("disabled", false); });
    }

    $(document).on("click", ".add-to-cart", function (e) {
      const $btn = $(this);
      const productId = $btn.data("product-id");

      if (!areFiltersComplete($btn)) {
        e.preventDefault();
        e.stopPropagation();
        showFilterMessage($btn);

        const $container = findProductContainer($btn);
        $container.find(".product-config-select").each(function () {
          const $select = $(this);
          if (!$select.val()) {
            $select.addClass("is-invalid");
          } else {
            $select.removeClass("is-invalid");
          }
        });

        const radioGroups = {};
        $container.find(".product-config-radio").each(function () {
          const key = $(this).data("filter-key");
          if (!radioGroups[key]) radioGroups[key] = false;
          if ($(this).is(":checked")) radioGroups[key] = true;
        });
        $container.find(".product-config-radio").each(function () {
          const key = $(this).data("filter-key");
          if (!radioGroups[key]) {
            $(this).closest(".formCheckBox").addClass("is-invalid");
          } else {
            $(this).closest(".formCheckBox").removeClass("is-invalid");
          }
        });

        // przewiń do pierwszej brakującej opcji, żeby użytkownik ją widział
        const $firstInvalid = $container.find(".is-invalid").first();
        if ($firstInvalid.length && $firstInvalid[0].scrollIntoView) {
          $firstInvalid[0].scrollIntoView({ behavior: "smooth", block: "center" });
        }

        return false;
      }

      if (!$btn.hasClass("button-active")) {
        const filters = getProductFilters($btn);
        const loadingHtml = $btn.data("loading") || "<img src='/images/icons/loader.svg' alt='Ładowanie...' class='spin'>";
        $btn.prop("disabled", true).html(loadingHtml);

        updateCart(productId, "add", filters, false, $btn, function () {
          openCartFancybox();
        });

        setTimeout(() => {
          $btn
            .prop("disabled", false)
            .addClass("button-active")
            .html("<img src='/images/icons/check.svg' alt='Dodano'>Koszyk");

          $(".cartIcon").addClass("active");
          setTimeout(() => $(".cartIcon").removeClass("active"), 500);

          $btn.off("click").on("click", () => (window.location.href = CART_URL));
        }, 1000);
      } else {
        window.location.href = CART_URL;
      }
    });

    $(document).on("click", ".add-to-cart-1", function () {
      const $btn = $(this);
      const filters = getProductFilters($btn);
      updateCart($btn.data("product-id"), "add", filters);
    });

    $(document).on("click", ".remove-from-cart", function () {
      const $btn = $(this);
      const filters = $btn.data("filters") || {};
      updateCart($btn.data("product-id"), "remove", filters);
    });

    $(document).on("click", ".delete-from-cart", function () {
      const $btn = $(this);
      const filters = $btn.data("filters") || {};
      updateCart($btn.data("product-id"), "delete", filters);
    });

    initProductConfigButtons();
    recalcCartTotals();
  }

  /* =========================================================
     10) Input whitelist (text/textarea)
  ========================================================= */
  function initInputWhitelist() {
    document.querySelectorAll("input[type='text'], textarea").forEach(function (input) {
      input.addEventListener("keypress", function (event) {
        const char = String.fromCharCode(event.which);
        const regex = /^[a-zA-Z0-9\sąćęłńóśźżĄĆĘŁŃÓŚŹŻ\-\+\?\!\.\_\.]*$/;
        if (!regex.test(char)) event.preventDefault();
      });
    });
  }

  /* =========================================================
     11) Language selector + Google Translate (Twoje)
  ========================================================= */
  function initLanguage() {
    $("html").on("click", function () { $(".lang-select").removeClass("open"); });

    $(".lang-selected").on("click", function (e) {
      e.stopPropagation();
      $(".lang-select").toggleClass("open");
    });

    $(".lang-select-item").on("click", function (e) {
      e.stopPropagation();
      const dataVal = $(this).attr("data-val");
      $(".lang-selected").attr("data-val", dataVal);
      $(".lang-selected img").attr("src", "/images/icons/flag-" + dataVal + ".png");
      $(".lang-select").removeClass("open");
    });

    class GoogTrans {
      constructor() {
        this.events();
        this.currLng = this.readCookie("googtrans");

        if (this.currLng === "/en/pl") this.setLng("pl");
        if (this.currLng === "/en/en") this.setLng("en");
        if (this.currLng === "/en/uk") this.setLng("uk");
        if (this.currLng === "/en/ru") this.setLng("ru");
        if (this.currLng === "/en/de") this.setLng("de");
        if (this.currLng === "/fr/fr") this.setLng("fr");
      }

      events() {
        document.querySelectorAll(".lang-select-item").forEach((item) => {
          item.addEventListener("click", (e) => {
            e.stopPropagation();
            e.preventDefault();
            const lng = $(item).attr("data-val");
            this.setLng(lng);
          });
        });
      }

      setLng(lng) {
        try {
          if (lng === "pl") {
            jQuery("#\\:1\\.container").contents().find("#\\:1\\.restore").click();
          } else {
            jQuery(".goog-te-combo").val(lng);
            this.triggerHtmlEvent();
          }
          this.currLng = "/en/" + lng;
        } catch (e) {}
      }

      triggerHtmlEvent() {
        try {
          const element = document.querySelector(".goog-te-combo");
          if (!element) return;

          const ev = document.createEvent("HTMLEvents");
          ev.initEvent("change", true, true);
          element.dispatchEvent(ev);
        } catch (e) {}
      }

      readCookie(name) {
        const c = document.cookie.split("; ");
        const cookies = {};
        for (let i = c.length - 1; i >= 0; i--) {
          const C = c[i].split("=");
          cookies[C[0]] = C[1];
        }
        return cookies[name];
      }
    }

    new GoogTrans();
  }

  // global dla Google Translate (musi zostać globalnie)
  window.googleTranslateElementInit = function () {
    new google.translate.TranslateElement({
      pageLanguage: "pl",
      includedLanguages: "en,pl,uk,de,fr",
      layout: google.translate.TranslateElement.FloatPosition.SIMPLE
    }, "google_translate_element");
  };

  // flag icon sync
  (function langFlagSync() {
    const first_load_lang = document.querySelector("html")?.getAttribute("lang");
    let times_run = 0;
    const check_lang = setInterval(function () {
      times_run++;
      if (times_run >= 20) clearInterval(check_lang);

      const lang = document.querySelector("html")?.getAttribute("lang");
      if (lang && lang !== first_load_lang) {
        jQuery(".lang-selected img").attr("src", "images/icons/flag-" + lang + ".png");
        clearInterval(check_lang);
      }
    }, 200);
  })();

 

  /* =========================================================
     13) Lazy loading obrazków (IntersectionObserver)
         Pierwsze 3 zdjęcia ładują się od razu (src),
         pozostałe mają data-src i ładują się przy scrollu.
  ========================================================= */
  function initLazyImages() {
    // Ustaw aspect-ratio na galleryItem przed lazy loadem
    $('.gallery img[data-width]').each(function() {
        var w = parseInt($(this).data('width'));
        var h = parseInt($(this).data('height'));
        if (w && h) {
            $(this).closest('.galleryItem').css('aspect-ratio', w + ' / ' + h);
        }
    });

    // obrazki lazy (img.lazy z data-src) generuje listImagesView w każdej galerii
    // (kontener <ul class="gallery …">). Wcześniejszy selektor .galleryLazy nie
    // istniał w markupie → obserwator nigdy nie startował i src="" zostawał pusty.
    const lazyImgs = document.querySelectorAll('.gallery img.lazy');
    if (!lazyImgs.length) return;

    if (!('IntersectionObserver' in window)) {
        lazyImgs.forEach(function(img) {
            if (img.dataset.src) img.src = img.dataset.src;
            img.classList.remove('lazy');
            $(img).closest('.galleryItem').addClass('loaded');
        });
        return;
    }

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (!entry.isIntersecting) return;
            const img = entry.target;
            if (img.dataset.src) {
                img.src = img.dataset.src;
                img.onload = function() {
                    $(img).closest('.galleryItem').addClass('loaded');
                    $(document).trigger('lazyLoaded'); // ← dodaj tę linię
                };
            }
            img.classList.remove('lazy');
            observer.unobserve(img);
        });
    }, { rootMargin: '200px 0px' });

    lazyImgs.forEach(function(img) {
        observer.observe(img);
    });
}



    $(function () {
      // Inicjuj każdą galerię osobno
      $('.gallery.horizontalGallery').each(function () {
        initHorizontalGrid($(this));
      });

      function initHorizontalGrid($ul) {
        if (!$ul.length) return;

        // 1) Struktura: viewport + rows
        const $viewport = $('<div class="horizontalGallery__viewport"></div>');
        const $rowsWrap = $('<div class="horizontalGallery__rows"></div>');
        const $row1 = $('<ul class="galleryRow galleryRow--1" data-dir="right"></ul>');
        const $row2 = $('<ul class="galleryRow galleryRow--2" data-dir="left"></ul>');
        const $row3 = $('<ul class="galleryRow galleryRow--3" data-dir="right"></ul>');

        // Owiń i podłóż wrapy tylko dla TEJ galerii
        $ul.wrap($viewport);
        $ul.after($rowsWrap);
        $rowsWrap.append($row1, $row2, $row3);

        // Rozpakuj elementy po 9 na rząd (nadwyżki do ostatniego)
        const perRow = 9;
        const $items = $ul.children('li.galleryItem');
        $items.each(function (i) {
          if (i < perRow) $row1.append(this);
          else if (i < perRow * 2) $row2.append(this);
          else if (i < perRow * 3) $row3.append(this);
          else $row3.append(this);
        });

        // Usuń tylko TEN UL (już przenieśliśmy dzieci)
        $ul.remove();

        // 2) Stan per–wiersz dla TEJ instancji
        const rows = [
          { $row: $row1, offset: 0, max: 0, dir: +1, active: false, lastY: 0, init: false }, // 1 jedzie w prawo
          { $row: $row2, offset: 0, max: 0, dir: -1, active: false, lastY: 0, init: false }, // 2 w lewo (odwrotnie)
          { $row: $row3, offset: 0, max: 0, dir: +1, active: false, lastY: 0, init: false }  // 3 w prawo
        ];

        function measure() {
          const viewportW = $rowsWrap.width();
          rows.forEach(r => {
            // policz szerokość zawartości rzędu (tylko TE dzieci)
            let contentW = 0;
            r.$row.children().each(function () {
              contentW += $(this).outerWidth(true);
            });
            r.max = Math.max(0, contentW - viewportW);

            // pozycja startowa zależnie od kierunku:
            // rzędy jadące W PRAWO startują przewinięte maksymalnie w lewo (offset = max)
            if (!r.init) {
              r.offset = (r.dir === +1) ? r.max : 0;
              r.init = true;
            } else {
              r.offset = Math.max(0, Math.min(r.max, r.offset));
            }
            r.$row.css('transform', 'translate3d(' + (-r.offset) + 'px,0,0)');
          });
        }

        function isInViewport(el) {
          const rect = el.getBoundingClientRect();
          const vh = window.innerHeight || document.documentElement.clientHeight;
          return rect.top < vh && rect.bottom > 0;
        }

        const speed = 0.6; // czułość ruchu dla TEJ galerii

        function raf() {
          const y = window.scrollY || window.pageYOffset;

          rows.forEach(r => {
            const visible = isInViewport(r.$row[0]);
            if (visible) {
              if (!r.active) { r.lastY = y; r.active = true; }
              const dy = y - r.lastY; // prędkość scrolla
              r.lastY = y;

              // 1 & 3 (dir=+1): przy scrollu w dół (dy>0) offset maleje => ruch w prawo
              // 2 (dir=-1): przy scrollu w dół offset rośnie => ruch w lewo
              r.offset += (-dy * speed * r.dir);
              if (r.offset < 0) r.offset = 0;
              if (r.offset > r.max) r.offset = r.max;

              r.$row.css('transform', 'translate3d(' + (-r.offset) + 'px,0,0)');
            } else {
              // poza widokiem – pauza (nie liczymy prędkości, nie przesuwamy)
              r.active = false;
            }
          });

          requestAnimationFrame(raf);
        }

        // Start tylko dla TEJ instancji
        measure();
        requestAnimationFrame(raf);

        // Reakcja na resize/load (tylko ta galeria się przelicza)
        $(window).on('resize', measure);
        $(window).on('load', measure);
      }
    });

  /* =========================================================
     INIT (jedno miejsce)
  ========================================================= */
  function initAll() {
    initLenisParallax();
    initMenu();
    initFancyboxBinds();
    initPopups();

    initDropdown();
    initTabs();

    initThemeAndFont();
    initUiMisc();

    initCart();
    initInputWhitelist();

    initLanguage();
    initLazyImages();
    initInstaReelVideos();
  }

  // Start
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAll);
  } else {
    initAll();
  }

})(window, document, window.jQuery);