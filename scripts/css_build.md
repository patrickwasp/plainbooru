# Building app.css (Tailwind + Basecoat)

## Prerequisites

Node.js 18+ and npm.

## Initial setup

```bash
npm install
```

## Build CSS

```bash
npm run build:css
```

This runs:
```
npx @tailwindcss/cli -i ./assets/app.src.css -o ./public/assets/app.css --minify
```

The input file (`assets/app.src.css`) imports both Tailwind v4 and basecoat-css:

```css
@import "tailwindcss";
@source "../templates/**/*.php";
@source "../src/**/*.php";
@import "basecoat-css";
```

The `@source` directives tell Tailwind to scan the PHP templates and source files
for utility class names so they are included in the output CSS.

## Watch mode (during development)

```bash
npm run watch:css
```

## Output

The compiled CSS is written to `public/assets/app.css` and is committed to the
repository so that production deployments have no Node.js dependency.

## Basecoat components used

See https://basecoatui.com/ for the full component reference.

Components used in this project (all CSS-only, no JS):
- `btn`, `btn-primary`, `btn-sm`, `btn-sm-primary`, `btn-sm-outline`, `btn-outline`, `btn-ghost`
- `card`
- `input`, `textarea`
- `badge`, `badge-outline`
- `alert`, `alert-destructive`
- `table`
