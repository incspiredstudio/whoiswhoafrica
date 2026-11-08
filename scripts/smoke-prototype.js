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
reload(); S().flags.nominations = { on: true, ref: "x" }; S().flags.judging = { on: true, ref: "x" }; S().flags.announcements = { on: true, ref: "x" }; S().flags.ai_drafting = { on: true, ref: "x" }; renderAll("flags-on");
reload(); S().kill = { on: true, by: "x", reason: "y" }; renderAll("kill-on");
reload(); S().profiles.forEach((p) => { if (p.status === "published") p.status = "unpublished"; }); renderAll("all-unpublished");
// action scenarios
reload(); S().role = "reviewer";
W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve blocked by scan", S().profiles.find((p) => p.id === "b5").status === "pending");
W.act.clearScan("b5|0"); W.act.rmFact("b5|2"); W.forms.approve({ id: "b5", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approve works", S().profiles.find((p) => p.id === "b5").status === "published");
check("approved in sitemap", W.publicProfiles().some((p) => p.id === "b5"));
W.forms.unpub({ id: "b6", reason: "test" }); check("unpublish -> gone", W.vis(W.profile("b6")) === "gone"); check("purge logged", S().purges.length >= 1);
S().role = "eic"; W.forms.killon({ c: "KILL", reason: "t" }); check("kill hides unclaimed individuals", !W.isPublic(W.profile("ip2")) && W.isPublic(W.profile("ip4")));
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
// request to claim -> email link -> staff verification; only staff set Claimed
reload(); S().role = "visitor";
W.forms.claim({ slug: "sample-university-a", name: "N", relation: "Authorised representative of the organisation", email: "n@example.org", phone: "", route: "Document", statement: "s", ok: "1" });
const cl = S().claims[0]; check("claim awaits email check", cl.status === "awaiting email check" && !cl.emailVerified);
check("claim submit does not set Claimed", W.profile("i1").trust === "unclaimed");
check("cannot decide before email confirmed", W.decideClaim(cl.id, "approve", "x") === "not open");
ctx.location.hash = "#/confirm/" + cl.token; W.render(); check("email link moves claim to queue", cl.status === "pending" && cl.emailVerified && S().used[cl.token]);
check("email link does not set Claimed", W.profile("i1").trust === "unclaimed");
S().role = "reviewer"; check("reason required", W.decideClaim(cl.id, "approve", "") === "reason required");
check("dispute: reviewer cannot approve", W.decideClaim(cl.id, "approve", "ok").indexOf("dispute") === 0 && W.profile("i1").trust === "unclaimed");
S().role = "eic"; check("EIC approves", W.decideClaim(cl.id, "approve", "evidence checked") === "" && W.profile("i1").trust === "claimed");
check("claim approval logged with reason", S().audit.some((a) => a.action === "claim.approve" && /evidence checked/.test(a.detail)));
ctx.location.hash = "#/confirm/zzz"; W.render(); check("unknown link -> expired page", /expired or already used/.test(app.innerHTML));
reload(); S().role = "visitor"; for (let k = 0; k < 4; k++) W.forms.claim({ slug: "sample-university-a", name: "N", relation: "Authorised representative of the organisation", email: "n@example.org", route: "Document", statement: "s" });
check("claim rate limit", S().claims.filter((c) => c.claimant === "N").length === 3);
// owner correction request -> staff -> review; second person approves
reload(); S().role = "visitor";
W.forms.correct({ id: "p-a", who: "The person named on this profile", need: "Correct a fact", part: "title", detail: "New Title", evidence: "", contact: "x@example.org" });
const cr = S().corrections[0]; check("owner request awaits email", cr.status === "awaiting email check");
ctx.location.hash = "#/confirm/" + cr.token; W.render(); check("owner request confirmed", cr.status === "open" && cr.emailVerified);
S().role = "reviewer"; check("apply sends to review", W.applyCorrection(cr.id) === "" && W.profile("p-a").edit.title === "New Title" && W.profile("p-a").title === "Chief Sample Officer");
const ap = { id: "p-a", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }; W.forms.approve(ap); check("same person cannot approve own change", W.profile("p-a").title === "Chief Sample Officer");
S().role = "eic"; W.forms.approve(ap); check("second person approves", W.profile("p-a").title === "New Title");
reload(); S().role = "visitor"; W.forms.correct({ id: "b1", who: "Someone else (a visitor)", need: "Correct a fact", part: "other", detail: "d", contact: "v@example.org" }); check("visitor correction needs no confirmation", S().corrections[0].status === "open");
// customer intake by single-use link
reload(); S().role = "visitor"; W.act.startInvite("demo"); check("intake draft held against link", !!S().intake.demo);
W.forms.profile({ token: "demo", type: "business", name: "Sample Organisation B", title: "t", industry: "Technology", city: "Lagos", bio: "Clean", achievements: "", warranty: "1", email: "c@example.org" }, null, { submitter: { value: "submit" } });
check("intake submit -> pending and link used", W.profile("p-new").status === "pending" && S().used.demo && W.profile("p-new").trust === "unclaimed");
ctx.location.hash = "#/invite/demo"; W.render(); check("invite link single use", /expired or already used/.test(app.innerHTML));
S().role = "reviewer"; W.forms.approve({ id: "p-new", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("staff approval publishes and sets Claimed", W.profile("p-new").status === "published" && W.profile("p-new").trust === "claimed");
reload(); S().role = "visitor"; W.act.startInvite("demo"); W.forms.profile({ token: "demo", type: "individual", name: "S", title: "t", industry: "Technology", city: "Lagos", bio: "Call 0803 123 4567", achievements: "", warranty: "1", adult: "1", email: "c@example.org" }, null, { submitter: { value: "submit" } }); check("intake scan blocks (no throw, still draft)", W.profile("p-new").status === "draft");
// nominator email link
reload(); S().flags.nominations = { on: true, ref: "x" }; ctx.location.hash = "#/nominate"; W.forms.nom0({ name: "A", email: "a@example.org", rel: "Colleague", ok: "1" }); check("nominator waits for link", W.ui.nom.step === 1 && S().emails[0].link === "#/nominate/verify");
ctx.location.hash = "#/nominate/verify"; W.render(); check("nominator link verifies", W.ui.nom.d.verified && W.ui.nom.step === 2);
// offline judging: staff enter scores; conflicts block
reload(); S().flags.judging = { on: true, ref: "r" }; S().role = "awards";
check("score blocked for conflicted judge", W.recordScore("n5", "Sample Judge 2", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("conflict") >= 0 && !(((S().scores.n5 || {}).by || {})["Sample Judge 2"]));
W.forms.scoregrid({ id: "n5", s_0_k1: 6, s_0_k2: 6, s_0_k3: 6, s_0_k4: 6, s_1_k1: 9, s_1_k2: 9, s_1_k3: 9, s_1_k4: 9 }); const by5 = (S().scores.n5 || {}).by || {};
check("grid saves allowed judge, drops conflicted judge", by5["Sample Judge 1"] && !by5["Sample Judge 2"] && by5["Sample Judge 1"].by === "Sample Awards Manager");
check("weighted total", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }) === "7.9", W.calcScore({ k1: 8, k2: 7, k3: 9, k4: 8 }));
check("score range enforced", W.recordScore("n4", "Sample Judge 1", { k1: 11, k2: 5, k3: 5, k4: 5 }).indexOf("0 to 10") >= 0);
S().role = "reviewer"; check("reviewer cannot enter scores", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }).indexOf("only the Awards") === 0);
S().role = "awards"; S().flags.judging.on = false; check("scores blocked while flag OFF", W.recordScore("n4", "Sample Judge 1", { k1: 5, k2: 5, k3: 5, k4: 5 }) === "the judging flag is OFF");
S().flags.judging.on = true; ctx.location.hash = "#/admin/scores/n5"; W.render(); check("conflict row has no score inputs", app.innerHTML.includes("Conflict recorded") && app.innerHTML.includes('name="s_0_k1"') && !app.innerHTML.includes('name="s_1_k1"'));
W.forms.judgecoi({ judge: "Sample Judge 1", n: "n4", why: "x" }); check("recording a conflict removes the judge's score", !(((S().scores.n4 || {}).by || {})["Sample Judge 1"]));
S().role = "admin"; W.find("nominations", "n4").status = "assessment"; W.act.nomStep("n4|shortlist"); check("shortlist via score page flow", W.find("nominations", "n4").status === "shortlisted");
// role access
reload(); S().role = "awards"; ctx.location.hash = "#/admin/review"; W.render(); check("awards manager cannot open review queue", /403/.test(app.innerHTML));
ctx.location.hash = "#/admin/scores"; W.render(); check("awards manager opens scores", /Scores and results/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/scores"; W.render(); check("reviewer cannot open scores", /403/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin"; W.render(); check("visitor gets 403 on admin", /403/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/roles"; W.render(); check("roles matrix opens for staff", /Roles and permissions/.test(app.innerHTML) && /Awards manager/.test(app.innerHTML));
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
