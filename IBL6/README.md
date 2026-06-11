# IBL6 — SvelteKit Frontend

IBL6 is the SvelteKit frontend for the IBL basketball league, under early
development and deployed at `ibl6.iblhoops.net`. It consumes the REST API
served by the IBL5 PHP backend (see `ibl5/docs/API_GUIDE.md`); pages are being
migrated from PHP-rendered IBL5 to SvelteKit equivalents as they mature.

## Relationship to IBL5

- **IBL5** (`ibl5/`): PHP backend + legacy server-rendered UI. Owns the
  database and exposes the REST API.
- **IBL6** (`IBL6/`): SvelteKit client. Talks to the IBL5 REST API for all
  data; it does not access the database directly in production.

See `ibl5/docs/STRATEGIC_PRIORITIES.md` for the migration roadmap.

## Developing

Install dependencies, then start the dev server:

```sh
npm install
npm run dev
```

## Building

```sh
npm run build      # production build (vite build)
npm run preview    # preview the production build locally
```

## Quality gates

```sh
npm run check      # svelte-check type checking
npm run lint       # prettier --check + eslint
npm run format     # prettier --write
npm run test:unit  # vitest unit tests
npm run test:e2e   # playwright end-to-end tests
```

## Configuration

Local environment variables live in `IBL6/.env` (gitignored secrets — never
commit credentials). Copy from the team's shared template when onboarding.
