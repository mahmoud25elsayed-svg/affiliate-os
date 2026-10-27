# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
