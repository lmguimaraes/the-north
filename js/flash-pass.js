(function () {
  "use strict";

  const offer = document.querySelector("[data-flash-pass]");
  if (!offer) return;

  const config = window.TNF_2027_FLASH_PASS || {};
  const cta = offer.querySelector("[data-flash-pass-cta]");
  const hours = offer.querySelector("[data-flash-pass-hours]");
  const minutes = offer.querySelector("[data-flash-pass-minutes]");
  const seconds = offer.querySelector("[data-flash-pass-seconds]");

  if (!cta || !hours || !minutes || !seconds) return;

  const HOUR = 60 * 60 * 1000;
  const durationHours = Number(config.durationHours);
  const duration = Number.isFinite(durationHours) && durationHours > 0
    ? durationHours * HOUR
    : 24 * HOUR;
  const start = new Date(config.startsAt).getTime();
  const end = start + duration;
  const manualPreview = config.showManually === true;
  const timedRelease = config.useTimedWindow === true;
  const purchaseUrl = typeof config.purchaseUrl === "string" ? config.purchaseUrl.trim() : "";
  const hasCheckout = /^https?:\/\//i.test(purchaseUrl);
  const previewEnd = Date.now() + duration;
  let missingLinkWarningShown = false;

  const pad = (value) => String(Math.max(0, value)).padStart(2, "0");

  function wireCheckout() {
    if (hasCheckout) {
      cta.href = purchaseUrl;
      cta.target = "_blank";
      cta.rel = "noopener noreferrer";
      cta.classList.remove("is-disabled");
      cta.removeAttribute("aria-disabled");
      return;
    }

    cta.removeAttribute("href");
    cta.removeAttribute("target");
    cta.removeAttribute("rel");
    cta.classList.add("is-disabled");
    cta.setAttribute("aria-disabled", "true");
  }

  function updateTimer(distance) {
    const remaining = Math.max(0, distance);
    const totalHours = Math.floor(remaining / HOUR);
    const remainingMinutes = Math.floor((remaining / (60 * 1000)) % 60);
    const remainingSeconds = Math.floor((remaining / 1000) % 60);

    hours.textContent = pad(totalHours);
    minutes.textContent = pad(remainingMinutes);
    seconds.textContent = pad(remainingSeconds);
  }

  function tick() {
    const now = Date.now();
    const hasValidWindow = Number.isFinite(start) && Number.isFinite(end);
    const timedWindowIsOpen = timedRelease && hasValidWindow && now >= start && now < end;
    const shouldShow = manualPreview || (timedWindowIsOpen && hasCheckout);

    offer.hidden = !shouldShow;

    if (!shouldShow) {
      if (timedWindowIsOpen && !hasCheckout && !missingLinkWarningShown) {
        missingLinkWarningShown = true;
        console.warn(
          "2027 Full Pass offer is inside its timed window, but purchaseUrl is empty or invalid in js/flash-pass.config.js."
        );
      }
      return;
    }

    const countdownEnd = timedWindowIsOpen ? end : previewEnd;
    updateTimer(countdownEnd - now);
  }

  wireCheckout();
  tick();
  window.setInterval(tick, 1000);
})();
