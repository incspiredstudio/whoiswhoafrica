# Clickable wireframe

Mid-fidelity, sample data only. Plain HTML, CSS and JavaScript, hash routing, no build step, no server. Not the product. The brand name comes from `tokens.js`.

## Open it
- Double-click `index.html`, or run `python -m http.server 8000` in this folder and open http://localhost:8000.
- GitHub Pages: `.github/workflows/pages.yml` publishes this folder. A repo admin must set Settings > Pages > Source to "GitHub Actions" once. Expected address: https://incspiredstudio.github.io/whoiswhoafrica/

## No accounts (D-034)
There is no login, signup, password, account area, owner dashboard or judge portal on the public site. Only staff sign in, to wp-admin (here: **Staff sign in (wp-admin preview)** in the top bar, code `000000`). Everything else works without an account:
- **Request to claim** (public form with evidence). The claimant confirms their email by a single-use link, then staff verify it on **Claim verification** and set Claimed. Only staff set Claimed.
- **Correction request** (public form). Owners use it to change fields, set contact visibility, switch enquiries on or off, or ask for views and sources. Staff apply it through the usual review.
- **Single-use signed links** (from the Email outbox) carry the nominator check, the nominee accept/decline/correct page, the customer intake form and the consent page. Each works once and then shows the expired page.
- **Offline judging.** Judges are names on a panel. The Awards manager enters their scores on **Scores and results**; a recorded conflict blocks entry for that judge.
- **Roles.** Administrator, Editor-in-Chief, Editor, Reviewer, Sales/Support, Finance, Awards manager. See **Roles and permissions** in the staff menu. Judge is not a role.

## Use it
- Top bar: **Viewing as** switches role (visitor or one of the seven staff roles). **Email outbox** shows every email the system would send; open the links to act as the recipient. **Reset sample data** starts over.
- Bottom left **Flows**: 19 scripted flows, each linked to inventory entries. Bottom right **Design notes**: the note for the screen you are on (including intended motion for the home page).
- State lives in your browser (localStorage, key `wiw-wireframe-v4`). Nothing leaves it.

## Files
| File | Holds |
|---|---|
| `tokens.js` | All brand values (name, tagline, colours, fonts, spacing). The only place. |
| `styles.css` | Layout; reads tokens only. |
| `data.js` | Sample data. Every name is "Sample ...". |
| `core.js` | State, rules (visibility, counting rule, sponsored label), router, events. |
| `views-public.js`, `views-awards.js` | Visitor and nominator screens, request to claim, correction request. |
| `views-links.js` | Pages reached from a single-use link: customer intake, consent, expired link. |
| `views-staff1.js`, `views-staff2.js` | Staff console preview: review, claim verification, corrections, register, awards, score entry, roles. |
| `inventory.js` | Screen inventory; source of `docs/wireframe-inventory.md`. |
| `flows.js` | The Flows menu. |

## Checks (Node 18+, no dependencies)
- `node scripts/smoke-prototype.js` renders every inventory route for every role and runs the key flows, including the no-account rules.
- `node scripts/build-inventory.js` regenerates `docs/wireframe-inventory.md`.

## Not built on purpose
3D and animation (the home hero is a labelled placeholder). Any real admin: the real one will be WordPress. Publish-first (D-033) is not yet in the wireframe.
