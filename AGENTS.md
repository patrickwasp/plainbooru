# AGENTS.md

## Project Overview

plainbooru is a minimalist booru-style photo/video archive.

Core principles:

- server-rendered HTML
- zero JavaScript shipped to the browser
- CSS/HTML only on the client
- simple PHP backend
- Twig templates
- Basecoat UI for styling/components
- SQLite database
- media files stored on disk

## Tech Stack

- PHP 8.5+
- Slim 4
- Twig
- SQLite
- Basecoat UI
- Tailwind/Basecoat build step allowed
- final browser artifact must be HTML + CSS only

## Hard Rules

- Do not add client-side JavaScript.
- Do not add Alpine, React, Vue, HTMX, Stimulus, jQuery, or inline scripts.
- Do not rely on JS for navigation, forms, upload flow, search, filtering, or UI
  state.
- All interactions must work with normal links and HTML forms.
- Build-time tooling is allowed, but runtime/browser JS is forbidden.

## UI Rules

- Use Twig for all rendered pages.
- Use Basecoat classes/components where possible.
- Keep UI responsive and mobile-first.
- Prefer semantic HTML: `header`, `nav`, `main`, `section`, `article`, `form`,
  `button`.
- Use plain anchors and forms for all actions.
- Pagination, search, tag browsing, and pool browsing must be no-JS.

## Backend Rules

- Keep architecture simple and explicit.
- Prefer small services over heavy abstractions.
- Use SQLite for metadata and relations.
- Store original uploads and thumbnails/posters on disk, not in SQLite blobs.
- Keep routes RESTful and predictable.
- Return HTML for page routes and JSON for API routes.

## Domain Concepts

- **media**: image or video item
- **tags**: normalized labels for search/filtering
- **pools**: ordered collections of media

## Coding Style

- Keep code readable and boring.
- Avoid unnecessary patterns or framework-like indirection.
- Prefer clear names and small files.
- Add comments only where they clarify non-obvious intent.

## Decision Guideline

When in doubt, choose:

1. simpler
2. more server-rendered
3. more semantic HTML
4. less magic
5. no JS
