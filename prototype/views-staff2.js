/* Staff console part 2: acquisition, commercial, content, awards, platform. Flow preview only. */
(function () {
  var W = window.W, e = W.esc, ACL = W.ACL, ALL = W.ALLSTAFF;
  function head(t, sub) { return "<h1>" + e(t) + "</h1>" + (sub ? '<p class="muted">' + sub + "</p>" : ""); }
  function mini(path, inv, key, fn) { W.staffRoute(path, inv, key, fn); }
  function onlyAdmin(path, inv, fn) { W.route(path, inv, fn, { area: "staff", roles: [] }); }

  /* ---------- sources, import, AI, prospects, suppression, lists ---------- */
  mini("/admin/sources", "A19", "sources", function () {
    return head("Source registry", "Ingestion is allowed only from sources with status Approved, checked by a named person (REQ-136, 137). Never LinkedIn or social platforms (REQ-138).") + W.table(["Source", "Entity types", "Access", "Licence / terms", "robots checked", "Approver", "Status", ""], W.S.sources.map(function (s) { return [e(s.name), e(s.types), e(s.access), e(s.licence), e(s.robots || "—"), e(s.approver || "—"), W.stPill(s.status === "Approved" ? "approved" : s.status === "Rejected" ? "rejected" : "pending"), s.status === "Pending approval" ? W.btn("Approve (I checked terms, licence, robots)", "srcOk", s.id, "sm") + " " + W.btn("Reject", "srcNo", s.id, "sm alt bad") : ""]; })) + '<form class="card stack-s" data-form="source"><h2>Add a source</h2>' + W.field("Name", "name", { req: true }) + W.field("URL", "url", { req: true }) + W.field("Access method", "access", { type: "select", options: ["API", "Export", "Crawl", "Manual"] }) + W.field("Licence and terms summary", "licence", { req: true }) + '<button class="btn">Add as pending</button></form>';
  });
  W.act.srcOk = function (id) { var s = W.find("sources", id); s.status = "Approved"; s.approver = W.persona(); s.robots = W.TODAY; W.log("source.approve", id, s.name); W.save(); W.render(); };
  W.act.srcNo = function (id) { var s = W.find("sources", id); s.status = "Rejected"; W.log("source.reject", id, s.name); W.save(); W.render(); };
  W.forms.source = function (d) { W.S.sources.push({ id: W.uid("src"), name: d.name, types: "tbc", url: d.url, access: d.access, licence: d.licence, robots: "", approver: "", status: "Pending approval" }); W.log("source.add", d.name, ""); W.save(); W.render(); };

  var SAMPLE_CSV = "name,type,industry,city,title,org,website\nSample Import Co. One,business,Technology,Lagos,,,https://example.org/one\nSample Import Co. Two,business,Energy,Abuja,,,https://example.org/two\nSample Person X,individual,Technology,Lagos,Director,Sample Import Co. One,\nSample Agritech Co.,business,Agriculture & Agritech,Lagos,,,https://example.org/agritech\n,business,Technology,Lagos,,,\nSample Person Y,individual,Technology,Lagos,Call me on 0800-000-0000,Sample Import Co. Two,";
  W.ui.imp = { csv: SAMPLE_CSV, src: "src1", rows: null };
  function dry(csv, src) {
    var lines = csv.trim().split(/\n/).slice(1), seen = {};
    return lines.map(function (l) {
      var c = l.split(","), r = { name: (c[0] || "").trim(), type: c[1] || "business", industry: c[2] || "", city: c[3] || "", title: c[4] || "", org: c[5] || "", website: c[6] || "", status: "ok", why: "" };
      if (!r.name) { r.status = "error"; r.why = "Name is required"; }
      else if (W.S.suppression.some(function (s) { return s.name.toLowerCase() === r.name.toLowerCase(); })) { r.status = "skipped"; r.why = "On the permanent suppression list (REQ-207)"; }
      else if (W.S.profiles.some(function (p) { return p.name.toLowerCase() === r.name.toLowerCase(); })) { r.status = "duplicate"; r.why = "Matches an existing profile; goes to duplicate review"; }
      else if (W.scan(r.title + " " + r.org).length) { r.status = "blocked"; r.why = "Free text has a phone/email/age pattern (REQ-141)"; }
      else if (seen[r.name]) { r.status = "duplicate"; r.why = "Repeated in this file"; }
      seen[r.name] = 1; return r;
    });
  }
  mini("/admin/import", "A20", "review", function () {
    var I = W.ui.imp, src = W.find("sources", I.src), ok = src && src.status === "Approved", ap = W.S.sources.filter(function (s) { return s.status === "Approved"; });
    return head("Bulk import (CSV)", "Dry-run first. Imports are tagged with source and importer and go to the review queue; nothing publishes directly (REQ-092, 148).") + '<form class="card stack-s" data-form="impdry">' + W.field("Source", "src", { type: "select", options: W.S.sources.map(function (s) { return [s.id, s.name + " — " + s.status]; }), value: I.src }) + (ok ? "" : '<div class="errbox">This source is not Approved. Import is disabled (REQ-137).</div>') + W.field("CSV (sample prefilled)", "csv", { type: "textarea", value: I.csv, attrs: 'rows="8" style="font-family:monospace"' }) + '<button class="btn">Dry run</button></form>' + (I.rows ? "<h2>Dry-run result</h2>" + W.table(["Row", "Name", "Result", "Why"], I.rows.map(function (r, i) { return [i + 1, e(r.name || "(blank)"), '<span class="pill ' + (r.status === "ok" ? "ok" : r.status === "duplicate" ? "warn" : "bad") + '">' + r.status + "</span>", e(r.why)]; })) + "<p>" + I.rows.filter(function (r) { return r.status === "ok"; }).length + " row(s) can be imported.</p>" + W.btn("Import valid rows to review queue", "impGo", "", ok ? "" : "disabled", ok ? "" : " disabled") : "") + (W.S.imports.length ? "<h2>Past imports</h2>" + W.table(["When", "Source", "By", "Rows"], W.S.imports.map(function (x) { return [e(x.at), e(x.src), e(x.by), x.n]; })) : "");
  });
  W.forms.impdry = function (d) { W.ui.imp = { csv: d.csv, src: d.src, rows: dry(d.csv, d.src) }; W.render(); };
  W.act.impGo = function () {
    var I = W.ui.imp, src = W.find("sources", I.src); if (!src || src.status !== "Approved") return;
    var rows = I.rows.filter(function (r) { return r.status === "ok"; });
    rows.forEach(function (r) { W.S.profiles.push({ id: W.uid("im"), slug: r.name.toLowerCase().replace(/[^a-z0-9]+/g, "-") + "-" + W.uid(""), type: r.type, name: r.name, title: r.title, org: r.org, industry: r.industry, city: r.city, status: "pending", trust: "unclaimed", sponsored: null, source: "acquired", aiDrafted: false, views: 0, enquiries: true, bio: r.name + " (imported; text to be written by a reviewer).", achievements: "", website: r.website, public: { website: true }, facts: [{ label: "Imported record", value: r.name, src: src.name, conf: "medium", method: "import", retrieved: W.TODAY }], leadership: [], products: "", history: [], scan: [], importedBy: W.persona(), drafter: W.persona(), submittedAt: W.TODAY }); });
    W.S.imports.unshift({ at: W.TODAY, src: src.name, by: W.persona(), n: rows.length }); W.log("import.run", src.name, rows.length + " rows"); W.ui.imp.rows = null; W.save(); W.render(); W.toast(rows.length + " profiles added to the review queue. You cannot approve your own import.");
  };
  mini("/admin/ai", "A23", "review", function () {
    var on = W.ok("ai_drafting");
    return head("AI drafting", "Drafts a profile only from fetched source text. Every claim must carry a verbatim quote from a stored passage or it is dropped (REQ-165 to 170).") + (on ? '<div class="card stack-s"><p>Flag ON (approval ref ' + e(W.S.flags.ai_drafting.ref) + ").</p>" + W.field("Approved source URL", "u", { value: "https://example.org/sample-source-page" }) + W.btn("Draft (sample output)", "aiDraft", "") + '</div><p class="small muted">The sample draft is a canned result. Prohibited topics (legal trouble, health, family, finances, politics) are removed automatically.</p>' : '<div class="warnbox"><strong>AI drafting is OFF.</strong> It needs Legal’s confirmation of the data transfer and an Admin to turn the flag on with an approval reference. <a href="#/admin/flags">Feature flags</a></div>');
  });
  W.act.aiDraft = function () {
    W.S.profiles.push({ id: W.uid("ai"), slug: "sample-ai-draft-co-" + W.uid(""), type: "business", name: "Sample AI Draft Co.", title: "Sample sector", industry: "Technology", city: "Lagos", status: "pending", trust: "unclaimed", sponsored: null, source: "acquired", aiDrafted: true, views: 0, enquiries: true, bio: "Sample AI Draft Co. is a sample company. It was founded in 2010.", achievements: "", website: "", public: {}, facts: [{ label: "Founded", value: "2010", src: "Sample source page", conf: "high", method: "AI", retrieved: W.TODAY }], leadership: [], products: "", history: [], scan: [], importedBy: W.persona(), drafter: "AI + " + W.persona(), submittedAt: W.TODAY, aiClaims: [{ claim: "Founded in 2010", passage: "p2", quote: "Sample AI Draft Co. was founded in 2010.", ok: true }, { claim: "Is the best company in its field", passage: "", quote: "", ok: false }, { claim: "Faced a lawsuit", passage: "p9", quote: "(prohibited topic)", ok: false }] });
    W.log("ai.draft", "Sample AI Draft Co.", "model-x prompt v1"); W.save(); W.go("#/admin/review"); W.toast("Draft created. 2 of 3 claims were dropped automatically.");
  };
  if (!Array.isArray(W.prospects)) W.prospects = null;
  mini("/admin/prospects", "A42", "leads", function () {
    var P = W.S.prospects = W.S.prospects || [{ id: "pr1", name: "Sample Prospect Co. A", source: "Manual research", status: "to contact" }, { id: "pr2", name: "Sample Prospect Co. B", source: "Licensed list (sample)", status: "contacted" }];
    return head("Prospects (sales research)", "A separate store. Never public. A prospect can become a profile only by entering the review path with an approved source (REQ-093, 176).") + W.table(["Prospect", "Source", "Status", ""], P.map(function (p) { return [e(p.name), e(p.source), e(p.status), W.btn("Send to review path", "prospectGo", p.id, "sm alt")]; })) + "<p>" + W.btn("Export CSV", "dl", "prospects", "alt") + "</p>";
  });
  W.act.prospectGo = function (id) { W.toast("Needs an Approved source first. Go to Import and choose an approved source (sample guard)."); };
  mini("/admin/suppression", "A22", "review", function () {
    return head("Suppression list", "Permanent. No import or sync can bring these back. There is no remove button on purpose (REQ-147, 207).") + W.table(["Name", "Reason type", "Date", "Detail"], W.S.suppression.map(function (s) { return [e(s.name), e(s.kind), e(s.at), e(s.reason)]; })) + '<form class="card stack-s" data-form="supp"><h2>Add manually</h2>' + W.field("Name", "name", { req: true }) + W.field("Reason", "reason", { req: true }) + '<button class="btn">Add</button></form>';
  });
  W.forms.supp = function (d) { W.S.suppression.unshift({ id: W.uid("sp"), name: d.name, kind: "Manual", at: W.TODAY, reason: d.reason }); W.log("suppression.add", d.name, d.reason); W.save(); W.render(); };
  mini("/admin/lists", "A25", "profiles", function () {
    var L = W.LISTS;
    return head("Controlled lists", "Used by profiles and filters (REQ-009). Country list is Nigeria only for the MVP (D-010).") + '<div class="grid c2">' + [["industries", "Industries"], ["cities", "Cities"], ["orgTypes", "Organisation types"], ["professions", "Professions"]].map(function (k) { return '<div class="card stack-s"><h2>' + e(k[1]) + "</h2><ul>" + L[k[0]].map(function (v) { return "<li>" + e(v) + "</li>"; }).join("") + '</ul><form data-form="listadd" class="row"><input type="hidden" name="k" value="' + k[0] + '"><label class="sr" for="la' + k[0] + '">Add</label><input id="la' + k[0] + '" name="v" required style="flex:1"><button class="btn sm">Add</button></form></div>'; }).join("") + "</div>";
  });
  W.forms.listadd = function (d) { W.LISTS[d.k].push(d.v); W.S.lists = W.S.lists || {}; W.S.lists[d.k] = W.LISTS[d.k].slice(); W.log("list.add", d.k, d.v); W.save(); W.render(); };

  /* ---------- leads, customers ---------- */
  mini("/admin/leads", "A13", "leads", function () {
    return '<div class="row between"><h1>Leads</h1>' + W.btn("Export CSV", "dl", "leads", "alt") + "</div>" + W.table(["ID", "Name / org", "Interest", "Contact", "Status", "Owner", "Notes", ""], W.S.leads.map(function (l) { return [e(l.ext), e(l.name) + "<br><span class=\"muted small\">" + e(l.org) + "</span>", e(l.interest), e(l.contact), '<label class="sr" for="ls' + l.id + '">Status</label><select id="ls' + l.id + '" data-change="leadStatus" data-lead="' + e(l.id) + '">' + ["new", "contacted", "qualified", "converted", "lost"].map(function (s) { return "<option" + (s === l.status ? " selected" : "") + ">" + s + "</option>"; }).join("") + "</select>", e(l.owner || "unassigned"), e(l.notes), l.status === "converted" ? "Converted" : W.btn("Convert", "leadConv", l.id, "sm")]; }), "No leads yet.") + '<p class="small muted">Leads come from the Get Featured form. Notes also live under Internal notes (never public).</p>';
  });
  W.act.leadStatus = function (v, el) { var l = W.find("leads", el.getAttribute("data-lead")); l.status = v; if (!l.owner) l.owner = W.persona(); W.log("lead.status", l.ext, v); W.save(); W.render(); };
  W.act.leadConv = function (id) {
    var l = W.find("leads", id), cid = W.uid("c"), aid = W.uid("arr");
    W.S.customers.push({ id: cid, name: l.org || l.name, segment: "Business", contact: l.name, ext: "CUS-" + ("000" + (W.S.customers.length + 1)).slice(-4), profileId: "" });
    W.S.arrangements.push({ id: aid, type: "other", customerId: cid, linkedProfile: "", amount: 0, currency: "NGN", method: "", ref: "", received: "", confirmedBy: "", onboarding: false, start: "", end: "", complimentary: false, agreed: false, cancelled: false, endedManual: false, stream: "subscription", notes: "From lead " + l.ext, consent: false });
    l.status = "converted"; W.log("lead.convert", l.ext, "customer + arrangement"); W.save(); W.go("#/admin/register/" + aid); W.toast("Customer and a proposed arrangement created.");
  };
  mini("/admin/customers", "A14", "customers", function () {
    return head("Customers") + W.table(["External ID", "Name", "Segment", "Contact", "Arrangements", ""], W.S.customers.map(function (c) { var n = W.S.arrangements.filter(function (a) { return a.customerId === c.id; }).length; return [e(c.ext), e(c.name), e(c.segment), e(c.contact), n, W.btn("Send profile invitation", "invite", c.id, "sm alt")]; })) + '<p class="small muted">Customers, leads and enquiries have stable external IDs and change timestamps for a later CaromHQ link (REQ-205).</p>';
  });

  /* ---------- register ---------- */
  mini("/admin/register", "A15", "register", function (par, q) {
    var rows = W.S.arrangements.filter(function (a) { return !q.s || W.arrStatus(a).indexOf(q.s) === 0; });
    return '<div class="row between"><h1>Commercial register</h1><div class="row"><a class="btn" href="#/admin/register/new">New arrangement</a>' + W.btn("Export CSV", "dl", "register", "alt") + '</div></div><div class="infobox"><strong>Counting rule (REQ-022).</strong> A paid subscription = a non-complimentary arrangement with status Confirmed. Confirmed needs amount, currency, payment method and reference, date received, a named confirmed-by admin, and onboarding started. Status is computed from the fields; it is not typed in. Complimentary never counts. <em>Ended arrangements that were confirmed still count in this wireframe: see report notes.</em></div>' +
      '<form class="filters" data-filter="#/admin/register">' + W.field("Status", "s", { type: "select", options: ["proposed", "awaiting payment", "confirmed", "ended", "cancelled"], blank: "Any", value: q.s }) + "</form>" +
      W.table(["ID", "Type", "Customer", "Amount", "Stream", "Status (computed)", "Counts?", "Dates", ""], rows.map(function (a) { var c = W.find("customers", a.customerId); return [e(a.id), e(W.LISTS.arrTypes[a.type]), e(c ? c.name : "?"), a.complimentary ? '<span class="pill gray">complimentary</span>' : W.money(a.amount, a.currency), e(W.LISTS.streams[a.stream]), W.stPill(W.arrStatus(a)), W.counts(a) ? '<span class="pill ok">Yes</span>' : '<span class="pill gray">No</span>', e((a.start || "?") + " to " + (a.end || "?")), '<a class="btn sm alt" href="#/admin/register/' + e(a.id) + '">Open</a>']; }));
  });
  function arrForm(a, isNew) {
    var miss = W.arrMissing(a);
    return '<form class="card stack-s" data-form="arr" data-live="arr"><input type="hidden" name="id" value="' + e(a.id) + '">' + W.field("Type", "type", { type: "select", options: Object.keys(W.LISTS.arrTypes).map(function (k) { return [k, W.LISTS.arrTypes[k]]; }), value: a.type }) + W.field("Customer", "customerId", { type: "select", options: W.S.customers.map(function (c) { return [c.id, c.name + " (" + c.segment + ")"]; }), value: a.customerId }) +
      W.field("Agreed with customer", "agreed", { type: "checkbox", value: a.agreed }) + W.field("Complimentary (excluded from paid counts and revenue)", "complimentary", { type: "checkbox", value: a.complimentary }) + W.field("Amount (NGN, whole naira)", "amount", { type: "number", value: a.amount / 100 }) + W.field("Currency", "currency", { value: a.currency }) + W.field("Payment method", "method", { type: "select", options: ["Bank transfer", "Cash", "Cheque", "Other"], blank: "—", value: a.method }) + W.field("Payment reference", "ref", { value: a.ref }) + W.field("Date received", "received", { type: "date", value: a.received }) + W.field("Confirmed by (named admin)", "confirmedBy", { value: a.confirmedBy }) + W.field("Onboarding started", "onboarding", { type: "checkbox", value: a.onboarding }) +
      W.field("Start date", "start", { type: "date", value: a.start }) + W.field("End date", "end", { type: "date", value: a.end }) + W.field("Revenue stream", "stream", { type: "select", options: Object.keys(W.LISTS.streams).map(function (k) { return [k, W.LISTS.streams[k]]; }), value: a.stream, hint: "Default from the type; editable." }) + W.field("Linked profile (for Sponsored label)", "linkedProfile", { type: "select", options: W.S.profiles.map(function (p) { return [p.id, p.name || p.id]; }), blank: "None", value: a.linkedProfile }) + W.field("Customer consent recorded (complimentary profiles)", "consent", { type: "checkbox", value: a.consent }) + W.field("Notes", "notes", { type: "textarea", value: a.notes }) + (isNew ? "" : W.field("Reason for this change (audited)", "reason", { req: true })) +
      '<div id="arrcheck" class="infobox" role="status" aria-live="polite">' + arrCheck(a, miss) + '</div><div class="row"><button class="btn">Save</button>' + (isNew ? "" : W.btn("End now", "arrEnd", a.id, "alt") + W.btn("Cancel arrangement", "arrCancel", a.id, "alt bad")) + "</div></form>";
  }
  function arrCheck(a, miss) {
    miss = miss || W.arrMissing(a); var st = W.arrStatus(a);
    return "<strong>Status: " + e(st) + ".</strong> Counts toward the 10 paid subscriptions: <strong>" + (W.counts(a) ? "Yes" : "No") + "</strong>" + (a.complimentary ? " (complimentary never counts)" : miss.length ? "; missing: " + e(miss.join(", ")) : "") + "." + (a.type === "sponsored_profile" && a.linkedProfile ? " The Sponsored label shows on the linked profile from " + e(a.start || "?") + " to " + e(a.end || "?") + " while Confirmed." : "") + " This never changes Verified or Featured.";
  }
  W.live.arr = function (f) {
    var d = W.formData(f), a = readArr(d, {}); var box = document.getElementById("arrcheck"); if (box) box.innerHTML = arrCheck(a);
  };
  function readArr(d, old) {
    var a = Object.assign({}, old, { type: d.type, customerId: d.customerId, agreed: !!d.agreed, complimentary: !!d.complimentary, amount: Math.round((Number(d.amount) || 0) * 100), currency: d.currency || "", method: d.method || "", ref: d.ref || "", received: d.received || "", confirmedBy: d.confirmedBy || "", onboarding: !!d.onboarding, start: d.start || "", end: d.end || "", stream: d.stream, linkedProfile: d.linkedProfile || "", consent: !!d.consent, notes: d.notes || "" });
    return a;
  }
  mini("/admin/register/new", "A16", "register", function () {
    var a = { id: "new", type: "sponsored_profile", customerId: W.S.customers[0].id, amount: 0, currency: "NGN", method: "", ref: "", received: "", confirmedBy: "", onboarding: false, start: "", end: "", complimentary: false, agreed: false, stream: "subscription", linkedProfile: "", notes: "", consent: false };
    return '<p><a href="#/admin/register">← Register</a></p>' + head("New arrangement") + arrForm(a, true);
  });
  mini("/admin/register/:id", "A16", "register", function (par) {
    var a = W.find("arrangements", par.id); if (!a) return "<h1>Not found</h1>";
    return '<p><a href="#/admin/register">← Register</a></p>' + head("Arrangement " + a.id) + arrForm(a, false) + W.notesBox(a.id);
  });
  W.forms.arr = function (d) {
    if (d.id === "new") { var n = readArr(d, { id: W.uid("arr"), cancelled: false, endedManual: false }); W.S.arrangements.push(n); W.log("arrangement.create", n.id, n.type); W.save(); W.go("#/admin/register/" + n.id); return; }
    var a = W.find("arrangements", d.id), before = W.arrStatus(a); var n2 = readArr(d, a); Object.assign(a, n2);
    W.log("arrangement.edit", a.id, "reason: " + d.reason + "; " + before + " to " + W.arrStatus(a)); W.save(); W.render(); W.toast("Saved. Status is now " + W.arrStatus(a) + "; Verified and Featured unchanged.");
  };
  W.act.arrEnd = function (id) { var a = W.find("arrangements", id); a.endedManual = true; W.log("arrangement.end", id, "ended by admin"); W.save(); W.render(); W.toast("Ended. The Sponsored label it set is removed automatically; nothing is deleted."); };
  W.act.arrCancel = function (id) { var a = W.find("arrangements", id); a.cancelled = true; W.log("arrangement.cancel", id, "recorded; refund handled offline by Finance"); W.save(); W.render(); };
  mini("/admin/sponsors", "A18", "sponsors", function () {
    return head("Sponsors", "Records admins attach to awards, categories and pages (REQ-186). Sponsor name is shown as a disclosure only.") + W.table(["Sponsor", "Link", "Logo", "Attached to"], W.S.sponsors.map(function (s) { return [e(s.name), e(s.link), e(s.logo), e(s.attached.join("; ") || "nothing")]; })) + '<form class="card stack-s" data-form="sponsor2"><h2>Add sponsor</h2>' + W.field("Name", "name", { req: true }) + W.field("Link", "link") + W.field("Attach to", "to", { type: "select", options: ["Awards: Maiden Edition", "Category page", "Stories page", "Not yet"] }) + '<button class="btn">Add</button></form>';
  });
  W.forms.sponsor2 = function (d) { W.S.sponsors.push({ id: W.uid("s"), name: d.name, link: d.link, logo: "Logo placeholder", attached: d.to === "Not yet" ? [] : [d.to] }); W.log("sponsor.add", d.name, d.to); W.save(); W.render(); };

  /* ---------- articles ---------- */
  mini("/admin/articles", "A24", "articles", function () {
    return '<div class="row between"><h1>Articles</h1><a class="btn" href="#/admin/articles/new">New article</a></div>' + W.table(["Title", "Type", "Status", "Date", "Sponsored", ""], W.S.articles.map(function (a) { return [e(a.title), e(a.type), W.stPill(a.status), e(a.date), e(W.sponsorOfArticle(a) || "—"), '<a class="btn sm alt" href="#/admin/articles/' + e(a.id) + '">Edit</a>']; }));
  });
  function artForm(a) {
    return '<form class="card stack-s" data-form="article"><input type="hidden" name="id" value="' + e(a.id) + '">' + W.field("Title", "title", { req: true, value: a.title }) + W.field("Type", "type", { type: "select", options: ["Interview", "Feature", "Spotlight", "Opinion", "Announcement", "News"], value: a.type }) + W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, value: a.industry }) + W.field("Author", "author", { value: a.author || W.persona() }) + W.ph("Hero image") + W.field("Body", "body", { type: "textarea", value: a.body }) + W.field("Tags", "tags", { value: a.tags || "" }) + W.field("SEO title and description", "seo", { value: a.seo || "" }) + W.field("Linked profiles", "profiles", { type: "select", options: W.S.profiles.filter(W.isPublic).map(function (p) { return [p.id, p.name]; }), blank: "None", value: (a.profiles || [])[0] || "" }) + W.field("Sponsor (shows a visible Sponsored label)", "sponsor", { value: a.sponsor || "" }) + W.field("Status", "status", { type: "select", options: ["draft", "scheduled", "published"], value: a.status }) + W.field("Publish date", "date", { type: "date", value: a.date }) + '<button class="btn">Save</button></form>';
  }
  mini("/admin/articles/new", "A24", "articles", function () { return '<p><a href="#/admin/articles">← Articles</a></p>' + head("New article") + artForm({ id: "new", title: "", type: "Feature", industry: W.LISTS.industries[0], body: "", status: "draft", date: W.TODAY, profiles: [] }); });
  mini("/admin/articles/:id", "A24", "articles", function (p) { var a = W.find("articles", p.id); return a ? '<p><a href="#/admin/articles">← Articles</a></p>' + head("Edit article") + artForm(a) : "<h1>Not found</h1>"; });
  W.forms.article = function (d) {
    var a = d.id === "new" ? { id: W.uid("a"), slug: "sample-article-" + W.uid(""), author: d.author } : W.find("articles", d.id);
    Object.assign(a, { title: d.title, type: d.type, industry: d.industry, author: d.author, body: d.body, tags: d.tags, seo: d.seo, profiles: d.profiles ? [d.profiles] : [], sponsor: d.sponsor || null, status: d.status, date: d.date });
    if (d.id === "new") W.S.articles.push(a); W.log("article." + d.status, a.id, a.title); if (d.status !== "published") W.purge("/stories/" + a.slug, "article not public");
    W.save(); W.go("#/admin/articles"); W.toast("Saved as " + d.status + ".");
  };

  /* ---------- awards ---------- */
  var STAT = { submitted: "Submitted", screening: "In screening", invited: "Nominee invited", accepted: "Nominee accepted", assessment: "Independent assessment", shortlisted: "Shortlisted", finalist: "Finalist (approved)", winner: "Winner", ineligible: "Ineligible", declined: "Declined (suppressed)" };
  mini("/admin/nominations", "A28", "awards", function (par, q) {
    var rows = W.S.nominations.filter(function (n) { return !q.s || n.status === q.s; });
    return '<div class="row between"><h1>Nominations</h1><div class="row">' + W.btn("Add curated nomination", "curate", "", "alt") + W.btn("Export CSV", "dl", "nominations", "alt") + "</div></div>" + (W.nomOpen() ? "" : '<div class="infobox">The public form is closed (flag OFF). This queue still works for curated nominations.</div>') + '<form class="filters" data-filter="#/admin/nominations">' + W.field("Status", "s", { type: "select", options: Object.keys(STAT).map(function (k) { return [k, STAT[k]]; }), blank: "Any", value: q.s }) + "</form>" +
      W.table(["Ref", "Nominee", "Category", "Status", "Stage", "Nominator verified", "Conflict", ""], rows.map(function (n) { return [e(n.ref), e(n.nominee), e(n.category), W.stPill(STAT[n.status] || n.status), W.nomStage(n) + " " + e(W.LISTS.stages[W.nomStage(n) - 1]), n.verified ? "Yes" : "No", n.coi ? '<span class="pill warn">declared</span>' : "—", '<a class="btn sm alt" href="#/admin/nominations/' + e(n.id) + '">Open</a>']; }), "No nominations.");
  });
  W.act.curate = function () { W.openModal("Add a curated nomination", '<form data-form="curate" class="stack-s">' + W.field("Nominee", "nominee", { req: true }) + W.field("Category", "category", { type: "select", options: W.cats().map(function (c) { return c.name; }) }) + W.field("Evidence", "evidence", { type: "textarea", req: true }) + '<button class="btn">Add</button></form>'); };
  W.forms.curate = function (d) { var ref = "NOM-" + ("000" + (W.S.nominations.length + 1)).slice(-4); W.S.nominations.push({ id: W.uid("n"), ref: ref, nominator: "Staff (curated)", nominatorEmail: "", verified: true, nominee: d.nominee, nomineeType: "business", nomineeEmail: "", category: d.category, evidence: d.evidence, status: "submitted", profileId: "", token: "t-" + W.uid("") }); W.log("nomination.curate", ref, d.nominee); W.ui.modal = null; W.save(); W.render(); };
  mini("/admin/nominations/:id", "A29", "awards", function (par) {
    var n = W.find("nominations", par.id); if (!n) return "<h1>Not found</h1>"; var st = W.nomStage(n);
    var next = { submitted: ["Start eligibility screening", "screen"], screening: ["Mark eligible and invite nominee", "eligible"], accepted: ["Move to independent assessment", "assess"], assessment: ["Shortlist (approver recorded)", "shortlist"], shortlisted: ["Final approval (finalist)", "final"], finalist: ["Mark winner", "winner"] }[n.status];
    return '<p><a href="#/admin/nominations">← Nominations</a></p>' + head(n.ref + ": " + n.nominee, e(n.category)) + W.steps(W.LISTS.stages, st - 1) +
      '<div class="grid side"><div class="stack"><div class="card stack-s"><h2>Status: ' + e(STAT[n.status] || n.status) + '</h2><p>Nominator: ' + e(n.nominator) + " (" + (n.verified ? "email verified" : "unverified") + ")</p><p>Evidence: " + e(n.evidence) + "</p>" + (n.coi ? "<p>Conflict declared: " + e(n.coi) + "</p>" : "") + '<div class="row">' + (next ? W.btn(next[0], "nomStep", n.id + "|" + next[1]) : "") + (n.status === "screening" || n.status === "submitted" ? W.btn("Mark ineligible", "nomStep", n.id + "|ineligible", "alt bad") : "") + (n.status === "invited" ? '<a class="btn alt" href="#/nominee/' + e(n.token) + '">Open the nominee link</a>' : "") + "</div>" +
      '<p class="small muted">Named approvers: screening ' + e(n.screenedBy || "—") + "; shortlist " + e(n.shortlistBy || "—") + "; final " + e(n.finalBy || "—") + ". Each step is a separate recorded action (REQ-202).</p></div></div>" +
      '<div class="stack"><div class="infobox"><strong>No commercial data here.</strong> There is no data path from payments, subscriptions or sponsorship into screening, shortlisting or judging (REQ-199).</div>' + W.notesBox(n.id) + "</div></div>";
  });
  W.act.nomStep = function (a) {
    var s = a.split("|"), n = W.find("nominations", s[0]), me = W.persona();
    if (s[1] === "screen") { n.status = "screening"; n.screenedBy = me; }
    else if (s[1] === "eligible") { n.status = "invited"; n.screenedBy = me; var p = W.S.profiles.filter(function (x) { return x.name.toLowerCase() === n.nominee.toLowerCase(); })[0]; if (p) n.profileId = p.id; W.mail(n.nomineeEmail || "Nominee (no email on file)", "You have been nominated", "Accept, decline or correct your details. Nothing is public.", "#/nominee/" + n.token); }
    else if (s[1] === "assess") n.status = "assessment";
    else if (s[1] === "shortlist") { if (["admin", "eic"].indexOf(W.S.role) < 0) { W.toast("Only the Editor-in-Chief or Admin can shortlist."); return; } n.status = "shortlisted"; n.shortlistBy = me; }
    else if (s[1] === "final") { if (W.S.role !== "admin") { W.toast("Final approval is by Admin (standing in for the Venture CEO)."); return; } n.status = "finalist"; n.finalBy = me; }
    else if (s[1] === "winner") { n.status = "winner"; }
    else if (s[1] === "ineligible") n.status = "ineligible";
    W.log("nomination." + s[1], n.ref, me); W.save(); W.render();
  };
  mini("/admin/awards", "A30", "awards", function () {
    var A = W.S.awards, sum = A.criteria.reduce(function (t, c) { return t + c.weight; }, 0);
    return head("Awards setup", "Everything here is configuration. Nothing is hard-coded (REQ-200). Sample values; the Venture CEO decides (N8).") + '<form class="card stack-s" data-form="awardcfg">' + W.field("Eligibility criteria", "eligibility", { type: "textarea", value: A.eligibility }) + W.field("Shortlist size per category", "shortlistSize", { type: "number", value: A.shortlistSize }) + W.field("Judges per category", "judgesPerCategory", { type: "number", value: A.judgesPerCategory }) + "<h2>Scoring criteria and weights</h2>" + A.criteria.map(function (c, i) { return '<div class="row"><label class="sr" for="cn' + i + '">Name</label><input id="cn' + i + '" name="cn' + i + '" value="' + e(c.name) + '" style="flex:1"><label class="sr" for="cw' + i + '">Weight</label><input id="cw' + i + '" type="number" name="cw' + i + '" value="' + c.weight + '" style="width:6rem"></div>'; }).join("") + '<p class="' + (sum === 100 ? "" : "err") + '">Weights total ' + sum + "%." + (sum === 100 ? "" : " Must total 100.") + '</p><button class="btn">Save</button></form><div class="card"><h2>Categories (' + W.cats().length + ")</h2>" + A.groups.map(function (g) { return "<h3>" + e(g.name) + "</h3><p>" + g.cats.map(e).join("; ") + "</p>"; }).join("") + "</div>";
  });
  W.forms.awardcfg = function (d) {
    var A = W.S.awards; A.eligibility = d.eligibility; A.shortlistSize = Number(d.shortlistSize); A.judgesPerCategory = Number(d.judgesPerCategory);
    A.criteria.forEach(function (c, i) { c.name = d["cn" + i]; c.weight = Number(d["cw" + i]) || 0; }); W.log("awards.config", "awards", "saved"); W.save(); W.render(); W.toast("Saved.");
  };
  mini("/admin/judges", "A31", "awards", function () {
    var asg = W.S.judgeAssign, j = "Sample Judge 1", mine = asg[j] || [], coi = W.S.judgeCoi[j] || [];
    return head("Judges", "Judges see only what is assigned to them (REQ-201). Declared conflicts remove the nomination from their view (REQ-036).") + W.table(["Judge", "Assigned", "Conflicts declared"], [[e(j), mine.map(function (id) { var n = W.find("nominations", id); return e(n.ref + " " + n.nominee); }).join("<br>") || "none", coi.map(function (id) { return e(W.find("nominations", id).ref); }).join(", ") || "none"]]) + '<form class="card stack-s" data-form="assign"><h2>Assign a nomination</h2>' + W.field("Nomination", "n", { type: "select", options: W.S.nominations.filter(function (n) { return ["assessment", "shortlisted", "finalist", "accepted"].indexOf(n.status) >= 0; }).map(function (n) { return [n.id, n.ref + " " + n.nominee]; }) }) + '<button class="btn">Assign to Sample Judge 1</button></form><form class="card stack-s" data-form="staffcoi"><h2>Staff conflict declaration</h2>' + W.field("Staff member", "who", { value: W.persona() }) + W.field("Nomination", "n", { type: "select", options: W.S.nominations.map(function (n) { return [n.id, n.ref + " " + n.nominee]; }) }) + '<button class="btn alt">Declare</button></form>';
  });
  W.forms.assign = function (d) { var a = W.S.judgeAssign["Sample Judge 1"] = W.S.judgeAssign["Sample Judge 1"] || []; if (a.indexOf(d.n) < 0) a.push(d.n); W.log("judge.assign", d.n, "Sample Judge 1"); W.save(); W.render(); };
  W.forms.staffcoi = function (d) { W.log("coi.staff", d.n, d.who + " declared a conflict"); W.save(); W.toast("Recorded. " + d.who + " is excluded from this nomination."); };
  mini("/admin/announcements", "A32", "awards", function () {
    var on = W.ok("announcements"), A = W.S.awards, wins = W.S.nominations.filter(function (n) { return n.status === "winner"; });
    return head("Announcements", "Winner notification and public announcement. Behind the announcements flag (REQ-038, 194).") + (on ? "" : '<div class="warnbox"><strong>Flag OFF.</strong> You can prepare, but nothing can be published. <a href="#/admin/flags">Feature flags</a></div>') + W.table(["Category", "Winner", "Notified"], wins.map(function (n) { return [e(n.category), e(n.nominee), n.notified ? "Yes" : W.btn("Prepare email", "winMail", n.id, "sm alt")]; }), "No winners selected yet. Mark one in a nomination.") + "<p>" + W.btn(A.published ? "Published" : "Publish announcement", "publishWin", "", "", (on && wins.length && !A.published) ? "" : " disabled") + "</p>" + (A.published ? '<p><a href="#/awards/winners">View public winners page</a></p>' : "");
  });
  W.act.winMail = function (id) { var n = W.find("nominations", id); n.notified = true; W.mail(n.nomineeEmail || "Winner", "Congratulations", "You are a winner in " + n.category + ". Private until announcement.", ""); W.save(); W.render(); };
  W.act.publishWin = function () { var A = W.S.awards; A.winners = W.S.nominations.filter(function (n) { return n.status === "winner"; }).map(function (n) { return { cat: n.category, name: n.nominee, ref: n.ref }; }); A.published = true; W.log("announcement.publish", "awards", A.winners.length + " winners"); W.save(); W.render(); };

  /* ---------- platform ---------- */
  onlyAdmin("/admin/flags", "A26", function () {
    var F = W.S.flags, desc = { nominations: "Public nominations form and queue", judging: "Judge scoring, shortlist and nominee pages", announcements: "Winners page and notifications", ai_drafting: "AI-assisted profile drafting" };
    return head("Feature flags", "OFF by default. Turning one ON needs the Admin role and the Venture CEO’s approval reference; it is audited (REQ-194).") + W.table(["Flag", "What it controls", "State", "Approval ref", ""], Object.keys(F).map(function (k) { return [e(k), e(desc[k]), '<span class="pill ' + (F[k].on ? "flag-on" : "flag-off") + '">' + (F[k].on ? "ON" : "OFF") + "</span>", e(F[k].ref || "—"), F[k].on ? W.btn("Turn OFF", "flagOff", k, "sm alt") : W.btn("Turn ON…", "flagOnDlg", k, "sm")]; }));
  });
  W.act.flagOnDlg = function (k) { W.openModal("Turn on: " + k, '<form data-form="flagon" class="stack-s"><input type="hidden" name="k" value="' + e(k) + '"><p>Public pages change immediately. Only turn this on with the Venture CEO’s explicit approval.</p>' + W.field("Approval reference (email, minute or ticket)", "ref", { req: true }) + '<button class="btn">Turn ON</button></form>'); };
  W.forms.flagon = function (d) { W.S.flags[d.k] = { on: true, ref: d.ref }; W.log("flag.on", d.k, "approval: " + d.ref); W.ui.modal = null; W.save(); W.render(); W.toast(d.k + " is ON. Check the Nominate, Awards and Judge pages."); };
  W.act.flagOff = function (k) { W.S.flags[k] = { on: false, ref: "" }; W.log("flag.off", k, ""); W.save(); W.render(); W.toast(k + " is OFF."); };
  mini("/admin/killswitch", "A27", "kill", function () {
    var K = W.S.kill, n = W.S.profiles.filter(function (p) { return p.type === "individual" && p.trust === "unclaimed" && p.status === "published"; }).length;
    return head("Kill switch", "Immediately hides all unclaimed individual profiles on public, indexed pages. Reversible, restricted to named roles, audited (REQ-193, 209).") + '<div class="' + (K.on ? "errbox" : "card") + ' stack-s"><h2>Status: ' + (K.on ? "ON" : "off") + "</h2><p>" + n + " unclaimed individual profile(s) " + (K.on ? "are hidden." : "would be hidden.") + "</p>" + (K.on ? "<p>By " + e(K.by) + ": " + e(K.reason) + "</p>" + W.btn("Restore (reversible)", "killOff", "", "") : W.btn("Activate kill switch…", "killDlg", "", "bad")) + '</div><p class="small muted">While on: those URLs return 410 + noindex, leave the sitemap and search, and the CDN is purged. After use, request search-engine removals for indexed URLs (N21).</p>';
  });
  W.act.killDlg = function () { W.openModal("Activate the kill switch", '<form data-form="killon" class="stack-s"><p class="errbox">This hides every unclaimed individual profile for the public.</p>' + W.field("Reason (audited)", "reason", { req: true }) + W.field("Type KILL to confirm", "c", { req: true, attrs: 'autocomplete="off"' }) + '<button class="btn bad">Activate</button></form>'); };
  W.forms.killon = function (d) { if (d.c !== "KILL") { W.toast("Type KILL exactly to confirm."); return; } W.S.kill = { on: true, by: W.persona(), reason: d.reason }; W.S.profiles.filter(function (p) { return W.isKilled(p); }).forEach(function (p) { W.purge("/profile/" + p.slug, "kill switch"); }); W.log("killswitch.on", "global", d.reason); W.ui.modal = null; W.save(); W.render(); W.toast("Kill switch ON. Check People, Search and the Sitemap."); };
  W.act.killOff = function () { W.S.kill = { on: false, by: "", reason: "" }; W.log("killswitch.off", "global", "restored"); W.S.profiles.forEach(function (p) { if (p.type === "individual" && p.trust === "unclaimed" && p.status === "published") W.purge("/profile/" + p.slug, "kill switch restored"); }); W.save(); W.render(); W.toast("Restored. Profiles return 200."); };
  onlyAdmin("/admin/audit", "A33", function () {
    var A = W.S.audit, bad = -1, prev = "00000000";
    return head("Audit log", "Append-only. Each row stores the hash of the one before, so tampering is detectable (REQ-051, 188). Exported nightly off-host.") + W.table(["#", "When", "Actor", "Action", "Target", "Detail", "Hash"], A.slice().reverse().slice(0, 60).map(function (a) { return [a.n, e(a.at), e(a.actor), e(a.action), e(a.target), e(a.detail), '<span class="mono">' + e(a.hash) + "</span>"]; }), "No actions yet. Do something as staff.") + "<p>" + W.btn("Verify chain", "verifyChain", "", "alt") + "</p>";
  });
  W.act.verifyChain = function () { var prev = "00000000", ok = true; W.S.audit.forEach(function (a) { if (a.prev !== prev) ok = false; prev = a.hash; }); W.toast(ok ? "Chain intact: " + W.S.audit.length + " rows verified." : "CHAIN BROKEN: alert raised."); };
  onlyAdmin("/admin/users", "A34", function () {
    var matrix = [["Review queue", "reviewer, eic"], ["Profiles and labels", "reviewer, editor, eic"], ["Register and customers", "finance, sales (read)"], ["Leads", "sales"], ["Articles", "editor, eic"], ["Awards setup and nominations", "eic"], ["Kill switch", "eic, admin"], ["Flags, audit, users", "admin"], ["Judge area", "judge only (no commercial data)"]];
    return head("Users and roles", "Roles: Admin, Editor, Reviewer, Sales/Support, Finance, Judge, plus Editor-in-Chief for disputes (REQ-083, 174). MFA is required (REQ-047).") + W.table(["User", "Role", "MFA", "Last seen", ""], W.S.users.map(function (u) { return [e(u.name), e(u.role), u.mfa ? '<span class="pill ok">on</span>' : '<span class="pill bad">OFF</span>', e(u.last), u.mfa ? "" : W.btn("Require MFA", "mfa", u.name, "sm")]; })) + "<h2>Who can open what</h2>" + W.table(["Area", "Roles (Admin can open all)"], matrix);
  });
  W.act.mfa = function (n) { W.S.users.filter(function (u) { return u.name === n; })[0].mfa = true; W.log("user.mfa", n, "required"); W.save(); W.render(); };
  mini("/admin/compliance", "A37", "dsr", function () {
    return head("Compliance", "Processor register, retention and breach log.") + "<h2>Processors (REQ-065)</h2>" + W.table(["Processor", "Role", "Location", "DPA"], W.S.processors.map(function (p) { return [e(p.name), e(p.role), e(p.location), e(p.dpa)]; })) + "<h2>Lawful basis by category of person (REQ-057; Legal's written position requested by 13 Nov)</h2>" + W.table(["Category", "Basis (default)", "Status"], [["Public figures published without consent", "Legitimate interests, right to object", "Awaiting Legal"], ["Profile owners and customers", "Contract / consent", "Awaiting Legal"], ["Nominators and nominees", "Consent / legitimate interests", "Awaiting Legal"], ["Email sign-ups", "Consent (stored with policy version)", "Built"]]) + '<div class="card stack-s"><h2>DPIA and data-protection contact (REQ-072)</h2><p>DPIA: <span class="pill warn">in progress</span> · Contact: <span class="mono">dpo@wiwi.africa (sample)</span></p><p class="small muted">If Legal objects, the kill switch is the response (REQ-193).</p></div>' + "<h2>Retention schedule (REQ-063; defaults pending Legal)</h2>" + W.table(["Data", "Keep for (D-023 defaults; rows marked sample are not decided)", "Then"], [["Subscriber data", "6 years after last payment", "Delete or anonymise"], ["Nominations", "2 years", "Delete"], ["Suppression record", "Indefinitely (name only)", "Keep"], ["Claim documents (sample)", "Until decision, then delete", "Delete"], ["Enquiries (sample)", "Not decided", "To decide"]]) + '<h2>Breach log (REQ-066)</h2><form class="card stack-s" data-form="breach">' + W.field("Describe what happened", "d", { type: "textarea", req: true }) + '<button class="btn alt">Log breach and start the response checklist</button></form>' + (W.S.breaches ? W.table(["Logged", "Description"], W.S.breaches.map(function (b) { return [e(b.at), e(b.d)]; })) : "");
  });
  W.forms.breach = function (d) { (W.S.breaches = W.S.breaches || []).push({ at: W.TODAY, d: d.d }); W.log("breach.log", "incident", d.d); W.save(); W.render(); W.toast("Logged. Checklist: contain, assess, notify the regulator and affected people within the legal deadline."); };
  mini("/admin/purges", "A48", null, function () {
    return head("Purge and unpublish log", "Each unpublish, removal, decline or kill-switch use queues a CDN purge (REQ-209). The origin already answers 410 + noindex.") + W.table(["URL", "Why", "Date", "Status"], W.S.purges.map(function (p) { return ['<span class="mono">' + e(p.url) + "</span>", e(p.why), e(p.at), e(p.status)]; }), "Nothing purged yet. Unpublish a profile to see an entry.");
  });
  mini("/admin/exports", "A41", null, function () {
    return head("Exports", "CSV and open formats for CaromHQ and data portability (REQ-077, 087, 131).") + '<div class="grid c3">' + [["register", "Register with counting-rule status"], ["leads", "Leads"], ["customers", "Customers"], ["nominations", "Nominations"], ["profiles", "All profiles (open format)"], ["enquiries", "Enquiries"]].map(function (x) { return '<div class="card stack-s"><h2>' + e(x[1]) + "</h2>" + W.btn("Download CSV", "dl", x[0], "alt") + "</div>"; }).join("") + '</div><p class="small muted">Bulk export of personal data is limited to named roles and logged (REQ-069).</p>';
  });
  W.act.dl = function (k) {
    var S = W.S, map = {
      register: [["id", "type", "customer", "amount_kobo", "currency", "status", "counts_toward_target", "complimentary"]].concat(S.arrangements.map(function (a) { return [a.id, a.type, (W.find("customers", a.customerId) || {}).name, a.amount, a.currency, W.arrStatus(a), W.counts(a) ? "yes" : "no", a.complimentary ? "yes" : "no"]; })),
      leads: [["id", "name", "org", "interest", "status"]].concat(S.leads.map(function (l) { return [l.ext, l.name, l.org, l.interest, l.status]; })),
      customers: [["id", "name", "segment"]].concat(S.customers.map(function (c) { return [c.ext, c.name, c.segment]; })),
      nominations: [["ref", "nominee", "category", "status"]].concat(S.nominations.map(function (n) { return [n.ref, n.nominee, n.category, n.status]; })),
      profiles: [["id", "name", "type", "status", "trust"]].concat(S.profiles.map(function (p) { return [p.id, p.name, p.type, p.status, p.trust]; })),
      enquiries: [["id", "name", "message"]].concat(S.enquiries.map(function (x) { return [x.id, x.name, x.message]; })),
      prospects: [["id", "name", "source", "status"]].concat((S.prospects || []).map(function (x) { return [x.id, x.name, x.source, x.status]; }))
    };
    W.log("export." + k, k, "csv"); W.save(); W.download(k + ".csv", W.csv(map[k] || [["empty"]]));
  };
  mini("/admin/emails", "A40", null, function () {
    return head("Email log", "Every notification the system sends (REQ-094). Provider bounces will be recorded here.") + W.table(["Date", "To", "Subject", "Link"], W.S.emails.map(function (m) { return [e(m.at), e(m.to), e(m.subject), m.link ? '<a href="' + e(m.link) + '">open</a>' : ""]; }), "No emails sent yet.");
  });
  onlyAdmin("/admin/settings", "A47", function () {
    var t = W.S.thresholds;
    return head("Settings") + '<form class="card stack-s" data-form="settings"><h2>Sprint thresholds</h2>' + W.field("Paid subscriptions needed", "subs", { type: "number", value: t.subs }) + W.field("Confirmed revenue needed (NGN)", "rev", { type: "number", value: t.revenue / 100 }) + "<h2>Default revenue stream per arrangement type</h2>" + Object.keys(W.LISTS.arrTypes).map(function (k) { return W.field(W.LISTS.arrTypes[k], "st_" + k, { type: "select", options: Object.keys(W.LISTS.streams).map(function (s) { return [s, W.LISTS.streams[s]]; }), value: W.LISTS.defaultStream[k] }); }).join("") + '<button class="btn">Save</button></form>';
  });
  W.forms.settings = function (d) { W.S.thresholds = { subs: Number(d.subs), revenue: Math.round(Number(d.rev) * 100) }; Object.keys(W.LISTS.arrTypes).forEach(function (k) { W.LISTS.defaultStream[k] = d["st_" + k]; }); W.log("settings.save", "thresholds", ""); W.save(); W.toast("Saved."); W.render(); };

  /* ---------- judge area ---------- */
  var J = { area: "judge", roles: ["judge"] };
  W.route("/judge/signin", "J01", function () { return '<div class="wrap section"><h1>Judge sign in</h1><form class="card stack-s" data-form="judgein" style="max-width:28rem">' + W.field("Email", "email", { type: "email", req: true }) + W.field("Password", "password", { type: "password", req: true }) + W.field("MFA code (wireframe: 000000)", "code", { req: true }) + '<button class="btn">Sign in</button></form></div>'; });
  W.forms.judgein = function (d) { if (d.code !== "000000") { W.toast("Wrong code."); return; } W.S.role = "judge"; W.save(); W.go("#/judge"); };
  function myNoms() { var j = "Sample Judge 1", coi = W.S.judgeCoi[j] || []; return (W.S.judgeAssign[j] || []).filter(function (id) { return coi.indexOf(id) < 0; }).map(function (id) { return W.find("nominations", id); }).filter(Boolean); }
  W.route("/judge", "J02", function () {
    if (!W.ok("judging")) return "<h1>Judging is not open</h1>" + W.empty("Judging is OFF", "The judging flag is off. You will be notified when scoring opens. Staff turn it on with the CEO’s approval.");
    var rows = myNoms();
    return "<h1>Your assignments</h1><p class=\"muted\">Only nominations assigned to you are listed. Payment, subscription and sponsorship information is never shown here (REQ-037, 201).</p>" + W.table(["Ref", "Nominee", "Category", "Your score", ""], rows.map(function (n) { var s = W.S.scores[n.id]; return [e(n.ref), e(n.nominee), e(n.category), s ? (s.locked ? '<span class="pill ok">submitted ' + s.total + "</span>" : '<span class="pill warn">draft</span>') : '<span class="pill gray">not started</span>', '<a class="btn sm" href="#/judge/score/' + e(n.id) + '">Open</a> ' + W.btn("Declare conflict", "coiDlg", n.id, "sm alt")]; }), "Nothing assigned to you (or you declared conflicts).");
  }, J);
  W.act.coiDlg = function (id) { W.openModal("Declare a conflict of interest", '<form data-form="coi" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '"><p>You will no longer see or score this nomination. Staff are told.</p>' + W.field("Describe the conflict", "why", { type: "textarea", req: true }) + '<button class="btn bad">Declare and remove from my list</button></form>'); };
  W.forms.coi = function (d) { var j = "Sample Judge 1"; (W.S.judgeCoi[j] = W.S.judgeCoi[j] || []).push(d.id); W.log("coi.judge", d.id, d.why); W.ui.modal = null; W.save(); W.go("#/judge"); W.toast("Conflict recorded. The nomination was removed from your list."); };
  W.route("/judge/score/:id", "J04", function (par) {
    var n = W.find("nominations", par.id), j = "Sample Judge 1";
    if (!W.ok("judging")) return W.empty("Judging is OFF", "Scoring is closed.");
    if (!n || (W.S.judgeAssign[j] || []).indexOf(n.id) < 0 || (W.S.judgeCoi[j] || []).indexOf(n.id) >= 0) return '<div class="errbox"><h1>Not available</h1><p>This nomination is not assigned to you, or you declared a conflict.</p></div>';
    var s = W.S.scores[n.id] || { v: {}, locked: false }, A = W.S.awards;
    return '<p><a href="#/judge">← Assignments</a></p><h1>' + e(n.nominee) + '</h1><p class="muted">' + e(n.category) + "</p><div class=\"card\"><h2>Evidence</h2><p>" + e(n.evidence) + '</p><p class="small muted">Nominator identity is hidden. No payment, subscription or sponsorship data is available to judges.</p></div><form class="card stack-s" data-form="score" data-live="score"><input type="hidden" name="id" value="' + e(n.id) + '"><h2>Score (0 to 10 each)</h2>' + A.criteria.map(function (c) { return W.field(c.name + " (weight " + c.weight + "%)", "k_" + c.id, { type: "number", value: s.v[c.id] == null ? "" : s.v[c.id], attrs: 'min="0" max="10" step="1"' + (s.locked ? " disabled" : "") }); }).join("") + W.field("Comment", "comment", { type: "textarea", value: s.comment || "", attrs: s.locked ? "disabled" : "" }) + '<p id="total" role="status"><strong>Weighted total: ' + calc(s.v) + ' / 10</strong></p>' + (s.locked ? '<p class="okbox">Submitted and locked.</p>' : '<div class="row"><button class="btn alt" name="mode" value="draft">Save draft</button><button class="btn" name="mode" value="submit">Submit and lock</button></div>') + "</form>";
  }, J);
  function calc(v) { var A = W.S.awards, t = 0; A.criteria.forEach(function (c) { t += (Number(v[c.id]) || 0) * c.weight / 100; }); return t.toFixed(1); }
  W.live.score = function (f) { var d = W.formData(f), v = {}; W.S.awards.criteria.forEach(function (c) { v[c.id] = d["k_" + c.id]; }); var t = document.getElementById("total"); if (t) t.innerHTML = "<strong>Weighted total: " + calc(v) + " / 10</strong>"; };
  W.forms.score = function (d, f, ev) {
    var v = {}, bad = false; W.S.awards.criteria.forEach(function (c) { var x = d["k_" + c.id]; if (x === "" || x == null) bad = true; else v[c.id] = Number(x); });
    var mode = ev && ev.submitter ? ev.submitter.value : "draft";
    if (mode === "submit" && bad) { W.toast("Enter a score for every criterion before submitting."); return; }
    W.S.scores[d.id] = { v: v, comment: d.comment, locked: mode === "submit", total: calc(v) }; W.log("judge.score", d.id, mode); W.save(); W.go("#/judge"); W.toast(mode === "submit" ? "Score submitted and locked." : "Draft saved.");
  };
  W.route("/judge/guidelines", "J06", function () { return "<h1>Judging guidelines</h1><p class=\"preview-note\">Placeholder. Governance text comes from the Venture CEO (N8).</p><ul><li>Score against the published criteria only.</li><li>Declare any conflict before you score.</li><li>You will never see payment or sponsorship information. If you think you have, tell the Editor-in-Chief.</li><li>Scores lock when you submit.</li></ul>"; }, J);
})();
