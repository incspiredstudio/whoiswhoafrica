/* Staff console part 1: shell, sign-in, dashboard, review, profiles, support inbox. Flow preview only; the real admin will be WordPress. */
(function () {
  var W = window.W, e = W.esc;
  var ALL = ["reviewer", "editor", "sales", "finance", "eic", "awards", "admin"];
  var ACL = { review: ["reviewer", "eic"], profiles: ["reviewer", "editor", "eic"], inbox: ["reviewer", "sales", "eic"], supprView: ["reviewer", "eic"], supprEdit: ["eic"], import: ["editor", "eic"], taxonomy: ["editor", "eic"], dsr: ["eic"], leads: ["sales"], customers: ["sales", "finance"], register: ["finance", "sales"], sponsors: ["finance", "editor", "sales"], articles: ["editor", "eic"], awards: ["eic", "awards"], scores: ["awards"], kill: ["eic"], purges: ["eic"], emails: ["eic"], none: [] };
  W.ACL = ACL; W.ALLSTAFF = ALL;
  var PERSONA = { reviewer: "Sample Reviewer 1", editor: "Sample Editor", sales: "Sample Sales 1", finance: "Sample Finance 1", eic: "Sample Eic 1", awards: "Sample Awards Manager", admin: "Sample Admin" };
  W.persona = function () { return PERSONA[W.S.role] || "system"; };
  W.staffRoute = function (path, inv, key, fn) { W.route(path, inv, fn, { area: "staff", roles: key ? ACL[key].concat([]) : ALL }); };
  function mini(path, inv, key, fn) { W.staffRoute(path, inv, key, fn); }
  function head(t, sub) { return "<h1>" + e(t) + "</h1>" + (sub ? '<p class="muted">' + sub + "</p>" : ""); }
  W.download = function (name, text) {
    try { var a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([text], { type: "text/csv" })); a.download = name; document.body.appendChild(a); a.click(); a.remove(); } catch (x) { /* no DOM */ }
    W.toast("Downloaded " + name + " (sample file).");
  };
  /* Exports of personal data are Administrator-only and logged (REQ-069). */
  W.exportBtn = function (k) { return W.S.role === "admin" ? W.btn("Export CSV", "dl", k, "alt") : '<span class="small muted">Export: Administrator only (REQ-069)</span>'; };
  W.csv = function (rows) { return rows.map(function (r) { return r.map(function (c) { return '"' + String(c == null ? "" : c).replace(/"/g, '""') + '"'; }).join(","); }).join("\n"); };
  function age(d) { if (!d) return 0; return Math.max(0, Math.round((new Date(W.TODAY) - new Date(d)) / 86400000)); }
  W.age = age;
  function notesBox(key) {
    var list = W.S.notes[key] || [];
    return '<div class="card stack-s"><h2>Internal notes</h2><p class="small muted">Never public (REQ-187). Not shown on score entry or results screens.</p>' + (list.length ? "<ul>" + list.map(function (n) { return "<li>" + e(n.text) + ' <span class="muted small">— ' + e(n.by) + ", " + e(n.at) + "</span></li>"; }).join("") + "</ul>" : '<p class="muted">No notes.</p>') + '<form data-form="note" class="row"><input type="hidden" name="key" value="' + e(key) + '"><label class="sr" for="nt' + e(key) + '">Add note</label><input id="nt' + e(key) + '" name="text" required style="flex:1"><button class="btn sm">Add note</button></form></div>';
  }
  W.notesBox = notesBox;
  W.forms.note = function (d) { (W.S.notes[d.key] = W.S.notes[d.key] || []).push({ text: d.text, by: W.persona(), at: W.TODAY }); W.log("note.add", d.key, "internal note"); W.save(); W.render(); };

  /* ---------- shell ---------- */
  var TAXMENU = Object.keys(W.TAXES).map(function (k) { return [W.TAXES[k][0], "/admin/taxonomy/" + k, ACL.taxonomy]; });
  var MENU = [
    ["Overview", [["Dashboard", "/admin", ALL]]],
    ["Review", [["Review queue", "/admin/review", ACL.review], ["Duplicates", "/admin/duplicates", ACL.review], ["Stale records", "/admin/stale", ACL.review]]],
    ["Directory", [["Profiles", "/admin/profiles", ACL.profiles], ["Import", "/admin/import", ACL.import], ["Prospects", "/admin/prospects", ACL.leads], ["Suppression list", "/admin/suppression", ACL.supprView]]],
    ["Taxonomies", TAXMENU],
    ["Inbox", [["Support inbox", "/admin/inbox", ACL.inbox], ["Data requests", "/admin/dsr", ACL.dsr]]],
    ["Commercial", [["Leads", "/admin/leads", ACL.leads], ["Customers", "/admin/customers", ACL.customers], ["Register", "/admin/register", ACL.register], ["Sponsors", "/admin/sponsors", ACL.sponsors]]],
    ["Content", [["Articles", "/admin/articles", ACL.articles]]],
    ["Awards", [["Nominations", "/admin/nominations", ACL.awards], ["Awards setup", "/admin/awards", ACL.awards], ["Scores and results", "/admin/scores", ACL.scores], ["Judging panel and conflicts", "/admin/judges", ACL.awards], ["Shortlist and winners", "/admin/announcements", ACL.awards]]],
    ["Platform", [["Feature flag", "/admin/flags", ACL.none], ["Kill switch", "/admin/killswitch", ACL.kill], ["Audit log", "/admin/audit", ACL.none], ["Users and roles", "/admin/users", ACL.none], ["Roles and permissions", "/admin/roles", ACL.none], ["Compliance", "/admin/compliance", ACL.dsr], ["Purge log", "/admin/purges", ACL.purges], ["Exports", "/admin/exports", ACL.none], ["Email log", "/admin/emails", ACL.emails], ["Settings", "/admin/settings", ACL.none]]]
  ];
  /* The left menu each role sees (D-041). Administrator sees everything. */
  W.menuFor = function (role) { var out = []; MENU.forEach(function (g) { g[1].forEach(function (i) { if (role === "admin" || i[2].indexOf(role) >= 0) out.push(i[0]); }); }); return out; };
  W.MENU = MENU;
  W.appShell = function (area, body, path) {
    var role = W.S.role, side = "";
    side = '<nav class="app-side" aria-label="Staff">' + MENU.map(function (g) {
      var items = g[1].filter(function (i) { return role === "admin" || i[2].indexOf(role) >= 0; });
      return items.length ? "<h2>" + e(g[0]) + "</h2>" + items.map(function (i) { return '<a href="#' + i[1] + '"' + (path === i[1] || (i[1] !== "/admin" && path.indexOf(i[1]) === 0) ? ' aria-current="page"' : "") + ">" + e(i[0]) + "</a>"; }).join("") : "";
    }).join("") + "</nav>";
    return '<div class="app">' + side + '<div class="app-main">' + W.note("Preview of flows only. The real admin will be WordPress. Signed in as <strong>" + e(W.persona()) + "</strong> (" + e(W.roleName(role)) + ").") + body + "</div></div>";
  };

  /* ---------- sign in (staff, with MFA) ---------- */
  W.ui.staffStep = 0; W.ui.staffRole = "reviewer";
  W.route("/staff/signin", "A01", function () {
    var s = W.ui.staffStep;
    return '<div class="wrap section"><h1>Staff sign in</h1>' + (s === 0 ? '<form class="card stack-s" data-form="staff0" style="max-width:28rem">' + W.field("Work email", "email", { type: "email", req: true }) + W.field("Password", "password", { type: "password", req: true }) + W.field("Role (wireframe only)", "role", { type: "select", options: ALL.map(function (r) { return [r, W.roleName(r)]; }) }) + '<button class="btn">Continue</button></form>' : '<form class="card stack-s" data-form="staff1" style="max-width:28rem"><p>Enter the 6-digit code from your authenticator app (MFA is required for staff, REQ-047). <span class="muted small">Wireframe code: 000000</span></p>' + W.field("Code", "code", { req: true, attrs: 'inputmode="numeric"' }) + (W.ui.staffErr ? '<p class="err" role="alert">' + e(W.ui.staffErr) + "</p>" : "") + '<button class="btn">Sign in</button></form>') + "</div>";
  });
  W.forms.staff0 = function (d) { W.ui.staffRole = d.role; W.ui.staffStep = 1; W.ui.staffErr = ""; W.render(); };
  W.forms.staff1 = function (d) { if (d.code.trim() !== "000000") { W.ui.staffErr = "That code is wrong."; W.render(); return; } W.S.role = W.ui.staffRole; W.ui.staffStep = 0; W.save(); W.go("#/admin"); };

  /* ---------- dashboard ---------- */
  function supportOpen() { var S = W.S; return S.corrections.concat(S.removals, S.reports).filter(function (x) { return x.status === "open" || x.status === "awaiting email check"; }).length; }
  mini("/admin", "A02", null, function () {
    var S = W.S, role = S.role;
    var can = function (list) { return role === "admin" || list.indexOf(role) >= 0; };
    var showDir = can(["reviewer", "editor", "eic"]), showCom = can(["finance", "sales"]), showAw = can(["awards", "eic"]), showPlat = can(["eic"]);
    var out = head("Dashboard", "Viewing as <strong>" + e(W.roleName(role)) + "</strong>. Each role sees only its own tiles; switch <strong>Viewing as</strong> in the top bar to preview the others. All numbers are sample data.");
    if (showDir) {
      var q = W.queue(), imported = S.profiles.filter(function (p) { return W.origin(p) === "import"; }).length, pending = S.profiles.filter(function (p) { return p.status === "pending"; }).length;
      var reviewed = S.profiles.filter(function (p) { return p.lastReviewed && (p.status === "published" || p.status === "unpublished"); }).length, published = W.publicProfiles().length;
      var takedowns = S.profiles.filter(function (p) { return p.status === "unpublished" || p.status === "suppressed"; }).length;
      var rej = {}; S.profiles.filter(function (p) { return p.status === "rejected"; }).forEach(function (p) { rej[p.reason || "Unspecified"] = (rej[p.reason || "Unspecified"] || 0) + 1; });
      var mine = q.filter(function (p) { return p.assignee === W.persona(); }).length, unassigned = q.filter(function (p) { return !p.assignee; }).length;
      out += '<div class="card stack-s"><h2>Profiles by state</h2><div class="kpis"><div class="kpi"><b>' + imported + '</b>imported</div><div class="kpi"><b>' + pending + '</b>pending human review</div><div class="kpi"><b>' + reviewed + '</b>reviewed</div><div class="kpi"><b>' + published + '</b>published</div></div><p class="small muted">Plain counts. There is no target number for the directory and no capacity figure until the pilot measures one (D-035). Imported = arrived through the acquisition import. Pending = waiting for a reviewer. Reviewed = a person has approved it. Published = visible to visitors now.</p></div>' +
        '<div class="kpis"><div class="kpi"><b>' + q.length + '</b>in review queue</div><div class="kpi"><b>' + takedowns + '</b>takedowns (unpublished or suppressed)</div><div class="kpi"><b>' + supportOpen() + '</b>open corrections, removals and reports</div></div>' +
        '<div class="card stack-s"><h2>Review workload</h2><p>Assigned to me: <strong>' + mine + '</strong> · Unassigned: <strong>' + unassigned + '</strong> · Oldest item: <strong>' + (q.length ? Math.max.apply(null, q.map(function (p) { return age(p.submittedAt || "2026-11-05"); })) : 0) + ' days</strong></p><p class="small">Rejection reasons: ' + (Object.keys(rej).length ? Object.keys(rej).map(function (k) { return e(k) + " (" + rej[k] + ")"; }).join(", ") : "none yet") + '.</p></div>' +
        '<div class="card stack-s"><h2>Profiles by type, source and status</h2>' + (function () { var m = {}; S.profiles.forEach(function (p) { var k = p.type + "|" + p.source + "|" + p.status; m[k] = (m[k] || 0) + 1; }); return W.table(["Type", "Source", "Status", "Count"], Object.keys(m).sort().map(function (k) { var a = k.split("|"); return [e(a[0]), e(a[1]), e(a[2]), m[k]]; })); })() + "</div>";
    }
    if (showCom) {
      var counted = S.arrangements.filter(W.counts), rev = counted.reduce(function (t, a) { return t + a.amount; }, 0), t = S.thresholds, pct = function (n, d) { return Math.min(100, Math.round(100 * n / d)); };
      var byType = {}; counted.forEach(function (a) { byType[a.type] = (byType[a.type] || 0) + 1; });
      var byStream = { subscription: 0, media: 0, sponsorship: 0 }; counted.forEach(function (a) { byStream[a.stream] += a.amount; });
      var comp = S.arrangements.filter(function (a) { return a.complimentary; }).length;
      var soon = S.arrangements.filter(function (a) { return !a.cancelled && a.end && a.end >= W.TODAY && (new Date(a.end) - new Date(W.TODAY)) / 86400000 <= 30; });
      out += '<div class="grid c2"><div class="card stack-s"><h2>Sprint target: either one is enough</h2><p><strong>Paid subscriptions</strong> (Confirmed paid arrangements, REQ-022): <strong>' + counted.length + " of " + t.subs + '</strong></p><div class="bar" role="img" aria-label="' + pct(counted.length, t.subs) + ' percent of the subscription target"><i style="width:' + pct(counted.length, t.subs) + '%"></i></div><p><strong>Confirmed revenue</strong>: <strong>' + W.money(rev) + " of " + W.money(t.revenue) + '</strong></p><div class="bar"><i style="width:' + pct(rev, t.revenue) + '%"></i></div><p>' + ((counted.length >= t.subs || rev >= t.revenue) ? '<span class="pill ok">Target met</span>' : '<span class="pill warn">Target not met yet</span>') + ' <span class="small muted">Thresholds are settings, not hard-coded. This is the commercial sprint target, not a directory size.</span></p><p class="small">By type: ' + (Object.keys(byType).length ? Object.keys(byType).map(function (k) { return e(W.LISTS.arrTypes[k]) + " " + byType[k]; }).join(", ") : "none") + ". Complimentary (excluded): " + comp + ".</p></div>" +
        '<div class="card stack-s"><h2>Revenue per stream</h2>' + W.table(["Stream", "Confirmed"], Object.keys(byStream).map(function (k) { return [e(W.LISTS.streams[k]), W.money(byStream[k])]; })) + "</div></div>" +
        '<div class="kpis"><div class="kpi"><b>' + S.enquiries.length + '</b>enquiries and messages</div><div class="kpi"><b>' + S.leads.length + '</b>leads</div><div class="kpi"><b>' + S.arrangements.length + '</b>arrangements</div></div>' +
        '<p class="small muted">Visitors, journey completion and conversions come from privacy-friendly analytics (not shown in the wireframe).</p>' +
        '<div class="card stack-s"><h2>Expiry reminders</h2>' + (soon.length ? "<ul>" + soon.map(function (a) { return "<li>" + e(W.LISTS.arrTypes[a.type]) + " for " + e((W.find("customers", a.customerId) || {}).name) + " ends " + e(a.end) + ' <a href="#/admin/register/' + e(a.id) + '">Open</a></li>'; }).join("") + "</ul>" : "<p class=\"muted\">Nothing ends in the next 30 days.</p>") + '<p class="small muted">Reminders only. The system takes no automatic action against customers (REQ-189).</p></div>';
    }
    if (showAw) out += '<div class="kpis"><div class="kpi"><b>' + S.nominations.length + '</b>nominations</div><div class="kpi"><b>' + S.nominations.filter(function (n) { return n.status === "shortlisted" || n.status === "finalist" || n.status === "winner"; }).length + '</b>shortlisted or later</div></div>';
    if (role === "editor") out += '<div class="kpis"><div class="kpi"><b>' + S.articles.length + '</b>articles</div><div class="kpi"><b>' + S.articles.filter(function (a) { return a.status === "scheduled"; }).length + '</b>scheduled</div></div>';
    if (showPlat) out += '<div class="infobox"><strong>Feature flag:</strong> <span class="pill ' + (S.flags.nominations.on ? "flag-on" : "flag-off") + '">nominations ' + (S.flags.nominations.on ? "ON" : "OFF") + '</span> (public nomination form). <br><strong>Kill switch</strong> (a legal safety control, not a flag): <strong>' + (S.kill.on ? "ON" : "off") + '</strong>. When on, every individual profile that came from the acquisition import returns 410 and is hidden.</div>';
    return out;
  });
  /* ---------- review queue ---------- */
  mini("/admin/review", "A03", "review", function (p, q) {
    var rows = W.queue().filter(function (x) { return !q.f || (q.f === "ai" ? x.aiDrafted : q.f === "edit" ? x.edit : q.f === "new" ? !x.edit : true); });
    return head("Review queue", "Every profile is reviewed by a person. The reviewer cannot be the importer or drafter (REQ-148). Sources are shown read-only beside each claim.") +
      '<form class="filters" data-filter="#/admin/review">' + W.field("Show", "f", { type: "select", options: [["new", "New profiles"], ["edit", "Edits to published"], ["ai", "AI-drafted"]], blank: "All", value: q.f }) + "</form>" +
      '<p role="status">' + rows.length + " item(s).</p>" + W.table(["Profile", "Kind", "Source", "AI", "Age (days)", "Assigned", "Flags", ""], rows.slice(0, 30).map(function (x) {
        var fl = (x.scan || []).filter(function (s) { return !s.cleared; }).length + (x.facts || []).filter(function (f) { return f.conf === "low" || !f.src; }).length * (x.source === "acquired" ? 1 : 0);
        return ['<a href="#/admin/review/' + e(x.id) + '">' + e(x.name) + "</a>", x.edit ? "Edit (" + Object.keys(x.edit).join(", ") + ")" : "New " + e(x.type), e(x.source), x.aiDrafted ? '<span class="pill warn">AI</span>' : "", age(x.submittedAt || "2026-11-05"), e(x.assignee || "Unassigned"), fl ? '<span class="pill bad">' + fl + " to clear</span>" : '<span class="pill ok">clear</span>', W.btn("Assign to me", "assign", x.id, "sm alt")];
      }), "The queue is empty. Nothing is waiting for review.");
  });
  W.act.assign = function (id) { W.profile(id).assignee = W.persona(); W.save(); W.render(); };

  mini("/admin/review/:id", "A04", "review", function (par) {
    var p = W.profile(par.id); if (!p) return "<h1>Not found</h1>";
    var me = W.persona(), blocked = (p.importedBy === me || (p.drafter || "").indexOf(me) >= 0);
    var scanOpen = (p.scan || []).filter(function (s) { return !s.cleared; }), lowFacts = (p.facts || []).filter(function (f) { return p.source === "acquired" && (f.conf === "low" || !f.src); });
    var dup = W.S.profiles.filter(function (x) { return x.id !== p.id && x.name && x.name.toLowerCase().replace(/[.,]/g, "") === p.name.toLowerCase().replace(/[.,]/g, ""); });
    var checklist = ["No allegations, litigation or rumours", "No opinion or praise language", "No personal life, family, health or finances", "Every fact is dated and sourced", "Professional facts only"];
    var fieldsDiff = p.edit ? Object.keys(p.edit).map(function (k) { return "<tr><th scope=\"row\">" + e(k) + '</th><td class="old">' + e(p[k]) + '</td><td class="new">' + e(p.edit[k]) + "</td><td>Source change</td></tr>"; }).join("") :
      ["name", "title", "org", "industry", "city", "bio", "achievements", "website"].filter(function (k) { return p[k]; }).map(function (k) { return "<tr><th scope=\"row\">" + e(k) + "</th><td>—</td><td class=\"new\">" + e(p[k]) + "</td><td>" + e(p.source === "acquired" ? "see facts" : "Customer-supplied") + "</td></tr>"; }).join("");
    var canApprove = !blocked && !scanOpen.length && !lowFacts.length;
    return '<p><a href="#/admin/review">← Review queue</a></p>' + head(p.name + (p.edit ? " (edit)" : ""), W.stPill(p.status) + " " + e(p.type) + " · source: " + e(p.source) + (p.importedBy ? " · imported by " + e(p.importedBy) : "")) +
      (blocked ? '<div class="errbox" role="alert"><strong>You cannot approve this.</strong> You imported or drafted it. Another reviewer must approve (REQ-148). Switch role to Admin or use the other reviewer to continue the demo.</div>' : "") +
      (dup.length ? '<div class="warnbox"><strong>Possible duplicate:</strong> ' + dup.map(function (d) { return '<a href="#/admin/profiles/' + e(d.id) + '">' + e(d.name) + "</a>"; }).join(", ") + ". Resolve in Duplicates before publishing (REQ-123, 146).</div>" : "") +
      '<div class="grid side"><div class="stack"><div class="card stack-s"><h2>' + (p.edit ? "Field-level diff" : "Fields") + '</h2><div class="tablewrap"><table class="diff"><thead><tr><th>Field</th><th>Before</th><th>After</th><th>Origin</th></tr></thead><tbody>' + fieldsDiff + "</tbody></table></div></div>" +
      (p.facts && p.facts.length ? '<div class="card stack-s"><h2>Facts with provenance (REQ-142)</h2><p class="small muted">Read-only. Approved sources are managed in the acquisition pipeline, not here (D-038).</p>' + W.table(["Field", "Value", "Source (URL)", "Retrieved", "Confidence", "Method", ""], p.facts.map(function (f, i) { var bad = p.source === "acquired" && (f.conf === "low" || !f.src); return [e(f.label), e(f.value), e(f.src || "NONE") + (f.url ? '<div class="small mono muted">' + e(f.url) + "</div>" : ""), e(f.retrieved), '<span class="pill ' + (f.conf === "high" ? "ok" : f.conf === "medium" ? "warn" : "bad") + '">' + e(f.conf) + "</span>", e(f.method), bad ? W.btn("Remove field", "rmFact", p.id + "|" + i, "sm bad") + '<div class="err small">Low confidence or no source cannot publish (REQ-143)</div>' : ""]; })) + "</div>" : "") +
      (p.aiClaims ? '<div class="card stack-s"><h2>AI-drafted claims next to their sources (REQ-166, 168)</h2><p class="small muted">Drafted in the acquisition pipeline before import. Source (read-only): ' + e((function () { var f0 = (p.facts || []).filter(function (f) { return f.src; })[0]; return f0 ? f0.src + ', ' + f0.url + ', retrieved ' + f0.retrieved : 'none'; })()) + '. Model, prompt version and source hash are recorded internally (sample: model-x, prompt v1, hash 3fa9…). Claims without a verbatim source passage are dropped automatically.</p>' + W.table(["Claim", "Source passage", "Quote", "Result"], p.aiClaims.map(function (c) { return [c.ok ? e(c.claim) : "<s>" + e(c.claim) + "</s>", e(c.passage || "—"), e(c.quote || "—"), c.ok ? '<span class="pill ok">matched</span>' : '<span class="pill bad">dropped: no source</span>']; })) + "</div>" : "") +
      '<div class="card stack-s"><h2>Scan flags (REQ-141)</h2>' + ((p.scan || []).length ? W.table(["Pattern", "Match", ""], p.scan.map(function (s, i) { return [e(s.kind), '<span class="mono">' + e(s.text) + "</span>", s.cleared ? '<span class="pill ok">cleared</span>' : W.btn("Mark cleared", "clearScan", p.id + "|" + i, "sm alt")]; })) + "<p class=\"small muted\">Approval is blocked until every flag is cleared. Clearing means the reviewer removed or accepted the text.</p>" : "<p class=\"muted\">No patterns found.</p>") + "</div></div>" +
      '<div class="stack"><form class="card stack-s" data-form="approve"><input type="hidden" name="id" value="' + e(p.id) + '"><h2>Neutral-content checklist (REQ-163)</h2>' + checklist.map(function (c, i) { return '<label class="check"><input type="checkbox" name="c' + i + '"> ' + e(c) + "</label>"; }).join("") + '<div class="row"><button class="btn" ' + (canApprove ? "" : 'aria-disabled="true"') + ">Approve and publish</button>" + W.btn("Reject…", "rejectDlg", p.id, "bad") + "</div>" + (canApprove ? "" : '<p class="err small">Approve is blocked: ' + [blocked ? "you drafted/imported it" : "", scanOpen.length ? scanOpen.length + " scan flag(s)" : "", lowFacts.length ? lowFacts.length + " unsourced/low-confidence field(s)" : ""].filter(Boolean).join("; ") + ".</p>") + '<p class="small muted">On approve, the page, search, sitemap and share cards switch on together (REQ-208).</p>' + (p.source === "acquired" ? '<p class="small muted">If this profile is live before review (D-033), the page shows: <em>' + e(W.PENDING_LABEL) + "</em> After approval it shows <em>Reviewed on " + e(W.TODAY) + ".</em></p>" : "") + (p.type === "individual" && p.source === "acquired" ? '<p class="small"><button type="button" class="btn alt sm" disabled>Notify the subject of this stub</button> <span class="muted">Waiting for Legal’s decision (Q5, REQ-159). Only if lawful and a business contact exists.</span></p>' : "") + '</form>' + notesBox(p.id) + "</div></div>";
  });
  W.act.rmFact = function (a) { var s = a.split("|"); W.profile(s[0]).facts.splice(Number(s[1]), 1); W.log("fact.remove", s[0], "unsourced"); W.save(); W.render(); };
  W.act.clearScan = function (a) { var s = a.split("|"); W.profile(s[0]).scan[Number(s[1])].cleared = true; W.log("scan.clear", s[0], ""); W.save(); W.render(); };
  W.forms.approve = function (d) {
    var p = W.profile(d.id), me = W.persona();
    var blocked = (p.importedBy === me || (p.drafter || "").indexOf(me) >= 0), scanOpen = (p.scan || []).some(function (s) { return !s.cleared; }), low = (p.facts || []).some(function (f) { return p.source === "acquired" && (f.conf === "low" || !f.src); });
    var allChecked = [0, 1, 2, 3, 4].every(function (i) { return d["c" + i]; });
    if (blocked || scanOpen || low) { W.toast("Cannot approve: resolve the blocking items first."); return; }
    if (!allChecked) { W.toast("Tick every item on the neutral-content checklist first."); return; }
    if (p.edit) { Object.keys(p.edit).forEach(function (k) { p[k] = p.edit[k]; }); p.edit = null; }
    p.status = "published"; p.lastReviewed = W.TODAY; p.history.push({ at: W.TODAY, by: me, what: "Approved and published" });
    W.log("review.approve", p.id, p.name); if (p.source === "subscriber") W.mail(p.contactEmail || "Customer contact for " + p.name, "Your profile is approved", "Your profile is now public.", "#/profile/" + p.slug);
    W.save(); W.go("#/admin/review"); W.toast(p.name + " is now public and in the sitemap.");
  };
  W.act.rejectDlg = function (id) { W.openModal("Reject with a reason", '<form data-form="reject" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Reason (sent to the owner or kept in the log)", "reason", { type: "textarea", req: true }) + '<button class="btn bad">Reject</button></form>'); };
  W.forms.reject = function (d) {
    var p = W.profile(d.id); if (p.edit) { p.edit = null; } else { p.status = "rejected"; }
    p.reason = d.reason; p.history.push({ at: W.TODAY, by: W.persona(), what: "Rejected: " + d.reason }); W.log("review.reject", p.id, d.reason);
    if (p.source === "subscriber") W.mail(p.contactEmail || "Customer contact for " + p.name, "Your profile needs changes", d.reason, "#/invite/demo");
    W.ui.modal = null; W.save(); W.go("#/admin/review"); W.toast("Rejected with reason.");
  };

  /* ---------- duplicates, stale ---------- */
  mini("/admin/duplicates", "A21", "review", function () {
    var rows = W.S.dupes || [];
    return head("Probable duplicates", "Matching uses registration number, Wikidata ID, and normalised name + organisation + location. Individuals are never auto-merged (REQ-146).") + W.table(["Existing profile", "Incoming", "Basis", "Kind", ""], rows.map(function (d) { var a = W.profile(d.aId); return ['<a href="#/admin/profiles/' + e(a.id) + '">' + e(a.name) + "</a>", e(d.bName), e(d.basis), e(d.kind), W.btn("Merge into existing", "dupe", d.id + "|merge", "sm") + " " + W.btn("Not a duplicate", "dupe", d.id + "|keep", "sm alt")]; }), "No duplicates waiting.");
  });
  W.act.dupe = function (a) { var s = a.split("|"); W.S.dupes = W.S.dupes.filter(function (d) { return d.id !== s[0]; }); W.log("duplicate." + s[1], s[0], "human decision"); W.save(); W.render(); W.toast(s[1] === "merge" ? "Merged by a reviewer (never automatic)." : "Kept separate."); };
  mini("/admin/stale", "A39", "review", function () {
    var rows = W.S.profiles.filter(function (p) { return p.status === "published"; }).map(function (p) { var m = Math.round(age(p.lastReviewed) / 30); return { p: p, m: m }; }).filter(function (x) { return x.m >= 6; });
    return head("Stale records", "Re-check at 6 months, flag stale at 12, unpublish at 18 unless re-reviewed. Customers are asked once a year, by email, to send corrections through the public form (REQ-160).") + W.table(["Profile", "Status", "Months since review", "Action due", ""], rows.map(function (x) { return ['<a href="#/admin/profiles/' + e(x.p.id) + '">' + e(x.p.name) + "</a>", W.trustPill(x.p), x.m, x.m >= 18 ? '<span class="pill bad">unpublish</span>' : x.m >= 12 ? '<span class="pill warn">flag stale</span>' : '<span class="pill gray">re-check</span>', x.m >= 18 ? W.btn("Unpublish", "unpubDlg", x.p.id, "sm bad") : W.btn("Mark re-reviewed", "rereview", x.p.id, "sm alt")]; }), "Nothing is stale.");
  });
  W.act.rereview = function (id) { var p = W.profile(id); p.lastReviewed = W.TODAY; W.log("review.recheck", id, ""); W.save(); W.render(); };

  /* ---------- profiles ---------- */
  mini("/admin/profiles", "A05", "profiles", function (par, q) {
    var rows = W.S.profiles.filter(function (p) { return (!q.status || p.status === q.status) && (!q.type || p.type === q.type) && (!q.q || p.name.toLowerCase().indexOf(q.q.toLowerCase()) >= 0); });
    return '<div class="row between"><h1>Profiles</h1><a class="btn" href="#/admin/profiles/new">Create on behalf of a customer</a></div><form class="filters" data-filter="#/admin/profiles">' + W.field("Name", "q", { value: q.q }) + W.field("Status", "status", { type: "select", options: ["published", "pending", "draft", "rejected", "unpublished", "suppressed"], blank: "Any", value: q.status }) + W.field("Type", "type", { type: "select", options: ["individual", "business", "institution"], blank: "Any", value: q.type }) + "</form>" +
      W.table(["Name", "Type", "Status", "Label", "Sponsored", "Visitors see", ""], rows.map(function (p) {
        var v = W.vis(p); return ['<a href="#/admin/profiles/' + e(p.id) + '">' + e(p.name || "(untitled)") + "</a>", e(p.type), W.stPill(p.status), W.trustPill(p) || "—", e(W.sponsorOf(p) || "—"), v === "public" ? "Page (200)" : v === "gone" ? "410 + noindex" : "404", p.status === "published" ? W.btn("Unpublish", "unpubDlg", p.id, "sm bad") : p.status === "unpublished" ? W.btn("Restore", "restore", p.id, "sm") : ""];
      }));
  });
  W.act.unpubDlg = function (id) { W.openModal("Unpublish " + W.profile(id).name, '<form data-form="unpub" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '"><p>The URL will return 410 Gone with noindex immediately, leave the sitemap and search, and be purged from the CDN.</p>' + W.field("Reason (audited)", "reason", { type: "textarea", req: true }) + '<button class="btn bad">Unpublish now</button></form>'); };
  W.forms.unpub = function (d) {
    var p = W.profile(d.id); p.status = "unpublished"; p.removedReason = d.reason; p.history.push({ at: W.TODAY, by: W.persona(), what: "Unpublished: " + d.reason });
    W.purge("/profile/" + p.slug, "unpublish"); W.log("profile.unpublish", p.id, d.reason); W.ui.modal = null; W.save(); W.render(); W.toast("Unpublished. 410 + noindex now; CDN purge queued.");
  };
  W.act.restore = function (id) { var p = W.profile(id); p.status = "published"; p.removedReason = ""; p.history.push({ at: W.TODAY, by: W.persona(), what: "Restored (reversible unpublish)" }); W.purge("/profile/" + p.slug, "restore"); W.log("profile.restore", id, ""); W.save(); W.render(); W.toast("Restored. The URL returns 200 again."); };

  mini("/admin/profiles/new", "A07", "profiles", function () {
    return '<p><a href="#/admin/profiles">← Profiles</a></p>' + head("Create a profile on behalf of a customer") + '<form class="card stack-s" data-form="onbehalf">' + W.field("Customer", "cust", { type: "select", options: W.S.customers.map(function (c) { return [c.id, c.name + " (" + c.segment + ")"]; }), req: true }) + W.field("Profile type", "type", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]] }) + W.field("Name", "name", { req: true }) + W.field("Complimentary listing (excluded from paid counts)", "comp", { type: "checkbox" }) + W.field("Customer consent recorded (required for complimentary publication, REQ-175)", "consent", { type: "checkbox" }) + '<p class="small muted">This record is flagged and attributed: created by ' + e(W.persona()) + ' on behalf of the customer. It still goes through review.</p><div class="row"><button class="btn">Create draft</button></div></form><div class="card stack-s"><h2>Or invite the customer to fill it in (REQ-013)</h2><p>After the offline agreement, send an invitation link. There is no payment step.</p>' + W.btn("Send single-use intake link (sample)", "invite", "", "alt") + " " + W.btn("Send consent request (sample)", "consentReq", "", "alt") + "</div>";
  });
  W.act.invite = function () { W.mail("Sample Customer", "You are invited to create your " + W.brand.name + " profile", "Single-use link, no account. Your arrangement is agreed. Click to start.", "#/invite/demo"); W.log("invite.send", "customer", "profile intake"); W.save(); W.render(); W.toast("Invitation sent. See the email outbox."); };
  W.act.consentReq = function () { W.mail("Sample Person A", "Consent to a complimentary listing", "Single-use link, no account. Click to give or withhold consent.", "#/consent/demo"); W.save(); W.render(); W.toast("Consent request sent."); };
  W.forms.onbehalf = function (d) {
    var c = W.find("customers", d.cust);
    if (d.comp && !d.consent) { W.toast("A complimentary profile needs the customer’s recorded consent before it can be published."); }
    W.S.profiles.push({ id: W.uid("p"), slug: d.name.toLowerCase().replace(/[^a-z0-9]+/g, "-") + "-" + W.uid(""), type: d.type, name: d.name, title: "", industry: "", city: "", status: "pending", trust: "", sponsored: null, source: d.comp ? "complimentary" : "subscriber", origin: "customer", country: "Nigeria", aiDrafted: false, views: 0, enquiries: true, bio: "Created on behalf of " + c.name + " by " + W.persona() + ".", achievements: "", website: "", facts: [], leadership: [], products: "", public: {}, history: [{ at: W.TODAY, by: W.persona(), what: "Created on behalf of customer" }], scan: [], importedBy: W.persona(), drafter: W.persona(), submittedAt: W.TODAY, consent: !!d.consent });
    W.log("profile.create", d.name, d.comp ? "complimentary" : "on behalf"); W.save(); W.go("#/admin/review"); W.toast("Draft created and queued. A different reviewer must approve it.");
  };

  mini("/admin/profiles/:id", "A06", "profiles", function (par) {
    var p = W.profile(par.id); if (!p) return "<h1>Not found</h1>";
    var arrs = W.S.arrangements.filter(function (a) { return a.linkedProfile === p.id; }), auditRows = W.S.audit.filter(function (a) { return a.target === p.id; });
    return '<p><a href="#/admin/profiles">← Profiles</a></p><div class="row between"><h1>' + e(p.name) + '</h1><div class="row"><a class="btn alt" href="#/profile/' + e(p.slug) + '">Preview as visitor</a>' + (p.status === "published" ? W.btn("Unpublish", "unpubDlg", p.id, "bad") : p.status === "unpublished" ? W.btn("Restore", "restore", p.id) : "") + "</div></div>" +
      '<div class="tagrow">' + W.stPill(p.status) + W.trustPill(p) + W.statePill(p) + W.spPill(W.sponsorOf(p)) + (W.isKilled(p) ? ' <span class="pill bad">hidden by kill switch</span>' : "") + ' <span class="pill gray">origin: ' + e(W.origin(p)) + "</span></div>" + editCard(p) +
      '<div class="grid c2"><div class="card stack-s"><h2>Trust label (editorial only)</h2><p class="small muted">Labels: none, Verified (staff checklist) or Featured (editorial). Neither can be bought. Payment is never an input (REQ-153, 183, 184, D-036).</p><form data-form="verify" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '"><fieldset><legend>Verified checklist (' + e(p.type) + ")</legend>" + ["Identity or registration checked against evidence", "Contact route confirmed", "Public sources match the profile", "No unresolved complaints"].map(function (c, i) { return '<label class="check"><input type="checkbox" name="v' + i + '"> ' + e(c) + "</label>"; }).join("") + W.field("Evidence reference", "evidence") + W.field("Payment status (not used)", "pay", { value: "Not an input", attrs: "disabled" }) + '</fieldset><button class="btn">Set Verified</button></form>' +
      '<form data-form="feature" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Featured: editorial reason", "reason", { req: true, value: p.featuredReason }) + '<button class="btn alt">Set Featured</button> ' + W.btn("Remove label", "trustReset", p.id, "sm alt") + "</form></div>" +
      '<div class="card stack-s"><h2>Sponsored label (separate from status)</h2><form data-form="sponsor" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Manual sponsor name (optional)", "name", { value: p.sponsored || "" }) + '<button class="btn alt">Save manual label</button></form><p class="small">Linked arrangements: ' + (arrs.length ? arrs.map(function (a) { return '<a href="#/admin/register/' + e(a.id) + '">' + e(a.id) + "</a> (" + e(W.arrStatus(a)) + ", " + e(a.start || "") + " to " + e(a.end || "") + ")"; }).join(", ") : "none") + '</p><p class="small muted">A confirmed sponsored-profile arrangement applies the label between its dates and removes it when it ends. Nothing is deleted.</p></div></div>' +
      '<div class="grid c2"><form class="card stack-s" data-form="slug"><h2>Public URL</h2><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Slug", "slug", { value: p.slug, hint: "Changing it creates a redirect from the old URL." }) + '<p class="small muted">Redirects: ' + e((p.redirects || []).join(", ") || "none") + '</p><button class="btn alt">Save slug</button></form><div class="card stack-s"><h2>Source sync</h2><p class="small">' + (p.qid ? "Wikidata ID " + e(p.qid) + "." : "Linked to a source record (sample).") + ' Changes from a source are flagged for review, never overwritten (REQ-135, 161).</p>' + W.btn("Check source for changes (sample)", "srcSync", p.id, "alt sm") + "</div></div>" +
      '<div class="grid c2"><form class="card stack-s" data-form="vis"><h2>Contact and enquiries (set on request)</h2><input type="hidden" name="id" value="' + e(p.id) + '"><p class="small muted">Nobody has a login. A request arrives through the correction form into the Support inbox and staff set this here (REQ-018, 060, D-040). Default: nothing public beyond the enquiry form.</p>' + W.field("Show website", "website", { type: "checkbox", value: (p.public || {}).website }) + W.field("Show email", "email", { type: "checkbox", value: (p.public || {}).email }) + W.field("Show phone", "phone", { type: "checkbox", value: (p.public || {}).phone }) + W.field("Allow enquiries", "enq", { type: "checkbox", value: p.enquiries }) + W.field("Requested by (name or email)", "by", { req: true }) + '<button class="btn alt">Save</button></form><div class="card stack-s"><h2>Send views and sources on request (REQ-236)</h2><p class="small">Send the view count and the sources used, as asked. Views: <strong>' + e(p.views) + "</strong> (view count only). Sources: " + e((p.facts || []).map(function (f) { return f.src || "none"; }).filter(function (v, i, a) { return a.indexOf(v) === i; }).join("; ") || "none") + ".</p>" + W.btn("Email this to the requester (sample)", "ownerInfo", p.id, "alt sm") + "</div></div>" +
      '<div class="grid c2"><div class="card stack-s"><h2>Revision history</h2>' + (p.history.length ? "<ul>" + p.history.map(function (h) { return "<li>" + e(h.at) + " · " + e(h.by) + ": " + e(h.what) + "</li>"; }).join("") + "</ul>" : '<p class="muted">None.</p>') + "<h3>Label changes (REQ-150)</h3>" + ((p.trustLog || []).length ? "<ul>" + p.trustLog.map(function (t) { return "<li>" + e(t) + "</li>"; }).join("") + "</ul>" : '<p class="muted small">None.</p>') + "<h3>Corrections log (REQ-164)</h3>" + (W.S.corrections.filter(function (c) { return c.profileId === p.id; }).map(function (c) { return "<p class=\"small\">" + e(c.kind) + ": " + e(c.detail) + " (" + e(c.status) + ")</p>"; }).join("") || '<p class="muted small">None.</p>') + "</div>" + notesBox(p.id) + "</div>" +
      '<div class="card"><h2>Audit entries for this profile</h2>' + W.table(["#", "When", "Actor", "Action", "Detail"], auditRows.map(function (a) { return [a.n, e(a.at), e(a.actor), e(a.action), e(a.detail)]; }), "None yet. Try setting a label.") + "</div>";
  });
  /* Staff edit a profile directly (D-040). There is no owner change request and no second-approver step. Every edit is written to the audit log automatically. */
  function editCard(p) {
    var L = W.LISTS, can = ACL.taxonomy.indexOf(W.S.role) >= 0 || W.S.role === "admin", ind = p.type === "individual";
    var items = W.S.corrections.filter(function (c) { return c.profileId === p.id && c.status === "open"; });
    var sel = function (lab, name, opts, val, blank) { return W.field(lab, name, { type: "select", options: opts, blank: blank || "None", value: val }); };
    return '<form class="card stack-s" data-form="profedit"><h2>Edit this profile (staff)</h2><input type="hidden" name="id" value="' + e(p.id) + '"><p class="small muted">Saving changes the page at once. The edit is written to the audit log with your name and reason. ' + (can ? 'You can add terms under <a href="#/admin/taxonomy/industries">Taxonomies</a>.' : "You can assign existing terms only. Editors and Administrators add terms (D-039).") + "</p>" +
      '<div class="grid c2">' + W.field("Name", "name", { value: p.name }) + W.field("Title or tagline", "title", { value: p.title }) + (ind ? W.field("Organisation", "org", { value: p.org }) : "") + W.field("Website", "website", { value: p.website }) + sel("Industry", "industry", L.industries, p.industry, "Choose…") + sel("Country", "country", L.countries, p.country, "Choose…") + sel("City", "city", L.cities, p.city, "Choose…") + (ind ? sel("Profession", "profession", L.professions, p.profession, "None") : sel("Organisation type", "orgType", L.orgTypes, p.orgType, "None")) + "</div>" +
      W.field("Biography", "bio", { type: "textarea", value: p.bio }) + W.field("Achievements", "achievements", { type: "textarea", value: p.achievements }) +
      W.field("Reason or request reference (audited)", "reason", { req: true }) + (items.length ? W.field("This resolves the open item", "item", { type: "select", options: items.map(function (c) { return [c.id, c.detail]; }), blank: "None" }) : "") + '<button class="btn">Save changes</button></form>';
  }
  W.forms.profedit = function (d) {
    var p = W.profile(d.id), keys = ["name", "title", "org", "website", "industry", "country", "city", "profession", "orgType", "bio", "achievements"], changed = [];
    keys.forEach(function (k) { if (d[k] !== undefined && d[k] !== (p[k] || "") ) { p[k] = d[k]; changed.push(k); } });
    if (!changed.length) { W.toast("Nothing changed."); return; }
    p.history.push({ at: W.TODAY, by: W.persona(), what: "Edited " + changed.join(", ") + " (" + d.reason + ")" });
    W.log("profile.edit", p.id, changed.join(", ") + "; " + d.reason);
    if (d.item) { var x = W.find("corrections", d.item); if (x) { x.status = "closed"; W.log("request.close", x.id, "resolved by edit"); } }
    W.save(); W.render(); W.toast("Saved. " + (p.status === "published" ? "The public page changed at once. " : "") + "The edit is in the audit log.");
  };
  function trust(p, to, why) { var from = p.trust || "none"; p.trust = to; (p.trustLog = p.trustLog || []).push(W.TODAY + " " + from + " → " + (to || "none") + " by " + W.persona() + ": " + why); W.log("trust.set", p.id, from + " to " + (to || "none") + ": " + why); }
  W.forms.verify = function (d) {
    var p = W.profile(d.id); if (![0, 1, 2, 3].every(function (i) { return d["v" + i]; })) { W.toast("Tick every checklist item. Payment is not an input."); return; }
    trust(p, "verified", "checklist passed; evidence " + (d.evidence || "n/a")); W.save(); W.render(); W.toast("Set to Verified.");
  };
  W.forms.feature = function (d) { var p = W.profile(d.id); p.featuredReason = d.reason; trust(p, "featured", d.reason); W.save(); W.render(); W.toast("Set to Featured (editorial)."); };
  W.act.trustReset = function (id) { var p = W.profile(id); trust(p, "", "label removed by staff"); W.save(); W.render(); };
  W.forms.vis = function (d) { var p = W.profile(d.id); p.public = { website: !!d.website, email: !!d.email, phone: !!d.phone }; p.enquiries = !!d.enq; p.history.push({ at: W.TODAY, by: W.persona(), what: "Contact visibility / enquiries set on request from " + d.by }); W.log("profile.visibility", p.id, "request from " + d.by + "; enquiries " + (p.enquiries ? "on" : "off")); W.save(); W.render(); W.toast("Saved. The enquiry button on the public profile " + (p.enquiries ? "is on." : "is hidden.")); };
  W.act.ownerInfo = function (id) { var p = W.profile(id); W.mail("Requester for " + p.name, "Your " + W.brand.name + " profile: views and sources", "Views: " + p.views + ". Sources are listed on the profile record.", ""); W.log("owner.info", id, "view count and sources sent"); W.save(); W.toast("Sent. See the email outbox."); };
  W.forms.slug = function (d) { var p = W.profile(d.id), s = d.slug.toLowerCase().replace(/[^a-z0-9-]+/g, "-"); if (s && s !== p.slug) { (p.redirects = p.redirects || []).push(p.slug); p.slug = s; p.history.push({ at: W.TODAY, by: W.persona(), what: "Slug changed; redirect created" }); W.purge("/profile/" + p.redirects[p.redirects.length - 1], "slug change"); W.log("slug.change", p.id, s); W.save(); W.render(); W.toast("Saved. The old URL redirects to the new one."); } };
  W.act.srcSync = function (id) { var p = W.profile(id); p.edit = Object.assign(p.edit || {}, { title: (p.title || "Role") + " (source changed)" }); W.log("source.sync", id, "change flagged"); W.save(); W.toast("A source change was found and queued for review instead of overwriting."); W.render(); };
  W.forms.sponsor = function (d) { var p = W.profile(d.id); p.sponsored = d.name || null; W.log("sponsored.set", p.id, d.name || "cleared"); W.save(); W.render(); W.toast("Sponsored label updated. Status unchanged."); };

  /* ---------- inbox, reports, corrections, dsr ---------- */
  W.dueText = function (due, status) {
    if (!due || status === "closed" || status === "done" || status === "declined") return "\u2014";
    var d = Math.round((new Date(due) - new Date(W.TODAY)) / 86400000);
    return d < 0 ? '<span class="pill bad">overdue by ' + (-d) + " day" + (d === -1 ? "" : "s") + "</span>" : d === 0 ? '<span class="pill warn">due today</span>' : '<span class="pill gray">due in ' + d + " day" + (d === 1 ? "" : "s") + "</span>";
  };
  mini("/admin/inbox", "A12", "inbox", function (par, q) {
    var S = W.S, rows = [], pn = function (id) { var p = W.profile(id); return p ? '<a href="#/admin/profiles/' + e(p.id) + '">' + e(p.name) + "</a>" : "\u2014"; };
    S.enquiries.forEach(function (x) { rows.push({ t: "Enquiry", sub: x.general ? "Contact form: " + x.name : "From " + x.name, p: x.general ? "" : x.profileId, st: x.read ? "read" : "new", due: "", at: x.at, owner: "Sales", act: x.read ? "" : W.btn("Mark read", "enqRead", x.id, "sm alt") }); });
    S.corrections.forEach(function (x) { var open = x.status === "open"; rows.push({ t: "Correction", sub: x.detail + (x.contact ? " (" + x.contact + (x.emailVerified ? ", email confirmed" : ", email not confirmed") + ")" : ""), p: x.profileId, st: x.status, due: x.due, at: x.at, owner: "Reviewer", act: open ? '<a class="btn sm" href="#/admin/profiles/' + e(x.profileId) + '">Edit the profile</a> ' + W.btn("Mark resolved", "corrDone", x.id, "sm alt") + " " + W.btn("Decline", "corrDecline", x.id, "sm alt") : "" }); });
    S.removals.forEach(function (x) { var open = x.status === "open"; rows.push({ t: "Removal", sub: x.detail + (x.contact ? " (" + x.contact + ")" : ""), p: x.profileId, st: x.status, due: x.due, at: x.at, owner: "Reviewer", act: open ? W.btn("Valid: hide + suppress", "rmOk", x.id, "sm bad") + " " + W.btn("Decline", "corrDecline", x.id, "sm alt") : "" }); });
    S.reports.forEach(function (x) { var open = x.status === "open"; rows.push({ t: "Report", sub: x.reason + ": " + x.detail, p: x.profileId, st: x.status, due: x.due, at: x.at, owner: x.owner || "unassigned", act: open ? W.btn("Hide profile now", "unpubDlg", x.profileId, "sm bad") + " " + W.btn("Dismiss", "repDone", x.id, "sm alt") + " " + W.btn("Resolve", "repDone", x.id, "sm") : "" }); });
    rows = rows.filter(function (r) { return !q.t || r.t === q.t; }).sort(function (a, b) { return age(b.at) - age(a.at); });
    return head("Support inbox", "Enquiries and the public correction, removal and report forms land here as typed items, each with a status and a due-date countdown (REQ-089, 158, D-040). Staff act by editing or unpublishing the profile directly in the admin; every edit and unpublish is written to the audit log automatically, and a valid removal also adds the person or organisation to the suppression list.") +
      '<form class="filters" data-filter="#/admin/inbox">' + W.field("Type", "t", { type: "select", options: ["Enquiry", "Correction", "Removal", "Report"], blank: "All", value: q.t }) + "</form>" +
      '<p class="small muted">Targets: acknowledge in 1 business day; hide within 2 business days of a valid removal; resolve corrections within 7 days (REQ-158).</p>' +
      W.table(["Type", "Item", "Profile", "Status", "Due", "Age (days)", "Owner", ""], rows.map(function (r) { return ['<span class="pill ' + (r.t === "Removal" ? "bad" : r.t === "Report" ? "warn" : "gray") + '">' + r.t + "</span>", e(r.sub), r.p ? pn(r.p) : "\u2014", W.stPill(r.st), W.dueText(r.due, r.st), age(r.at), e(r.owner), r.act]; }), "Inbox zero.");
  });
  W.act.enqRead = function (id) { W.find("enquiries", id).read = true; W.log("enquiry.read", id, ""); W.save(); W.render(); };
  W.act.repDone = function (id) { W.find("reports", id).status = "closed"; W.log("report.close", id, ""); W.save(); W.render(); };
  W.act.corrDone = function (id) { var x = W.find("corrections", id); x.status = "closed"; W.log("request.close", id, x.kind + " resolved"); W.save(); W.render(); };
  W.act.corrDecline = function (id) { var x = W.find("corrections", id) || W.find("removals", id); x.status = "declined"; W.log("request.decline", id, x.kind); W.save(); W.render(); };
  W.act.rmOk = function (id) {
    var x = W.find("removals", id), p = W.profile(x.profileId); x.status = "done"; p.status = "suppressed"; p.removedReason = "Removal request";
    W.S.suppression.unshift({ id: W.uid("sp"), name: p.name, kind: "Removed individual or organisation", at: W.TODAY, reason: "Removal request " + id }); W.purge("/profile/" + p.slug, "removal request"); W.log("removal.approve", p.id, "suppressed"); W.mail(x.contact || "requester", "Your removal request was accepted", "The profile is no longer public.", ""); W.save(); W.render(); W.toast("Hidden (410 + noindex), added to the permanent suppression list, CDN purge queued.");
  };
  mini("/admin/dsr", "A35", "dsr", function () {
    return head("Data requests", "Access, correction, erasure, objection, portability, each with a due date (REQ-059).") + W.table(["Type", "Requester", "Received", "Due", "Status", ""], W.S.dsr.map(function (d) { return [e(d.kind), e(d.who), e(d.at), e(d.due), W.stPill(d.status), d.status !== "closed" ? W.btn("Close", "dsrDone", d.id, "sm") : ""]; }), "No requests.");
  });
  W.act.dsrDone = function (id) { W.find("dsr", id).status = "closed"; W.log("dsr.close", id, ""); W.save(); W.render(); };
})();
