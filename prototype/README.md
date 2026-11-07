# WHOISWHO clickable wireframe

Mid-fidelity, sample data only. Plain HTML, CSS and JavaScript, hash routing, no build step, no server. Not the product.

## Open it
- Double-click `index.html`, or run `python -m http.server 8000` in this folder and open http://localhost:8000.
- GitHub Pages: `.github/workflows/pages.yml` publishes this folder. A repo admin must set Settings > Pages > Source to "GitHub Actions" once. Expected address: https://incspiredstudio.github.io/whoiswhoafrica/

## Use it
- Top bar: **Viewing as** switches role (visitor, owner, reviewer, editor, sales, finance, Editor-in-Chief, admin, judge). **Email outbox** shows every email the system would send; open the links to act as the recipient. **Reset sample data** starts over.
- Bottom left **Flows**: 19 scripted flows, each linked to inventory entries. Bottom right **Design notes**: the note for the screen you are on (including intended motion for the home page).
- State lives in your browser (localStorage). Nothing leaves it.

## Files
| File | Holds |
|---|---|
| `tokens.js` | All brand values (name, tagline, colours, fonts, spacing). The only place. |
| `styles.css` | Layout; reads tokens only. |
| `data.js` | Sample data. Every name is "Sample ...". |
| `core.js` | State, rules (visibility, counting rule, sponsored label), router, events. |
| `views-*.js` | Screens by audience. |
| `inventory.js` | Screen inventory; source of `docs/wireframe-inventory.md`. |
| `flows.js` | The Flows menu. |

## Checks (Node 18+, no dependencies)
- `node scripts/smoke-prototype.js` renders every inventory route for every role and runs the key flows.
- `node scripts/build-inventory.js` regenerates `docs/wireframe-inventory.md`.

## Not built on purpose
3D and animation (the home hero is a labelled placeholder). Any real admin: the real one will be WordPress.
