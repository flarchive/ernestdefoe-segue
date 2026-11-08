# Segue

Make your forum feel like an app. Segue does two things:

- **A loading screen instead of the flash.** When someone opens or refreshes
  the forum, the page normally appears in pieces: the header, a "Loading..."
  line, then everything else. Segue covers that with your forum's background
  and a loading icon, and fades it away once the page is ready. A fast load is
  just a fade; the icon only shows if loading takes more than a moment.
- **Page transitions.** Moving between pages, the header stays still and the
  new page fades in as it rises. Open a discussion and its title glides out of
  the list into the page heading while the starter's avatar moves into the
  first post. Press Back and it plays in reverse. Tags glide from the tags page
  into the tag's heading the same way.

Inspired by the "native page transitions" technique shared on the XenForo
community, rebuilt for Flarum: Flarum moves between pages without reloading,
so Segue runs the browser's View Transitions for each move itself.

## Loading icons

Pick one in the admin panel; each choice shows a live preview.

- **Ring, Dots, Bars, Pulse, Orbit:** drawn in CSS, in your forum's primary
  color or one you choose, in three sizes.
- **Your logo:** your forum's logo, gently breathing (the dark-mode logo in
  dark mode).
- **Your own:** upload GIF, PNG, WebP or JPEG images (up to 2 MB, 24 icons).
  Animated GIFs and WebPs keep moving.

## Settings

| Setting | What it does |
| --- | --- |
| Show a loading screen | On by default. |
| Loading icon, size, color | See above. |
| Page transitions | Glide (default), Fade, or Off. |
| Transition speed | Fast, Normal, Slow. |
| Smooth full page loads | Cross-fades full page loads inside the forum (after signing in, for example) instead of blanking first. |

## Good to know

- **Browsers.** Transitions play in browsers with View Transitions (current
  Chrome, Edge, Opera, Samsung Internet and Safari). Other browsers open pages
  exactly as before.
- **Reduced motion.** Members who ask their device for less motion get no
  transitions, and the loading icons stand still.
- **Nothing to wait for.** The loading screen is drawn by CSS before any
  JavaScript runs and comes down as soon as the page has drawn under it. If
  the forum's JavaScript ever fails, it lifts by itself after 8 seconds so the
  error message can be read. Visitors without JavaScript never see it.
- **No extra requests.** Settings ride along in the page; nothing is fetched.
- **Themes.** Colors come from your theme's CSS custom properties, so light,
  dark and custom themes all match.

## Installation

```bash
composer require ernestdefoe/segue
php flarum cache:clear
```

## Updating

```bash
composer update ernestdefoe/segue
php flarum cache:clear
```

## Support

Questions and bug reports: [ernestdefoe.online](https://ernestdefoe.online).

☕ If Segue is useful to you, you can [buy me a coffee](https://buymeacoffee.com/ernestdefoe).
