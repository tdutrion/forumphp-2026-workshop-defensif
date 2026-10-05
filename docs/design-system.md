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
| `<twig:Button>` | `variant`: primary, secondary, danger, ghost, accent; `size`: sm, md; `tag`: button, a | pass `type`, `href`, `class` as attributes |
| `<twig:ButtonGroup>` | | Flowbite-like group: joined buttons sharing their borders. Without a primary action: one row. With one (`data-primary`, first): it takes the first row, the others share the second row. Toggles carry `aria-pressed` |
| `<twig:Icon>` | `name`: ticket, check, eye-slash | inline outline SVG, decorative (`aria-hidden`) |
| `<twig:Card>` | | a `<section>` |
| `<twig:Alert>` | `type`: success, error, info | renders the `flash flash-{type}` hooks |
| `<twig:Badge>` | `tone`: neutral, brand, warning (accent orange) | |

Every form gets the theme `templates/form/theme.html.twig` automatically.

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
