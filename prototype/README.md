# Clickable wireframe

Mid-fidelity, sample data only. Plain HTML, CSS and JavaScript, hash routing, no build step, no server. Not the product. The brand name comes from `tokens.js`.

## Open it
- Double-click `index.html`, or run `python -m http.server 8000` in this folder and open http://localhost:8000.
- GitHub Pages: `.github/workflows/pages.yml` publishes this folder. A repo admin must set Settings > Pages > Source to "GitHub Actions" once. Expected address: https://incspiredstudio.github.io/whoiswhoafrica/

## No accounts, no claiming (D-034, D-036)
There is no login, signup, password, account area, owner dashboard or judge portal on the public site, and no claiming at all. Only staff sign in, to wp-admin (here: **Staff sign in (wp-admin preview)** in the top bar, code `000000`). Everything else works without an account:
- **Correction, removal and report** are public forms. They land in the staff **Support inbox** as typed items (Correction, Removal, Report) with a status and a due-date countdown. Staff edit or unpublish the profile directly; every edit and unpublish is audited; a valid removal also adds the name to the suppression list (D-040).
- **Single-use signed links** (from the Email outbox) carry the nominator check, the nominee accept/decline/correct page, the customer intake form, the consent page and the correction email check. Each works once and then shows the expired page.
- **Labels.** Verified (staff checklist) and Featured (editorial), never bought; Sponsored is a separate paid disclosure; pages say "Reviewed on <date>". There are no Claimed or Unclaimed labels.
- **One feature flag**: Nominations (D-037). The kill switch is a separate legal safety control: it hides every individual profile that came from the acquisition import (even if later Verified, Featured or Sponsored); customer-submitted individuals are not affected.
- **Offline judging.** Judges are names on a panel. The Awards manager enters their scores on **Scores and results**; a recorded conflict blocks entry for that judge. Shortlist and winner pages go public when staff publish them (no flag).
- **Dashboard and menu by role** (D-035, D-041). Counts by state (imported, pending human review, reviewed, published); no directory target and no capacity figure. Reviewers see only the review and directory tiles and a seven-item menu. Commercial tiles show for Administrator, Finance and Sales/Support. **Roles and permissions** lists the menu for every role.
- **No Source registry, no AI drafting, no controlled-lists screen in the admin.** Approved sources and AI drafting belong to the acquisition pipeline (D-038). Industries, Cities, Countries, Organisation types and Professions are WordPress taxonomies (D-039): the wireframe mimics the native term screens; Editors and Administrators add terms, Reviewers only assign existing ones.
- **Exports** of personal data are Administrator-only and logged (REQ-069).
- **Roles.** Administrator, Editor-in-Chief, Editor, Reviewer, Sales/Support, Finance, Awards manager. Judge is not a role.

## Use it
- Top bar: **Viewing as** switches role (visitor or one of the seven staff roles). **Email outbox** shows every email the system would send; open the links to act as the recipient. **Reset sample data** starts over.
- Bottom left **Flows**: 19 scripted flows, each linked to inventory entries. Bottom right **Design notes**: the note for the screen you are on (including intended motion for the home page).
- State lives in your browser (localStorage, key `wiw-wireframe-v5`). Nothing leaves it.

## Files
| File | Holds |
|---|---|
| `tokens.js` | All brand values (name, tagline, colours, fonts, spacing). The only place. |
| `styles.css` | Layout; reads tokens only. |
| `data.js` | Sample data. Every name is "Sample ...". |
| `core.js` | State, rules (visibility, counting rule, sponsored label), router, events. |
| `views-public.js`, `views-awards.js` | Visitor and nominator screens, correction, removal and report forms. |
| `views-links.js` | Pages reached from a single-use link: customer intake, consent, expired link. |
| `views-staff1.js`, `views-staff2.js` | Staff console preview: dashboard by role, review, profiles, Support inbox, import, taxonomies, register, awards, score entry, roles and menus. |
| `inventory.js` | Screen inventory; source of `docs/wireframe-inventory.md`. |
| `flows.js` | The Flows menu. |

## Checks (Node 18+, no dependencies)
- `node scripts/smoke-prototype.js` renders every inventory route for every role and runs the key flows, including the no-account, no-claiming, one-flag and role-menu rules.
- `node scripts/build-inventory.js` regenerates `docs/wireframe-inventory.md`.

## Not built on purpose
3D and animation (the home hero is a labelled placeholder). Any real admin: the real one will be WordPress. Publish-first (D-033) is not yet in the wireframe.
