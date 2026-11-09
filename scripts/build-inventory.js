// Generates docs/wireframe-inventory.md from prototype/inventory.js (the single source) and docs/requirements.md.
// Run: node scripts/build-inventory.js
const vm = require("vm"), fs = require("fs"), path = require("path");
const root = path.join(__dirname, "..");
const ctx = { console, document: { createElement: () => ({}), head: { appendChild() {} } }, localStorage: { getItem() { return null; }, setItem() {} }, location: { hash: "" }, Intl, FormData: function () {} };
ctx.window = ctx; vm.createContext(ctx);
for (const f of ["tokens.js", "data.js", "core.js", "theme/hero-geo.js", "theme/components.js", "views-public.js", "views-awards.js", "theme/home-view.js", "views-links.js", "views-staff1.js", "views-staff2.js", "inventory.js", "flows.js"]) vm.runInContext(fs.readFileSync(path.join(root, "prototype", f), "utf8"), ctx, { filename: f });
const W = ctx.W;
const req = fs.readFileSync(path.join(root, "docs", "requirements.md"), "utf8");
const reqs = {}; // n -> {text, withdrawn}
for (const l of req.split("\n")) { const m = l.match(/^\| REQ-(\d+) \| (.*?) \|/); if (m) reqs[+m[1]] = { text: m[2].replace(/[*~]/g, "").replace(/\s+/g, " "), withdrawn: /WITHDRAWN/.test(m[2]) }; }
const cited = new Set(); W.INV.forEach((x) => x.reqs.forEach((n) => cited.add(n)));
const pad = (n) => "REQ-" + ("00" + n).slice(-3);
const cell = (s) => String(s).replace(/\|/g, "\\|");

const NOUI = {
  20: "Later (post-sprint): analytics beyond the view count.",
  41: "Performance target; measured in QA, not a screen.", 42: "Image delivery through a CDN; a build property.", 43: "Applies to every screen; checked by accessibility tests, not a separate screen.", 44: "Rendering approach (server-rendered); a build property.",
  45: "Uptime monitoring service outside the product.", 46: "Security configuration and scanning.", 49: "Backup and restore process.", 50: "Error and uptime monitoring service.",
  52: "Lives in prototype/tokens.js (name) and prototype/theme/tokens.css (design values); not a screen.", 53: "Formatting (UTF-8, NGN, WAT) applies across screens; visible in the register and dates.", 54: "Capacity target for hosting.", 55: "Browser support target.", 56: "Staging and CI.",
  78: "Analytics service; loads only after consent (banner V46).", 80: "Object storage behind a CDN; a build property.", 95: "Runbook document.",
  96: "Delivery date.", 98: "Delivery date; the templates are V09 to V11.", 101: "Delivery date.", 103: "Delivery date.", 104: "Delivery volume (Q1).",
  108: "Staff sign-in is A01. Password reset and session list are WordPress defaults, not designed here (D-034: no public login).",
  190: "Build check that fails on hard-coded brand values; not a screen."
};
Object.assign(NOUI, {
  136: "Enforced in the acquisition pipeline by the named approver, not in the website admin (D-038).", 137: "Enforced in the acquisition pipeline (D-038); the import dry run (A20) checks the file against the import contract.", 138: "Enforced in the acquisition pipeline (D-038).",
  165: "AI drafting belongs to the acquisition pipeline, not the website (D-037).", 167: "Pipeline drafting rule (acquisition), not a screen.", 169: "Pipeline and provider terms (acquisition), not a screen.",
  191: "No directory-size target on the website or its admin (D-035).", 206: "No review-capacity figure until the pilot measures one (D-035).",
  124: "Claiming is removed (D-036); the requirement is stale.", 150: "Verified shown to staff as a checkmark (D-057); no public Claimed or Unclaimed label (A06, A61).", 152: "Claiming is removed (D-036); the requirement is stale.", 174: "Disputes escalate through the Support inbox (A12) to the Editor-in-Chief.",
  255: "WordPress plugin that cuts out photo backgrounds on import (D-057); a background job with no screen in the wireframe.",   238: "Future item, not designed or built now (D-033 as amended).", 230: "Request to claim removed (D-036).", 231: "Claim verification screen removed (D-036).", 232: "Replaced by the Support inbox and direct staff edits (D-040).", 192: "Sourcing runs in the acquisition pipeline."
});
for (let n = 210; n <= 228; n++) NOUI[n] = "Publish-first rule (D-033) with no screen of its own. The public label and listing rules are in V52 and V56; the staff states are not yet fully in the wireframe.";

// gaps found while inventorying (REQ has a surface, no screen in the first pass) and the fix
const GAPS = [
  [7, "Filters by profession, organisation type and country were missing", "V03, V04, V05 (country filter lists Nigeria first, then other African countries and pan-African bodies)"],
  [16, "Admin marking of posts/profiles as Sponsored had no named screen", "A06, A24"],
  [57, "Lawful basis per category of person had no place to be recorded", "A37 (table added)"],
  [72, "DPIA status and data-protection contact had no place", "A37"],
  [123, "Duplicate warning at submission (not only in review)", "O06 (warning), A04, A21"],
  [129, "Slug change with redirect", "A06 (Public URL card); old URL redirects"],
  [132, "Organisation records compiled from public sources, imported and reviewed", "V10, A04, A20"],
  [135, "Re-sync of source-backed records on request", "A06 (Source sync card)"],
  [145, "Persistent ID and match basis", "A06, A21"],
  [160, "Stale-record workflow", "A39 (the yearly prompt is an email with a link to the correction request, V39)"],
  [161, "Source changes flagged for review, not overwritten", "A06 (Source sync card)"],
  [171, "Dashboard by type, source, status and age", "A02 (table added)"],
  [172, "Right of reply", "V39 (choose Add a right-of-reply note)"],
  [236, "Subjects can learn status, view count and sources used", "V39 (ask), A56 (staff reply)"],
  [175, "Consent for complimentary profiles", "A07, O22"],
  [189, "Expiry reminders", "A02, A44"],
  [192, "Continuing sourcing pipeline", "A20 (import). Sourcing itself runs in the acquisition pipeline (D-038)."],
  [204, "Sending domain visible in outgoing mail", "A40"],
  [159, "Notify the subject when a stub is created", "A04 shows a disabled control: waiting for Legal (Q5). Not designed further."]
];
const PARTIAL = [
  [7, "Achievement / recognition and award-category filters (priority S, not M) are not in the wireframe."],
  [79, "Search is a simple text match here; ranking and relevance are not designed."],
  [93, "Prospect lists: CSV export is mocked; import is not."]
];

let md = "# WHOISWHO wireframe inventory\n\n";
md += "Generated by `node scripts/build-inventory.js` from `prototype/inventory.js`. Do not edit the tables by hand; edit the inventory and regenerate.\n\n";
md += "**Tags.** SPECIFIED: the brief or `docs/requirements.md` names the page, screen or flow. IMPLIED: needed to make a named thing work, but never stated.\n\n";
md += "**Where.** Every row has a route in the clickable prototype (`/prototype`, hash routing). Staff screens preview flows only (there are no owner, nominee or judge accounts, D-034); the real admin will be WordPress (D-031).\n\n";
const total = W.INV.length, spec = W.INV.filter((x) => x.tag === "SPECIFIED").length;
md += `**Totals.** ${total} entries: ${spec} SPECIFIED, ${total - spec} IMPLIED. Some entries are states of one screen (modals, empty states, error states); each still has its own row so none is forgotten.\n\n`;
const FID = { high: 0, wireframe: 0, tool: 0 }; W.INV.forEach((x) => FID[x.fidelity]++);
md += `**Fidelity.** \`high\`: built on the design system in \`prototype/theme/\`, looks like the final site (${FID.high} entries). \`wireframe\`: staff screens that keep the wireframe look on purpose (${FID.wireframe}). \`tool\`: prototype tools (${FID.tool}). See docs/design-direction.md and docs/design-system.md.\n\n`;
const WPT = ["native", "customised-native", "custom", "n/a (wireframe tool)"];
const wpCount = {}; WPT.forEach((k) => (wpCount[k] = W.INV.filter((x) => x.wpScreen === k).length));
const untagged = W.INV.filter((x) => WPT.indexOf(x.wpScreen) < 0 || !x.wpRec);
if (untagged.length) { console.log("untagged:", untagged.map((x) => x.id).join(",")); process.exit(1); }
md += "**WordPress screen type (D-053).** `native`: a core WordPress screen or a page/post template, shown only for illustration. `customised-native`: a core screen or template with plugin-added columns, filters, fields, actions or a custom post type. `custom`: a screen or page the plugin builds. Public pages are tagged by how WordPress builds them: a plain content page is `native`, a template over profiles or posts is `customised-native`, a form or signed-link page is `custom`. Counts: " + WPT.map((k) => `${wpCount[k]} ${k}`).join(", ") + ".\n\n";
md += "**Recommendation.** `Remove from wireframe: WordPress provides it`, `Keep as illustration only`, or `Keep: custom`. Staff screens that WordPress already provides are shown in the wireframe only so the flows can be walked; the real build does not design them.\n\n";
const removals = W.INV.filter((x) => /^Remove/.test(x.wpRec));
md += "## Recommended removals\n\n| ID | Screen | Why WordPress already provides it |\n|---|---|---|\n" + removals.map((x) => `| ${x.id} | ${cell(x.name)} | ${cell(x.wpNote)} |\n`).join("") + "\n";
const needNote = W.INV.filter((x) => x.wpNote && !/^Remove/.test(x.wpRec));
md += "## Native-overlap notes (not removals)\n\n| ID | Screen | Note |\n|---|---|---|\n" + needNote.map((x) => `| ${x.id} | ${cell(x.name)} | ${cell(x.wpNote)} |\n`).join("") + "\n";
const groups = {}; W.INV.forEach((x) => { (groups[x.aud] = groups[x.aud] || []).push(x); });
for (const a of Object.keys(groups)) {
  md += `## ${a} (${groups[a].length})\n\n| ID | Screen or state | Serves | Tag | Fidelity | WP screen | Recommendation | Prototype route |\n|---|---|---|---|---|---|---|---|\n`;
  for (const x of groups[a]) md += `| ${x.id} | ${cell(x.name)} | ${cell(x.reqText)}${x.dec ? "; " + cell(x.dec) : ""} | ${x.tag} | ${x.fidelity} | ${x.wpScreen} | ${cell(x.wpRec)} | \`${x.route}\` |\n`;
  md += "\n";
}
md += "## Flows to try (the floating Flows menu)\n\n| # | Flow | Requirements | Entries |\n|---|---|---|---|\n";
W.FLOWS.forEach((f, i) => { md += `| ${i + 1} | ${cell(f.t.replace(/^\d+\. /, ""))} | ${cell(f.r)} | ${f.inv.join(", ")} |\n`; });
md += "\n## REQs with a surface that had no screen on the first pass, and the fix\n\n| REQ | What was missing | Now covered by |\n|---|---|---|\n";
for (const g of GAPS) md += `| ${pad(g[0])} | ${cell(g[1])} | ${cell(g[2])} |\n`;
md += "\n## Partly designed (known holes)\n\n| REQ | What is not in the wireframe |\n|---|---|\n";
for (const g of PARTIAL) md += `| ${pad(g[0])} | ${cell(g[1])} |\n`;
const noui = Object.keys(reqs).map(Number).filter((n) => !reqs[n].withdrawn && !cited.has(n));
md += "\n## REQs with no UI at all\n\nThese are not screens: delivery dates, build properties, monitoring and processes. Withdrawn REQs are excluded.\n\n| REQ | Requirement | Why there is no screen |\n|---|---|---|\n";
let unexplained = 0;
for (const n of noui) { const why = NOUI[n] || "NOT EXPLAINED"; if (!NOUI[n]) unexplained++; md += `| ${pad(n)} | ${cell(reqs[n].text.slice(0, 110))} | ${cell(why)} |\n`; }
const cov = Object.keys(reqs).map(Number).filter((n) => !reqs[n].withdrawn && cited.has(n)).length;
md += `\n**Coverage.** ${cov} active REQs are served by at least one screen; ${noui.length} have no UI; ${Object.keys(reqs).map(Number).filter((n) => reqs[n].withdrawn).length} are withdrawn.\n`;
fs.writeFileSync(path.join(root, "docs", "wireframe-inventory.md"), md, "utf8");
console.log("written; entries", total, "no-UI", noui.length, "unexplained", unexplained);
if (unexplained) { console.log(noui.filter((n) => !NOUI[n]).join(",")); process.exit(1); }
