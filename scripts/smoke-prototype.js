// Smoke test for /prototype: renders every inventory route for every role in a stubbed DOM.
// Run: node scripts/smoke-prototype.js   (no dependencies)
const vm = require("vm"), fs = require("fs"), path = require("path");
const dir = path.join(__dirname, "..", "prototype");
const store = {};
const app = { innerHTML: "" };
const ctx = {
  console, setTimeout, clearTimeout, Intl, URL, Blob: function () {},
  localStorage: { getItem: (k) => store[k] || null, setItem: (k, v) => { store[k] = v; }, removeItem: (k) => { delete store[k]; } },
  location: { hash: "#/" },
  document: { createElement: () => ({ style: {}, appendChild() {}, remove() {}, click() {} }), head: { appendChild() {} }, body: { appendChild() {} }, getElementById: (id) => (id === "app" ? app : null), querySelector: () => null, addEventListener() {} },
  FormData: function () {},
};
ctx.window = ctx; ctx.scrollY = 0; ctx.scrollTo = () => {}; ctx.addEventListener = () => {};
vm.createContext(ctx);
for (const f of ["tokens.js", "data.js", "core.js", "views-public.js", "views-awards.js", "views-links.js", "views-staff1.js", "views-staff2.js", "inventory.js", "flows.js"]) {
  vm.runInContext(fs.readFileSync(path.join(dir, f), "utf8"), ctx, { filename: f });
}
const W = ctx.W; W.load();
let fails = 0, n = 0;
function check(label, cond, extra) { n++; if (!cond) { fails++; console.log("FAIL", label, extra || ""); } }
const roles = Object.keys(W.LISTS.roles);
const ids = new Set(W.routes.map((r) => r.inv));
for (const x of W.INV) {
  const m = W.match(x.route);
  check("route resolves " + x.id + " " + x.route, m.r !== W.notFound || x.id === "V13", "-> notFound");
  if (!x.route.startsWith("#/")) check("route format " + x.id, false);
}
for (const r of W.routes) check("route has inventory entry " + r.pattern + " " + r.inv, !!W.INV.find((x) => x.id === r.inv), r.inv);
const seen = new Set(); for (const x of W.INV) { check("unique id " + x.id, !seen.has(x.id)); seen.add(x.id); }
const BAD = /undefined|\[object|NaN|null</;
function renderAll(label) {
  for (const role of roles) {
    for (const x of W.INV) {
      W.S.role = role; ctx.location.hash = x.route; app.innerHTML = "";
      try { W.render(); } catch (e) { check(label + " " + role + " " + x.id, false, e.stack.split("\n").slice(0, 3).join(" | ")); continue; }
      check(label + " " + role + " " + x.id + " nonempty", app.innerHTML.length > 200);
      const bad = app.innerHTML.match(BAD); if (bad) check(label + " " + role + " " + x.id + " has " + bad[0], false, app.innerHTML.slice(Math.max(0, app.innerHTML.search(BAD) - 80), app.innerHTML.search(BAD) + 60));
    }
  }
}
renderAll("seed");
// state-changing scenarios
const S = () => W.S;
function reload() { W.reset(); W.load(); }
reload(); S().flags.nominations = { on: true, ref: "x" }; renderAll("flags-on");
reload(); S().profiles.forEach((p) => { if (p.status === "published") p.status = "unpublished"; }); renderAll("all-unpublished");
// action scenarios
reload(); S().role = "reviewer";
W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve blocked by scan", S().profiles.find((p) => p.id === "b5").status === "pending");
W.act.clearScan("b5|0"); W.act.rmFact("b5|2"); W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve works", S().profiles.find((p) => p.id === "b5").status === "published");
check("approved in sitemap", W.publicProfiles().some((p) => p.id === "b5"));
S().role = "editor"; W.forms.unpub({ id: "b6", reason: "test" }); check("unpublish -> gone", W.vis(W.profile("b6")) === "gone"); check("purge logged", S().purges.length >= 1);
// D-043: bulk unpublish by filter replaces the kill switch
S().role = "editor"; W.forms.bulk({ f: "~individual~~~import~", reason: "t" }); check("editor cannot bulk unpublish unless granted (D-045)", W.isPublic(W.profile("ip2")));
S().role = "reviewer"; ctx.location.hash = "#/admin/profiles?type=individual&origin=import"; W.render();
check("bulk panel shows the match count and the button", /Bulk unpublish by filter/.test(app.innerHTML) && /published profile\(s\) match/.test(app.innerHTML) && /data-act="bulkDlg"/.test(app.innerHTML));
const audit0 = S().audit.length, purge0 = S().purges.length, nbulk = W.profFilter({ type: "individual", origin: "import" }).filter((p) => p.status === "published").length;
W.forms.bulk({ f: "~individual~~~import~", reason: "" }); check("bulk needs a reason", W.isPublic(W.profile("ip2")));
W.forms.bulk({ f: "~individual~~~import~", reason: "legal review" });
check("bulk unpublish hides imported individuals, even Verified or Featured", !W.isPublic(W.profile("ip2")) && !W.isPublic(W.profile("ip4")) && !W.isPublic(W.profile("ip5")) && W.vis(W.profile("ip2")) === "gone");
check("bulk leaves customer-submitted individuals and organisations", W.isPublic(W.profile("p-a")) && W.isPublic(W.profile("b1")));
check("bulk writes one audit row per profile plus one batch row, and a purge each", S().audit.length === audit0 + nbulk + 1 && S().audit.filter((a) => a.action === "bulk.unpublish").length === 1 && S().purges.length === purge0 + nbulk);
check("recent bulk actions listed with a restore button", (ctx.location.hash = "#/admin/profiles", W.render(), /Restore this batch/.test(app.innerHTML)));
S().role = "visitor"; ctx.location.hash = "#/people"; W.render(); check("people list drops the bulk-unpublished profiles", !/Sample Person B/.test(app.innerHTML));
S().role = "reviewer"; W.act.bulkRestore(S().bulkLog[0].id); check("restore this batch brings them back", W.isPublic(W.profile("ip2")) && W.isPublic(W.profile("ip4")) && S().bulkLog[0].restored);
W.forms.bulk({ f: "Sample Annual Report~~B-2026-11-06~~~", reason: "x" }); check("bulk by source and batch", !W.isPublic(W.profile("ip8")) && W.isPublic(W.profile("ip2")) && W.profile("ip8").bulkId);
// register counting
reload(); const a = S().arrangements; check("seed counts", a.filter(W.counts).length === 2, a.filter(W.counts).map((x) => x.id).join());
check("complimentary never counts", !W.counts(a.find((x) => x.id === "arr5")));
check("sponsor label via arrangement", W.sponsorOf(W.profile("b2")) === "Sample Fintech Ltd.");
W.act.arrEnd("arr1"); check("label removed when ended", W.sponsorOf(W.profile("b2")) === null); check("trust unchanged", W.profile("b2").trust === "verified");
// leads
S().role = "editor"; W.forms.lead({ name: "N", org: "O", interest: "x", contact: "c", msg: "m" }); check("lead added", S().leads.length === 4); W.act.leadConv(S().leads[0].id); check("lead converted", S().arrangements.length === 6);
// nominations
reload(); S().role = "editor"; W.act.nomStep("n2|screen"); W.act.nomStep("n2|eligible"); check("invited", W.find("nominations", "n2").status === "invited");
const nn = W.find("nominations", "n2"); W.act.nomDeclineYes(nn.token); check("decline suppresses", W.profile("b1").status === "suppressed" && S().suppression.some((s) => s.name === "Sample Fintech Ltd." || s.name === "Sample Agritech Co."));
// D-034: no public accounts
check("no login/signup/account/judge routes", !W.routes.some((r) => /^\/(login|signup|account|judge|forgot|reset|verify)(\/|$)/.test(r.pattern)), W.routes.filter((r) => /^\/(login|signup|account|judge|forgot|reset|verify)/.test(r.pattern)).map((r) => r.pattern).join());
check("no owner or judge role", !roles.includes("owner") && !roles.includes("judge"));
check("exactly four roles: visitor, reviewer, editor, administrator", roles.join() === "visitor,reviewer,editor,admin", roles.join());
check("removed roles are gone from the role names", !JSON.stringify(W.LISTS.roles).match(/Editor-in-Chief|Sales|Finance|Awards manager/));
for (const x of ["#/login", "#/signup", "#/account", "#/judge", "#/forgot"]) { reload(); S().role = "visitor"; check("route gone " + x, W.match(x).r === W.notFound); }
// D-036: no claiming at all
check("no claim routes or screens", !W.routes.some((r) => /claim/i.test(r.pattern)) && W.match("#/claim/sample-university-a").r === W.notFound && W.match("#/admin/claims").r === W.notFound);
check("no claims in state", S().claims === undefined);
// D-037: the only feature flag is nominations
check("only the nominations flag exists", Object.keys(S().flags).join() === "nominations");
check("no flags screen entries for the removed flags", W.match("#/admin/ai").r === W.notFound);
// D-038: no source registry in the website admin
check("no source registry state in the site", S().sources === undefined);
// D-042: Sources is a read-only screen; every profile carries a source and an import batch (REQ-242, 243)
check("every profile has a source name; imports have a batch", S().profiles.every((p) => p.srcName && (W.origin(p) === "customer" || p.batch)));
reload(); for (const r of ["reviewer", "editor", "admin"]) { S().role = r; ctx.location.hash = "#/admin/sources"; W.render(); check("sources screen opens for " + r, /Sources/.test(app.innerHTML) && /Sample Registry A/.test(app.innerHTML) && /Last import/.test(app.innerHTML) && /Import errors/.test(app.innerHTML) && /Removals/.test(app.innerHTML)); }
check("sources screen has no mutation controls", (() => { const i = app.innerHTML.indexOf("<h1>Sources</h1>"), j = app.innerHTML.indexOf("Import errors are rows"); const main = app.innerHTML.slice(i, j); return i > 0 && j > i && /Last import/.test(main) && !/<form|<button|data-act=|type="submit"|<input|<select/i.test(main); })());
check("sources screen says approval happens in the pipeline", /acquisition pipeline/.test(app.innerHTML) && /REQ-136 to 138/.test(app.innerHTML));
check("sources link filters profiles by source", /#\/admin\/profiles\?src=Sample%20Registry%20A/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin/sources"; W.render(); check("visitor 403 on sources", /403/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/profiles?src=Sample%20Registry%20A"; W.render(); check("profiles filter by source", /Sample Agritech Co\./.test(app.innerHTML) && !/Sample Person B/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?batch=B-2026-11-05"; W.render(); check("profiles filter by import batch", /Sample Person B/.test(app.innerHTML) && !/Sample Agritech Co\./.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?group=src"; W.render(); check("profiles grouped by source", /Source: Sample Registry A/.test(app.innerHTML) && /Source: Sample Annual Report/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?group=batch"; W.render(); check("profiles grouped by import batch", /Batch: B-2026-11-01/.test(app.innerHTML) && /Batch: B-2026-11-05/.test(app.innerHTML));
check("reviewer sees no unpublish, edit or bulk controls", !/data-act="unpubDlg"|data-act="bulkDlg"|Create on behalf/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles/b1"; W.render(); check("reviewer profile screen: terms only, no edit form", /Assign existing terms/.test(app.innerHTML) && !/data-form="profedit"/.test(app.innerHTML) && !/data-form="verify"/.test(app.innerHTML) && /source: Sample Registry A/.test(app.innerHTML));
W.forms.profedit({ id: "b1", name: "Hacked", reason: "x" }); check("reviewer cannot edit by form", W.profile("b1").name === "Sample Agritech Co.");
W.forms.profterms({ id: "b1", industry: "Technology" }); check("reviewer assigns an existing term", W.profile("b1").industry === "Technology");
reload(); S().role = "reviewer"; ctx.location.hash = "#/admin/review/b5"; W.render(); check("review screen shows each field's source URL and retrieval date, read-only", /Source \(URL\)/.test(app.innerHTML) && /Retrieved/.test(app.innerHTML) && /example\.org\/sources\//.test(app.innerHTML) && !/Approve source|Reject source/.test(app.innerHTML));
// D-039: taxonomies are native term screens; Reviewers cannot edit terms
check("controlled lists screen gone", W.match("#/admin/lists").r === W.notFound);
reload(); S().role = "reviewer"; ctx.location.hash = "#/admin/taxonomy/cities"; W.render(); check("reviewer gets 403 on taxonomy", /403/.test(app.innerHTML));
S().role = "editor"; ctx.location.hash = "#/admin/taxonomy/cities"; W.render(); check("editor opens cities taxonomy with Ghana as parent", /Add new city/.test(app.innerHTML) && /Accra/.test(app.innerHTML) && /Ghana/.test(app.innerHTML));
W.forms.taxadd({ k: "cities", name: "Kumasi", slug: "", parent: "Ghana" }); check("editor adds a city term", W.LISTS.cities.indexOf("Kumasi") >= 0 && S().tax.cities.find((t) => t.name === "Kumasi").parent === "Ghana");
W.forms.taxadd({ k: "industries", name: "Mining", slug: "" }); W.forms.impdry({ csv: W.ui.imp.csv, src: "x.csv" }); check("import dry run accepts a term once an Editor adds it", W.ui.imp.rows.every((r) => !/not a term/.test(r.why)));
check("country list is Nigeria-first and has other African countries and pan-African bodies", W.LISTS.countries[0] === "Nigeria" && W.LISTS.countries.length > 3 && W.LISTS.countries.some((c) => /Pan-African/.test(c)));
reload(); S().role = "visitor"; ctx.location.hash = "#/businesses"; W.render(); check("country filter is enabled and offers other countries", /Ghana/.test(app.innerHTML) && !/Nigeria only/i.test(app.innerHTML));
// D-040: correction, removal and report land in the Support inbox; staff edit directly; no separate screen
check("corrections and reports screens gone", W.match("#/admin/corrections").r === W.notFound && W.match("#/admin/reports").r === W.notFound);
reload(); S().role = "visitor";
W.forms.correct({ id: "b1", who: "The person named on this profile", need: "Correct a fact", part: "title", detail: "New Title", evidence: "", contact: "x@example.org" });
const cr = S().corrections[0]; check("correction is a typed Correction item awaiting email", cr.kind === "Correction" && cr.status === "awaiting email check");
ctx.location.hash = "#/confirm/" + cr.token; W.render(); check("email link opens the correction", cr.status === "open" && cr.emailVerified && S().used[cr.token]);
W.forms.remove({ id: "ip3", who: "I am the person", detail: "d", contact: "r@example.org" }); W.forms.report({ id: "b6", reason: "Other", detail: "d" });
S().role = "editor"; ctx.location.hash = "#/admin/inbox"; W.render();
check("inbox shows Correction, Removal and Report with a due countdown", /Correction/.test(app.innerHTML) && /Removal/.test(app.innerHTML) && /Report/.test(app.innerHTML) && /due in|overdue|due today/.test(app.innerHTML));
const before = S().audit.length; W.forms.profedit({ id: "b1", name: "Sample Agritech Co.", title: "New Title", org: undefined, website: "https://example.org/sample-agritech", industry: "Agriculture & Agritech", country: "Nigeria", city: "Lagos", orgType: "Company", bio: "b", achievements: "a", reason: "request", item: cr.id });
check("staff edit goes live at once and is audited", W.profile("b1").title === "New Title" && S().audit.length > before && S().audit.some((a) => a.action === "profile.edit") && cr.status === "closed");
W.act.rmOk(S().removals[0].id); check("valid removal hides and suppresses", W.profile("ip3").status === "suppressed" && S().suppression.some((x) => x.name === "Sample Person C"));
// REQ-069: export is Administrator-only and logged
S().role = "reviewer"; const exp0 = S().audit.length; W.act.dl("profiles"); check("reviewer cannot export", S().audit.some((a) => a.action === "export.denied") && !S().audit.some((a) => a.action === "export.profiles"));
S().role = "admin"; W.act.dl("profiles"); check("administrator export is logged", S().audit.some((a) => a.action === "export.profiles"));
ctx.location.hash = "#/admin/exports"; S().role = "reviewer"; W.render(); check("exports screen 403 for reviewer", /403/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/leads"; W.render(); check("reviewer has no leads screen", /403/.test(app.innerHTML));
S().role = "editor"; ctx.location.hash = "#/admin/leads"; W.render(); check("editor sees no export button, only the note", /Administrator by default/.test(app.innerHTML) && !/data-arg="leads"/.test(app.innerHTML));
// D-035: dashboard counts by state, no target numbers, no capacity, role-based
reload();
for (const role of Object.keys(W.LISTS.roles)) {
  S().role = role; ctx.location.hash = "#/admin"; app.innerHTML = ""; W.render();
  if (role === "visitor") { check("visitor 403 on dashboard", /403/.test(app.innerHTML)); continue; }
  check("no directory target or progress on " + role, !/Directory target/i.test(app.innerHTML) && !/\d+\s*\/\s*100\b/.test(app.innerHTML) && !/\d+\s*\/\s*1,?000\b/.test(app.innerHTML));
  check("no capacity assumption on " + role, !/capacity/i.test(app.innerHTML.replace(/no capacity figure[^.]*\./i, "")) && !/×\s*about\s*40/.test(app.innerHTML));
}
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render();
check("reviewer dashboard: counts by state present", /imported/.test(app.innerHTML) && /pending human review/.test(app.innerHTML) && />reviewed</.test(app.innerHTML) && />published</.test(app.innerHTML));
check("reviewer dashboard hides commercial tiles", !/Sprint target/.test(app.innerHTML) && !/Revenue per stream/.test(app.innerHTML) && !/Expiry reminders/.test(app.innerHTML));
for (const role of ["admin"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard shows sprint target and revenue per stream", /Sprint target/.test(app.innerHTML) && /Revenue per stream/.test(app.innerHTML)); }
for (const role of ["reviewer", "editor"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard hides the sprint target", !/Sprint target/.test(app.innerHTML)); }
// D-041: left menu by role
const REVIEWER_MENU = ["Dashboard", "Review queue", "Duplicates", "Stale records", "Profiles", "Sources", "Suppression list"];
check("reviewer menu is exactly the seven screens (no Support inbox, no leads)", JSON.stringify(W.menuFor("reviewer")) === JSON.stringify(REVIEWER_MENU), W.menuFor("reviewer").join());
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render(); { const side = (app.innerHTML.match(/<nav class="app-side"[\s\S]*?<\/nav>/) || [""])[0]; const links = [...side.matchAll(/<a href="#[^"]*"[^>]*>([^<]+)<\/a>/g)].map((m) => m[1]); check("reviewer left menu renders exactly those links", JSON.stringify(links) === JSON.stringify(REVIEWER_MENU), links.join()); }
check("administrator menu includes everything", ["Import", "Taxonomies", "Exports", "Roles and permissions", "Feature flag", "Shortlist and winners"].every((x) => W.menuFor("admin").concat(Object.values(W.TAXES).map((t) => "Taxonomies")).indexOf(x) >= 0));
check("no Claim verification, AI drafting, kill switch or corrections screen in any menu", ["reviewer", "editor", "admin"].every((r) => !W.menuFor(r).some((x) => /claim|ai drafting|kill|controlled lists|corrections and removals|reports/i.test(x))));
check("Sources is in the Reviewer, Editor and Administrator menus", ["reviewer", "editor", "admin"].every((r) => W.menuFor(r).indexOf("Sources") >= 0));
check("Editor menu has what the Reviewer has plus the Editor screens", REVIEWER_MENU.every((x) => W.menuFor("editor").indexOf(x) >= 0) && ["Support inbox", "Leads", "Articles", "Nominations", "Scores and results"].every((x) => W.menuFor("editor").indexOf(x) >= 0) && !["Import", "Register", "Customers", "Users and roles", "Exports", "Audit log", "Feature flag"].some((x) => W.menuFor("editor").indexOf(x) >= 0), W.menuFor("editor").join());
check("Administrator menu includes Register, Customers, Import and Users and roles", ["Register", "Customers", "Import", "Users and roles", "Audit log"].every((x) => W.menuFor("admin").indexOf(x) >= 0));
for (const r of ["claim", "ai", "killswitch"]) check("no route /admin/" + r, W.match("#/admin/" + r).r === W.notFound);
for (const [role, path] of [["reviewer", "#/admin/import"], ["reviewer", "#/admin/taxonomy/industries"], ["reviewer", "#/admin/flags"], ["reviewer", "#/admin/roles"], ["reviewer", "#/admin/inbox"], ["reviewer", "#/admin/leads"], ["reviewer", "#/admin/register"], ["editor", "#/admin/import"], ["editor", "#/admin/register"], ["editor", "#/admin/customers"], ["editor", "#/admin/users"], ["editor", "#/admin/exports"], ["editor", "#/admin/flags"], ["editor", "#/admin/audit"], ["editor", "#/admin/roles"], ["visitor", "#/admin/sources"]]) { S().role = role; ctx.location.hash = path; W.render(); check("403 for " + role + " on " + path, /403/.test(app.innerHTML)); }
S().role = "admin"; ctx.location.hash = "#/admin/roles"; W.render(); check("roles screen lists the menu per role and the export row", /Left menu by role/.test(app.innerHTML) && /Export personal data/.test(app.innerHTML) && /Taxonomy terms: add and edit/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/flags"; W.render(); check("flag screen shows only nominations and no kill switch", /nominations/.test(app.innerHTML) && !/kill/i.test(app.innerHTML) && !/ai_drafting|announcements flag|judging flag/i.test(app.innerHTML));

// customer intake by single-use link
reload(); S().role = "visitor"; W.act.startInvite("demo"); check("intake draft held against link", !!S().intake.demo);
W.forms.profile({ token: "demo", type: "business", name: "Sample Organisation B", title: "t", industry: "Technology", city: "Lagos", bio: "Clean", achievements: "", warranty: "1", email: "c@example.org" }, null, { submitter: { value: "submit" } });
check("intake submit -> pending and link used", W.profile("p-new").status === "pending" && S().used.demo && W.profile("p-new").trust === "");
ctx.location.hash = "#/invite/demo"; W.render(); check("invite link single use", /expired or already used/.test(app.innerHTML));
S().role = "reviewer"; W.forms.approve({ id: "p-new", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("staff approval publishes with the Reviewed label and no Claimed state", W.profile("p-new").status === "published" && W.profile("p-new").trust === "" && /Reviewed on/.test(W.statePill(W.profile("p-new"))));
reload(); S().role = "visitor"; W.act.startInvite("demo"); W.forms.profile({ token: "demo", type: "individual", name: "S", title: "t", industry: "Technology", city: "Lagos", bio: "Call 0803 123 4567", achievements: "", warranty: "1", adult: "1", email: "c@example.org" }, null, { submitter: { value: "submit" } }); check("intake scan blocks (no throw, still draft)", W.profile("p-new").status === "draft");
// nominator email link
reload(); S().flags.nominations = { on: true, ref: "x" }; ctx.location.hash = "#/nominate"; W.forms.nom0({ name: "A", email: "a@example.org", rel: "Colleague", ok: "1" }); check("nominator waits for link", W.ui.nom.step === 1 && S().emails[0].link === "#/nominate/verify");
ctx.location.hash = "#/nominate/verify"; W.render(); check("nominator link verifies", W.ui.nom.d.verified && W.ui.nom.step === 2);
// offline judging: staff enter scores; conflicts block
reload(); S().role = "editor";
check("score blocked for conflicted judge", W.recordScore("n5", "Sample Judge 2", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("conflict") >= 0 && !(((S().scores.n5 || {}).by || {})["Sample Judge 2"]));
W.forms.scoregrid({ id: "n5", s_0_k1: 6, s_0_k2: 6, s_0_k3: 6, s_0_k4: 6, s_1_k1: 9, s_1_k2: 9, s_1_k3: 9, s_1_k4: 9 }); const by5 = (S().scores.n5 || {}).by || {};
check("grid saves allowed judge, drops conflicted judge", by5["Sample Judge 1"] && !by5["Sample Judge 2"] && by5["Sample Judge 1"].by === "Sample Editor");
check("weighted total", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }) === "7.9", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }));
check("score range enforced", W.recordScore("n4", "Sample Judge 1", { k1: 11, k2: 5, k3: 5, k4: 5 }).indexOf("0 to 10") >= 0);
S().role = "reviewer"; check("reviewer cannot enter scores", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("only a user with the score_entry") === 0);
S().role = "editor"; check("scores can be entered with no flag", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }) === "");
ctx.location.hash = "#/admin/scores/n5"; W.render(); check("conflict row has no score inputs", app.innerHTML.includes("Conflict recorded") && app.innerHTML.includes('name="s_0_k1"') && !app.innerHTML.includes('name="s_1_k1"'));
W.forms.judgecoi({ judge: "Sample Judge 1", n: "n4", why: "x" }); check("recording a conflict removes the judge's score", !(((S().scores.n4 || {}).by || {})["Sample Judge 1"]));
S().role = "admin"; W.find("nominations", "n4").status = "assessment"; W.act.nomStep("n4|shortlist"); check("shortlist via score page flow", W.find("nominations", "n4").status === "shortlisted");
// shortlist and winners publish through the normal publish step (no flag)
reload(); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("shortlisted nominee page is not public until staff publish it", /404|Page not found/.test(app.innerHTML));
S().role = "editor"; W.act.pubNom("n1"); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("nominee page public after publish", /Shortlisted/.test(app.innerHTML) && W.nomPublic(W.find("nominations", "n1")));
S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); check("winners empty until announcement published", /Winners will be announced/.test(app.innerHTML));
S().role = "admin"; W.find("nominations", "n1").status = "winner"; W.act.publishWin(); S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); check("winners page after publish", /Sample Fintech Ltd\./.test(app.innerHTML));
// no kill switch text anywhere in rendered pages
reload(); { let bad = 0; for (const role of Object.keys(W.LISTS.roles)) for (const x of W.INV) { S().role = role; ctx.location.hash = x.route; W.render(); if (/kill/i.test(app.innerHTML.replace(/<[^>]+>/g, " ")) || /Editor-in-Chief|Sales \/ Support|Awards manager/i.test(app.innerHTML.replace(/<[^>]+>/g, " "))) { bad++; console.log("kill/removed-role text on", role, x.id); } } check("no kill switch or removed role text in rendered pages", bad === 0); }
// no Claimed or Unclaimed anywhere in rendered pages, for every role and route
reload(); { let bad = 0; for (const role of Object.keys(W.LISTS.roles)) for (const x of W.INV) { S().role = role; ctx.location.hash = x.route; W.render(); const t = app.innerHTML.replace(/<[^>]+>/g, " "); if (/\bun?claimed\b/i.test(t) || /Claim this profile|Request to claim|\bclaim verification\b/i.test(t)) { bad++; console.log("CLAIM TEXT", role, x.id); } } check("no Claimed/Unclaimed/claim feature text on any screen", bad === 0); }
// role access
reload(); S().role = "editor"; ctx.location.hash = "#/admin/scores"; W.render(); check("editor opens scores", /Scores and results/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/scores"; W.render(); check("reviewer cannot open scores", /403/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin"; W.render(); check("visitor gets 403 on admin", /403/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/roles"; W.render(); check("roles matrix opens for the Administrator", /Roles and permissions/.test(app.innerHTML) && /Reviewer/.test(app.innerHTML) && /Editor/.test(app.innerHTML) && !/Awards manager|Editor-in-Chief|Sales|Finance/.test(app.innerHTML.replace(/<nav class="app-side"[\s\S]*?<\/nav>/, "")));
// WordPress-fit tags: every inventory entry has a valid wpScreen and a recommendation
for (const x of W.INV) {
  check("inventory wpScreen valid " + x.id, ["native", "customised-native", "custom", "n/a (wireframe tool)"].includes(x.wpScreen), x.wpScreen);
  check("inventory wpRec present " + x.id, !!x.wpRec, x.id);
  if (/^Remove/.test(x.wpRec)) check("removal has a reason " + x.id, !!x.wpNote, x.id);
}
// flows: every start route resolves and every inventory id exists
for (const f of W.FLOWS) {
  for (const id of f.inv) check("flow inventory id " + f.t + " " + id, !!W.invById(id), id);
  reload(); S().role = f.role; ctx.location.hash = f.go; app.innerHTML = ""; try { W.render(); } catch (e) { check("flow start " + f.t, false, e.stack.split("\n")[0]); continue; }
  check("flow start nonempty " + f.t, app.innerHTML.length > 200); if (!/^1\./.test(f.t)) check("flow start not 404 " + f.t, !/404 · Page not found/.test(app.innerHTML));
  check("flow role exists " + f.t, roles.includes(f.role), f.role);
}
check("19 flows or fewer", W.FLOWS.length <= 19 && !W.FLOWS.some((f) => /kill/i.test(f.t + f.s.join(" "))));
// D-044 role details
reload(); S().role = "editor"; ctx.location.hash = "#/admin/profiles/b1"; W.render(); check("editor profile screen: edit form, labels and field sources", /data-form="profedit"/.test(app.innerHTML) && /data-form="verify"/.test(app.innerHTML) && /Fields and their sources/.test(app.innerHTML) && /Source \(URL\)/.test(app.innerHTML));
ctx.location.hash = "#/admin"; W.render(); check("editor dashboard: support counts, no sprint tile", /open corrections, removals and reports/.test(app.innerHTML) && !/Sprint target/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render(); check("reviewer dashboard: no support counts", !/open corrections, removals and reports/.test(app.innerHTML));
S().role = "editor"; ctx.location.hash = "#/admin/suppression"; W.render(); check("editor sees the suppression list read-only", /Read-only for your role/.test(app.innerHTML) && !/data-form="supp"/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/suppression"; W.render(); check("administrator can add to the suppression list", /data-form="supp"/.test(app.innerHTML));
S().role = "editor"; check("editor can edit but not bulk unpublish; reviewer can bulk unpublish but not edit (D-045)", W.can("edit") && !W.can("bulk") && (S().role = "reviewer", !W.can("edit") && W.can("bulk") && W.can("assignTerms")));
S().role = "reviewer"; ctx.location.hash = "#/admin/suppression"; W.render(); check("reviewer sees the suppression list read-only", /Read-only for your role/.test(app.innerHTML));
check("seed users have only the three staff roles, one role and one unique email each", S().users.every((u) => ["reviewer", "editor", "admin"].includes(u.rk) && typeof u.rk === "string" && /@/.test(u.email)) && new Set(S().users.map((u) => u.email.toLowerCase())).size === S().users.length);
check("confirmed-by and lead owners are not removed roles", S().arrangements.every((a) => !/Finance|Sales|Eic/.test(a.confirmedBy)) && S().leads.every((l) => !/Sales/.test(l.owner)));
// D-045 capabilities (REQ-244)
reload(); S().role = "reviewer"; ctx.location.hash = "#/admin/articles"; W.render();
check("reviewer has no Articles by default: not in the menu and 403", W.menuFor("reviewer").indexOf("Articles") < 0 && /403/.test(app.innerHTML));
S().role = "editor"; W.act.capToggle("reviewer|stories"); check("only the Administrator changes capabilities", !W.hasCap("stories", "reviewer"));
S().role = "reviewer"; W.act.capToggle("reviewer|stories"); check("a Reviewer cannot grant themselves anything", !W.hasCap("stories", "reviewer"));
S().role = "admin"; const au0 = S().audit.length; W.act.capToggle("reviewer|stories");
check("grant stories to a Reviewer: effective, logged", W.hasCap("stories", "reviewer") && S().audit.length === au0 + 1 && S().audit[S().audit.length - 1].action === "capability.grant");
S().role = "reviewer"; ctx.location.hash = "#/admin/articles"; W.render(); check("granted capability opens the screen and shows in the menu", W.menuFor("reviewer").indexOf("Articles") >= 0 && !/403/.test(app.innerHTML));
S().role = "admin"; W.act.capToggle("reviewer|stories"); S().role = "reviewer"; ctx.location.hash = "#/admin/articles"; W.render();
check("revoking the grant brings the 403 back", !W.hasCap("stories", "reviewer") && /403/.test(app.innerHTML));
S().role = "admin"; W.act.capToggle("editor|support_inbox"); S().role = "editor"; ctx.location.hash = "#/admin/inbox"; W.render();
check("revoking a default takes the screen away from that Editor", W.menuFor("editor").indexOf("Support inbox") < 0 && /403/.test(app.innerHTML) && W.menuFor("editor", true).indexOf("Support inbox") >= 0);
S().role = "admin"; W.act.capReset("editor"); check("reset to role defaults", W.hasCap("support_inbox", "editor"));
W.act.capToggle("editor|users_roles"); W.act.capToggle("reviewer|settings"); W.act.capToggle("editor|commercial_register");
check("locked capabilities (users_roles, settings, commercial_register) are not grantable to others (Q72)", !W.hasCap("users_roles", "editor") && !W.hasCap("settings", "reviewer") && !W.hasCap("commercial_register", "editor"));
W.act.capToggle("editor|bulk_unpublish"); S().role = "editor";
ctx.location.hash = "#/admin/profiles?type=individual&origin=import"; W.render(); check("Editor granted bulk_unpublish sees the bulk button", W.can("bulk") && /data-act="bulkDlg"/.test(app.innerHTML));
S().role = "admin"; W.act.capToggle("editor|bulk_unpublish"); check("revoked again", !W.hasCap("bulk_unpublish", "editor"));
S().role = "admin"; ctx.location.hash = "#/admin/capabilities"; W.render(); check("Users and capabilities screen lists the three sample users", /Sample Reviewer 1/.test(app.innerHTML) && /Sample Editor/.test(app.innerHTML) && /Sample Admin/.test(app.innerHTML) && /proposed limit, Q72/.test(app.innerHTML));
S().role = "editor"; ctx.location.hash = "#/admin/capabilities"; W.render(); check("Editor gets 403 on Users and capabilities", /403/.test(app.innerHTML));
check("handle_disputes: Editor by default, not Reviewer", W.hasCap("handle_disputes", "editor") && !W.hasCap("handle_disputes", "reviewer") && /Editor/.test(W.disputeHandlers()));

// staff sign-in has no role selector; role comes from the account (REQ-245, D-048)
S().role = "visitor"; S().user = null; ctx.location.hash = "#/staff/signin"; W.ui.staffStep = 0; W.ui.staffErr = ""; W.render();
check("staff sign-in step 1 has email and password and no role selector", /name="email"/.test(app.innerHTML) && /name="password"/.test(app.innerHTML) && !/name="role"/.test(app.innerHTML) && !/Role \(wireframe only\)/.test(app.innerHTML));
check("sign-in lists the sample accounts", /Wireframe sample accounts/.test(app.innerHTML) && /reviewer@example\.test/.test(app.innerHTML));
W.forms.staff0({ email: "reviewer@example.test", password: "wrong" });
check("wrong password is refused with a generic message", W.ui.staffStep === 0 && /Email or password is incorrect/.test(app.innerHTML) && S().role === "visitor");
W.forms.staff0({ email: "nobody@example.test", password: W.SAMPLE_PW });
check("unknown email is refused with the same generic message", W.ui.staffStep === 0 && /Email or password is incorrect/.test(app.innerHTML));
W.forms.staff0({ email: "Editor@Example.test", password: W.SAMPLE_PW });
check("right email and password go to the two-factor step", W.ui.staffStep === 1 && S().role === "visitor");
W.forms.staff1({ code: "111111" });
check("wrong code is refused and no role is set", S().role === "visitor" && /code is wrong/.test(app.innerHTML));
W.forms.staff1({ code: "000000" });
check("role is resolved from the email (Editor)", S().role === "editor" && S().user === "editor@example.test" && W.persona() === "Sample Editor");
W.act.signout(); check("sign out returns to Visitor", S().role === "visitor" && S().user === null);
W.forms.staff0({ email: "admin@example.test", password: W.SAMPLE_PW }); W.forms.staff1({ code: "000000" });
check("role is resolved from the email (Administrator)", S().role === "admin");
ctx.location.hash = "#/admin"; W.render();
check("no signed-in staff screen has a role selector for the user's own role", !/name="role"/.test(app.innerHTML));
ctx.location.hash = "#/admin/users"; W.render();
check("Users and roles shows emails and an Add user form with one role", /admin@example\.test/.test(app.innerHTML) && /data-form="addUser"/.test(app.innerHTML) && (app.innerHTML.match(/name="role"/g) || []).length === 1);
const nUsers = S().users.length;
W.forms.addUser({ name: "Sample New", email: "EDITOR@example.test", role: "reviewer" });
check("duplicate email is refused (case-insensitive) with the one-role message", S().users.length === nUsers && /already belongs to a user; an email can have only one role/.test(app.innerHTML));
W.forms.addUser({ name: "Sample New", email: "new.user@example.test", role: "reviewer" });
const nu = S().users.filter((u) => u.email === "new.user@example.test");
check("a new user is added with exactly one role and two-factor not yet enrolled", S().users.length === nUsers + 1 && nu.length === 1 && nu[0].rk === "reviewer" && typeof nu[0].rk === "string" && nu[0].mfa === false);
W.act.setRole("new.user@example.test|editor");
check("changing a user's role replaces it (one role) and is audit-logged", nu[0].rk === "editor" && S().audit.some((a) => a.action === "user.role" && a.target === "new.user@example.test"));
W.act.setRole("admin@example.test|reviewer");
check("an Administrator cannot change their own role", S().users.filter((u) => u.email === "admin@example.test")[0].rk === "admin");
S().role = "editor"; S().user = "editor@example.test"; W.act.setRole("new.user@example.test|admin");
check("a non-Administrator cannot change roles", nu[0].rk === "editor");
S().role = "visitor"; S().user = null;
// audit chain
let prev = "00000000", okc = true; S().audit.forEach((r) => { if (r.prev !== prev) okc = false; prev = r.hash; }); check("audit chain", okc);
console.log(n + " checks, " + fails + " failures");
process.exit(fails ? 1 : 0);
