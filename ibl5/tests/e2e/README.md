# E2E Tests (Playwright)

Browser-based end-to-end tests for IBL5. These automate functional verification of key user flows so manual testing can focus on visual/UX review.

## Prerequisites

- **MAMP running** with IBL5 accessible at `http://localhost/ibl5/`
- **Test user credentials** — a valid IBL account for authenticated tests
- **CAPTCHA disabled** — login CAPTCHA must be off in your local `config.php` (`$gfx_chk = 0`)

## Setup

```bash
cd ibl5

# Install dependencies (includes Playwright)
bun install

# Install Chromium browser
bunx playwright install chromium

# Configure test credentials
cp .env.test.example .env.test
# Edit .env.test with your IBL username and password
```

## Running Tests

```bash
# Run all tests (headless)
bun run test:e2e

# Run with visible browser
bun run test:e2e:headed

# Interactive UI mode (pick & debug tests)
bun run test:e2e:ui

# Run specific test file
bunx playwright test tests/e2e/smoke/public-pages.spec.ts
```

## Test Structure

```
tests/e2e/
├── auth.setup.ts              # Login once, save browser state
├── fixtures/
│   └── auth.ts                # Auth fixture for authenticated tests
├── smoke/
│   ├── public-pages.spec.ts   # Public pages load without PHP errors
│   └── auth-pages.spec.ts     # Protected pages load when logged in
├── flows/
│   └── trading.spec.ts        # Interactive trading flow tests
└── README.md
```

### Test Categories

- **Smoke tests** (`smoke/`) — verify pages load and render key elements. Fast, broad coverage.
- **Flow tests** (`flows/`) — test multi-step user interactions (e.g., selecting trade partners, checking players).

### Authentication

The `setup` project in `playwright.config.ts` runs `auth.setup.ts` first, which logs in and saves browser state to `playwright/.auth/user.json`. All other tests reuse this state — no per-test login overhead.

Tests that need authentication import from `./fixtures/auth.ts`. Tests for public pages use `test.use({ storageState: { cookies: [], origins: [] } })` to run unauthenticated.

## Adding New Tests

1. **Public page test** — add to `smoke/public-pages.spec.ts` or create a new file in `smoke/`
2. **Authenticated page test** — import `{ test, expect }` from `../fixtures/auth` and add to `smoke/auth-pages.spec.ts`
3. **Interactive flow test** — create a new `.spec.ts` in `flows/`

## Troubleshooting

### CAPTCHA blocking login
Set `$gfx_chk = 0` in your local `ibl5/config.php`. Login CAPTCHA only triggers when `$gfx_chk` is 2, 4, 5, or 7.

### Trading tests skipped
Trading flow tests auto-skip when the season phase has trades closed. This is expected — they'll run when trading reopens.

### Different base URL
Set the `BASE_URL` environment variable:
```bash
BASE_URL=http://localhost:9090/ibl5/ bun run test:e2e
```

### Viewing test reports
After a run, open the HTML report:
```bash
bunx playwright show-report
```
