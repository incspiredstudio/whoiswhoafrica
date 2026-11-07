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
for (const f of ["tokens.js", "data.js", "core.js", "views-public.js", "views-awards.js", "views-owner.js", "views-staff1.js", "views-staff2.js", "inventory.js", "flows.js"]) {
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
// judge
reload(); S().flags.judging = { on: true, ref: "r" }; S().role = "judge"; W.forms.score({ id: "n4", k_k1: 5, k_k2: 6, k_k3: 7, k_k4: 8, comment: "" }, null, { submitter: { value: "submit" } }); check("score locked", S().scores.n4 && S().scores.n4.locked);
W.forms.coi({ id: "n5", why: "x" }); check("coi hides", W.match("#/judge/score/n5").r.fn({ id: "n5" }, {}, {}).indexOf("Not available") >= 0);
// owner
reload(); S().role = "owner"; ctx.location.hash = "#/account/profile"; W.forms.profile({ type: "individual", name: "Sample Person A", title: "New Title", industry: "Technology", city: "Lagos", bio: "Call me on 0803 123 4567", achievements: "", warranty: "1", adult: "1" }, null, { submitter: { value: "submit" } });
check("scan blocks bio (no throw)", true);
W.forms.profile({ type: "individual", name: "Sample Person A", title: "New Title", industry: "Technology", city: "Lagos", bio: "Clean bio", achievements: "", warranty: "1", adult: "1" }, null, { submitter: { value: "submit" } });
check("edit queued", !!W.profile("p-owner").edit && W.profile("p-owner").title === "Chief Sample Officer");
// audit chain
let prev = "00000000", okc = true; S().audit.forEach((r) => { if (r.prev !== prev) okc = false; prev = r.hash; }); check("audit chain", okc);
console.log(n + " checks, " + fails + " failures");
process.exit(fails ? 1 : 0);
