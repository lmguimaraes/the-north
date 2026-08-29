// 2027 Full Pass: quick launch controls.
//
// MANUAL PREVIEW:
//   Set showManually to true to display the offer immediately for a client preview.
//   Set it back to false before publishing the timed release.
//
// TIMED RELEASE:
//   Keep useTimedWindow true and set startsAt to the exact launch time.
//   The ISO timezone offset is important. August in Montreal is EDT (UTC-4).
//
// CHECKOUT:
//   Paste the direct 2027 Full Pass Stripe/payment URL into purchaseUrl.
window.TNF_2027_FLASH_PASS = {
  showManually: true,
  useTimedWindow: true,

  startsAt: "2026-08-29T10:00:00-04:00",
  durationHours: 24,

  purchaseUrl: ""
};
