# The North Latin Festival landing page

Mobile-first static landing page for The North Latin Festival.

## Current structure

- `index.html` - home page markup with the hero, overview, artists preview, Launch Pass cards, and closing/contact sections
- `artists.html` - artist lineup page with mobile-first expandable artist bios
- `competition.html` - competition overview, categories, registration details, and links to rules and prizes
- `prizes.html` - bilingual competition prizes and awards page with international and national partner opportunities, cash prizes, awards, and terms
- `rules.html` - bilingual competition rules launcher for the supplied English and French PDFs
- `css/styles.css` - mobile-first visual system, responsive layout, hero/closing backdrop treatment, pass-card styling, artist cards, prizes and awards components, contact strip, and language toggle
- `js/site.config.js` - quick-edit settings for event date, ticket link, email, and social links
- `js/i18n.js` - English and French copy used by the language switcher, including the artist and prizes pages
- `js/main.js` - countdown, mobile menu, language switching, artist bio behavior, ticket-link wiring, social-link wiring, and email wiring
- `assets/` - optimized images for the site
- `assets/artists/` - optimized WebP artist artwork for Megan Lapointe, Erick Morales, and Brayian & Elana

## Local preview

Install nothing. This project includes a tiny Node server.

```bash
npm run dev
```

Open:

```text
http://127.0.0.1:3000
```

To share with people on the same Wi-Fi/network:

```bash
npm run dev:network
```

Then open the local network URL shown in the terminal from another device on the same network.

To use a different port:

```bash
node server.js --port 3001
```

## GoDaddy upload

For GoDaddy Web Hosting / cPanel, upload this update ZIP into `/public_html` and extract it there over the current site.

After extraction, the structure should include:

```text
/public_html/index.html
/public_html/artists.html
/public_html/competition.html
/public_html/prizes.html
/public_html/rules.html
/public_html/assets/artists/
/public_html/css/
/public_html/js/
```

Back up the live site before extracting an update over `/public_html`. This full project includes the existing assets and `js/site.config.js`; preserve any live configuration values that differ from the project copy.

## Quick edits

Most launch settings are in:

```text
js/site.config.js
```

When tickets go live, set:

```js
ticketLink: "https://your-ticket-platform.com/the-north-latin-festival"
```

The official contact and social links are also in `js/site.config.js`:

```js
contactEmail: "info@thenorthlatinfestival.ca"

social: {
  instagram: "https://www.instagram.com/thenorthlatinfestival/",
  facebook: "https://www.facebook.com/thenorthlatinfestival"
}
```

To edit English or French page copy, update:

```text
js/i18n.js
```

The language switcher stores the visitor's last selected language in the browser, so returning visitors keep their preference.

The Competition and Prizes & Awards navigation items are active across the main site pages.

## Mobile-first notes

The base CSS is optimized for mobile screens first, then scales up with media queries for tablet and desktop.

The hero and closing artwork are used as full-section backdrops with gradient fades rather than image frames. The mobile hero uses the full horizontal festival banner so the complete artwork is visible while still blending into the off-white page background. On tablet and desktop, the wide artwork is shown without aggressive cropping so the banner reads properly.

## Pass cards

The pass cards are styled to resemble the provided pass examples: white card, icon at the top, title, divider, and icon-based benefit list. The current pass cards are:

- Launch Pass - Available until May 31
- Full Pass - Live the full experience
- Dancer Pass - Be part of the show

The "More passes coming soon" message appears as a note below the cards.

## Prizes & awards page

The `prizes.html` page follows the existing site header, hero, card, color, and footer system without using promotional flyer images. It includes:

- International Alliance prizes for Open Solo Showcase and Open Couple Showcase
- National Alliance prizes for Amateur Couple Salsa & Bachata Showcase
- Cash prizes
- The Rising Star of The North and The North Top School awards
- Prize terms and a direct link to the Competition Rules & Guidelines

All English and French page copy is stored in `js/i18n.js`. The Prizes & Awards navigation tab appears across the home, artists, competition, and prizes pages.

## Artists page

The home page now has a compact artists preview section that links to `artists.html`.

On mobile, the artist page shows each artist photo, country, and name first. The biography text is collapsed under each card and can be expanded with the bio button. On desktop, bios open automatically so the page reads like a polished lineup page.

Current artists:

- Megan Lapointe - Canada 🇨🇦
- Erick “El Terrible” Morales - Mexico 🇲🇽
- Brayian & Elana - Colombia 🇨🇴 & USA 🇺🇸

## Version 11 updates

- Added the official Instagram link: `https://www.instagram.com/thenorthlatinfestival/`
- Added the official Facebook link: `https://www.facebook.com/thenorthlatinfestival`
- Added the contact email: `info@thenorthlatinfestival.ca`
- Added a minimal contact strip in the closing section and the same email in the footer.
- Added English/French copy and an EN/FR language switcher in the header.
- Language preference is remembered with local storage.

## Version 13 updates

- Replaced the "More Passes" card with the Launch Pass card.
- Reordered cards to Launch Pass, Full Pass, Dancer Pass.
- Added a stronger Buy Now style for the Launch Pass button.
- Updated English/French i18n copy for Launch Pass and the new more-passes note.

## Version 14 updates

- Added `artists.html` as a new static page that fits the existing GoDaddy file structure.
- Added an Artists nav link and a compact artists preview section on the home page.
- Added mobile-first expandable artist bios with desktop auto-open behavior.
- Added optimized WebP artist images under `assets/artists/`.
- Added English/French i18n copy for all artist names, countries, bios, alt text, page meta, and buttons.

## Prizes & Awards update

- Added `prizes.html` with all supplied English and French prizes, awards, and terms content.
- Added a Prizes & Awards tab to the shared navigation.
- Added responsive alliance, partner-prize, cash-prize, award, and terms components using the existing navy, cyan, aqua, flame, and ice visual system.
- Updated the Competition page’s awards section with a direct link to the new page.
- Added bilingual metadata and accessibility labels for the new page.

