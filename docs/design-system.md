# Design system

The web interface uses Tailwind CSS v4 (standalone CLI, no Node.js) with a closed set of tokens
and a few Twig components. The living style guide is at https://localhost/design-system.

## Tokens

The tokens are defined once, in the `@theme` block of `assets/styles/app.css`. Tailwind's default
palette is removed (`--color-*: initial`), so `bg-red-500` produces nothing.

| Token | Utilities | Use |
|---|---|---|
| `brand-50` … `brand-900` | `bg-brand-600`, `text-brand-700`… | blue `#2980b9` (600): actions, links, focus |
| `accent-50` … `accent-700` | `bg-accent-500`, `text-accent-700`… | orange `#e67e22` (500): highlights, late-arrival badges |
| `ink`, `ink-muted` | `text-ink`, `text-ink-muted` | text |
| `canvas`, `surface`, `surface-muted`, `line` | `bg-canvas`, `bg-surface`, `ring-line`… | page, cards, borders |
| `success-*`, `danger-*`, `info-*` | `bg-danger-50`, `text-danger-700`… | feedback |
| `rounded-control`, `rounded-card` | | radii of controls and cards |
| `shadow-card` | | elevation of cards |

## Components

| Component | Props | Notes |
|---|---|---|
| `<twig:Button>` | `variant`: primary, secondary, danger, ghost, accent; `size`: sm, md, lg; `tag`: button, a | pass `type`, `href`, `class` as attributes |
| `<twig:ButtonGroup>` | | Flowbite-like group: joined buttons sharing their borders. Without a primary action: one row. With one (`data-primary`, first): the buttons are stacked, full width, the primary action on top. Toggles carry `aria-pressed`. Add `button-group-sm` for a compact group (tables) |
| `<twig:Icon>` | `name`: ticket, check, eye-slash, link, film, sun, moon, chevron-down, ellipsis-vertical, map-pin, clock, language, arrow-path, code-bracket, server-stack, shield-check | inline outline SVG, decorative (`aria-hidden`) |
| `<twig:Logo>` | `wordmark`: true (default) or false | the ScreenRoute symbol, a route in S from a start ring to a black and orange film reel, in the brand colours whatever the theme; the app icon and favicon are `assets/images/logo.svg` (same symbol, white on a blue tile) |
| `<twig:Dropdown>` | `label`; `align`: start, end; `compact` (a "⋮" button) | a `<details>` menu, closed by the `dropdown` Stimulus controller on a click outside or Escape; its links and buttons are dressed as menu items |
| `<twig:Pagination>` | `page`, `pages`, `route`, `query` | GET links that keep the page in the URL; nothing when there is one page |
| `<twig:Card>` | | a `<section>` |
| `<twig:Alert>` | `type`: success, error, info | renders the `flash flash-{type}` hooks |
| `<twig:Badge>` | `tone`: neutral, brand, warning (accent orange) | |

Themes: the tokens are redefined for the dark theme on any element carrying `data-theme="dark"`
(`<html>` follows the setting of the user; "auto" follows the system). The style guide renders
every component in both themes, side by side.

Every form gets the theme `templates/form/theme.html.twig` automatically. The city autocomplete (Tom Select) is dressed like the
other fields by unlayered rules at the end of `assets/styles/app.css` (its own stylesheet is unlayered too).

## Rules

- Templates use the components and the token utilities only. A new color, radius or shadow is a
  new token: add it to `@theme` and to this page.
- Classes such as `flash-error`, `programme` or `plan-message` are hooks for tests and scripts.
  Never style them.
- Every user-facing string still goes through `|trans`. The style guide is a developer page, in
  English only.

## Commands

- `make css` builds once; `make css-watch` rebuilds on every change.
- The production image builds and minifies the CSS (`tailwind:build --minify`).
