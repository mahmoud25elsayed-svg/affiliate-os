# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security
- Fake payment gateway (public webhook secret) is only registered in `local`/`testing`; forged "paid" callbacks were accepted before.
- Courier webhooks fail closed when the courier account has no webhook secret (anyone with a tracking number could mark an order delivered and accrue commission), and are exempt from session CSRF so real courier callbacks no longer get 419.
- `tenant.user` middleware no longer adopts users without a tenant into whichever store they visit; `ProvisionTenant` never grants ownership to an account of another tenant.
- OTP: merchant onboarding SMS was never sent (broken DTO call swallowed by `catch`), customer OTPs were logged in plaintext and never sent; both now go through SMS, codes are redacted from `notification_logs`, and affiliate phone/payout OTPs are burned after 5 wrong guesses. Affiliate auth and payout-OTP routes are throttled.
- `/health` token has no hard-coded default and is accepted only via header, never the query string.
- JSON-LD and merchant custom CSS can no longer break out of their `<script>`/`<style>` tags.
- Stock Lunar hub (`/lunar`, not tenant-aware) returns 404 unless `LUNAR_ADMIN_PANEL=true`; `TenantSeeder` no longer creates a shared `admin@tenant.local / password` account.

- Platform 2FA challenge and setup confirmation are throttled (5/min) against code brute-forcing.

### Fixed
- Browser-driven end-to-end pass (Playwright on every panel + affiliate link → COD purchase → fulfilment → commission): merchant panel 500s on Lunar-backed resources, unstyled panel after login, CSP blocking Alpine, add-to-cart never reaching the cart (Livewire without tenancy), tracking-link price charged but not shown, stock never reservable (supplier receipts bypassed warehouse balances), stale order status actions, 14 merchant tools unreachable from the panel, partner app 500/419s, broken links. See commit 6e72cfa for details.
- Merchant prices are entered in pounds; inventory has "receive stock" and "stock count" actions; products accept opening stock; checkout pre-fills logged-in customers; partner order form shows the server-side price range (commission cap); store and platform dashboards show real KPIs.
- Platform 2FA challenge rejected every recovery code (it only accepted 6 digits and never checked them); recovery codes are now accepted once, case-insensitively.
- Test suite ran against (and wiped) the dev `database.sqlite`; it now uses in-memory SQLite with `RefreshDatabase` for every test.
- `/search` without a query returned 500; store staff were redirected to the central login instead of `/store/login`.
- Backup/restore of SQLite (invalid file name, non-replayable dump, wrong quote escaping); `perf:seed-tenants` created tenant-less channels.
- Payment webhook tenant lookup queried a non-existent column; demo orders used an invalid `placed` status.
- Tracking-link custom prices: one shared validator for portal and PWA (integer money, honours per-product min/max, no invented wholesale price).
- All PHPStan level 8 errors (44), Pint violations and TypeScript `any` warnings.

### Added
- **SETUP-Bootstrap (P02):**
  - Production Laravel 12 project structure with Clean-room architecture.
  - Multi-tenancy setup with `stancl/tenancy` v3.10 and Lunar v1.5 headless ecommerce engine.
  - Filament v4 admin panel configured with Arabic default locale and RTL support.
  - Inertia.js v3 + React + TypeScript + Tailwind CSS storefront foundation with Cairo Arabic typography.
  - Quality gates: Laravel Pint preset, Larastan level 8, Pest PHP with Arch testing plugin.
  - Docker Compose environment (PHP 8.3 FPM, Nginx, MySQL 8, Redis, Mailpit) and `Makefile`.
  - GitHub Actions CI workflow covering Lint, Static Analysis, Tests (with MySQL 8 & Redis services), and Frontend Build.
  - Horizon queue management, scheduler configuration, and JSON logging channel.
  - Domain architecture structure under `app/Domain/` and `app/Support/`.
  - Architectural constraints tested via Pest Arch tests.

- **Spike Base Platform (P01):**
  - ADR-010 confirming Lunar + Filament + stancl/tenancy feasibility on Laravel 12.
  - Tenant database isolation benchmarks (~5.65s provisioning).
