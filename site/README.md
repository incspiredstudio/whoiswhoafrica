# site/

The WordPress website code for WHOISWHO. See `docs/architecture.md` and `docs/build/` for the design, and the GitHub issues (milestones M0 to M5) for the plan.

## Install

```
composer install
npm ci
```

Copy `.env.example` to the secrets file yourself (agents never read `.env*`). Local environment: see issue M0-INF-02.

## Commands

| Task | Command |
|---|---|
| Tests | `composer test` (one test: `composer test -- --filter REQ_208`; architecture: `composer test -- --testsuite Arch`) |
| Style | `composer lint` |
| Static analysis | `composer analyse` |
| Dependency audit | `composer audit` and `npm audit --audit-level=high` |
| Build assets | `npm run build` |
| End to end | `npx playwright test` |

## Who owns what

| Area | Paths |
|---|---|
| plugin | `wp-content/plugins/whoiswho-core/**`, `wp-content/mu-plugins/**`, `bin/`, `tests/Unit`, `tests/Integration`, `tests/Arch` |
| theme | `wp-content/themes/whoiswho/**`, `wp-content/themes/whoiswho-child/**`, assets, `tests/e2e` UI specs |
| infra | `deploy/`, `composer.json`, `composer.lock`, `package.json`, wp-config template, `.env.example` |
| qa | `tests/e2e`, `tests/Visibility`, `tests/Security` |
