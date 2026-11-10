# Testing and CI

How work is tested and what must pass before a change merges. Tests are written first, and each test names the requirement it proves.

Architecture: section 7. Record: ADR-012. Rules: `CLAUDE.md`.

## Principles

- **Tests first.** Write the test, then the code.
- **Name the requirement.** Test names cite REQs, for example `test_REQ_208_hides_unreviewed_profiles_from_sitemap`. To run one test: `composer test -- --filter REQ_208`.
- **A separate reviewer marks work done.** The author never does.
- **Every pull request cites REQ IDs** and says `Closes #N`, with test evidence.

## What is tested, by kind of requirement

| Area | Approach |
|---|---|
| Visibility and publication (REQ-208, 209, 210, 216 to 218, 193, 147, 207) | A matrix: one profile per state, requested on every surface, asserting response, noindex header and label. A faked CDN client for purge calls and the 410 path. State-transition tests for illegal moves. A latency check on the live host |
| Authorisation (REQ-083, 235, 240, 244) | Role and capability matrix of 200 and 403 results. Granted and revoked capability cases. Menu tests. Architecture tests for module boundaries |
| Import (REQ-092, 132 to 148, 161, 212, 214, 221, 237) | Contract fixtures; the worked example's hash as a test vector; dry-run diff; allowlist and forbidden-field refusal; provenance required; suppression re-check; idempotent re-import; undo; a 1,000-record chunked run |
| Business rules (REQ-022, 023, 112, 179 to 184) | Unit and property tests for the counting rule, money and label separation |
| Forms and links (REQ-018, 081, 122, 196, 232, 233) | Validation, throttling, a fake bot challenge, single-use links, a scanner-style `GET` that must not consume a link, expiry |
| Awards (REQ-030 to 038, 196 to 202) | Stage changes are audited with the approver; the flag on and off; no commercial data reachable |
| Privacy (REQ-057 to 072) | The data-request workflow, the retention job, completeness of the personal-data catalogue |
| UI, accessibility, performance (REQ-040 to 043, 055) | Playwright end-to-end on Chromium and a WebKit mobile profile; axe on key pages; a Lighthouse budget for LCP and CLS on the local build |
| Security (REQ-046) | Static analysis, dependency audits, secret scan, a header test, and a test that no registration or login route is exposed to visitors |
| Theme (REQ-052, 190) | A check that fails if brand colours or fonts appear outside the tokens file |

## Architecture tests

Architecture tests keep the module rules true as code grows:

- `Awards` does not reference `Commerce`.
- Commercial code and the Sponsored label never write a profile mark.
- Only the state machine writes the publication state.
- Only the audit logger writes the audit log.
- Public templates call only the read models.

See [Roles in code](/build/roles-in-code).

## CI gates

Every pull request must pass **all** of these, in order:

1. PHP coding standards (PHPCS with the WordPress ruleset). Command: `composer lint`.
2. Static analysis (PHPStan with a WordPress extension; level agreed at M0). Command: `composer analyse`.
3. PHPUnit unit and integration tests on the WordPress test suite, plus architecture tests, with a coverage threshold for `src/`. Command: `composer test`, and `composer test -- --testsuite Arch` for architecture only.
4. Asset build. Command: `npm run build`.
5. Playwright smoke tests and axe accessibility checks. Command: `npx playwright test`.
6. Dependency audit: `composer audit` and `npm audit --audit-level=high`.
7. Secret scan.
8. The pull request cites REQ IDs and `Closes #N`.

Going live is **not** a CI deploy. It is a manual migration of the finished local site, approved by the owner ([Deployment](/build/deployment)). Automated staging and production deploys can be added later.

## Environments for testing

| Environment | Use |
|---|---|
| Local (wp-env or an equivalent) | The main build and test place |
| Live host | Checked once at import time for limits, 410 behaviour, mail, cron and restore |

## The prototype's own checks

The clickable prototype in `prototype/` has its own checks, separate from the WordPress build:

| Command | Does |
|---|---|
| `node scripts/smoke-prototype.js` | Runs the prototype's assertions against a stubbed DOM |
| `node scripts/build-inventory.js` | Regenerates the wireframe inventory and fails if any requirement is unexplained |

They guard the prototype's rules, such as no public accounts, and flows such as claim approval. They do not replace the WordPress tests above.

## Traceability

A traceability table shows each requirement with its issue, pull request and test, and whether it was verified. It exists to prove nothing was missed. An independent verify step audits the result against the requirements before go-live.
