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
reload(); S().kill = { on: true, by: "x", reason: "y" }; renderAll("kill-on");
reload(); S().profiles.forEach((p) => { if (p.status === "published") p.status = "unpublished"; }); renderAll("all-unpublished");
// action scenarios
reload(); S().role = "reviewer";
W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve blocked by scan", S().profiles.find((p) => p.id === "b5").status === "pending");
W.act.clearScan("b5|0"); W.act.rmFact("b5|2"); W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve works", S().profiles.find((p) => p.id === "b5").status === "published");
check("approved in sitemap", W.publicProfiles().some((p) => p.id === "b5"));
W.forms.unpub({ id: "b6", reason: "test" }); check("unpublish -> gone", W.vis(W.profile("b6")) === "gone"); check("purge logged", S().purges.length >= 1);
S().role = "eic"; W.forms.killon({ c: "KILL", reason: "t" }); check("kill hides imported individuals, even Verified or Featured", !W.isPublic(W.profile("ip2")) && !W.isPublic(W.profile("ip4")) && !W.isPublic(W.profile("ip5")));
check("kill leaves customer-submitted individuals and organisations", W.isPublic(W.profile("p-a")) && W.isPublic(W.profile("b1")));
W.act.killOff(); check("kill restored", W.isPublic(W.profile("ip2")));
// register counting
reload(); const a = S().arrangements; check("seed counts", a.filter(W.counts).length === 2, a.filter(W.counts).map((x) => x.id).join());
check("complimentary never counts", !W.counts(a.find((x) => x.id === "arr5")));
check("sponsor label via arrangement", W.sponsorOf(W.profile("b2")) === "Sample Fintech Ltd.");
W.act.arrEnd("arr1"); check("label removed when ended", W.sponsorOf(W.profile("b2")) === null); check("trust unchanged", W.profile("b2").trust === "verified");
// leads
S().role = "sales"; W.forms.lead({ name: "N", org: "O", interest: "x", contact: "c", msg: "m" }); check("lead added", S().leads.length === 4); W.act.leadConv(S().leads[0].id); check("lead converted", S().arrangements.length === 6);
// nominations
reload(); S().role = "eic"; W.act.nomStep("n2|screen"); W.act.nomStep("n2|eligible"); check("invited", W.find("nominations", "n2").status === "invited");
const nn = W.find("nominations", "n2"); W.act.nomDeclineYes(nn.token); check("decline suppresses", W.profile("b1").status === "suppressed" && S().suppression.some((s) => s.name === "Sample Fintech Ltd." || s.name === "Sample Agritech Co."));
// D-034: no public accounts
check("no login/signup/account/judge routes", !W.routes.some((r) => /^\/(login|signup|account|judge|forgot|reset|verify)(\/|$)/.test(r.pattern)), W.routes.filter((r) => /^\/(login|signup|account|judge|forgot|reset|verify)/.test(r.pattern)).map((r) => r.pattern).join());
check("no owner or judge role", !roles.includes("owner") && !roles.includes("judge") && roles.includes("awards"));
check("seven staff roles + visitor", roles.length === 8, roles.join());
for (const x of ["#/login", "#/signup", "#/account", "#/judge", "#/forgot"]) { reload(); S().role = "visitor"; check("route gone " + x, W.match(x).r === W.notFound); }
// D-036: no claiming at all
check("no claim routes or screens", !W.routes.some((r) => /claim/i.test(r.pattern)) && W.match("#/claim/sample-university-a").r === W.notFound && W.match("#/admin/claims").r === W.notFound);
check("no claims in state", S().claims === undefined);
// D-037: the only feature flag is nominations
check("only the nominations flag exists", Object.keys(S().flags).join() === "nominations");
check("no flags screen entries for the removed flags", W.match("#/admin/ai").r === W.notFound);
// D-038: no source registry in the website admin
check("sources screen gone", W.match("#/admin/sources").r === W.notFound && S().sources === undefined);
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
S().role = "reviewer"; ctx.location.hash = "#/admin/inbox"; W.render();
check("inbox shows Correction, Removal and Report with a due countdown", /Correction/.test(app.innerHTML) && /Removal/.test(app.innerHTML) && /Report/.test(app.innerHTML) && /due in|overdue|due today/.test(app.innerHTML));
const before = S().audit.length; W.forms.profedit({ id: "b1", name: "Sample Agritech Co.", title: "New Title", org: undefined, website: "https://example.org/sample-agritech", industry: "Agriculture & Agritech", country: "Nigeria", city: "Lagos", orgType: "Company", bio: "b", achievements: "a", reason: "request", item: cr.id });
check("staff edit goes live at once and is audited", W.profile("b1").title === "New Title" && S().audit.length > before && S().audit.some((a) => a.action === "profile.edit") && cr.status === "closed");
W.act.rmOk(S().removals[0].id); check("valid removal hides and suppresses", W.profile("ip3").status === "suppressed" && S().suppression.some((x) => x.name === "Sample Person C"));
// REQ-069: export is Administrator-only and logged
S().role = "reviewer"; const exp0 = S().audit.length; W.act.dl("profiles"); check("reviewer cannot export", S().audit.some((a) => a.action === "export.denied") && !S().audit.some((a) => a.action === "export.profiles"));
S().role = "admin"; W.act.dl("profiles"); check("administrator export is logged", S().audit.some((a) => a.action === "export.profiles"));
ctx.location.hash = "#/admin/exports"; S().role = "reviewer"; W.render(); check("exports screen 403 for reviewer", /403/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/leads"; W.render(); check("reviewer has no leads screen", /403/.test(app.innerHTML));
S().role = "sales"; ctx.location.hash = "#/admin/leads"; W.render(); check("sales sees no export button, only the note", /Administrator only/.test(app.innerHTML) && !/data-arg="leads"/.test(app.innerHTML));
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
for (const role of ["admin", "finance", "sales"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard shows sprint target and revenue per stream", /Sprint target/.test(app.innerHTML) && /Revenue per stream/.test(app.innerHTML)); }
for (const role of ["reviewer", "editor", "eic", "awards"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard hides the sprint target", !/Sprint target/.test(app.innerHTML)); }
// D-041: left menu by role
const REVIEWER_MENU = ["Dashboard", "Review queue", "Duplicates", "Stale records", "Profiles", "Suppression list", "Support inbox"];
check("reviewer menu is exactly the seven screens", JSON.stringify(W.menuFor("reviewer")) === JSON.stringify(REVIEWER_MENU), W.menuFor("reviewer").join());
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render(); { const side = (app.innerHTML.match(/<nav class="app-side"[\s\S]*?<\/nav>/) || [""])[0]; const links = [...side.matchAll(/<a href="#[^"]*"[^>]*>([^<]+)<\/a>/g)].map((m) => m[1]); check("reviewer left menu renders exactly those links", JSON.stringify(links) === JSON.stringify(REVIEWER_MENU), links.join()); }
check("administrator menu includes everything", ["Import", "Taxonomies", "Exports", "Roles and permissions", "Feature flag", "Shortlist and winners"].every((x) => W.menuFor("admin").concat(Object.values(W.TAXES).map((t) => "Taxonomies")).indexOf(x) >= 0));
check("no Claim verification, Sources or AI drafting in any menu", !["reviewer", "editor", "sales", "finance", "eic", "awards", "admin"].some((r) => W.menuFor(r).some((x) => /claim|source|ai drafting|controlled lists|corrections and removals|reports/i.test(x))));
for (const [role, path] of [["reviewer", "#/admin/import"], ["reviewer", "#/admin/taxonomy/industries"], ["reviewer", "#/admin/flags"], ["reviewer", "#/admin/roles"], ["editor", "#/admin/review"], ["finance", "#/admin/inbox"], ["awards", "#/admin/profiles"], ["sales", "#/admin/import"]]) { S().role = role; ctx.location.hash = path; W.render(); check("403 for " + role + " on " + path, /403/.test(app.innerHTML)); }
S().role = "admin"; ctx.location.hash = "#/admin/roles"; W.render(); check("roles screen lists the menu per role and the export row", /Left menu by role/.test(app.innerHTML) && /Export personal data/.test(app.innerHTML) && /Taxonomy terms: add and edit/.test(app.innerHTML));
// kill-switch screen copy
S().role = "eic"; ctx.location.hash = "#/admin/killswitch"; W.render(); check("kill switch screen says what it unpublishes", /imported individual profile/.test(app.innerHTML) && /even if it was later made Verified/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/flags"; W.render(); check("flag screen shows nominations and the kill switch apart", /nominations/.test(app.innerHTML) && /Kill switch \(not a flag\)/.test(app.innerHTML) && !/ai_drafting|announcements flag|judging flag/i.test(app.innerHTML));

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
reload(); S().role = "awards";
check("score blocked for conflicted judge", W.recordScore("n5", "Sample Judge 2", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("conflict") >= 0 && !(((S().scores.n5 || {}).by || {})["Sample Judge 2"]));
W.forms.scoregrid({ id: "n5", s_0_k1: 6, s_0_k2: 6, s_0_k3: 6, s_0_k4: 6, s_1_k1: 9, s_1_k2: 9, s_1_k3: 9, s_1_k4: 9 }); const by5 = (S().scores.n5 || {}).by || {};
check("grid saves allowed judge, drops conflicted judge", by5["Sample Judge 1"] && !by5["Sample Judge 2"] && by5["Sample Judge 1"].by === "Sample Awards Manager");
check("weighted total", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }) === "7.9", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }));
check("score range enforced", W.recordScore("n4", "Sample Judge 1", { k1: 11, k2: 5, k3: 5, k4: 5 }).indexOf("0 to 10") >= 0);
S().role = "reviewer"; check("reviewer cannot enter scores", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("only the Awards") === 0);
S().role = "awards"; check("scores can be entered with no flag", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }) === "");
ctx.location.hash = "#/admin/scores/n5"; W.render(); check("conflict row has no score inputs", app.innerHTML.includes("Conflict recorded") && app.innerHTML.includes('name="s_0_k1"') && !app.innerHTML.includes('name="s_1_k1"'));
W.forms.judgecoi({ judge: "Sample Judge 1", n: "n4", why: "x" }); check("recording a conflict removes the judge's score", !(((S().scores.n4 || {}).by || {})["Sample Judge 1"]));
S().role = "admin"; W.find("nominations", "n4").status = "assessment"; W.act.nomStep("n4|shortlist"); check("shortlist via score page flow", W.find("nominations", "n4").status === "shortlisted");
// shortlist and winners publish through the normal publish step (no flag)
reload(); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("shortlisted nominee page is not public until staff publish it", /404|Page not found/.test(app.innerHTML));
S().role = "awards"; W.act.pubNom("n1"); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("nominee page public after publish", /Shortlisted/.test(app.innerHTML) && W.nomPublic(W.find("nominations", "n1")));
S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); check("winners empty until announcement published", /Winners will be announced/.test(app.innerHTML));
S().role = "admin"; W.find("nominations", "n1").status = "winner"; W.act.publishWin(); S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); check("winners page after publish", /Sample Fintech Ltd\./.test(app.innerHTML));
// no Claimed or Unclaimed anywhere in rendered pages, for every role and route
reload(); { let bad = 0; for (const role of Object.keys(W.LISTS.roles)) for (const x of W.INV) { S().role = role; ctx.location.hash = x.route; W.render(); const t = app.innerHTML.replace(/<[^>]+>/g, " "); if (/\bun?claimed\b/i.test(t) || /Claim this profile|Request to claim|\bclaim verification\b/i.test(t)) { bad++; console.log("CLAIM TEXT", role, x.id); } } check("no Claimed/Unclaimed/claim feature text on any screen", bad === 0); }
// role access
reload(); S().role = "awards"; ctx.location.hash = "#/admin/review"; W.render(); check("awards manager cannot open review queue", /403/.test(app.innerHTML));
ctx.location.hash = "#/admin/scores"; W.render(); check("awards manager opens scores", /Scores and results/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/scores"; W.render(); check("reviewer cannot open scores", /403/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin"; W.render(); check("visitor gets 403 on admin", /403/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/roles"; W.render(); check("roles matrix opens for the Administrator", /Roles and permissions/.test(app.innerHTML) && /Awards manager/.test(app.innerHTML));
// flows: every start route resolves and every inventory id exists
for (const f of W.FLOWS) {
  for (const id of f.inv) check("flow inventory id " + f.t + " " + id, !!W.invById(id), id);
  reload(); S().role = f.role; ctx.location.hash = f.go; app.innerHTML = ""; try { W.render(); } catch (e) { check("flow start " + f.t, false, e.stack.split("\n")[0]); continue; }
  check("flow start nonempty " + f.t, app.innerHTML.length > 200); if (!/^1\./.test(f.t)) check("flow start not 404 " + f.t, !/404 · Page not found/.test(app.innerHTML));
  check("flow role exists " + f.t, roles.includes(f.role), f.role);
}
check("19 flows", W.FLOWS.length === 19);
// audit chain
let prev = "00000000", okc = true; S().audit.forEach((r) => { if (r.prev !== prev) okc = false; prev = r.hash; }); check("audit chain", okc);
console.log(n + " checks, " + fails + " failures");
process.exit(fails ? 1 : 0);
