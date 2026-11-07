/* Staff console part 1: shell, sign-in, dashboard, review, profiles, claims, inboxes. Flow preview only; the real admin will be WordPress. */
(function () {
  var W = window.W, e = W.esc;
  var ALL = ["reviewer", "editor", "sales", "finance", "eic", "admin"];
  var ACL = { review: ["reviewer", "eic"], profiles: ["reviewer", "editor", "eic"], claims: ["reviewer", "sales", "eic"], inbox: ["sales", "reviewer", "eic"], reports: ["reviewer", "eic"], dsr: ["eic"], leads: ["sales"], customers: ["sales", "finance"], register: ["finance", "sales"], sponsors: ["finance", "editor", "sales"], articles: ["editor", "eic"], awards: ["eic"], kill: ["eic"], sources: ["editor", "reviewer", "eic"], none: [] };
  W.ACL = ACL; W.ALLSTAFF = ALL;
  var PERSONA = { reviewer: "Sample Reviewer 1", editor: "Sample Editor", sales: "Sample Sales 1", finance: "Sample Finance 1", eic: "Sample Eic 1", admin: "Sample Admin", judge: "Sample Judge 1", owner: "Sample Person A" };
  W.persona = function () { return PERSONA[W.S.role] || "system"; };
  W.staffRoute = function (path, inv, key, fn) { W.route(path, inv, fn, { area: "staff", roles: key ? ACL[key].concat([]) : ALL }); };
  function mini(path, inv, key, fn) { W.staffRoute(path, inv, key, fn); }
  function head(t, sub) { return "<h1>" + e(t) + "</h1>" + (sub ? '<p class="muted">' + sub + "</p>" : ""); }
  W.download = function (name, text) {
    try { var a = document.createElement("a"); a.href = URL.createObjectURL(new Blob([text], { type: "text/csv" })); a.download = name; document.body.appendChild(a); a.click(); a.remove(); } catch (x) { /* no DOM */ }
    W.toast("Downloaded " + name + " (sample file).");
  };
  W.csv = function (rows) { return rows.map(function (r) { return r.map(function (c) { return '"' + String(c == null ? "" : c).replace(/"/g, '""') + '"'; }).join(","); }).join("\n"); };
  function age(d) { if (!d) return 0; return Math.max(0, Math.round((new Date(W.TODAY) - new Date(d)) / 86400000)); }
  W.age = age;
  function notesBox(key) {
    var list = W.S.notes[key] || [];
    return '<div class="card stack-s"><h2>Internal notes</h2><p class="small muted">Never public. Never visible to judges (REQ-187).</p>' + (list.length ? "<ul>" + list.map(function (n) { return "<li>" + e(n.text) + ' <span class="muted small">— ' + e(n.by) + ", " + e(n.at) + "</span></li>"; }).join("") + "</ul>" : '<p class="muted">No notes.</p>') + '<form data-form="note" class="row"><input type="hidden" name="key" value="' + e(key) + '"><label class="sr" for="nt' + e(key) + '">Add note</label><input id="nt' + e(key) + '" name="text" required style="flex:1"><button class="btn sm">Add note</button></form></div>';
  }
  W.notesBox = notesBox;
  W.forms.note = function (d) { (W.S.notes[d.key] = W.S.notes[d.key] || []).push({ text: d.text, by: W.persona(), at: W.TODAY }); W.log("note.add", d.key, "internal note"); W.save(); W.render(); };

  /* ---------- shell ---------- */
  var MENU = [
    ["Overview", [["Dashboard", "/admin", ALL]]],
    ["Review", [["Review queue", "/admin/review", ACL.review], ["Duplicates", "/admin/duplicates", ACL.review], ["Stale records", "/admin/stale", ACL.review]]],
    ["Directory", [["Profiles", "/admin/profiles", ACL.profiles], ["Claims", "/admin/claims", ACL.claims], ["Import", "/admin/import", ACL.review], ["Sources", "/admin/sources", ACL.sources], ["AI drafting", "/admin/ai", ACL.review], ["Prospects", "/admin/prospects", ACL.leads], ["Suppression list", "/admin/suppression", ACL.review], ["Controlled lists", "/admin/lists", ACL.profiles]]],
    ["Inbox", [["Support inbox", "/admin/inbox", ACL.inbox], ["Reports", "/admin/reports", ACL.reports], ["Corrections and removals", "/admin/corrections", ACL.reports], ["Data requests", "/admin/dsr", ACL.dsr]]],
    ["Commercial", [["Leads", "/admin/leads", ACL.leads], ["Customers", "/admin/customers", ACL.customers], ["Register", "/admin/register", ACL.register], ["Sponsors", "/admin/sponsors", ACL.sponsors]]],
    ["Content", [["Articles", "/admin/articles", ACL.articles]]],
    ["Awards", [["Nominations", "/admin/nominations", ACL.awards], ["Awards setup", "/admin/awards", ACL.awards], ["Judges", "/admin/judges", ACL.awards], ["Announcements", "/admin/announcements", ACL.awards]]],
    ["Platform", [["Feature flags", "/admin/flags", []], ["Kill switch", "/admin/killswitch", ACL.kill], ["Audit log", "/admin/audit", []], ["Users and roles", "/admin/users", []], ["Compliance", "/admin/compliance", ACL.dsr], ["Purge log", "/admin/purges", ALL], ["Exports", "/admin/exports", ALL], ["Email log", "/admin/emails", ALL], ["Settings", "/admin/settings", []]]]
  ];
  W.appShell = function (area, body, path) {
    var role = W.S.role, side = "";
    if (area === "judge") {
      side = '<nav class="app-side" aria-label="Judge"><h2>Judge</h2>' + [["Assignments", "/judge"], ["Guidelines", "/judge/guidelines"]].map(function (m) { return '<a href="#' + m[1] + '"' + (path === m[1] ? ' aria-current="page"' : "") + ">" + m[0] + "</a>"; }).join("") + "</nav>";
      return '<div class="app judge">' + side + '<div class="app-main">' + W.note("Judge area preview. Judges see assigned nominations only and never payment, subscription or sponsorship data.") + body + "</div></div>";
    }
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
  mini("/admin", "A02", null, function () {
    var S = W.S, counted = S.arrangements.filter(W.counts), rev = counted.reduce(function (s, a) { return s + a.amount; }, 0);
    var byType = {}; counted.forEach(function (a) { byType[a.type] = (byType[a.type] || 0) + 1; });
    var byStream = { subscription: 0, media: 0, sponsorship: 0 }; counted.forEach(function (a) { byStream[a.stream] += a.amount; });
    var comp = S.arrangements.filter(function (a) { return a.complimentary; }).length;
    var t = S.thresholds, q = W.queue(), cap = 80, pct = function (n, d) { return Math.min(100, Math.round(100 * n / d)); };
    var rej = {}; S.profiles.filter(function (p) { return p.status === "rejected"; }).forEach(function (p) { rej[p.reason || "Unspecified"] = (rej[p.reason || "Unspecified"] || 0) + 1; });
    var soon = S.arrangements.filter(function (a) { return !a.cancelled && a.end && a.end >= W.TODAY && age(W.TODAY) >= 0 && (new Date(a.end) - new Date(W.TODAY)) / 86400000 <= 30; });
    var r1 = q.filter(function (p) { return p.assignee === "Sample Reviewer 1"; }).length, r2 = q.filter(function (p) { return p.assignee === "Sample Reviewer 2"; }).length;
    return head("Dashboard", "Weekly view. All numbers come from sample data in this wireframe.") +
      '<div class="grid c2"><div class="card stack-s"><h2>Sprint target: either one is enough</h2><p><strong>Paid subscriptions</strong> (Confirmed paid arrangements, REQ-022): <strong>' + counted.length + " / " + t.subs + '</strong></p><div class="bar" role="img" aria-label="' + pct(counted.length, t.subs) + ' percent"><i style="width:' + pct(counted.length, t.subs) + '%"></i></div><p><strong>Confirmed revenue</strong>: <strong>' + W.money(rev) + " / " + W.money(t.revenue) + '</strong></p><div class="bar"><i style="width:' + pct(rev, t.revenue) + '%"></i></div><p>' + ((counted.length >= t.subs || rev >= t.revenue) ? '<span class="pill ok">Target met</span>' : '<span class="pill warn">Target not met yet</span>') + ' <span class="small muted">Thresholds are settings, not hard-coded.</span></p><p class="small">By type: ' + (Object.keys(byType).length ? Object.keys(byType).map(function (k) { return e(W.LISTS.arrTypes[k]) + " " + byType[k]; }).join(", ") : "none") + ". Complimentary (excluded): " + comp + ".</p></div>" +
      '<div class="card stack-s"><h2>Revenue per stream</h2>' + W.table(["Stream", "Confirmed"], Object.keys(byStream).map(function (k) { return [e(W.LISTS.streams[k]), W.money(byStream[k])]; })) + "</div></div>" +
      '<div class="card stack-s"><h2>Directory target (about 100 organisations and 100 individuals reviewed)</h2>' + (function () { var o = S.profiles.filter(function (p) { return p.status === "published" && p.type !== "individual"; }).length, i = S.profiles.filter(function (p) { return p.status === "published" && p.type === "individual"; }).length; return "<p>Organisations: <strong>" + o + ' / 100</strong></p><div class="bar"><i style="width:' + Math.min(100, o) + '%"></i></div><p>Individuals: <strong>' + i + ' / 100</strong></p><div class="bar"><i style="width:' + Math.min(100, i) + '%"></i></div>'; })() + "</div>" +
      '<div class="kpis"><div class="kpi"><b>' + S.profiles.filter(function (p) { return p.status === "published"; }).length + "</b>profiles published</div><div class=\"kpi\"><b>" + q.length + '</b>in review queue</div><div class="kpi"><b>' + S.enquiries.length + '</b>enquiries and messages</div><div class="kpi"><b>' + S.nominations.length + "</b>nominations</div></div>" +
      '<p class="small muted">Visitors, journey completion and conversions come from privacy-friendly analytics (not shown in the wireframe).</p>' +
      '<div class="grid c2"><div class="card stack-s"><h2>Review capacity</h2><p>Queue ' + q.length + " vs capacity " + cap + " per week (2 reviewers × about 40).</p><div class=\"bar\"><i style=\"width:" + pct(q.length, cap) + '%"></i></div>' + (q.length > cap ? '<div class="warnbox" role="alert"><strong>Backlog exceeds capacity.</strong> Consider adding a third reviewer (REQ-206).</div>' : "") + "<p>Sample Reviewer 1: " + r1 + " assigned · Sample Reviewer 2: " + r2 + " assigned · Unassigned: " + (q.length - r1 - r2) + "</p><p class=\"small\">Oldest item: " + (q.length ? Math.max.apply(null, q.map(function (p) { return age(p.submittedAt || "2026-11-05"); })) : 0) + ' days. Rejection reasons: ' + (Object.keys(rej).length ? Object.keys(rej).map(function (k) { return e(k) + " (" + rej[k] + ")"; }).join(", ") : "none yet") + '.</p><p>' + W.btn("Add 40 sample items to queue (demo)", "flood", "", "sm alt") + "</p></div>" +
      '<div class="card stack-s"><h2>Expiry reminders</h2>' + (soon.length ? "<ul>" + soon.map(function (a) { return "<li>" + e(W.LISTS.arrTypes[a.type]) + " for " + e((W.find("customers", a.customerId) || {}).name) + " ends " + e(a.end) + ' <a href="#/admin/register/' + e(a.id) + '">Open</a></li>'; }).join("") + "</ul>" : "<p class=\"muted\">Nothing ends in the next 30 days.</p>") + '<p class="small muted">Reminders only. The system takes no automatic action against customers (REQ-189).</p></div></div>' +
      '<div class="card stack-s"><h2>Profiles by type, source and status</h2>' + (function () { var m = {}; S.profiles.forEach(function (p) { var k = p.type + "|" + p.source + "|" + p.status; m[k] = (m[k] || 0) + 1; }); return W.table(["Type", "Source", "Status", "Count"], Object.keys(m).sort().map(function (k) { var a = k.split("|"); return [e(a[0]), e(a[1]), e(a[2]), m[k]]; })); })() + "</div>" +
      '<div class="infobox"><strong>Flags:</strong> ' + Object.keys(S.flags).map(function (k) { return '<span class="pill ' + (S.flags[k].on ? "flag-on" : "flag-off") + '">' + e(k) + " " + (S.flags[k].on ? "ON" : "OFF") + "</span>"; }).join(" ") + ' · Kill switch: <strong>' + (S.kill.on ? "ON" : "off") + "</strong></div>";
  });
  W.act.flood = function () {
    for (var i = 0; i < 40; i++) W.S.profiles.push({ id: W.uid("fl"), slug: "sample-queue-item-" + i + W.uid(""), type: "business", name: "Sample Queue Item " + (i + 1), title: "", industry: "Technology", city: "Lagos", status: "pending", trust: "unclaimed", sponsored: null, source: "acquired", aiDrafted: false, views: 0, enquiries: true, bio: "Sample.", achievements: "", website: "", facts: [], leadership: [], products: "", public: {}, history: [], scan: [], importedBy: "Sample Importer", drafter: "Sample Importer", submittedAt: "2026-11-01" });
    W.save(); W.render(); W.toast("40 sample items added.");
  };

  /* ---------- review queue ---------- */
  mini("/admin/review", "A03", "review", function (p, q) {
    var rows = W.queue().filter(function (x) { return !q.f || (q.f === "ai" ? x.aiDrafted : q.f === "edit" ? x.edit : q.f === "new" ? !x.edit : true); });
    return head("Review queue", "Every profile is reviewed by a person before it is public. The reviewer cannot be the importer or drafter (REQ-148).") +
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
    var fieldsDiff = p.edit ? Object.keys(p.edit).map(function (k) { return "<tr><th scope=\"row\">" + e(k) + '</th><td class="old">' + e(p[k]) + '</td><td class="new">' + e(p.edit[k]) + "</td><td>Owner-supplied</td></tr>"; }).join("") :
      ["name", "title", "org", "industry", "city", "bio", "achievements", "website"].filter(function (k) { return p[k]; }).map(function (k) { return "<tr><th scope=\"row\">" + e(k) + "</th><td>—</td><td class=\"new\">" + e(p[k]) + "</td><td>" + e(p.source === "acquired" ? "see facts" : "Owner-supplied") + "</td></tr>"; }).join("");
    var canApprove = !blocked && !scanOpen.length && !lowFacts.length;
    return '<p><a href="#/admin/review">← Review queue</a></p>' + head(p.name + (p.edit ? " (edit)" : ""), W.stPill(p.status) + " " + e(p.type) + " · source: " + e(p.source) + (p.importedBy ? " · imported by " + e(p.importedBy) : "")) +
      (blocked ? '<div class="errbox" role="alert"><strong>You cannot approve this.</strong> You imported or drafted it. Another reviewer must approve (REQ-148). Switch role to Admin or use the other reviewer to continue the demo.</div>' : "") +
      (dup.length ? '<div class="warnbox"><strong>Possible duplicate:</strong> ' + dup.map(function (d) { return '<a href="#/admin/profiles/' + e(d.id) + '">' + e(d.name) + "</a>"; }).join(", ") + ". Resolve in Duplicates before publishing (REQ-123, 146).</div>" : "") +
      '<div class="grid side"><div class="stack"><div class="card stack-s"><h2>' + (p.edit ? "Field-level diff" : "Fields") + '</h2><div class="tablewrap"><table class="diff"><thead><tr><th>Field</th><th>Before</th><th>After</th><th>Origin</th></tr></thead><tbody>' + fieldsDiff + "</tbody></table></div></div>" +
      (p.facts && p.facts.length ? '<div class="card stack-s"><h2>Facts with provenance (REQ-142)</h2>' + W.table(["Field", "Value", "Source", "Retrieved", "Confidence", "Method", ""], p.facts.map(function (f, i) { var bad = p.source === "acquired" && (f.conf === "low" || !f.src); return [e(f.label), e(f.value), e(f.src || "NONE"), e(f.retrieved), '<span class="pill ' + (f.conf === "high" ? "ok" : f.conf === "medium" ? "warn" : "bad") + '">' + e(f.conf) + "</span>", e(f.method), bad ? W.btn("Remove field", "rmFact", p.id + "|" + i, "sm bad") + '<div class="err small">Low confidence or no source cannot publish (REQ-143)</div>' : ""]; })) + "</div>" : "") +
      (p.aiClaims ? '<div class="card stack-s"><h2>AI-drafted claims next to their sources (REQ-166, 168)</h2><p class="small muted">Model, prompt version and source hash are recorded internally (sample: model-x, prompt v1, hash 3fa9…). Claims without a verbatim source passage are dropped automatically.</p>' + W.table(["Claim", "Source passage", "Quote", "Result"], p.aiClaims.map(function (c) { return [c.ok ? e(c.claim) : "<s>" + e(c.claim) + "</s>", e(c.passage || "—"), e(c.quote || "—"), c.ok ? '<span class="pill ok">matched</span>' : '<span class="pill bad">dropped: no source</span>']; })) + "</div>" : "") +
      '<div class="card stack-s"><h2>Scan flags (REQ-141)</h2>' + ((p.scan || []).length ? W.table(["Pattern", "Match", ""], p.scan.map(function (s, i) { return [e(s.kind), '<span class="mono">' + e(s.text) + "</span>", s.cleared ? '<span class="pill ok">cleared</span>' : W.btn("Mark cleared", "clearScan", p.id + "|" + i, "sm alt")]; })) + "<p class=\"small muted\">Approval is blocked until every flag is cleared. Clearing means the reviewer removed or accepted the text.</p>" : "<p class=\"muted\">No patterns found.</p>") + "</div></div>" +
      '<div class="stack"><form class="card stack-s" data-form="approve"><input type="hidden" name="id" value="' + e(p.id) + '"><h2>Neutral-content checklist (REQ-163)</h2>' + checklist.map(function (c, i) { return '<label class="check"><input type="checkbox" name="c' + i + '"> ' + e(c) + "</label>"; }).join("") + '<div class="row"><button class="btn" ' + (canApprove ? "" : 'aria-disabled="true"') + ">Approve and publish</button>" + W.btn("Reject…", "rejectDlg", p.id, "bad") + "</div>" + (canApprove ? "" : '<p class="err small">Approve is blocked: ' + [blocked ? "you drafted/imported it" : "", scanOpen.length ? scanOpen.length + " scan flag(s)" : "", lowFacts.length ? lowFacts.length + " unsourced/low-confidence field(s)" : ""].filter(Boolean).join("; ") + ".</p>") + '<p class="small muted">On approve, the page, search, sitemap and share cards switch on together (REQ-208).</p>' + (p.type === "individual" && p.source === "acquired" ? '<p class="small"><button type="button" class="btn alt sm" disabled>Notify the subject of this stub</button> <span class="muted">Waiting for Legal’s decision (Q5, REQ-159). Only if lawful and a business contact exists.</span></p>' : "") + '</form>' + notesBox(p.id) + "</div></div>";
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
    W.log("review.approve", p.id, p.name); if (p.source === "subscriber") W.mail("Owner of " + p.name, "Your profile is approved", "Your profile is now public.", "#/profile/" + p.slug);
    W.save(); W.go("#/admin/review"); W.toast(p.name + " is now public and in the sitemap.");
  };
  W.act.rejectDlg = function (id) { W.openModal("Reject with a reason", '<form data-form="reject" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Reason (sent to the owner or kept in the log)", "reason", { type: "textarea", req: true }) + '<button class="btn bad">Reject</button></form>'); };
  W.forms.reject = function (d) {
    var p = W.profile(d.id); if (p.edit) { p.edit = null; } else { p.status = "rejected"; }
    p.reason = d.reason; p.history.push({ at: W.TODAY, by: W.persona(), what: "Rejected: " + d.reason }); W.log("review.reject", p.id, d.reason);
    if (p.source === "subscriber") W.mail("Owner of " + p.name, "Your profile needs changes", d.reason, "#/account");
    W.ui.modal = null; W.save(); W.go("#/admin/review"); W.toast("Rejected with reason.");
  };

  /* ---------- duplicates, stale ---------- */
  mini("/admin/duplicates", "A21", "review", function () {
    var rows = W.S.dupes || [];
    return head("Probable duplicates", "Matching uses registration number, Wikidata ID, and normalised name + organisation + location. Individuals are never auto-merged (REQ-146).") + W.table(["Existing profile", "Incoming", "Basis", "Kind", ""], rows.map(function (d) { var a = W.profile(d.aId); return ['<a href="#/admin/profiles/' + e(a.id) + '">' + e(a.name) + "</a>", e(d.bName), e(d.basis), e(d.kind), W.btn("Merge into existing", "dupe", d.id + "|merge", "sm") + " " + W.btn("Not a duplicate", "dupe", d.id + "|keep", "sm alt")]; }), "No duplicates waiting.");
  });
  W.act.dupe = function (a) { var s = a.split("|"); W.S.dupes = W.S.dupes.filter(function (d) { return d.id !== s[0]; }); W.log("duplicate." + s[1], s[0], "human decision"); W.save(); W.render(); W.toast(s[1] === "merge" ? "Merged by a reviewer (never automatic)." : "Kept separate."); };
  mini("/admin/stale", "A39", "review", function () {
    var rows = W.S.profiles.filter(function (p) { return p.status === "published"; }).map(function (p) { var m = Math.round(age(p.lastReviewed) / 30); return { p: p, m: m }; }).filter(function (x) { return x.m >= 6 || x.p.trust === "claimed" && x.m >= 12; });
    return head("Stale records", "Unclaimed records: re-check at 6 months, flag stale at 12, unpublish at 18 unless re-reviewed. Claimed owners are prompted yearly (REQ-160).") + W.table(["Profile", "Status", "Months since review", "Action due", ""], rows.map(function (x) { return ['<a href="#/admin/profiles/' + e(x.p.id) + '">' + e(x.p.name) + "</a>", W.trustPill(x.p), x.m, x.m >= 18 ? '<span class="pill bad">unpublish</span>' : x.m >= 12 ? '<span class="pill warn">flag stale</span>' : '<span class="pill gray">re-check</span>', x.m >= 18 ? W.btn("Unpublish", "unpubDlg", x.p.id, "sm bad") : W.btn("Mark re-reviewed", "rereview", x.p.id, "sm alt")]; }), "Nothing is stale.");
  });
  W.act.rereview = function (id) { var p = W.profile(id); p.lastReviewed = W.TODAY; W.log("review.recheck", id, ""); W.save(); W.render(); };

  /* ---------- profiles ---------- */
  mini("/admin/profiles", "A05", "profiles", function (par, q) {
    var rows = W.S.profiles.filter(function (p) { return (!q.status || p.status === q.status) && (!q.type || p.type === q.type) && (!q.q || p.name.toLowerCase().indexOf(q.q.toLowerCase()) >= 0); });
    return '<div class="row between"><h1>Profiles</h1><a class="btn" href="#/admin/profiles/new">Create on behalf of a customer</a></div><form class="filters" data-filter="#/admin/profiles">' + W.field("Name", "q", { value: q.q }) + W.field("Status", "status", { type: "select", options: ["published", "pending", "draft", "rejected", "unpublished", "suppressed"], blank: "Any", value: q.status }) + W.field("Type", "type", { type: "select", options: ["individual", "business", "institution"], blank: "Any", value: q.type }) + "</form>" +
      W.table(["Name", "Type", "Status", "Trust", "Sponsored", "Visitors see", ""], rows.map(function (p) {
        var v = W.vis(p); return ['<a href="#/admin/profiles/' + e(p.id) + '">' + e(p.name || "(untitled)") + "</a>", e(p.type), W.stPill(p.status), W.trustPill(p), e(W.sponsorOf(p) || "—"), v === "public" ? "Page (200)" : v === "gone" ? "410 + noindex" : "404", p.status === "published" ? W.btn("Unpublish", "unpubDlg", p.id, "sm bad") : p.status === "unpublished" ? W.btn("Restore", "restore", p.id, "sm") : ""];
      }));
  });
  W.act.unpubDlg = function (id) { W.openModal("Unpublish " + W.profile(id).name, '<form data-form="unpub" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '"><p>The URL will return 410 Gone with noindex immediately, leave the sitemap and search, and be purged from the CDN.</p>' + W.field("Reason (audited)", "reason", { type: "textarea", req: true }) + '<button class="btn bad">Unpublish now</button></form>'); };
  W.forms.unpub = function (d) {
    var p = W.profile(d.id); p.status = "unpublished"; p.removedReason = d.reason; p.history.push({ at: W.TODAY, by: W.persona(), what: "Unpublished: " + d.reason });
    W.purge("/profile/" + p.slug, "unpublish"); W.log("profile.unpublish", p.id, d.reason); W.ui.modal = null; W.save(); W.render(); W.toast("Unpublished. 410 + noindex now; CDN purge queued.");
  };
  W.act.restore = function (id) { var p = W.profile(id); p.status = "published"; p.removedReason = ""; p.history.push({ at: W.TODAY, by: W.persona(), what: "Restored (reversible unpublish)" }); W.purge("/profile/" + p.slug, "restore"); W.log("profile.restore", id, ""); W.save(); W.render(); W.toast("Restored. The URL returns 200 again."); };

  mini("/admin/profiles/new", "A07", "profiles", function () {
    return '<p><a href="#/admin/profiles">← Profiles</a></p>' + head("Create a profile on behalf of a customer") + '<form class="card stack-s" data-form="onbehalf">' + W.field("Customer", "cust", { type: "select", options: W.S.customers.map(function (c) { return [c.id, c.name + " (" + c.segment + ")"]; }), req: true }) + W.field("Profile type", "type", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]] }) + W.field("Name", "name", { req: true }) + W.field("Complimentary listing (excluded from paid counts)", "comp", { type: "checkbox" }) + W.field("Customer consent recorded (required for complimentary publication, REQ-175)", "consent", { type: "checkbox" }) + '<p class="small muted">This record is flagged and attributed: created by ' + e(W.persona()) + ' on behalf of the customer. It still goes through review.</p><div class="row"><button class="btn">Create draft</button></div></form><div class="card stack-s"><h2>Or invite the customer to fill it in (REQ-013)</h2><p>After the offline agreement, send an invitation link. There is no payment step.</p>' + W.btn("Send invitation link (sample)", "invite", "", "alt") + " " + W.btn("Send consent request (sample)", "consentReq", "", "alt") + "</div>";
  });
  W.act.invite = function () { W.mail("Sample Customer", "You are invited to create your WHOISWHO profile", "Your arrangement is agreed. Click to start.", "#/invite/demo"); W.log("invite.send", "customer", "profile intake"); W.save(); W.render(); W.toast("Invitation sent. See the email outbox."); };
  W.act.consentReq = function () { W.mail("Sample Person A", "Consent to a complimentary listing", "Click to give or withhold consent.", "#/consent/demo"); W.save(); W.render(); W.toast("Consent request sent."); };
  W.forms.onbehalf = function (d) {
    var c = W.find("customers", d.cust);
    if (d.comp && !d.consent) { W.toast("A complimentary profile needs the customer’s recorded consent before it can be published."); }
    W.S.profiles.push({ id: W.uid("p"), slug: d.name.toLowerCase().replace(/[^a-z0-9]+/g, "-") + "-" + W.uid(""), type: d.type, name: d.name, title: "", industry: "", city: "", status: "pending", trust: "unclaimed", sponsored: null, source: d.comp ? "complimentary" : "subscriber", aiDrafted: false, views: 0, enquiries: true, bio: "Created on behalf of " + c.name + " by " + W.persona() + ".", achievements: "", website: "", facts: [], leadership: [], products: "", public: {}, history: [{ at: W.TODAY, by: W.persona(), what: "Created on behalf of customer" }], scan: [], importedBy: W.persona(), drafter: W.persona(), submittedAt: W.TODAY, consent: !!d.consent });
    W.log("profile.create", d.name, d.comp ? "complimentary" : "on behalf"); W.save(); W.go("#/admin/review"); W.toast("Draft created and queued. A different reviewer must approve it.");
  };

  mini("/admin/profiles/:id", "A06", "profiles", function (par) {
    var p = W.profile(par.id); if (!p) return "<h1>Not found</h1>";
    var arrs = W.S.arrangements.filter(function (a) { return a.linkedProfile === p.id; }), auditRows = W.S.audit.filter(function (a) { return a.target === p.id; });
    return '<p><a href="#/admin/profiles">← Profiles</a></p><div class="row between"><h1>' + e(p.name) + '</h1><div class="row"><a class="btn alt" href="#/profile/' + e(p.slug) + '">Preview as visitor</a>' + (p.status === "published" ? W.btn("Unpublish", "unpubDlg", p.id, "bad") : p.status === "unpublished" ? W.btn("Restore", "restore", p.id) : "") + "</div></div>" +
      '<div class="tagrow">' + W.stPill(p.status) + W.trustPill(p) + W.spPill(W.sponsorOf(p)) + (W.isKilled(p) ? ' <span class="pill bad">hidden by kill switch</span>' : "") + "</div>" +
      '<div class="grid c2"><div class="card stack-s"><h2>Trust status (editorial only)</h2><p class="small muted">Ladder: Unclaimed → Claimed → Verified → Featured. Payment is never an input (REQ-153, 183, 184).</p><form data-form="verify" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '"><fieldset><legend>Verified checklist (' + e(p.type) + ")</legend>" + ["Identity or registration checked against evidence", "Contact route confirmed", "Public sources match the profile", "No unresolved complaints"].map(function (c, i) { return '<label class="check"><input type="checkbox" name="v' + i + '"> ' + e(c) + "</label>"; }).join("") + W.field("Evidence reference", "evidence") + W.field("Payment status (not used)", "pay", { value: "Not an input", attrs: "disabled" }) + '</fieldset><button class="btn">Set Verified</button></form>' +
      '<form data-form="feature" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Featured: editorial reason", "reason", { req: true, value: p.featuredReason }) + '<button class="btn alt">Set Featured</button> ' + W.btn("Reset to Claimed/Unclaimed", "trustReset", p.id, "sm alt") + "</form></div>" +
      '<div class="card stack-s"><h2>Sponsored label (separate from status)</h2><form data-form="sponsor" class="stack-s"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Manual sponsor name (optional)", "name", { value: p.sponsored || "" }) + '<button class="btn alt">Save manual label</button></form><p class="small">Linked arrangements: ' + (arrs.length ? arrs.map(function (a) { return '<a href="#/admin/register/' + e(a.id) + '">' + e(a.id) + "</a> (" + e(W.arrStatus(a)) + ", " + e(a.start || "") + " to " + e(a.end || "") + ")"; }).join(", ") : "none") + '</p><p class="small muted">A confirmed sponsored-profile arrangement applies the label between its dates and removes it when it ends. Nothing is deleted.</p></div></div>' +
      '<div class="grid c2"><form class="card stack-s" data-form="slug"><h2>Public URL</h2><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Slug", "slug", { value: p.slug, hint: "Changing it creates a redirect from the old URL." }) + '<p class="small muted">Redirects: ' + e((p.redirects || []).join(", ") || "none") + '</p><button class="btn alt">Save slug</button></form><div class="card stack-s"><h2>Source sync</h2><p class="small">' + (p.qid ? "Wikidata ID " + e(p.qid) + "." : "Linked to a source record (sample).") + ' Changes from a source are flagged for review, never overwritten (REQ-135, 161).</p>' + W.btn("Check source for changes (sample)", "srcSync", p.id, "alt sm") + "</div></div>" +
      '<div class="grid c2"><div class="card stack-s"><h2>Revision history</h2>' + (p.history.length ? "<ul>" + p.history.map(function (h) { return "<li>" + e(h.at) + " · " + e(h.by) + ": " + e(h.what) + "</li>"; }).join("") + "</ul>" : '<p class="muted">None.</p>') + "<h3>Trust transitions (REQ-150)</h3>" + ((p.trustLog || []).length ? "<ul>" + p.trustLog.map(function (t) { return "<li>" + e(t) + "</li>"; }).join("") + "</ul>" : '<p class="muted small">None.</p>') + "<h3>Corrections log (REQ-164)</h3>" + (W.S.corrections.filter(function (c) { return c.profileId === p.id; }).map(function (c) { return "<p class=\"small\">" + e(c.kind) + ": " + e(c.detail) + " (" + e(c.status) + ")</p>"; }).join("") || '<p class="muted small">None.</p>') + "</div>" + notesBox(p.id) + "</div>" +
      '<div class="card"><h2>Audit entries for this profile</h2>' + W.table(["#", "When", "Actor", "Action", "Detail"], auditRows.map(function (a) { return [a.n, e(a.at), e(a.actor), e(a.action), e(a.detail)]; }), "None yet. Try setting a label.") + "</div>";
  });
  function trust(p, to, why) { var from = p.trust; p.trust = to; (p.trustLog = p.trustLog || []).push(W.TODAY + " " + from + " → " + to + " by " + W.persona() + ": " + why); W.log("trust.set", p.id, from + " to " + to + ": " + why); }
  W.forms.verify = function (d) {
    var p = W.profile(d.id); if (![0, 1, 2, 3].every(function (i) { return d["v" + i]; })) { W.toast("Tick every checklist item. Payment is not an input."); return; }
    trust(p, "verified", "checklist passed; evidence " + (d.evidence || "n/a")); W.save(); W.render(); W.toast("Set to Verified.");
  };
  W.forms.feature = function (d) { var p = W.profile(d.id); p.featuredReason = d.reason; trust(p, "featured", d.reason); W.save(); W.render(); W.toast("Set to Featured (editorial)."); };
  W.act.trustReset = function (id) { var p = W.profile(id); trust(p, W.S.claims.some(function (c) { return c.profileId === id && c.status === "approved"; }) || p.source === "subscriber" ? "claimed" : "unclaimed", "reset by staff"); W.save(); W.render(); };
  W.forms.slug = function (d) { var p = W.profile(d.id), s = d.slug.toLowerCase().replace(/[^a-z0-9-]+/g, "-"); if (s && s !== p.slug) { (p.redirects = p.redirects || []).push(p.slug); p.slug = s; p.history.push({ at: W.TODAY, by: W.persona(), what: "Slug changed; redirect created" }); W.purge("/profile/" + p.redirects[p.redirects.length - 1], "slug change"); W.log("slug.change", p.id, s); W.save(); W.render(); W.toast("Saved. The old URL redirects to the new one."); } };
  W.act.srcSync = function (id) { var p = W.profile(id); p.edit = Object.assign(p.edit || {}, { title: (p.title || "Role") + " (source changed)" }); W.log("source.sync", id, "change flagged"); W.save(); W.toast("A source change was found and queued for review instead of overwriting."); W.render(); };
  W.forms.sponsor = function (d) { var p = W.profile(d.id); p.sponsored = d.name || null; W.log("sponsored.set", p.id, d.name || "cleared"); W.save(); W.render(); W.toast("Sponsored label updated. Status unchanged."); };

  /* ---------- claims ---------- */
  mini("/admin/claims", "A08", "claims", function () {
    var rows = W.S.claims;
    return head("Claims", "Organisations claim by domain email or document; individuals by document and manual approval (D-022).") + W.table(["Profile", "Claimant", "Proof", "Status", "Notes", ""], rows.map(function (c) { var p = W.profile(c.profileId), multi = rows.filter(function (x) { return x.profileId === c.profileId && x.status === "pending"; }).length > 1 && c.status === "pending"; return [e(p ? p.name : "?"), e(c.claimant), e(c.route), W.stPill(c.status), multi ? '<span class="pill bad">dispute: 2+ claimants</span>' : "", '<a class="btn sm alt" href="#/admin/claims/' + e(c.id) + '">Review</a>']; }), "No claims.");
  });
  mini("/admin/claims/:id", "A09", "claims", function (par) {
    var c = W.find("claims", par.id), p = c && W.profile(c.profileId); if (!c) return "<h1>Not found</h1>";
    var dispute = W.S.claims.filter(function (x) { return x.profileId === c.profileId && x.status === "pending"; }).length > 1;
    return '<p><a href="#/admin/claims">← Claims</a></p>' + head("Claim on " + p.name) + (dispute ? '<div class="warnbox"><strong>Ownership dispute.</strong> Another claimant is pending for this profile. Escalate to the Editor-in-Chief (REQ-124, 174).</div>' : "") + '<div class="card stack-s"><p>Claimant: <strong>' + e(c.claimant) + "</strong> (" + e(c.relation) + ")</p><p>Proof: " + e(c.route) + ' <span class="mono">claim-document.pdf</span> (private storage, retention-limited)</p><p>Status: ' + W.stPill(c.status) + '</p><div class="row">' + W.btn("Approve claim", "claimOk", c.id) + W.btn("Deny…", "claimNo", c.id, "bad") + (dispute ? W.btn("Escalate to Editor-in-Chief", "claimEsc", c.id, "alt") : "") + "</div></div>" + notesBox(c.id);
  });
  W.act.claimOk = function (id) {
    var c = W.find("claims", id), p = W.profile(c.profileId); c.status = "approved"; if (p.trust === "unclaimed") trust(p, "claimed", "claim approved: " + c.claimant);
    W.S.claims.forEach(function (x) { if (x.profileId === c.profileId && x.id !== id && x.status === "pending") x.status = "needs EIC"; });
    W.mail(c.claimant, "Your claim was approved", "You can now manage " + p.name + ".", "#/profile/" + p.slug); W.log("claim.approve", c.profileId, c.claimant); W.save(); W.render(); W.toast("Approved. The profile is now Claimed.");
  };
  W.act.claimNo = function (id) { var c = W.find("claims", id); c.status = "denied"; W.mail(c.claimant, "Your claim was not approved", "We could not verify your proof.", ""); W.log("claim.deny", c.profileId, c.claimant); W.save(); W.render(); };
  W.act.claimEsc = function (id) { var c = W.find("claims", id); W.S.escalations.push({ id: id, at: W.TODAY, kind: "claim dispute" }); c.status = "escalated"; W.log("claim.escalate", c.profileId, "to Editor-in-Chief"); W.save(); W.render(); W.toast("Escalated to the Editor-in-Chief."); };

  /* ---------- inbox, reports, corrections, dsr ---------- */
  mini("/admin/inbox", "A12", "inbox", function () {
    var S = W.S, rows = [];
    S.enquiries.forEach(function (x) { rows.push(["Enquiry", e(x.general ? "Contact form: " + x.name : "To " + ((W.profile(x.profileId) || {}).name || "?") + " from " + x.name), e(x.read ? "read" : "new"), age(x.at), "Sales"]); });
    S.claims.filter(function (c) { return c.status === "pending" || c.status === "needs EIC" || c.status === "escalated"; }).forEach(function (x) { rows.push(["Claim", e(x.claimant) + " on " + e((W.profile(x.profileId) || {}).name), e(x.status), age(x.at), "Reviewer"]); });
    S.reports.forEach(function (x) { rows.push(["Report", e(x.reason) + " on " + e((W.profile(x.profileId) || {}).name), e(x.status), age(x.at), e(x.owner || "unassigned")]); });
    S.corrections.concat(S.removals).forEach(function (x) { rows.push([e(x.kind), e(x.detail), e(x.status), age(x.at), "Reviewer"]); });
    S.escalations.forEach(function (x) { rows.push(["Escalation", e(x.kind), "with Editor-in-Chief", age(x.at), "Editor-in-Chief"]); });
    rows.sort(function (a, b) { return b[3] - a[3]; });
    return head("Support inbox", "Enquiries, claims, reports and complaints in one list with owner, status and age (REQ-089).") + W.table(["Type", "Subject", "Status", "Age (days)", "Owner"], rows, "Inbox zero.");
  });
  mini("/admin/reports", "A10", "reports", function () {
    return head("Abuse reports", "Reports from the button on every profile (REQ-090).") + W.table(["Profile", "Reason", "Detail", "Status", ""], W.S.reports.map(function (r) { var p = W.profile(r.profileId); return [e(p ? p.name : "?"), e(r.reason), e(r.detail), W.stPill(r.status), r.status === "open" ? W.btn("Hide profile now", "unpubDlg", r.profileId, "sm bad") + " " + W.btn("Dismiss", "repDone", r.id, "sm alt") + " " + W.btn("Resolve", "repDone", r.id, "sm") : ""]; }), "No reports.");
  });
  W.act.repDone = function (id) { W.find("reports", id).status = "closed"; W.log("report.close", id, ""); W.save(); W.render(); };
  mini("/admin/corrections", "A11", "reports", function () {
    var rows = W.S.removals.concat(W.S.corrections);
    return head("Corrections and removals", "Targets: acknowledge in 1 business day; hide within 2 business days of a valid removal; resolve corrections in 7 days (REQ-158). Breaches are flagged.") + W.table(["Kind", "Profile", "Detail", "Due", "Status", ""], rows.map(function (x) { var p = W.profile(x.profileId), late = x.status === "open" && x.due < W.TODAY; return [e(x.kind), e(p ? p.name : "—"), e(x.detail), e(x.due) + (late ? ' <span class="pill bad">overdue</span>' : ""), W.stPill(x.status), x.status !== "open" ? "" : x.kind === "Removal" ? W.btn("Approve: hide + suppress", "rmOk", x.id, "sm bad") + " " + W.btn("Decline", "corrDone", x.id, "sm alt") : W.btn("Mark resolved", "corrDone", x.id, "sm")]; }), "Nothing open.");
  });
  W.act.corrDone = function (id) { var x = W.find("corrections", id) || W.find("removals", id); x.status = "closed"; var p = W.profile(x.profileId); if (p && x.kind !== "Removal") p.history.push({ at: W.TODAY, by: W.persona(), what: "Correction resolved: " + x.detail }); W.log("request.close", id, x.kind); W.save(); W.render(); };
  W.act.rmOk = function (id) {
    var x = W.find("removals", id), p = W.profile(x.profileId); x.status = "done"; p.status = "suppressed"; p.removedReason = "Removal request";
    W.S.suppression.unshift({ id: W.uid("sp"), name: p.name, kind: "Removed individual or organisation", at: W.TODAY, reason: "Removal request " + id }); W.purge("/profile/" + p.slug, "removal request"); W.log("removal.approve", p.id, "suppressed"); W.mail(x.contact || "requester", "Your removal request was accepted", "The profile is no longer public.", ""); W.save(); W.render(); W.toast("Hidden (410 + noindex), added to the permanent suppression list, CDN purge queued.");
  };
  mini("/admin/dsr", "A35", "dsr", function () {
    return head("Data requests", "Access, correction, erasure, objection, portability, each with a due date (REQ-059).") + W.table(["Type", "Requester", "Received", "Due", "Status", ""], W.S.dsr.map(function (d) { return [e(d.kind), e(d.who), e(d.at), e(d.due), W.stPill(d.status), d.status !== "closed" ? W.btn("Close", "dsrDone", d.id, "sm") : ""]; }), "No requests.");
  });
  W.act.dsrDone = function (id) { W.find("dsr", id).status = "closed"; W.log("dsr.close", id, ""); W.save(); W.render(); };
})();
