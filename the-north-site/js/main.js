(function () {
  const config = window.TNF_CONFIG || {};
  const dictionaries = window.TNF_I18N || {};
  const supportedLanguages = ["en", "fr"];
  let currentLanguage = "en";
  let closeMobileMenu = function () {};

  function getDictionary(language) {
    return dictionaries[language] || dictionaries.en || {};
  }

  function translate(key, fallback) {
    const dictionary = getDictionary(currentLanguage);
    return dictionary[key] || fallback || key;
  }

  function getStoredLanguage() {
    try {
      return window.localStorage.getItem("tnf-language");
    } catch (error) {
      return null;
    }
  }

  function storeLanguage(language) {
    try {
      window.localStorage.setItem("tnf-language", language);
    } catch (error) {}
  }

  function applyLanguage(language) {
    currentLanguage = supportedLanguages.includes(language) ? language : "en";
    const dictionary = getDictionary(currentLanguage);
    const page = document.body ? document.body.getAttribute("data-page") || "home" : "home";
    const metaPrefix = page === "home" ? "meta" : `${page}.meta`;
    const getMetaCopy = (name) => dictionary[`${metaPrefix}.${name}`] || dictionary[`meta.${name}`];

    document.documentElement.setAttribute("lang", currentLanguage);

    const title = getMetaCopy("title");
    if (title) document.title = title;

    const metaDescription = document.querySelector('meta[name="description"]');
    const description = getMetaCopy("description");
    if (metaDescription && description) metaDescription.setAttribute("content", description);

    const ogTitle = document.querySelector('meta[property="og:title"]');
    const openGraphTitle = getMetaCopy("ogTitle");
    if (ogTitle && openGraphTitle) ogTitle.setAttribute("content", openGraphTitle);

    const ogDescription = document.querySelector('meta[property="og:description"]');
    const openGraphDescription = getMetaCopy("ogDescription");
    if (ogDescription && openGraphDescription) ogDescription.setAttribute("content", openGraphDescription);

    document.querySelectorAll("[data-i18n]").forEach((element) => {
      const key = element.getAttribute("data-i18n");
      if (key && dictionary[key]) element.textContent = dictionary[key];
    });

    document.querySelectorAll("[data-i18n-html]").forEach((element) => {
      const key = element.getAttribute("data-i18n-html");
      if (key && dictionary[key]) element.innerHTML = dictionary[key];
    });

    document.querySelectorAll("[data-i18n-aria-label]").forEach((element) => {
      const key = element.getAttribute("data-i18n-aria-label");
      if (key && dictionary[key]) element.setAttribute("aria-label", dictionary[key]);
    });

    document.querySelectorAll("[data-i18n-alt]").forEach((element) => {
      const key = element.getAttribute("data-i18n-alt");
      if (key && dictionary[key]) element.setAttribute("alt", dictionary[key]);
    });

    document.querySelectorAll("[data-lang-set]").forEach((button) => {
      const isActive = button.getAttribute("data-lang-set") === currentLanguage;
      button.classList.toggle("is-active", isActive);
      button.setAttribute("aria-pressed", String(isActive));
    });

    wireTicketButtons();
    storeLanguage(currentLanguage);
  }

  function setupLanguageSwitcher() {
    const storedLanguage = getStoredLanguage();
    const initialLanguage = supportedLanguages.includes(storedLanguage) ? storedLanguage : "en";

    document.querySelectorAll("[data-lang-set]").forEach((button) => {
      button.addEventListener("click", () => {
        const language = button.getAttribute("data-lang-set");
        applyLanguage(language);
        closeMobileMenu();
      });
    });

    applyLanguage(initialLanguage);
  }

  function wireTicketButtons() {
    if (!config.ticketLink) return;

    document.querySelectorAll("[data-ticket-button]").forEach((button) => {
      button.setAttribute("href", config.ticketLink);
      button.setAttribute("target", "_blank");
      button.setAttribute("rel", "noopener");

      const liveKey = button.getAttribute("data-ticket-live-key");
      if (liveKey) button.textContent = translate(liveKey, "Buy Now");
    });
  }

  function wireSocialLinks() {
    const instagram = document.querySelector('[data-social="instagram"]');
    const facebook = document.querySelector('[data-social="facebook"]');

    if (instagram && config.social && config.social.instagram) instagram.href = config.social.instagram;
    if (facebook && config.social && config.social.facebook) facebook.href = config.social.facebook;
  }

  function wireContactEmail() {
    if (!config.contactEmail) return;

    document.querySelectorAll("[data-email-link]").forEach((link) => {
      link.href = `mailto:${config.contactEmail}`;
      link.textContent = config.contactEmail;
    });
  }

  function wireHotelLinks() {
    const hotelLinks = config.hotelLinks || config.accommodationLinks || {};

    document.querySelectorAll("[data-hotel-link]").forEach((button) => {
      const key = button.getAttribute("data-hotel-link");
      const url = key && hotelLinks[key];

      if (!url) return;

      button.href = url;
      button.target = "_blank";
      button.rel = "noopener";
    });
  }

  function startCountdown() {
    const countdown = document.querySelector("[data-countdown]");
    if (!countdown || !config.eventStart) return;

    const target = new Date(config.eventStart).getTime();
    const days = countdown.querySelector("[data-days]");
    const hours = countdown.querySelector("[data-hours]");
    const minutes = countdown.querySelector("[data-minutes]");
    const seconds = countdown.querySelector("[data-seconds]");

    if (!days || !hours || !minutes || !seconds || Number.isNaN(target)) return;

    const pad = (number) => String(number).padStart(2, "0");

    const tick = () => {
      const distance = target - Date.now();

      if (distance <= 0) {
        days.textContent = "00";
        hours.textContent = "00";
        minutes.textContent = "00";
        seconds.textContent = "00";
        return;
      }

      days.textContent = Math.floor(distance / (1000 * 60 * 60 * 24));
      hours.textContent = pad(Math.floor((distance / (1000 * 60 * 60)) % 24));
      minutes.textContent = pad(Math.floor((distance / (1000 * 60)) % 60));
      seconds.textContent = pad(Math.floor((distance / 1000) % 60));
    };

    tick();
    window.setInterval(tick, 1000);
  }


  function setupArtistDetails() {
    const detailElements = Array.from(document.querySelectorAll("[data-artist-details]"));
    if (!detailElements.length) return;

    const desktopQuery = window.matchMedia("(min-width: 720px)");

    const syncDetails = () => {
      detailElements.forEach((detail) => {
        detail.open = desktopQuery.matches;
      });
    };

    syncDetails();

    if (typeof desktopQuery.addEventListener === "function") {
      desktopQuery.addEventListener("change", syncDetails);
    } else if (typeof desktopQuery.addListener === "function") {
      desktopQuery.addListener(syncDetails);
    }
  }

  function setupArtistsCarousel() {
    document.querySelectorAll("[data-artists-carousel]").forEach((carousel) => {
      const track = carousel.querySelector("[data-carousel-track]");
      const previousButton = carousel.querySelector("[data-carousel-prev]");
      const nextButton = carousel.querySelector("[data-carousel-next]");

      if (!track || !previousButton || !nextButton) return;

      let frameRequested = false;

      const getMaxScrollLeft = () => Math.max(0, track.scrollWidth - track.clientWidth);

      const updateButtons = () => {
        frameRequested = false;
        const maxScrollLeft = getMaxScrollLeft();
        const isScrollable = maxScrollLeft > 2;

        carousel.classList.toggle("is-scrollable", isScrollable);
        previousButton.disabled = !isScrollable || track.scrollLeft <= 2;
        nextButton.disabled = !isScrollable || track.scrollLeft >= maxScrollLeft - 2;
      };

      const requestButtonUpdate = () => {
        if (frameRequested) return;
        frameRequested = true;
        window.requestAnimationFrame(updateButtons);
      };

      const scrollByStep = (direction) => {
        const scrollAmount = Math.max(180, Math.round(track.clientWidth * 0.72));
        track.scrollBy({ left: direction * scrollAmount, behavior: "smooth" });
      };

      previousButton.addEventListener("click", () => scrollByStep(-1));
      nextButton.addEventListener("click", () => scrollByStep(1));
      track.addEventListener("scroll", requestButtonUpdate, { passive: true });
      window.addEventListener("resize", requestButtonUpdate);

      updateButtons();
      window.setTimeout(updateButtons, 250);
    });
  }

  function setupMobileMenu() {
    const header = document.querySelector(".site-header");
    const toggle = document.querySelector(".nav-toggle");
    const menu = document.querySelector("#site-menu");

    if (!header || !toggle || !menu) return;

    const closeMenu = () => {
      header.dataset.menuOpen = "false";
      toggle.setAttribute("aria-expanded", "false");
      toggle.setAttribute("aria-label", translate("nav.open", "Open navigation"));
    };

    const openMenu = () => {
      header.dataset.menuOpen = "true";
      toggle.setAttribute("aria-expanded", "true");
      toggle.setAttribute("aria-label", translate("nav.close", "Close navigation"));
    };

    closeMobileMenu = closeMenu;
    closeMenu();

    toggle.addEventListener("click", () => {
      const isOpen = toggle.getAttribute("aria-expanded") === "true";
      if (isOpen) closeMenu(); else openMenu();
    });

    menu.querySelectorAll("a").forEach((link) => {
      link.addEventListener("click", closeMenu);
    });

    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape") closeMenu();
    });

    window.addEventListener("resize", () => {
      if (window.matchMedia("(min-width: 720px)").matches) closeMenu();
    });
  }

function setupSectionAnchorScrolling() {
  if ("scrollRestoration" in window.history) {
    window.history.scrollRestoration = "manual";
  }

  const normalizePath = (path) => path.replace(/\/index\.html$/, "/");

  const getTargetFromHash = (hash) => {
    if (!hash || hash === "#") return null;

    try {
      return document.getElementById(decodeURIComponent(hash.slice(1)));
    } catch (error) {
      return null;
    }
  };

  const getPreferredScrollTarget = (target) => {
    if (target.id === "home") return document.body;

    const section = target.classList.contains("section")
      ? target
      : target.closest(".section");

    if (section) {
      return section.querySelector(".section-kicker, .section-title") || section;
    }

    return target;
  };

  const getScrollOffset = () => {
    const header = document.querySelector(".site-header");
    const headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 0;
    const extraGap = window.matchMedia("(max-width: 719px)").matches ? 32 : 24;

    return headerHeight + extraGap;
  };

  const scrollToHash = (hash, options = {}) => {
    const target = getTargetFromHash(hash);
    if (!target) return;

    if (target.id === "home") {
      window.scrollTo({
        top: 0,
        behavior: options.smooth ? "smooth" : "auto"
      });

      if (options.updateHash && window.history.pushState) {
        window.history.pushState(null, "", hash);
      }

      return;
    }

    const preferredTarget = getPreferredScrollTarget(target);
    const y = preferredTarget.getBoundingClientRect().top + window.scrollY - getScrollOffset();

    window.scrollTo({
      top: Math.max(0, Math.round(y)),
      behavior: options.smooth ? "smooth" : "auto"
    });

    if (options.updateHash && window.history.pushState) {
      window.history.pushState(null, "", hash);
    }
  };

  document.querySelectorAll('a[href*="#"]').forEach((link) => {
    link.addEventListener(
      "click",
      (event) => {
        const rawHref = link.getAttribute("href");
        if (!rawHref || rawHref === "#") return;

        const url = new URL(rawHref, window.location.href);
        const isSamePage =
          url.origin === window.location.origin &&
          normalizePath(url.pathname) === normalizePath(window.location.pathname);

        if (!isSamePage || !url.hash) return;

        const target = getTargetFromHash(url.hash);
        if (!target) return;

        const header = document.querySelector(".site-header");
        const wasMenuOpen = header && header.dataset.menuOpen === "true";

        event.preventDefault();
        closeMobileMenu();

        window.setTimeout(() => {
          scrollToHash(url.hash, {
            smooth: true,
            updateHash: true
          });
        }, wasMenuOpen ? 360 : 30);
      },
      true
    );
  });

  const correctInitialHashScroll = () => {
    if (!window.location.hash) return;

    [0, 160, 420, 900].forEach((delay) => {
      window.setTimeout(() => {
        scrollToHash(window.location.hash, {
          smooth: false,
          updateHash: false
        });
      }, delay);
    });
  };

  correctInitialHashScroll();
  window.addEventListener("load", correctInitialHashScroll, { once: true });
}

  setupMobileMenu();
  setupSectionAnchorScrolling();
  setupLanguageSwitcher();
  setupArtistDetails();
  setupArtistsCarousel();
  wireSocialLinks();
  wireContactEmail();
  wireHotelLinks();
  startCountdown();
})();
