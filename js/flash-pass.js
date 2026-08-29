(() => {
  "use strict";

  /*
   * FLASH PASS SALE WINDOW
   *
   * Saturday, August 29, 2026 at 10:00 PM Eastern Time
   * through
   * Sunday, August 30, 2026 at 11:59:59 PM Eastern Time
   *
   * Eastern Time is EDT / UTC-4 on these dates.
   *
   * END_AT is midnight immediately after Sunday and is exclusive,
   * which gives an exact 26-hour sale window.
   */
  const START_AT = Date.parse("2026-08-29T22:00:00-04:00");
  const END_AT = Date.parse("2026-08-31T00:00:00-04:00");

  const CHECKOUT_URL =
    "https://buy.stripe.com/6oU7sK12g1Lc8Ok81983C00";

  const offer = document.querySelector("[data-flash-pass]");

  if (!offer) {
    return;
  }

  const cta = offer.querySelector("[data-flash-pass-cta]");
  const hoursEl = offer.querySelector("[data-flash-pass-hours]");
  const minutesEl = offer.querySelector("[data-flash-pass-minutes]");
  const secondsEl = offer.querySelector("[data-flash-pass-seconds]");

  const badgeEl = offer.querySelector(".flash-pass-badge");
  const copyEl = offer.querySelector(
    '[data-i18n="flashPass.copy"]'
  );

  /*
   * Configure Stripe checkout.
   */
  if (cta) {
    cta.href = CHECKOUT_URL;
    cta.target = "_blank";
    cta.rel = "noopener noreferrer";
  }

  /*
   * The existing HTML currently says "Available for 24 hours".
   * This changes it to the correct 26-hour wording.
   *
   * It also keeps the text correct when switching EN / FR.
   */
  function updateWindowCopy() {
    const isFrench = (
      document.documentElement.lang || ""
    )
      .toLowerCase()
      .startsWith("fr");

    if (badgeEl) {
      badgeEl.removeAttribute("data-i18n");

      badgeEl.textContent = isFrench
        ? "Disponible pendant 26 heures"
        : "Available for 26 hours";
    }

    if (copyEl) {
      copyEl.removeAttribute("data-i18n");

      copyEl.textContent = isFrench
        ? "Disponible du samedi 22 h au dimanche 23 h 59, heure de l’Est."
        : "Available from Saturday 10:00 PM until Sunday 11:59 PM Eastern Time.";
    }
  }

  updateWindowCopy();

  /*
   * Watch the site's existing EN / FR switcher.
   */
  const languageObserver = new MutationObserver(
    updateWindowCopy
  );

  languageObserver.observe(
    document.documentElement,
    {
      attributes: true,
      attributeFilter: ["lang"]
    }
  );

  /*
   * Prefer the hosting server's clock.
   *
   * This reduces the chance that somebody with an incorrectly
   * configured computer clock sees the offer too early or too late.
   *
   * If server time can't be read, browser time is used instead.
   */
  let clockOffsetMs = 0;

  function nowMs() {
    return Date.now() + clockOffsetMs;
  }

  async function syncClock() {
    if (
      location.protocol !== "http:" &&
      location.protocol !== "https:"
    ) {
      return;
    }

    try {
      const url = new URL(location.href);

      url.hash = "";

      /*
       * Cache-busting parameter so we're not reading the time
       * from a stale cached response.
       */
      url.searchParams.set(
        "_flashPassTime",
        String(Date.now())
      );

      const response = await fetch(
        url.toString(),
        {
          method: "HEAD",
          cache: "no-store",
          credentials: "same-origin"
        }
      );

      const serverDate =
        response.headers.get("date");

      const serverTime = serverDate
        ? Date.parse(serverDate)
        : NaN;

      if (Number.isFinite(serverTime)) {
        clockOffsetMs =
          serverTime - Date.now();
      }
    } catch (_) {
      /*
       * Safe fallback:
       * use the visitor's browser clock.
       */
    }
  }

  function setTime(element, value) {
    if (!element) {
      return;
    }

    element.textContent = String(value)
      .padStart(2, "0");
  }

  function render() {
    const now = nowMs();

    /*
     * ACTIVE:
     * 2026-08-29 22:00:00 EDT
     * <= now <
     * 2026-08-31 00:00:00 EDT
     */
    const isActive =
      now >= START_AT &&
      now < END_AT;

    /*
     * Your existing HTML already uses the hidden attribute.
     * We simply turn it on/off.
     */
    offer.hidden = !isActive;

    if (!isActive) {
      return;
    }

    /*
     * Countdown uses TOTAL remaining hours.
     *
     * Therefore the sale begins at:
     * 26:00:00
     *
     * rather than wrapping around to 02:00:00.
     */
    const remainingSeconds =
      Math.max(
        0,
        Math.ceil(
          (END_AT - now) / 1000
        )
      );

    const hours =
      Math.floor(
        remainingSeconds / 3600
      );

    const minutes =
      Math.floor(
        (remainingSeconds % 3600) / 60
      );

    const seconds =
      remainingSeconds % 60;

    setTime(hoursEl, hours);
    setTime(minutesEl, minutes);
    setTime(secondsEl, seconds);
  }

  /*
   * Render immediately.
   */
  render();

  /*
   * Then synchronize against server time
   * and render again.
   */
  syncClock().finally(render);

  /*
   * Four checks per second means the card appears/disappears
   * very close to the exact boundary rather than potentially
   * being a full second late.
   */
  window.setInterval(
    render,
    250
  );

  /*
   * Re-sync server time periodically in case the page remains
   * open for many hours.
   */
  window.setInterval(
    () => {
      syncClock().finally(render);
    },
    5 * 60 * 1000
  );
})();
