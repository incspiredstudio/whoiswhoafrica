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
for (const f of ["tokens.js", "data.js", "core.js", "theme/hero-geo.js", "theme/components.js", "views-public.js", "views-awards.js", "theme/home-view.js", "views-links.js", "views-staff1.js", "views-staff2.js", "inventory.js", "flows.js"]) {
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
S().role = "visitor"; ctx.location.hash = "#/people"; W.render(); check("people list drops the bulk-unpublished profiles", !/Tunde Achebe-Wanjala/.test(app.innerHTML));
S().role = "reviewer"; W.act.bulkRestore(S().bulkLog[0].id); check("restore this batch brings them back", W.isPublic(W.profile("ip2")) && W.isPublic(W.profile("ip4")) && S().bulkLog[0].restored);
W.forms.bulk({ f: "Annual Report~~B-2026-11-06~~~", reason: "x" }); check("bulk by source and batch", !W.isPublic(W.profile("ip8")) && W.isPublic(W.profile("ip2")) && W.profile("ip8").bulkId);
// sponsored story deals (REQ-263, REQ-269, REQ-022): one arrangement type, four steps, counting rule
reload(); const a = S().arrangements; S().role = "admin";
check("seed counts", a.filter(W.counts).length === 3, a.filter(W.counts).map((x) => x.id).join());
check("the only arrangement type is the sponsored post", Object.keys(W.LISTS.arrTypes).join() === "sponsored_post" && a.every((x) => x.type === "sponsored_post"));
check("seed deals cover every status", ["needs payment", "needs story", "live", "scheduled", "ended"].every((s) => a.some((x) => W.dealStatus(x) === s)), a.map(W.dealStatus).join());
check("a live deal without a payment record does not count", W.dealStatus(a.find((x) => x.id === "arr2")) === "live" && !W.counts(a.find((x) => x.id === "arr2")));
check("a profile is never Sponsored, even with a counted deal (D-055)", W.sponsorOf(W.profile("b2")) === null);
W.act.dealCancel("arr1");  check("a cancelled deal no longer counts and the profile trust is unchanged", !W.counts(a.find((x) => x.id === "arr1")) && W.dealStatus(a.find((x) => x.id === "arr1")) === "cancelled" && W.profile("b2").trust === "verified");
// enquiries and customers
S().role = "editor"; W.forms.bizenquiry({ name: "N", org: "O", interest: "Awards sponsorship", contact: "c@example.org", msg: "m" }); check("enquiry lands in the Support inbox", S().enquiries.length === 5 && S().enquiries[0].business && S().enquiries[0].kind === "Awards sponsorship"); ctx.location.hash = "#/admin/inbox"; W.render(); check("inbox shows the enquiry with its kind", /Awards sponsorship/.test(app.innerHTML));
S().role = "admin"; W.forms.customer({ name: "New Co", segment: "Business", contact: "x" }); check("customer created directly", S().customers.length === 6 && S().customers[S().customers.length - 1].name === "New Co"); check("no leads or prospects state", S().leads === undefined && S().prospects === undefined);
// nominations
reload(); S().role = "editor"; check("no invite UI, no nominee token route and no token on any nomination", !W.act.nomStep && !W.act.nomAccept && !W.act.nomDeclineYes && !W.routes.some((r) => /^\/nominee\//.test(r.pattern)) && S().nominations.every((n) => !("token" in n)));
ctx.location.hash = "#/admin/nominations/n2"; W.render(); check("nomination detail has no Invite nominee, no Recorded-by line and no Shortlisted hint", !/Invite nominee|Open the nominee link|Recorded by|Shortlisted, Finalist/.test(app.innerHTML));
check("no old stage names in the admin Nominations screens", (() => { ctx.location.hash = "#/admin/nominations"; W.render(); const a = app.innerHTML; ctx.location.hash = "#/admin/nominations/n2"; W.render(); return !/Submitted|Screened|Shortlisted|Finalist|Ineligible|In assessment|Nominee invited/.test(a + app.innerHTML); })());
check("no old nomination stage vocabulary anywhere in the prototype source", fs.readdirSync(dir).concat(fs.readdirSync(path.join(dir, "theme")).map((f) => "theme/" + f)).filter((f) => /\.js$/.test(f)).every((f) => { const s = fs.readFileSync(path.join(dir, f), "utf8"); return !/status: "(submitted|screening|shortlisted|finalist|invited|accepted|assessment|ineligible)"|nomStep|nomAccept|nomDeclineYes|pubNom|publishWin|PUBLICABLE|SETTABLE|n\.pub|shortlistBy|finalBy|screenedBy/.test(s); }));
// D-034: no public accounts
check("no login/signup/account/judge routes", !W.routes.some((r) => /^\/(login|signup|account|judge|forgot|reset|verify)(\/|$)/.test(r.pattern)), W.routes.filter((r) => /^\/(login|signup|account|judge|forgot|reset|verify)/.test(r.pattern)).map((r) => r.pattern).join());
check("no owner or judge role", !roles.includes("owner") && !roles.includes("judge"));
check("exactly four roles: visitor, reviewer, editor, administrator", roles.join() === "visitor,reviewer,editor,admin", roles.join());
check("removed roles are gone from the role names", !JSON.stringify(W.LISTS.roles).match(/Editor-in-Chief|Sales|Finance|Awards manager/));
for (const x of ["#/login", "#/signup", "#/account", "#/judge", "#/forgot"]) { reload(); S().role = "visitor"; check("route gone " + x, W.match(x).r === W.notFound); }
// D-036: no claiming at all
check("claim routes exist (D-057)", W.match("#/claim/oyo-crest-university").r !== W.notFound && W.match("#/admin/claims").r !== W.notFound);
check("claims are held in state, one new and one claimed profile (D-057)", Array.isArray(S().claims) && S().claims.some((c) => c.status === "new") && S().profiles.some((p) => p.claimed && p.manager));
// claim flow (D-057): approve sets Claimed and Verified; assign a user; the checkmark shows to staff only
reload(); S().role = "editor"; S().user = "editor@example.test"; { const c = S().claims.find((x) => x.status === "new"), p = W.profile(c.profileId); W.act.claimOk(c.id); check("approving a claim sets Claimed only", c.status === "approved" && p.claimed === true && p.trust !== "verified"); ctx.location.hash = "#/admin/profiles/" + p.id; W.render(); check("staff profile shows the checkmark and the assign form", /class="vcheck/.test(app.innerHTML) && /name="manager"/.test(app.innerHTML)); ctx.location.hash = "#/profile/" + p.slug; S().role = "visitor"; W.render(); check("a claimed profile shows no checkmark and no Claim button to visitors", !/vcheck/.test(app.innerHTML) && !/Claim this profile/.test(app.innerHTML)); ctx.location.hash = "#/profile/bonny-reach-logistics"; W.render(); check("an unclaimed profile offers Claim this profile", /Claim this profile/.test(app.innerHTML)); }
// D-037: the only feature flag is nominations
check("only the nominations flag exists", Object.keys(S().flags).join() === "nominations");
check("no flags screen entries for the removed flags", W.match("#/admin/ai").r === W.notFound);
// D-038: no source registry in the website admin
check("no source registry state in the site", S().sources === undefined);
// D-042: Sources is a read-only screen; every profile carries a source and an import batch (REQ-242, 243)
check("every profile has a source name; imports have a batch", S().profiles.every((p) => p.srcName && (W.origin(p) === "customer" || p.batch)));
reload(); for (const r of ["reviewer", "editor", "admin"]) { S().role = r; ctx.location.hash = "#/admin/sources"; W.render(); check("sources screen opens for " + r, /Sources/.test(app.innerHTML) && /National Company Registry/.test(app.innerHTML) && /Last import/.test(app.innerHTML) && /Import errors/.test(app.innerHTML) && /Removals/.test(app.innerHTML)); }
check("sources screen has no mutation controls", (() => { const i = app.innerHTML.indexOf("<h1>Sources</h1>"), j = app.innerHTML.indexOf("Import errors are rows"); const main = app.innerHTML.slice(i, j); return i > 0 && j > i && /Last import/.test(main) && !/<form|<button|data-act=|type="submit"|<input|<select/i.test(main); })());
check("sources screen says approval happens in the pipeline", /acquisition pipeline/.test(app.innerHTML) && /REQ-136 to 138/.test(app.innerHTML));
check("sources link filters profiles by source", /#\/admin\/profiles\?src=National%20Company%20Registry/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin/sources"; W.render(); check("visitor 403 on sources", /403/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/profiles?src=National%20Company%20Registry"; W.render(); check("profiles filter by source", /Savanna Loop Agritech/.test(app.innerHTML) && !/Tunde Achebe-Wanjala/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?batch=B-2026-11-05"; W.render(); check("profiles filter by import batch", /Tunde Achebe-Wanjala/.test(app.innerHTML) && !/Savanna Loop Agritech/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?group=src"; W.render(); check("profiles grouped by source", /Source: National Company Registry/.test(app.innerHTML) && /Source: Annual Report/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles?group=batch"; W.render(); check("profiles grouped by import batch", /Batch: B-2026-11-01/.test(app.innerHTML) && /Batch: B-2026-11-05/.test(app.innerHTML));
check("reviewer sees no unpublish, edit or bulk controls", !/data-act="unpubDlg"|data-act="bulkDlg"|Create on behalf/.test(app.innerHTML));
ctx.location.hash = "#/admin/profiles/b1"; W.render(); check("reviewer profile screen: terms only, no edit form", /Assign existing terms/.test(app.innerHTML) && !/data-form="profedit"/.test(app.innerHTML) && !/data-form="verify"/.test(app.innerHTML) && /source: National Company Registry/.test(app.innerHTML));
W.forms.profedit({ id: "b1", name: "Hacked", reason: "x" }); check("reviewer cannot edit by form", W.profile("b1").name === "Savanna Loop Agritech");
W.forms.profterms({ id: "b1", industry: "Technology" }); check("reviewer assigns an existing term", W.profile("b1").industry === "Technology");
reload(); S().role = "reviewer"; ctx.location.hash = "#/admin/review/b5"; W.render(); check("review screen shows each field's source URL and retrieval date, read-only", /Source \(URL\)/.test(app.innerHTML) && /Retrieved/.test(app.innerHTML) && /example\.org\/sources\//.test(app.innerHTML) && !/Approve source|Reject source/.test(app.innerHTML));
// D-039: taxonomies are native term screens; Reviewers cannot edit terms
check("controlled lists screen gone", W.match("#/admin/lists").r === W.notFound);
S().tax.industries.push({ name: "Mining", slug: "mining", parent: "" }); W.syncLists(); W.forms.impdry({ csv: W.ui.imp.csv, src: "x.csv" }); check("import dry run accepts a term once an Editor adds it", W.ui.imp.rows.every((r) => !/not a term/.test(r.why)));
check("country list is Nigeria-first and has other African countries and pan-African bodies", W.LISTS.countries[0] === "Nigeria" && W.LISTS.countries.length > 3 && W.LISTS.countries.some((c) => /Pan-African/.test(c)));
reload(); S().role = "visitor"; ctx.location.hash = "#/businesses"; W.render(); check("country filter is enabled and offers other countries", /Ghana/.test(app.innerHTML) && !/Nigeria only/i.test(app.innerHTML));
// D-069: Enquiries, Corrections and Requests and reports are three screens; staff still edit directly
check("Corrections and Requests and reports screens exist, no separate Reports screen", W.match("#/admin/corrections").r !== W.notFound && W.match("#/admin/dsr").r !== W.notFound && W.match("#/admin/reports").r === W.notFound);
reload(); S().role = "visitor";
W.forms.correct({ id: "b1", who: "The person named on this profile", need: "Correct a fact", part: "title", detail: "New Title", evidence: "", contact: "x@example.org" });
const cr = S().corrections[0]; check("correction is a typed Correction item awaiting email", cr.kind === "Correction" && cr.status === "awaiting email check");
ctx.location.hash = "#/confirm/" + cr.token; W.render(); check("email link opens the correction", cr.status === "open" && cr.emailVerified && S().used[cr.token]);
W.forms.remove({ id: "ip3", who: "I am the person", detail: "d", contact: "r@example.org" }); W.forms.report({ id: "b6", reason: "Other", detail: "d" });
S().role = "editor"; ctx.location.hash = "#/admin/inbox"; W.render();
check("Enquiries holds enquiries only: no Removal, Report or Correction rows", /Enquiries/.test(app.innerHTML) && !/pill bad">Removal/.test(app.innerHTML) && !/pill warn">Report/.test(app.innerHTML) && !/Suggestion/.test(app.innerHTML));
ctx.location.hash = "#/admin/corrections"; W.render();
check("Corrections lists the suggestion with a due countdown and no removals or reports", /Corrections/.test(app.innerHTML) && /headquarters is wrong/.test(app.innerHTML) && /due in|overdue|due today/.test(app.innerHTML) && !/Request removal/.test(app.innerHTML));
W.profile("p-a").claimed = true; ctx.location.hash = "#/admin/corrections/cr2"; W.render();
check("correction detail shows the claimed-profile review hint, Apply, Resolve and Decline", /goes to a Reviewer or Administrator for approval/.test(app.innerHTML) && /Apply: edit the profile/.test(app.innerHTML) && /Decline with a reason/.test(app.innerHTML));
ctx.location.hash = "#/admin/dsr"; W.render();
check("Requests and reports lists data requests, removals and reports together", /Requests and reports/.test(app.innerHTML) && /Data request/.test(app.innerHTML) && /pill bad">Removal/.test(app.innerHTML) && /pill warn">Report/.test(app.innerHTML));
ctx.location.hash = "#/admin/dsr/" + S().removals[0].id; W.render();
check("removal detail offers Valid and Not valid, with the 2 business day rule", /Valid: hide and suppress/.test(app.innerHTML) && /Not valid, with a reason/.test(app.innerHTML) && /2 business days/.test(app.innerHTML));
W.profile("p-a").claimed = false; W.forms.decline({ arg: "corrections:" + S().corrections[0].id, reason: "Not verifiable" }); check("decline records a reason and the status", S().corrections[0].status === "declined" && S().corrections[0].reason === "Not verifiable" && S().audit.some((a) => a.action === "request.decline"));
S().role = "reviewer"; ctx.location.hash = "#/admin/dsr"; W.render(); check("Reviewer has no Requests and reports by default: 403", /403/.test(app.innerHTML) && W.menuFor("reviewer").indexOf("Requests and reports") < 0);
S().role = "editor";
const before = S().audit.length; W.forms.profedit({ id: "b1", name: "Savanna Loop Agritech", title: "New Title", org: undefined, website: "https://example.org/sample-agritech", industry: "Agriculture & Agritech", country: "Nigeria", city: "Lagos", orgType: "Company", bio: "b", achievements: "a", reason: "request", item: cr.id });
check("staff edit goes live at once and is audited", W.profile("b1").title === "New Title" && S().audit.length > before && S().audit.some((a) => a.action === "profile.edit") && cr.status === "closed");
W.act.rmOk(S().removals[0].id); check("valid removal hides and suppresses", W.profile("ip3").status === "suppressed" && S().suppression.some((x) => x.name === "Halima Osei-Danjuma"));
// REQ-069: export is Administrator-only and logged
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
for (const role of ["admin"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard shows the sprint target and slots ending soon", /Sprint target/.test(app.innerHTML) && /Slots ending soon/.test(app.innerHTML) && !/Revenue per stream/.test(app.innerHTML)); }
S().role = "admin"; ctx.location.hash = "#/admin/thresholds"; W.render(); check("Sprint thresholds has the Show sprint tracking switch with the theme-setting note", /Show sprint tracking/.test(app.innerHTML) && /Theme settings/.test(app.innerHTML));
W.forms.sprintsw({}); ctx.location.hash = "#/admin"; W.render(); check("switching sprint tracking off hides the sprint card and is audited", S().sprintOn === false && !/Sprint target/.test(app.innerHTML) && S().audit.some((a) => a.action === "settings.sprint_tracking"));
W.forms.sprintsw({ on: "1" }); ctx.location.hash = "#/admin"; W.render(); check("switching it back on shows the sprint card again", S().sprintOn === true && /Sprint target/.test(app.innerHTML));
for (const role of ["reviewer", "editor"]) { S().role = role; ctx.location.hash = "#/admin"; W.render(); check(role + " dashboard hides the sprint target", !/Sprint target/.test(app.innerHTML)); }
// D-041: left menu by role
const REVIEWER_MENU = ["Dashboard", "Review queue", "Duplicates", "Profiles", "Sources", "Suppression list"];
check("reviewer menu is exactly the six screens (no Enquiries, no leads)", JSON.stringify(W.menuFor("reviewer")) === JSON.stringify(REVIEWER_MENU), W.menuFor("reviewer").join());
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render(); { const side = (app.innerHTML.match(/<nav class="app-side"[\s\S]*?<\/nav>/) || [""])[0]; const links = [...side.matchAll(/<a href="#[^"]*"[^>]*>([^<]+)<\/a>/g)].map((m) => m[1]); check("reviewer left menu renders exactly those links", JSON.stringify(links) === JSON.stringify(REVIEWER_MENU), links.join()); }
check("administrator menu includes everything", ["Import", "Sprint thresholds", "Award categories"].every((x) => W.menuFor("admin").concat(Object.values(W.TAXES).map((t) => "Taxonomies")).indexOf(x) >= 0));
check("no AI drafting, kill switch or corrections screen in any menu; Claims only for those with the capability", ["reviewer", "editor", "admin"].every((r) => !W.menuFor(r).some((x) => /ai drafting|kill|controlled lists/i.test(x))) && W.menuFor("editor").includes("Claims") && !W.menuFor("reviewer").includes("Claims"));
check("Sources is in the Reviewer, Editor and Administrator menus", ["reviewer", "editor", "admin"].every((r) => W.menuFor(r).indexOf("Sources") >= 0));
check("Editor menu has what the Reviewer has plus the Editor screens", REVIEWER_MENU.every((x) => W.menuFor("editor").indexOf(x) >= 0) && ["Enquiries", "Corrections", "Requests and reports", "Nominations"].every((x) => W.menuFor("editor").indexOf(x) >= 0) && !["Import", "Customers", "Exports", "Audit log", "Feature flag"].some((x) => W.menuFor("editor").indexOf(x) >= 0), W.menuFor("editor").join());
check("Administrator menu includes Sponsored stories, Customers, Import and Sprint thresholds", ["Sponsored stories", "Customers", "Import", "Sprint thresholds", "Audit log"].every((x) => W.menuFor("admin").indexOf(x) >= 0));
for (const r of ["claim", "ai", "killswitch"]) check("no route /admin/" + r, W.match("#/admin/" + r).r === W.notFound);
for (const [role, path] of [["reviewer", "#/admin/import"], ["reviewer", "#/admin/inbox"], ["reviewer", "#/admin/sponsors"], ["editor", "#/admin/import"], ["editor", "#/admin/customers"], ["editor", "#/admin/audit"], ["visitor", "#/admin/sources"]]) { S().role = role; ctx.location.hash = path; W.render(); check("403 for " + role + " on " + path, /403/.test(app.innerHTML)); }
for (const h of ["#/admin/flags", "#/admin/announcements"]) { ctx.location.hash = h; W.render(); check("route gone " + h, W.match(h).r === W.notFound); }
check("no nominations_flag capability and no Feature flag or Shortlist and winners menu items", !W.CAPS.some((c) => c[0] === "nominations_flag") && ["Feature flag", "Shortlist and winners", "Awards setup"].every((x) => W.menuFor("admin").indexOf(x) < 0));

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
// judging and scoring happen offline: no judge or score screens; staff shortlist and publish without scores
reload(); S().role = "editor";
check("no judge or score routes", !W.routes.some((r) => /^\/admin\/(judges|scores)/.test(r.pattern)));
check("no scoring or panel data in state", !("scores" in S()) && !("judgeAssign" in S()) && !("judgeCoi" in S()) && !("judgesPerCategory" in S().awards) && !("shortlistSize" in S().awards));
check("no score_entry or final_approval capability", !W.CAPS.some((c) => c[0] === "score_entry" || c[0] === "final_approval"));
check("editor menu has no scores or judging screens", ["Scores and results", "Judging panel and conflicts"].every((x) => W.menuFor("editor").indexOf(x) < 0 && W.menuFor("admin").indexOf(x) < 0));
for (const h of ["#/admin/scores", "#/admin/judges", "#/admin/scores/n4"]) { ctx.location.hash = h; W.render(); check("route gone " + h, W.match(h).r === W.notFound); }
S().role = "editor"; W.forms.nomstage({ id: "n4", stage: "winner", live: "1" }); check("a winner can be set live with no scores and no separate approval capability", W.find("nominations", "n4").stage === "winner" && W.find("nominations", "n4").live === true && W.find("nominations", "n4").stageBy === "Sample Editor");
reload(); S().role = "editor"; ctx.location.hash = "#/admin/awards"; W.render(); check("Award categories screen holds categories only", /Award categories/.test(app.innerHTML) && /data-form="catadd"/.test(app.innerHTML) && !/Eligibility criteria|Published judging criteria|weight|panel|Shortlist size/i.test(app.innerHTML));
{ const n0 = W.cats().length; W.forms.catadd({ g: "0", name: "Outstanding Mentor" }); check("admin adds a category and the public pages and nomination form read it", W.cats().length === n0 + 1 && W.cats().some((c) => c.name === "Outstanding Mentor"));
  S().role = "visitor"; ctx.location.hash = "#/awards"; W.render(); check("new category shows on the Awards page", /Outstanding Mentor/.test(app.innerHTML)); S().role = "editor";
  W.forms.catadd({ g: "0", name: "outstanding mentor" }); check("duplicate category is refused", W.cats().length === n0 + 1);
  W.forms.catrename({ g: "0", c: String(W.S.awards.groups[0].cats.length - 1), name: "Outstanding Mentor of the Year" }); check("category can be renamed", W.cats().some((c) => c.name === "Outstanding Mentor of the Year") && !W.cats().some((c) => c.name === "Outstanding Mentor"));
  const g0 = W.S.awards.groups[0]; const last = g0.cats.length - 1; const first = g0.cats[0]; W.act.catMove("0|" + last + "|-1"); check("category can be moved up", g0.cats[last - 1] === "Outstanding Mentor of the Year");
  W.act.catRemove("0|" + (last - 1)); check("an unused category can be removed", !W.cats().some((c) => c.name === "Outstanding Mentor of the Year") && W.cats().length === n0);
  const used = W.find("nominations", "n14").category; const gi = W.S.awards.groups.findIndex((g) => g.cats.indexOf(used) >= 0); W.act.catRemove(gi + "|" + W.S.awards.groups[gi].cats.indexOf(used)); check("a category with nominations cannot be removed", W.cats().some((c) => c.name === used)); }
// Nominations: the open/closed switch replaces the feature flag page (REQ-267)
reload(); S().role = "admin"; ctx.location.hash = "#/admin/nominations"; W.render(); check("Nominations screen has the Open/Closed switch, add profile and no old curated form", /id="nomSwitch"/.test(app.innerHTML) && /Add profile to nominations/.test(app.innerHTML) && !/Add curated nomination/.test(app.innerHTML));
W.forms.nomswitch({ to: "open", reason: "Launch" }); check("switch opens nominations and is audited", W.nomOpen() && W.S.audit.some((a) => a.action === "nominations.open"));
S().role = "visitor"; W.ui.nom = { step: 0, d: {} }; ctx.location.hash = "#/nominate"; W.render(); check("open: the Nominate page shows the form", /data-form="nom0"/.test(app.innerHTML));
S().role = "admin"; W.forms.nomswitch({ to: "close", reason: "" }); S().role = "visitor"; ctx.location.hash = "#/nominate"; W.render(); check("closed: the Nominate page says closed", !W.nomOpen() && /Nominations are closed/.test(app.innerHTML) && !/data-form="nom0"/.test(app.innerHTML));
S().role = "editor"; { const pid = W.S.profiles.find((p) => p.status === "published").id; const before = W.S.nominations.length; W.forms.nomprofile({ profile: W.profile(pid).name + " (" + pid + ")", category: W.cats()[0].name, email: "contact.test@example.org", note: "Staff added" });
  const nn = W.S.nominations[W.S.nominations.length - 1]; check("add profile creates a nomination linked to the profile, Nominated and Not live, with the contact email", W.S.nominations.length === before + 1 && nn.profileId === pid && nn.stage === "nominated" && nn.live === false && nn.nomineeEmail === "contact.test@example.org" && nn.category === W.cats()[0].name);
  W.forms.nomprofile({ profile: "Nobody (zz9)", category: W.cats()[0].name }); check("a nomination needs an existing profile", W.S.nominations.length === before + 1);
  W.forms.nomstage({ id: nn.id, stage: "winner", live: "1" }); check("stage and status set with the approver recorded and audited", nn.stage === "winner" && nn.live === true && nn.stageBy === "Sample Editor" && W.S.audit.some((a) => a.action === "nomination.stage"));
  W.forms.nomstage({ id: nn.id, stage: "banana", live: "1" }); check("only Nominated and Winner are accepted as stages", nn.stage === "winner");
  W.act.nomLive(nn.id); check("Make not live toggles the status and is audited", nn.live === false && W.S.audit.some((a) => a.action === "nomination.notlive"));
  const mails0 = W.S.emails.length, audit0 = W.S.audit.length; W.act.nomMail(nn.id);
  check("Send nomination email goes through the outbox, records who and when, and is audited without the address", W.S.emails.length === mails0 + 1 && W.S.emails[0].to === "contact.test@example.org" && nn.mailedAt === W.TODAY && nn.mailedBy === "Sample Editor" && W.S.audit.length === audit0 + 1 && !JSON.stringify(W.S.audit).includes("contact.test@example.org"));
  W.forms.nomprofile({ profile: W.profile(pid).name + " (" + pid + ")", category: W.cats()[1].name, note: "No email" }); const n2 = W.S.nominations[W.S.nominations.length - 1]; const m1 = W.S.emails.length; W.act.nomMail(n2.id);
  ctx.location.hash = "#/admin/nominations/" + n2.id; W.render(); check("without a contact email the send button is disabled and nothing is sent", W.S.emails.length === m1 && /disabled title="Add a contact email first"/.test(app.innerHTML) && /No contact email on file/.test(app.innerHTML));
  W.forms.nomstage({ id: n2.id, stage: "winner", live: "1" }); const m2 = W.S.emails.length; W.act.winMail(n2.id); check("winner email needs the contact email too", W.S.emails.length === m2 && !n2.notified);
  W.act.nomMail(nn.id); W.forms.nomstage({ id: nn.id, stage: "winner", live: "1" }); W.act.winMail(nn.id); check("Prepare winner email uses the contact email", nn.notified === true && W.S.emails[0].to === "contact.test@example.org" && /Congratulations/.test(W.S.emails[0].subject));
  const cnt = W.S.nominations.length; W.act.nomRemoveYes(nn.id); check("Remove deletes the nomination and keeps an audit entry", W.S.nominations.length === cnt - 1 && !W.find("nominations", nn.id) && W.S.audit.some((a) => a.action === "nomination.remove")); }
ctx.location.hash = "#/admin/nominations/n4"; W.render(); check("nomination detail has stage (Nominated, Winner only) and status selects", /data-form="nomstage"/.test(app.innerHTML) && /<option value="winner"/.test(app.innerHTML) && /<option value="nominated"/.test(app.innerHTML) && (app.innerHTML.match(/name="stage"[\s\S]*?<\/select>/)[0].match(/<option /g) || []).length === 2 && />Live</.test(app.innerHTML) && />Not live</.test(app.innerHTML));
ctx.location.hash = "#/admin/nominations"; W.render(); check("nominations list shows Ref, Nominee, Category, Stage, Status and filters by category, stage and status", /<th[^>]*>Stage/.test(app.innerHTML) && /<th[^>]*>Status/.test(app.innerHTML) && /name="c"/.test(app.innerHTML) && /name="s"/.test(app.innerHTML) && /name="l"/.test(app.innerHTML) && !/Publish winners announcement/.test(app.innerHTML));
ctx.location.hash = "#/admin/nominations?l=1&s=winner"; W.render(); check("the list filters by status and stage", /NOM-0003/.test(app.innerHTML) && !/NOM-0007/.test(app.innerHTML) && !/NOM-0004/.test(app.innerHTML));
ctx.location.hash = "#/admin/nominations/n4"; W.render(); check("nomination screen shows no commercial data", !/payment|subscription|sponsor(ship)? amount|customer/i.test(app.innerHTML.replace(/<div class="infobox">[\s\S]*?<\/div>/, "")));
// Live status gates every public nomination page; a Live Winner is public as a winner
reload(); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("a Not live nominee page is not public", /Page not found/.test(app.innerHTML));
S().role = "editor"; W.act.nomLive("n1"); S().role = "visitor"; ctx.location.hash = "#/awards/nominee/NOM-0001"; W.render(); check("a Live nominee page is public", /Nominee/.test(app.innerHTML) && W.nomPublic(W.find("nominations", "n1")));
ctx.location.hash = "#/awards/nominees"; W.render(); check("the Nominees page lists the Live nominee", /awards\/nominee\/NOM-0001/.test(app.innerHTML));
S().role = "editor"; W.act.nomLive("n1"); S().role = "visitor"; ctx.location.hash = "#/awards/nominees"; W.render(); check("making it Not live takes it off the Nominees page", !/awards\/nominee\/NOM-0001/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners"; W.render(); const wc0 = Number((/(\d+) winners?/.exec(app.innerHTML) || [0, 0])[1]); check("the Winners page lists the seeded Live winners of this edition among the archive", S().nominations.filter((n) => n.live && n.stage === "winner").length === 2 && wc0 > 2 && /Kigali Data Foundry/.test((ctx.location.hash = "#/awards/winners?year=" + W.TODAY.slice(0, 4), W.render(), app.innerHTML)));
S().role = "editor"; W.act.nomLive("n3"); S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); const wc1 = Number((/(\d+) winners?/.exec(app.innerHTML) || [0, 0])[1]); check("a Not live winner is not on the Winners page", wc1 === wc0 - 1);
reload(); S().role = "editor"; W.forms.nomprofile({ profile: W.profile("b2").name + " (b2)", category: W.cats()[0].name, email: "secret.contact@example.org" }); S().role = "visitor";
check("the contact email appears in no public view", ["#/", "#/awards", "#/awards/nominees", "#/awards/winners", "#/awards/nominee/NOM-0003", "#/awards/category/0", "#/nominate", "#/people", "#/profile/tiderail-payments"].every((h) => { ctx.location.hash = h; W.render(); return !/secret\.contact@example\.org|nominee3@example\.org/.test(app.innerHTML); }));
S().role = "visitor"; ctx.location.hash = "#/awards/winners"; W.render(); check("winners page lists winners from the start, newest edition first, with filters", /Rift Valley Power/.test(app.innerHTML) && /Mwanga Pay/.test(app.innerHTML) && /<a class="btn sm alt"[^>]*>3</.test(app.innerHTML) && /name="year"/.test(app.innerHTML) && /name="category"/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners?year=2026"; W.render(); check("winners filter by year, oldest edition", /Tiderail Payments/.test(app.innerHTML) && !/Rift Valley Power/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners?year=2027"; W.render(); check("winners filter by year", /Wanjiru Owusu-Eze/.test(app.innerHTML) && !/Kigali Data Foundry/.test(app.innerHTML) && !/Rift Valley Power/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners?category=Entrepreneur%20of%20the%20Year"; W.render(); check("winners filter by category", /Adaeze Onyekachi-Mbeki/.test(app.innerHTML) && /Ifeoma Kariuki-Bello/.test(app.innerHTML) && !/Mwanga Pay/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners?country=Kenya&type=organisation"; W.render(); check("winners filter by country and type", /Rift Valley Power/.test(app.innerHTML) && /Mara Fresh Logistics/.test(app.innerHTML) && !/Samuel Mwangi-Otieno/.test(app.innerHTML));
ctx.location.hash = "#/awards/winners?q=zzzz"; W.render(); check("winners with no match say so", /No winners match/.test(app.innerHTML));
// no kill switch text anywhere in rendered pages
reload(); { let bad = 0; for (const role of Object.keys(W.LISTS.roles)) for (const x of W.INV) { S().role = role; ctx.location.hash = x.route; W.render(); if (/kill/i.test(app.innerHTML.replace(/<[^>]+>/g, " ")) || /Editor-in-Chief|Sales \/ Support|Awards manager/i.test(app.innerHTML.replace(/<[^>]+>/g, " "))) { bad++; console.log("kill/removed-role text on", role, x.id); } } check("no kill switch or removed role text in rendered pages", bad === 0); }
// claims (D-057, replacing the D-036 check): the checkmark is staff-only; the public never sees Claimed, Unclaimed or Verified
reload(); { let bad = 0; for (const role of Object.keys(W.LISTS.roles)) for (const x of W.INV) { if (!/^#\/(profile|people|businesses|institutions|directory|search|industries|awards|stories|$)/.test(x.route) && !/^#\/?$/.test(x.route)) continue; S().role = role; ctx.location.hash = x.route; W.render(); const t = app.innerHTML; if (role === "visitor" && (/un?claimed/i.test(t.replace(/<[^>]+>/g, " ")) || /class="vcheck/.test(t))) { bad++; console.log("CLAIM LEAK", role, x.id); } } check("visitors never see Claimed, Unclaimed or the Verified checkmark", bad === 0); }
// role access
reload(); S().role = "editor"; ctx.location.hash = "#/admin/nominations"; W.render(); check("editor opens nominations", /Nominations/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin/nominations"; W.render(); check("reviewer cannot open nominations", /403/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/admin"; W.render(); check("visitor gets 403 on admin", /403/.test(app.innerHTML));
// WordPress-fit tags: every inventory entry has a valid wpScreen and a recommendation
for (const x of W.INV) {
  check("inventory wpScreen valid " + x.id, ["native", "customised-native", "custom", "n/a (wireframe tool)"].includes(x.wpScreen), x.wpScreen);
  check("inventory wpRec present " + x.id, !!x.wpRec, x.id);
  if (/^Remove/.test(x.wpRec)) check("removal has a reason " + x.id, !!x.wpNote, x.id);
}
// High-fidelity public pages (design-direction.md): rules that must hold on every public route, for a visitor
reload(); S().role = "visitor";
for (const x of W.INV.filter((i) => i.fidelity === "high")) {
  ctx.location.hash = x.route; W.render(); const html = app.innerHTML, main = html.slice(html.indexOf('<main'), html.indexOf('</main>'));
  const txt = main.replace(/<[^>]+>/g, " ");
  check("high fidelity marks the page " + x.id, W.fid === "hf" && /fiction-strip/.test(html) && /site-foot/.test(html));
  check("no password, sign-up or checkout on " + x.id, !/type="password"|\bsign ?up\b|\bcheckout\b|\bcart\b|\blog ?in\b/i.test(txt), x.route);
  check("no REQ or decision codes in public copy " + x.id, !/\bREQ-\d|\bD-0\d\d\b|\bQ\d\d\b/.test(txt), x.route);
  check("exactly one h1 on " + x.id, (main.match(/<h1[\s>]/g) || []).length === 1, x.route + " " + (main.match(/<h1[\s>]/g) || []).length);
  check("every image has alt on " + x.id, !/<img(?![^>]*\balt=)[^>]*>/.test(main));
  check("no goal counts on " + x.id, !/\b\d[\d,]*\+?\s*(profiles? by|target|goal)\b/i.test(txt) && !/1,?000/.test(txt), x.route);
  check("image slots are marked on " + x.id, !/<img(?![^>]*data-img)(?![^>]*\bdip\b)/.test(main.replace(/<figure[^>]*data-img[^>]*><img/g, "<figure>").replace(/<img[^>]*data-img[^>]*>/g, "")));
}
// profile states
reload(); S().role = "visitor";
const pageFor = (slug) => { ctx.location.hash = "#/profile/" + slug; W.render(); return app.innerHTML; };
{
  const pend = pageFor("deltaline-cold-chain");
  check("pending profile is public with the full label", /Compiled automatically from public sources\. Human review pending\./.test(pend) && /badge--pending/.test(pend));
  check("pending profile has correct and remove links", /#\/correct\/deltaline-cold-chain/.test(pend) && /#\/remove\/deltaline-cold-chain/.test(pend));
  check("pending profile shows the label in its share preview", /pending-note/.test(pend));
  check("pending profile has no trust or sponsored label", !/badge--verified|badge--featured|badge--sponsored/.test(pend));
  const rv = pageFor("bonny-reach-logistics"); check("public profile shows no review date and no trust badge", !/badge--reviewed|Reviewed on|Last reviewed|badge--verified/.test(rv));
  const v = pageFor("tiderail-payments"); check("a profile never shows Sponsored or Verified (D-055)", !/badge--verified|notice--sponsored/.test(v) && !/badge--sponsored/.test(v.split("profile-body")[0]));
  check("featured is not shown as a public badge", !/badge--featured/.test(pageFor("confluence-health-network")));
  const sp = pageFor("osu-harbour-foods"); check("a profile with an arrangement shows no Sponsored label", !/notice--sponsored/.test(sp) && !/badge--sponsored/.test(sp.split("profile-body")[0]) && !/badge--verified|badge--featured/.test(sp));
  check("a business profile has no cover image", !/profile-cover/.test(sp));
  const en = pageFor("chidi-sesay-abara"); check("entrepreneur profile has ventures", /Ventures/.test(en) && /Entrepreneur/.test(en) && W.cur.inv === "V54");
  check("an individual is never pending", S().profiles.every((p) => !p.compiled || p.type !== "individual"));
  check("a pending profile holds no label or arrangement (REQ-226)", S().profiles.filter((p) => p.compiled).every((p) => !p.trust && !p.sponsored));
}
// REQ-218 (amended by D-054): lists show reviewed only, with no pending view; direct URL and search show pending with the label
ctx.location.hash = "#/directory"; W.render(); check("directory hides pending by default", !/Deltaline Cold Chain/.test(app.innerHTML));
ctx.location.hash = "#/directory?pending=1"; W.render(); check("directory has no pending view", !/Deltaline Cold Chain/.test(app.innerHTML));
ctx.location.hash = "#/search?q=deltaline"; W.render(); check("search finds pending with the label", /Deltaline Cold Chain/.test(app.innerHTML) && /badge--pending/.test(app.innerHTML));
ctx.location.hash = "#/sitemap.xml"; W.render(); check("sitemap leaves out pending", !/profile\/deltaline-cold-chain&lt;/.test(app.innerHTML) && /200 \+ noindex/.test(app.innerHTML));
ctx.location.hash = "#/"; W.render(); check("home has no pending profile", !/Deltaline|Lake Basin/.test(app.innerHTML));
ctx.location.hash = "#/directory?view=list"; W.render(); check("directory list view", /results--list/.test(app.innerHTML));
S().role = "reviewer"; check("pending organisations are in the review queue", W.queue().some((p) => p.id === "b7"));
W.forms.approve({ id: "b7", c0: 1, c1: 1, c2: 1, c3: 1, c4: 1 }); check("approving clears the pending state", !W.isPending(W.profile("b7")) && W.profile("b7").lastReviewed === W.TODAY); S().role = "visitor";
// awards stages from the design review switch
reload(); S().role = "visitor"; W.ui.nom = { step: 0, d: {} }; W.act.stage("open|#/nominate"); ctx.location.hash = "#/nominate"; W.render(); check("stage open shows the full form", /data-form="nom0"/.test(app.innerHTML) && W.nomOpen());
W.act.stage("soon|#/nominate"); W.render(); check("nominations switched off: the page says closed and has no sign-up form", /Nominations are closed/.test(app.innerHTML) && !/data-form="signup"/.test(app.innerHTML) && !W.nomOpen());
W.act.stage("nominees|#/awards/nominees"); ctx.location.hash = "#/awards/nominees"; W.render(); check("stage nominees shows citations", /citation/.test(app.innerHTML) && /Wanjiru Owusu-Eze/.test(app.innerHTML));
W.act.stage("winners|#/awards/winners"); ctx.location.hash = "#/awards/winners"; W.render(); check("stage winners shows winners", /Winner/.test(app.innerHTML) && W.awardStage().key === "winners");
W.act.stage("soon|#/awards/nominees"); W.render(); check("stage back to soon empties nominees", /Nominees are not published yet/.test(app.innerHTML));
ctx.location.hash = "#/awards"; W.render(); check("awards page has no nominee strip and a public timeline", !/np-reel/.test(app.innerHTML) && /Nominees announced/.test(app.innerHTML) && !/Shortlisting/.test(app.innerHTML) && /id="categories"/.test(app.innerHTML));
ctx.location.hash = "#/commercial-terms"; W.render(); check("commercial terms page is gone", /not found|Page not found|404/i.test(app.innerHTML));
// staff screens keep the wireframe chrome; public pages do not use it
reload(); S().role = "editor"; ctx.location.hash = "#/admin"; W.render(); check("staff screen keeps the wireframe look", W.fid === "wf" && /WIREFRAME/.test(app.innerHTML) && !/fiction-strip/.test(app.innerHTML));
ctx.location.hash = "#/design-review"; W.render(); check("design review opens and lists public pages", W.fid === "hf" && /Compiled automatically, review pending/.test(app.innerHTML) && /Preview as/.test(app.innerHTML) && /flows-btn/.test(app.innerHTML));
S().role = "visitor"; ctx.location.hash = "#/"; W.render(); check("public page keeps the role switcher and Flows menu", W.fid === "hf" && /data-change="role"/.test(app.innerHTML) && /flows-btn/.test(app.innerHTML));
// flows: every start route resolves and every inventory id exists
for (const f of W.FLOWS) {
  for (const id of f.inv) check("flow inventory id " + f.t + " " + id, !!W.invById(id), id);
  reload(); S().role = f.role; ctx.location.hash = f.go; app.innerHTML = ""; try { W.render(); } catch (e) { check("flow start " + f.t, false, e.stack.split("\n")[0]); continue; }
  check("flow start nonempty " + f.t, app.innerHTML.length > 200); if (!/^1\./.test(f.t)) check("flow start not 404 " + f.t, !/Page not found/.test(app.innerHTML));
  check("flow role exists " + f.t, roles.includes(f.role), f.role);
}
check("20 flows or fewer", W.FLOWS.length <= 20 && !W.FLOWS.some((f) => /kill/i.test(f.t + f.s.join(" "))));
// D-044 role details
reload(); S().role = "editor"; ctx.location.hash = "#/admin/profiles/b1"; W.render(); check("editor profile screen: edit form, no Verified form, field sources", /data-form="profedit"/.test(app.innerHTML) && !/data-form="verify"/.test(app.innerHTML) && /Fields and their sources/.test(app.innerHTML) && /Source \(URL\)/.test(app.innerHTML));
ctx.location.hash = "#/admin"; W.render(); check("editor dashboard: support counts, no sprint tile", /open corrections, removals and reports/.test(app.innerHTML) && !/Sprint target/.test(app.innerHTML));
S().role = "reviewer"; ctx.location.hash = "#/admin"; W.render(); check("reviewer dashboard: no support counts", !/open corrections, removals and reports/.test(app.innerHTML));
S().role = "editor"; ctx.location.hash = "#/admin/suppression"; W.render(); check("editor sees the suppression list read-only", /Read-only for your role/.test(app.innerHTML) && !/data-form="supp"/.test(app.innerHTML));
S().role = "admin"; ctx.location.hash = "#/admin/suppression"; W.render(); check("administrator can add to the suppression list", /data-form="supp"/.test(app.innerHTML));
S().role = "editor"; check("editor can edit but not bulk unpublish; reviewer can bulk unpublish but not edit (D-045)", W.can("edit") && !W.can("bulk") && (S().role = "reviewer", !W.can("edit") && W.can("bulk") && W.can("assignTerms")));
S().role = "reviewer"; ctx.location.hash = "#/admin/suppression"; W.render(); check("reviewer sees the suppression list read-only", /Read-only for your role/.test(app.innerHTML));
check("seed users have only the three staff roles, one role and one unique email each", S().users.every((u) => ["reviewer", "editor", "admin"].includes(u.rk) && typeof u.rk === "string" && /@/.test(u.email)) && new Set(S().users.map((u) => u.email.toLowerCase())).size === S().users.length);
check("confirmed-by owners are not removed roles", S().arrangements.every((a) => !/Finance|Sales|Eic/.test(a.confirmedBy)));
// D-045 capabilities (REQ-244)
// Capability grants are made in WordPress; the prototype only models their effect on menus and screens (REQ-244).
const tog = (role, cap) => { const g = S().caps[role], on = W.hasCap(cap, role), def = W.capDefault(cap, role); if (on) { g.grant = g.grant.filter((x) => x !== cap); if (def && g.revoke.indexOf(cap) < 0) g.revoke.push(cap); } else { g.revoke = g.revoke.filter((x) => x !== cap); if (!def && g.grant.indexOf(cap) < 0) g.grant.push(cap); } };
reload(); S().role = "reviewer"; ctx.location.hash = "#/admin/inbox"; W.render();
check("reviewer has no Enquiries by default: not in the menu and 403", W.menuFor("reviewer").indexOf("Enquiries") < 0 && /403/.test(app.innerHTML));
S().role = "admin"; tog("reviewer", "support_inbox");
check("a granted capability is effective for a Reviewer", W.hasCap("support_inbox", "reviewer"));
S().role = "reviewer"; ctx.location.hash = "#/admin/inbox"; W.render(); check("granted capability opens the screen and shows in the menu", W.menuFor("reviewer").indexOf("Enquiries") >= 0 && !/403/.test(app.innerHTML));
S().role = "admin"; tog("reviewer", "support_inbox"); S().role = "reviewer"; ctx.location.hash = "#/admin/inbox"; W.render();
check("revoking the grant brings the 403 back", !W.hasCap("support_inbox", "reviewer") && /403/.test(app.innerHTML));
S().role = "admin"; tog("editor", "support_inbox"); S().role = "editor"; ctx.location.hash = "#/admin/inbox"; W.render();
check("revoking a default takes the screen away from that Editor", W.menuFor("editor").indexOf("Enquiries") < 0 && /403/.test(app.innerHTML) && W.menuFor("editor", true).indexOf("Enquiries") >= 0);
check("locked capabilities (settings, commercial_register) are not grantable to others (Q72)", !W.hasCap("settings", "reviewer") && !W.hasCap("commercial_register", "editor"));
tog("editor", "bulk_unpublish"); S().role = "editor";
ctx.location.hash = "#/admin/profiles?type=individual&origin=import"; W.render(); check("Editor granted bulk_unpublish sees the bulk button", W.can("bulk") && /data-act="bulkDlg"/.test(app.innerHTML));
check("handle_disputes: Editor by default, not Reviewer", W.hasCap("handle_disputes", "editor") && !W.hasCap("handle_disputes", "reviewer") && /Editor/.test(W.disputeHandlers()));

// events as their own post type, related events, and paid story placement (REQ-262 to REQ-265, D-060)
{
  reload(); S().role = "visitor";
  const html = (h) => { ctx.location.hash = h; W.render(); return app.innerHTML; };
  const slugsAfter = (h, marker) => { const s = html(h), i = s.indexOf(marker); return i < 0 ? [] : [...s.slice(i).matchAll(/href="#\/stories\/([a-z0-9-]+)"/g)].map((m) => m[1]); };
  const evs = S().events;
  check("events are their own collection, not stories: upcoming and past samples exist", evs.length === 4 && evs.filter((v) => W.eventState(v) === "upcoming").length >= 3 && evs.filter((v) => W.eventState(v) === "past").length >= 1 && !S().articles.some((a) => a.type === "Event" || a.ev));
  const sp = html("#/stories");
  check("stories page has no Events pill, no upcoming-events strip and no event cards", !/type=Event/.test(sp) && !/>Events</.test(sp.slice(sp.indexOf('class="bl-body"'), sp.indexOf("<footer"))) && !/ev-strip/.test(sp) && !/\/events\//.test(sp.slice(sp.indexOf('class="bl-body"'))));
  check("events do not appear in search results", !/\/events\//.test(html("#/search?q=forum").slice(html("#/search?q=forum").indexOf("<main"))) && !/Lagos Agri-Finance Forum/.test(html("#/search?q=forum").slice(html("#/search?q=forum").indexOf("<main"))));
  // events list and event page
  const el = html("#/events");
  const order = [...el.matchAll(/href="#\/events\/([a-z0-9-]+)"/g)].map((m) => m[1]).filter((s, i, x) => x.indexOf(s) === i);
  check("Events list shows upcoming events soonest first, then Past events", order.join(",") === "kigali-data-infrastructure-summit,tema-manufacturing-open-day,lagos-agri-finance-forum-2026,abuja-mediators-roundtable" && /<h2 class="ev-h">Upcoming</.test(el) && /Past events/.test(el), order.join(","));
  check("Events list filters by city and industry", /name="city"/.test(el) && /name="industry"/.test(el) && !/tema-manufacturing-open-day/.test(html("#/events?city=Lagos")) && /lagos-agri-finance-forum-2026/.test(html("#/events?city=Lagos")));
  check("Events is in the header, the mobile menu and the footer", /class="nav-link" href="#\/events"/.test(el) && /class="drawer-link" href="#\/events"/.test(el) && /<footer[\s\S]*href="#\/events"/.test(el));
  const evp = html("#/events/lagos-agri-finance-forum-2026");
  check("event page shows when, where, organiser and a register link to the organiser's site", /class="ev-card"/.test(evp) && /Eko Convention Hall/.test(evp) && /Lagos Agri-Finance Forum \(sample organiser\)/.test(evp) && /href="https:\/\/example\.org\/lagos-agri-forum" target="_blank" rel="noopener noreferrer"/.test(evp));
  check("event page takes no payment (D-005)", !/name="(amount|price|card|payment|currency)"/.test(evp) && /takes no payment/.test(evp) && !/<form[^>]*data-form="(pay|buy|ticket)/.test(evp));
  check("event page lists the stories that link it as Related stories", /Related stories/.test(evp) && /building-for-the-smallholder/.test(evp) && !/Related stories/.test(html("#/events/tema-manufacturing-open-day")));
  check("Events and the event page do not look like stories: agenda rows and a poster, no story cards, byline, reading time or comments", /class="ev-hero"/.test(el) && /class="ag-row/.test(el) && !/bl-card|bl-grid|bl-meta/.test(el) && /class="ev-poster/.test(evp) && /class="ev-poster-date"/.test(evp) && !/article-head|article-body|article-cover|min read|st-comments|By /.test(evp.slice(evp.indexOf("<main"))));
  check("Events list has a Next up block and months as headings", /class="ev-next"/.test(el) && /class="ag-month-h"/.test(el));
  check("the list filters are one quiet bar: inline selects, a count, Clear only while filtered, no boxed form, no visible Apply", [el, html("#/stories"), html("#/people"), html("#/awards/winners")].every((h) => /class="fbar"/.test(h) && /class="fbar-n"/.test(h) && !/bl-filter|win-filters|class="filters"|class="chip"/.test(h.slice(h.indexOf("<main")))) && !/fbar-clear/.test(el) && /fbar-clear/.test(html("#/events?city=Lagos")) && /<button class="sr" type="submit">/.test(el));
  const past = html("#/events/abuja-mediators-roundtable");
  check("a past event says it has ended and has no register button", /This event has ended/.test(past) && !/Register on the organiser/.test(past));
  check("an event is not a story: its old story address is a 404", /404|not found/i.test(html("#/stories/lagos-agri-finance-forum-2026")));
  // home page
  const hm = html("#/");
  const hev = hm.slice(hm.indexOf('class="h-events"'));
  check("Home has an Events section with the next three upcoming events and an All events link", /class="h-events"/.test(hm) && [...hev.matchAll(/class="ag-link" href="#\/events\/([a-z0-9-]+)"/g)].map((m) => m[1]).join(",") === "kigali-data-infrastructure-summit,tema-manufacturing-open-day,lagos-agri-finance-forum-2026" && /href="#\/events"[^>]*>All events/.test(hev) && !/abuja-mediators/.test(hev.slice(0, hev.indexOf("</section>"))));
  check("Home red awards block is gone but the endless strip with the award categories stays", /h-marquee/.test(hm) && !/h-awards-in|h-groups|class="h-big h-rv">[^<]*WHOISWHO/.test(hm) && S().awards.groups.every((g) => hm.slice(hm.indexOf("h-marquee")).includes(g.name.replace(/&/g, "&amp;"))) && /The Maiden Edition/.test(hm.slice(hm.indexOf("h-marquee"))));
  // related event at the end of a story
  const st1 = html("#/stories/building-for-the-smallholder"), ri = st1.indexOf("ev-rel-card");
  check("a story that links an event ends with a Related event card to the event page", ri > st1.indexOf("article-body") && ri < st1.indexOf("st-comments") && /href="#\/events\/lagos-agri-finance-forum-2026"/.test(st1.slice(ri - 60, ri + 120)) && !/ev-rel-card/.test(html("#/stories/soil-before-software")));
  // paid placement
  const base = html("#/stories"), home = html("#/");
  check("an active paid slot leads the stories page, labelled Sponsored by the sponsor", /bl-slot/.test(base) && /Sponsored by Amber Coast Insurance/.test(base.slice(base.indexOf("bl-slot"))));
  check("an active paid slot leads the home rail, labelled", /h-story/.test(home) && /Sponsored by Amber Coast Insurance/.test(home.slice(home.indexOf('id="hTrack"'))));
  check("a scheduled slot and an expired slot show no label", W.sponsorOfArticle(W.find("articles", "a4")) === null && W.sponsorOfArticle(W.find("articles", "a6")) === null);
  const withHome = slugsAfter("#/", 'id="hTrack"'), withStories = slugsAfter("#/stories", 'class="bl-mosaic"');
  const searchWith = slugsAfter("#/search?q=the", "<main"), wins = html("#/awards/winners"), noms = html("#/awards/nominees"), peopleWith = html("#/people");
  const savedPl = S().placements; S().placements = [];
  const noHome = slugsAfter("#/", 'id="hTrack"'), noStories = slugsAfter("#/stories", 'class="bl-mosaic"');
  const notA3 = (x) => x !== "merchant-settlement-spotlight";
  check("a paid slot does not reorder the other stories on the home page", JSON.stringify(withHome.filter(notA3).slice(0, 5)) === JSON.stringify(noHome.filter(notA3).slice(0, 5)), withHome + " | " + noHome);
  check("a paid slot does not reorder the other stories on the stories page", JSON.stringify(withStories.filter(notA3)) === JSON.stringify(noStories.filter(notA3)), withStories + " | " + noStories);
  check("a paid slot does not change search results order", JSON.stringify(searchWith) === JSON.stringify(slugsAfter("#/search?q=the", "<main")));
  check("a paid slot does not change nominees, winners or the people list", wins === html("#/awards/winners") && noms === html("#/awards/nominees") && peopleWith === html("#/people"));
  S().placements = savedPl;
  // dates decide what shows
  try {
    W.TODAY = "2026-12-06";
    check("after the end date the slot and its label are gone", W.sponsorOfArticle(W.find("articles", "a3")) === null);
    check("a slot shows from its start date", W.sponsorOfArticle(W.find("articles", "a4")) === "Lodestar Building Supplies" && W.slotFor("Home") && W.slotFor("Home").id === "a4" && W.slotFor("Stories") === null);
    check("an event past its dates moves to past", W.eventState(W.find("events", "ev2")) === "past");
  } finally { W.TODAY = "2026-11-07"; }
  // staff: the screens
  S().role = "editor";
  const adm = html("#/admin/sponsors"), stf = html("#/admin/stories");
  check("Sponsored stories list shows statuses and, for an Editor, no money fields", />live</.test(adm) && />scheduled</.test(adm) && />ended</.test(adm) && !/name="(amount|price|currency|method|ref|payment)"/.test(adm) && !/₦/.test(adm) && !/>Amount</.test(adm));
  check("Stories screen has no Event type or event fields and has a Related event select", !/<option[^>]*>Event</.test(stf) && !/name="evStart"/.test(stf) && /name="relatedEvent"/.test(stf));
  check("Story sponsors cannot target an event: events are not in the story list and a forced event id is refused", !/Lagos Agri-Finance Forum/.test(adm) && (() => { const n = S().placements.length; W.forms.placement({ sponsor: "Lodestar Building Supplies", storyId: "ev1", placement: "Both", start: "2027-02-01", end: "2027-02-05" }); return S().placements.length === n; })());
  // Events screen
  check("Editor and Administrator have the Events screen and capability; Reviewer does not", W.hasCap("manage_events", "editor") && W.hasCap("manage_events", "admin") && !W.hasCap("manage_events", "reviewer") && W.menuFor("editor", true).includes("Events") && !W.menuFor("reviewer", true).includes("Events"));
  const evs2 = html("#/admin/events");
  check("Events screen lists events with publish and unpublish, and a form with all the event fields and no money fields", /Unpublish/.test(evs2) && ["title", "start", "time", "end", "venue", "city", "country", "organiser", "industry", "summary", "image", "regUrl", "price"].every((n) => new RegExp('name="' + n + '"').test(evs2)) && !/name="(amount|currency|method|payment)"/.test(evs2));
  const ne0 = S().events.length;
  W.forms.event({ title: "Test event", summary: "x", status: "published", start: "", venue: "V", organiser: "O", regUrl: "https://example.org/x" });
  check("an event without a start date is refused", S().events.length === ne0);
  W.forms.event({ title: "Test event", summary: "x", status: "published", start: "2026-12-20", time: "10:00", venue: "Online", organiser: "O", regUrl: "https://example.org/x", price: "Free" });
  const ne = S().events.find((v) => v.slug === "test-event");
  check("a new event is saved, upcoming and public, and audit-logged", !!ne && W.eventState(ne) === "upcoming" && W.publicEvents().some((v) => v.id === ne.id) && /test-event/.test(html("#/events")) && S().audit.some((r) => /event\.publish/.test(JSON.stringify(r))));
  W.forms.story({ title: "Linked story", type: "News", lede: "x", status: "published", date: "2026-11-07", relatedEvent: ne.id });
  S().role = "visitor";
  check("a story can link the new event and shows it at the end", /ev-rel-card/.test(html("#/stories/linked-story")) && /Test event/.test(html("#/stories/linked-story")));
  S().role = "editor"; W.act.evToggle(ne.id);
  S().role = "visitor";
  check("unpublishing an event removes it from the list, the Home page and the story's related card, and is audit-logged", !/test-event/.test(html("#/events")) && !/test-event/.test(html("#/")) && !/ev-rel-card/.test(html("#/stories/linked-story")) && /404|not found/i.test(html("#/events/test-event")) && S().audit.some((r) => /event\.unpublish/.test(JSON.stringify(r))));
  S().role = "editor";
  const pn = S().placements.length, profBefore = JSON.stringify(S().profiles.map((p) => [p.id, p.trust, p.sponsored, p.status]));
  W.forms.placement({ sponsor: "Lodestar Building Supplies", storyId: "a1", placement: "Stories", start: "2026-11-05", end: "2026-11-30" });
  check("a second paid slot on the same page and dates is refused", S().placements.length === pn);
  W.forms.placement({ sponsor: "Lodestar Building Supplies", storyId: "a7", placement: "Home", start: "2027-01-01", end: "2027-01-10" });
  check("an unpublished story cannot fill a paid slot", S().placements.length === pn);
  W.forms.placement({ sponsor: "Lodestar Building Supplies", storyId: "a1", placement: "Home", start: "2026-12-20", end: "2026-12-30" });
  check("a valid slot is saved and audit-logged", S().placements.length === pn + 1 && S().audit.some((r) => /placement\.add/.test(JSON.stringify(r))));
  W.act.plEnd("pl1");
  check("ending a slot removes the label and the slot", W.sponsorOfArticle(W.find("articles", "a3")) === null && W.slotFor("Stories") === null);
  check("sponsoring a story never changes any profile label or state", profBefore === JSON.stringify(S().profiles.map((p) => [p.id, p.trust, p.sponsored, p.status])));
  check("Reviewer has neither the Stories nor the Sponsored stories screen by default", !W.menuFor("reviewer", true).includes("Stories") && !W.menuFor("reviewer", true).includes("Sponsored stories"));
  // the sponsored story deal flow: agreed offline, recorded here in four steps (REQ-269)
  S().role = "admin"; const dn = S().arrangements.length, pc = S().placements.length;
  W.forms.dealNew({ customerId: "c3", newName: "", newContact: "", amount: "50000", currency: "NGN", notes: "", method: "", ref: "", received: "", confirmedBy: "" });
  const nd = S().arrangements[S().arrangements.length - 1];
  check("a new deal starts at Needs payment with step 1 done and is audit-logged", S().arrangements.length === dn + 1 && W.dealStatus(nd) === "needs payment" && W.dealSteps(nd).join() === "true,false,false,false" && S().audit.some((r) => /deal\.create/.test(JSON.stringify(r))));
  W.forms.dealPay({ id: nd.id, method: "Bank transfer", ref: "R-1", received: "2026-11-07", confirmedBy: "Sample Admin" });
  check("a complete payment record counts toward the sprint target and moves the deal to Needs story", W.counts(nd) && W.dealStatus(nd) === "needs story" && W.dealSteps(nd)[1]);
  W.forms.dealStory({ id: nd.id, storyId: "a7" }); W.forms.dealSlot({ id: nd.id, placement: "Home", start: "2027-02-01", end: "2027-02-10" });
  check("a draft story keeps the deal at Needs story and no slot is added", W.dealStatus(nd) === "needs story" && S().placements.length === pc);
  W.forms.dealStory({ id: nd.id, storyId: "a1" });
  check("a published story moves the deal to Ready to schedule", W.dealStatus(nd) === "ready to schedule");
  W.forms.dealSlot({ id: nd.id, placement: "Home", start: "2027-02-01", end: "2027-02-10" });
  const ndp = W.dealPlacement(nd);
  check("scheduling the slot moves the deal to Scheduled, with the sponsor's name and the deal id on the slot", W.dealStatus(nd) === "scheduled" && ndp && ndp.dealId === nd.id && ndp.sponsor === "Amber Coast Insurance" && S().audit.some((r) => /placement\.add/.test(JSON.stringify(r))));
  check("the deal page shows the four steps, and warns when payment is missing on a live deal", /Deal agreed/.test(html("#/admin/sponsors/" + nd.id)) && /Placement/.test(html("#/admin/sponsors/" + nd.id)) && /Payment not recorded/.test(html("#/admin/sponsors/arr2")));
  check("the list filters by status", /Amber Coast/.test(html("#/admin/sponsors?s=live")) && !/Lodestar Building Supplies<\/td>/.test(html("#/admin/sponsors?s=live").split("Supported by")[0]));
  S().role = "editor";
  const ed = html("#/admin/sponsors/arr1"), edn = html("#/admin/sponsors/new");
  check("an Editor sees the deal steps but no money fields, and cannot open New sponsored story", /Story ready/.test(ed) && !/name="(amount|method|ref|received|confirmedBy)"/.test(ed) && /name="storyId"/.test(ed) && /Only an Administrator/.test(edn) && !/data-form="dealNew"/.test(edn));
  const before = JSON.stringify(S().arrangements.find((x) => x.id === "arr5"));
  W.forms.dealPay({ id: "arr5", method: "Cash", ref: "X", received: "2026-11-07", confirmedBy: "Editor" });
  check("an Editor cannot record a payment", JSON.stringify(S().arrangements.find((x) => x.id === "arr5")) === before);
  S().role = "admin";
  check("an Administrator sees the money fields and New sponsored story", /name="amount"/.test(html("#/admin/sponsors/arr5")) && /data-form="dealNew"/.test(html("#/admin/sponsors/new")) && /New sponsored story/.test(html("#/admin/sponsors")));
  W.act.dealCancel(nd.id);
  check("cancelling a deal ends its slot and removes its label", W.dealStatus(nd) === "cancelled" && W.dealPlacement(nd).ended === true && !W.counts(nd));
  check("a new story made from a deal is linked to it", (() => { const k = S().arrangements.length; W.forms.dealNew({ customerId: "__new", newName: "Test Sponsor Ltd", newContact: "Sample Contact 9", amount: "100", currency: "NGN" }); const d2 = S().arrangements[S().arrangements.length - 1]; W.forms.story({ title: "Made for a sponsor", type: "Feature", lede: "x", status: "draft", dealId: d2.id }); return S().arrangements.length === k + 1 && W.dealStory(d2) && W.dealStory(d2).title === "Made for a sponsor" && S().customers.some((c) => c.name === "Test Sponsor Ltd"); })());
  S().role = "editor";
  S().role = "visitor";
}
S().role = "visitor"; S().user = null;
// audit chain
let prev = "00000000", okc = true; S().audit.forEach((r) => { if (r.prev !== prev) okc = false; prev = r.hash; }); check("audit chain", okc);
check("the Roles, Users and capabilities, Exports and Compliance screens are gone (WordPress provides them)", ["#/admin/roles", "#/admin/capabilities", "#/admin/exports", "#/admin/compliance", "#/admin/settings"].every((h) => { S().role = "admin"; ctx.location.hash = h; W.render(); return /not found|404/i.test(app.innerHTML); }));
check("Sprint thresholds opens for the Administrator at /admin/thresholds", (() => { S().role = "admin"; ctx.location.hash = "#/admin/thresholds"; W.render(); return /Sprint thresholds/.test(app.innerHTML) && !/Default revenue stream/.test(app.innerHTML); })());
console.log(n + " checks, " + fails + " failures");
process.exit(fails ? 1 : 0);
