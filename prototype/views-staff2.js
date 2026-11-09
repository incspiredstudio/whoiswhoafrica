/* Staff console part 2: import, taxonomies, commercial, content, awards, platform. Flow preview only. */
(function () {
  var W = window.W, e = W.esc, ACL = W.ACL, ALL = W.ALLSTAFF;
  function head(t, sub) { return "<h1>" + e(t) + "</h1>" + (sub ? '<p class="muted">' + sub + "</p>" : ""); }
  function mini(path, inv, key, fn) { W.staffRoute(path, inv, key, fn); }
  function capRoute(cap, path, inv, fn) { W.route(path, inv, fn, { area: "staff", cap: cap, roles: W.rolesWithCap(cap) }); }

  /* ---------- import, prospects, suppression, taxonomies ---------- */
  /* There is no Source registry here (D-038): approved sources are managed in the acquisition pipeline by the named approver. */

  var SAMPLE_CSV = "name,type,industry,city,title,org,website,country\nSample Import Co. One,business,Technology,Lagos,,,https://example.org/one,NG\nSample Import Co. Two,business,Energy,Abuja,,,https://example.org/two,NG\nSample Person X,individual,Technology,Lagos,Director,Sample Import Co. One,,NG\nSavanna Loop Agritech,business,Agriculture & Agritech,Lagos,,,https://example.org/agritech,NG\n,business,Technology,Lagos,,,,NG\nSample Person Y,individual,Technology,Lagos,Call me on 0800-000-0000,Sample Import Co. Two,,NG\nSample Mining Co.,business,Mining,Lagos,,,,NG\nSample Accra Co.,business,Technology,Accra,,,,GH";
  W.ui.imp = { csv: SAMPLE_CSV, src: "export-sample.csv", rows: null };
  var CC = { NG: "Nigeria", GH: "Ghana", KE: "Kenya", ZA: "South Africa", RW: "Rwanda", EG: "Egypt", PAN: "Pan-African (bodies)" };
  function dry(csv) {
    var lines = csv.trim().split(/\n/).slice(1), seen = {};
    return lines.map(function (l) {
      var c = l.split(","), r = { name: (c[0] || "").trim(), type: c[1] || "business", industry: c[2] || "", city: c[3] || "", title: c[4] || "", org: c[5] || "", website: c[6] || "", cc: (c[7] || "").trim(), status: "ok", why: "" };
      if (!r.name) { r.status = "error"; r.why = "Name is required"; }
      else if (!CC[r.cc]) { r.status = "error"; r.why = "Country must be an ISO 3166-1 alpha-2 code or PAN (import contract)"; }
      else if (r.industry && W.LISTS.industries.indexOf(r.industry) < 0) { r.status = "blocked"; r.why = "Industry \u201c" + r.industry + "\u201d is not a term in Taxonomies; an Editor adds it first (D-039)"; }
      else if (r.city && W.LISTS.cities.indexOf(r.city) < 0) { r.status = "blocked"; r.why = "City \u201c" + r.city + "\u201d is not a term in Taxonomies; an Editor adds it first (D-039)"; }
      else if (W.S.suppression.some(function (s) { return s.name.toLowerCase() === r.name.toLowerCase(); })) { r.status = "skipped"; r.why = "On the permanent suppression list (REQ-207)"; }
      else if (W.S.profiles.some(function (p) { return p.name.toLowerCase() === r.name.toLowerCase(); })) { r.status = "duplicate"; r.why = "Matches an existing profile; goes to duplicate review"; }
      else if (W.scan(r.title + " " + r.org).length) { r.status = "blocked"; r.why = "Free text has a phone/email/age pattern (REQ-141)"; }
      else if (seen[r.name]) { r.status = "duplicate"; r.why = "Repeated in this file"; }
      seen[r.name] = 1; return r;
    });
  }
  mini("/admin/import", "A20", "import", function () {
    var I = W.ui.imp;
    return head("Import from the acquisition pipeline", "Dry-run first. The file is validated against the import contract (docs/contracts/import-format.md): required fields, country code, terms that exist in Taxonomies, suppression list, duplicates, free-text patterns. Imports are tagged with the file and the importer and go to the review queue (REQ-092, 148, 237).") +
      '<div class="infobox">Approved sources are managed in the acquisition pipeline by the named approver, not in this admin (D-038). The real bundle is a manifest plus a records file with provenance for every field; this wireframe uses a small CSV to show the checks.</div>' +
      '<form class="card stack-s" data-form="impdry">' + W.field("File name", "src", { value: I.src }) + W.field("CSV (sample prefilled)", "csv", { type: "textarea", value: I.csv, attrs: 'rows="9" style="font-family:monospace"' }) + '<button class="btn">Dry run</button></form>' +
      (I.rows ? "<h2>Dry-run result</h2>" + W.table(["Row", "Name", "Result", "Why"], I.rows.map(function (r, i) { return [i + 1, e(r.name || "(blank)"), '<span class="pill ' + (r.status === "ok" ? "ok" : r.status === "duplicate" ? "warn" : "bad") + '">' + r.status + "</span>", e(r.why)]; })) + "<p>" + I.rows.filter(function (r) { return r.status === "ok"; }).length + " row(s) can be imported.</p>" + W.btn("Import valid rows to review queue", "impGo", "") : "") +
      (W.S.imports.length ? "<h2>Past imports</h2>" + W.table(["When", "File", "By", "Rows"], W.S.imports.map(function (x) { return [e(x.at), e(x.src), e(x.by), x.n]; })) : "");
  });
  W.forms.impdry = function (d) { W.ui.imp = { csv: d.csv, src: d.src, rows: dry(d.csv) }; W.render(); };
  W.act.impGo = function () {
    var I = W.ui.imp, rows = I.rows.filter(function (r) { return r.status === "ok"; });
    rows.forEach(function (r) { W.S.profiles.push({ id: W.uid("im"), slug: r.name.toLowerCase().replace(/[^a-z0-9]+/g, "-") + "-" + W.uid(""), type: r.type, name: r.name, title: r.title, org: r.org, industry: r.industry, city: r.city, country: CC[r.cc], status: "pending", trust: "", sponsored: null, source: "acquired", origin: "import", aiDrafted: false, views: 0, enquiries: true, bio: r.name + " (imported; text to be written by a reviewer).", achievements: "", website: r.website, public: { website: true }, facts: [{ label: "Imported record", value: r.name, src: I.src, url: "https://example.org/imports/" + I.src, conf: "medium", method: "import", retrieved: W.TODAY }], leadership: [], products: "", history: [], scan: [], importedBy: W.persona(), drafter: W.persona(), submittedAt: W.TODAY }); });
    W.S.imports.unshift({ at: W.TODAY, src: I.src, by: W.persona(), n: rows.length }); W.log("import.run", I.src, rows.length + " rows"); W.ui.imp.rows = null; W.save(); W.render(); W.toast(rows.length + " profiles added to the review queue. You cannot approve your own import.");
  };
  if (!Array.isArray(W.prospects)) W.prospects = null;
  mini("/admin/prospects", "A42", "leads", function () {
    var P = W.S.prospects = W.S.prospects || [{ id: "pr1", name: "Sample Prospect Co. A", source: "Manual research", status: "to contact" }, { id: "pr2", name: "Sample Prospect Co. B", source: "Licensed list (sample)", status: "contacted" }];
    return head("Prospects (sales research)", "A separate store. Never public. A prospect can become a profile only through the acquisition pipeline and its import (REQ-093, 176).") + W.table(["Prospect", "Source", "Status", ""], P.map(function (p) { return [e(p.name), e(p.source), e(p.status), W.btn("Send to review path", "prospectGo", p.id, "sm alt")]; })) + "<p>" + W.exportBtn("prospects") + "</p>";
  });
  W.act.prospectGo = function (id) { W.toast("Prospects never go straight to the site. They can enter only through the acquisition pipeline and the import (sample guard)."); };
  mini("/admin/suppression", "A22", "supprView", function () {
    var edit = W.can("supprEdit");
    return head("Suppression list", "Permanent. No import or sync can bring these back. There is no remove button on purpose (REQ-147, 207)." + (edit ? "" : " Read-only for your role.")) + W.table(["Name", "Reason type", "Date", "Detail"], W.S.suppression.map(function (s) { return [e(s.name), e(s.kind), e(s.at), e(s.reason)]; })) + (edit ? '<form class="card stack-s" data-form="supp"><h2>Add manually</h2>' + W.field("Name", "name", { req: true }) + W.field("Reason", "reason", { req: true }) + '<button class="btn">Add</button></form>' : '<p class="small muted">A valid removal request adds a name here automatically (Support inbox). Adding by hand is for the Administrator; a valid removal handled by an Editor adds the name automatically.</p>');
  });
  W.forms.supp = function (d) { W.S.suppression.unshift({ id: W.uid("sp"), name: d.name, kind: "Manual", at: W.TODAY, reason: d.reason }); W.log("suppression.add", d.name, d.reason); W.save(); W.render(); };
  var TAXFIELD = { industries: "industry", cities: "city", countries: "country", orgTypes: "orgType", professions: "profession" };
  function taxCount(k, name) { return W.S.profiles.filter(function (p) { return p[TAXFIELD[k]] === name; }).length; }
  mini("/admin/taxonomy/:tax", "A57", "taxonomy", function (par) {
    var k = par.tax, def = W.TAXES[k]; if (!def) return "<h1>Not found</h1>";
    var T = W.S.tax[k], hier = def[2];
    var rows = [];
    if (hier) { W.LISTS.countries.forEach(function (c) { T.filter(function (t) { return t.parent === c; }).forEach(function (t) { rows.push([t, false]); }); }); T.filter(function (t) { return W.LISTS.countries.indexOf(t.parent) < 0; }).forEach(function (t) { rows.push([t, false]); }); } else T.forEach(function (t) { rows.push([t, false]); });
    return head(def[0], "A custom taxonomy in WordPress, managed on its native term screen (D-039, REQ-009). Editors and Administrators add and edit terms. Reviewers can only assign existing terms to a profile. Preview of the native screen.") +
      '<div class="grid side"><form class="card stack-s" data-form="taxadd"><h2>Add new ' + e(def[1].toLowerCase()) + '</h2><input type="hidden" name="k" value="' + e(k) + '">' + W.field("Name", "name", { req: true, hint: "The name as it shows on the site." }) + W.field("Slug", "slug", { hint: "Optional. The URL-friendly version of the name." }) + (hier ? W.field("Parent country", "parent", { type: "select", options: W.LISTS.countries, blank: "None", value: "Nigeria" }) : "") + '<button class="btn">Add new ' + e(def[1].toLowerCase()) + '</button></form>' +
      '<div class="stack"><div class="tablewrap"><table class="t"><thead><tr><th scope="col">Name</th><th scope="col">Slug</th><th scope="col">' + (hier ? "Parent" : "Description") + '</th><th scope="col">Count</th><th scope="col"></th></tr></thead><tbody>' + rows.map(function (r) { var t = r[0]; return "<tr><td>" + (t.parent && hier ? "\u2014 " : "") + "<strong>" + e(t.name) + "</strong></td><td class=\"mono\">" + e(t.slug) + "</td><td>" + (hier ? e(t.parent || "\u2014") : "\u2014") + "</td><td>" + taxCount(k, t.name) + "</td><td>" + W.btn("Edit", "taxEdit", k + "|" + t.slug, "sm alt") + " " + W.btn("Delete", "taxDel", k + "|" + t.slug, "sm alt bad") + "</td></tr>"; }).join("") + '</tbody></table></div><p class="small muted">Count = profiles using the term. A term in use cannot be deleted until its profiles are reassigned. Terms are used by profile forms, filters and the import check.</p></div></div>';
  });
  function taxTerm(a) { var s = a.split("|"); return { k: s[0], t: W.S.tax[s[0]].filter(function (x) { return x.slug === s[1]; })[0] }; }
  W.forms.taxadd = function (d) {
    var T = W.S.tax[d.k], name = d.name.trim();
    if (T.some(function (t) { return t.name.toLowerCase() === name.toLowerCase(); })) { W.toast("That term already exists."); return; }
    T.push({ name: name, slug: W.slugify(d.slug || name), parent: d.parent || "" }); W.syncLists(); W.log("taxonomy.add", d.k, name); W.save(); W.render(); W.toast("Term added: " + name + ". Profile forms, filters and the import check now accept it.");
  };
  W.act.taxEdit = function (a) { var x = taxTerm(a); W.openModal("Edit term", '<form data-form="taxsave" class="stack-s"><input type="hidden" name="k" value="' + e(x.k) + '"><input type="hidden" name="old" value="' + e(x.t.slug) + '">' + W.field("Name", "name", { req: true, value: x.t.name }) + W.field("Slug", "slug", { value: x.t.slug }) + (W.TAXES[x.k][2] ? W.field("Parent country", "parent", { type: "select", options: W.LISTS.countries, blank: "None", value: x.t.parent }) : "") + '<button class="btn">Update</button></form>'); };
  W.forms.taxsave = function (d) {
    var x = taxTerm(d.k + "|" + d.old), before = x.t.name, f = TAXFIELD[d.k];
    W.S.profiles.forEach(function (p) { if (p[f] === before) p[f] = d.name.trim(); });
    x.t.name = d.name.trim(); x.t.slug = W.slugify(d.slug || d.name); if (d.parent !== undefined) x.t.parent = d.parent;
    W.syncLists(); W.log("taxonomy.edit", d.k, before + " to " + x.t.name); W.ui.modal = null; W.save(); W.render(); W.toast("Term updated.");
  };
  W.act.taxDel = function (a) {
    var x = taxTerm(a); if (taxCount(x.k, x.t.name)) { W.toast("In use by " + taxCount(x.k, x.t.name) + " profile(s). Reassign them first."); return; }
    W.S.tax[x.k] = W.S.tax[x.k].filter(function (t) { return t !== x.t; }); W.syncLists(); W.log("taxonomy.delete", x.k, x.t.name); W.save(); W.render(); W.toast("Term deleted.");
  };

  /* ---------- claims (D-057) ---------- */
  mini("/admin/claims", "A61", "review_claims", function () {
    var rows = (W.S.claims || []).map(function (c) {
      var p = W.profile(c.profileId), act = c.status === "new" ? W.btn("Approve", "claimOk", c.id, "sm") + " " + W.btn("Reject", "claimNo", c.id, "sm alt") : "";
      return [e(c.id), p ? '<a href="#/admin/profiles/' + e(p.id) + '">' + e(p.name) + "</a>" : "(removed)", e(c.name) + '<br><span class="muted small">' + e(c.relationship) + "</span>", e(c.email), '<span class="small">' + e(c.evidence) + "</span>", W.stPill(c.status === "new" ? "pending" : c.status === "approved" ? "approved" : "rejected") + (c.reason ? '<br><span class="muted small">' + e(c.reason) + "</span>" : ""), e(c.at), act];
    });
    return head("Claims", "Requests from a person or organisation to claim a profile. Approving sets Claimed and Verified (shown to staff as a checkmark) and lets you assign a user to keep the profile up to date (REQ-251 to 253).") + (rows.length ? W.table(["ID", "Profile", "Claimant", "Email", "Evidence", "Status", "Received", ""], rows) : W.empty("No claims", "Claims from the public form appear here."));
  });
  W.act.claimOk = function (id) {
    var c = (W.S.claims || []).filter(function (x) { return x.id === id; })[0], p = c && W.profile(c.profileId); if (!c || !p || !W.can("review_claims")) return;
    if (W.isPending(p)) { W.toast("Review the profile first: a pending profile cannot be Verified (REQ-226)."); return; }
    c.status = "approved"; c.by = W.persona(); p.claimed = true; p.claimedAt = W.TODAY; p.trust = "verified";
    p.history.push({ at: W.TODAY, by: W.persona(), what: "Claim approved; set Claimed and Verified" });
    W.log("claim.approve", p.id, id + " approved; Verified set"); W.mail(c.email, "Your claim is approved", "You can now ask us to update the profile for " + p.name + ".", ""); W.save(); W.render(); W.toast("Claim approved. Assign a user on the profile if needed.");
  };
  W.act.claimNo = function (id) {
    var c = (W.S.claims || []).filter(function (x) { return x.id === id; })[0]; if (!c || !W.can("review_claims")) return;
    c.status = "rejected"; c.by = W.persona(); c.reason = "Evidence did not match";
    W.log("claim.reject", c.profileId, id + " rejected"); W.mail(c.email, "About your claim", "We could not confirm the claim from the evidence sent. You can send it again with more.", ""); W.save(); W.render(); W.toast("Claim rejected.");
  };

  /* ---------- leads, customers ---------- */
  mini("/admin/leads", "A13", "leads", function () {
    return '<div class="row between"><h1>Leads</h1>' + W.exportBtn("leads") + "</div>" + W.table(["ID", "Name / org", "Interest", "Contact", "Status", "Owner", "Notes", ""], W.S.leads.map(function (l) { return [e(l.ext), e(l.name) + "<br><span class=\"muted small\">" + e(l.org) + "</span>", e(l.interest), e(l.contact), '<label class="sr" for="ls' + l.id + '">Status</label><select id="ls' + l.id + '" data-change="leadStatus" data-lead="' + e(l.id) + '">' + ["new", "contacted", "qualified", "converted", "lost"].map(function (s) { return "<option" + (s === l.status ? " selected" : "") + ">" + s + "</option>"; }).join("") + "</select>", e(l.owner || "unassigned"), e(l.notes), l.status === "converted" ? "Converted" : W.btn("Convert", "leadConv", l.id, "sm")]; }), "No leads yet.") + '<p class="small muted">Leads come from the Get Featured form. Notes also live under Internal notes (never public).</p>';
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
  W.act.arrCancel = function (id) { var a = W.find("arrangements", id); a.cancelled = true; W.log("arrangement.cancel", id, "recorded; refund handled offline and recorded by an Administrator"); W.save(); W.render(); };
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
    return '<div class="row between"><h1>Nominations</h1><div class="row">' + W.btn("Add curated nomination", "curate", "", "alt") + W.exportBtn("nominations") + "</div></div>" + (W.nomOpen() ? "" : '<div class="infobox">The public form is closed (nominations flag OFF). This queue still works for curated nominations.</div>') + '<form class="filters" data-filter="#/admin/nominations">' + W.field("Status", "s", { type: "select", options: Object.keys(STAT).map(function (k) { return [k, STAT[k]]; }), blank: "Any", value: q.s }) + "</form>" +
      W.table(["Ref", "Nominee", "Category", "Status", "Stage", "Nominator verified", "Conflict", ""], rows.map(function (n) { return [e(n.ref), e(n.nominee), e(n.category), W.stPill(STAT[n.status] || n.status), W.nomStage(n) + " " + e(W.LISTS.stages[W.nomStage(n) - 1]), n.verified ? "Yes" : "No", n.coi ? '<span class="pill warn">declared</span>' : "—", '<a class="btn sm alt" href="#/admin/nominations/' + e(n.id) + '">Open</a>']; }), "No nominations.");
  });
  W.act.curate = function () { W.openModal("Add a curated nomination", '<form data-form="curate" class="stack-s">' + W.field("Nominee", "nominee", { req: true }) + W.field("Category", "category", { type: "select", options: W.cats().map(function (c) { return c.name; }) }) + W.field("Evidence", "evidence", { type: "textarea", req: true }) + '<button class="btn">Add</button></form>'); };
  W.forms.curate = function (d) { var ref = "NOM-" + ("000" + (W.S.nominations.length + 1)).slice(-4); W.S.nominations.push({ id: W.uid("n"), ref: ref, nominator: "Staff (curated)", nominatorEmail: "", verified: true, nominee: d.nominee, nomineeType: "business", nomineeEmail: "", category: d.category, evidence: d.evidence, status: "submitted", profileId: "", token: "t-" + W.uid("") }); W.log("nomination.curate", ref, d.nominee); W.ui.modal = null; W.save(); W.render(); };
  mini("/admin/nominations/:id", "A29", "awards", function (par) {
    var n = W.find("nominations", par.id); if (!n) return "<h1>Not found</h1>"; var st = W.nomStage(n);
    var next = { submitted: ["Start eligibility screening", "screen"], screening: ["Mark eligible and invite nominee", "eligible"], accepted: ["Move to independent assessment", "assess"], assessment: ["Shortlist (approver recorded)", "shortlist"], shortlisted: ["Final approval (finalist)", "final"], finalist: ["Mark winner", "winner"] }[n.status];
    return '<p><a href="#/admin/nominations">← Nominations</a></p>' + head(n.ref + ": " + n.nominee, e(n.category)) + W.steps(W.LISTS.stages, st - 1) +
      '<div class="grid side"><div class="stack"><div class="card stack-s"><h2>Status: ' + e(STAT[n.status] || n.status) + '</h2><p>Nominator: ' + e(n.nominator) + " (" + (n.verified ? "email verified" : "unverified") + ")</p><p>Evidence: " + e(n.evidence) + "</p>" + (n.coi ? "<p>Conflict declared: " + e(n.coi) + "</p>" : "") + '<div class="row">' + (next ? W.btn(next[0], "nomStep", n.id + "|" + next[1]) : "") + (n.status === "screening" || n.status === "submitted" ? W.btn("Mark ineligible", "nomStep", n.id + "|ineligible", "alt bad") : "") + (n.status === "invited" ? '<a class="btn alt" href="#/nominee/' + e(n.token) + '">Open the nominee link</a>' : "") + (["shortlisted", "finalist", "winner"].indexOf(n.status) >= 0 ? (n.pub ? W.btn("Unpublish nominee page", "unpubNom", n.id, "alt bad") : W.btn("Publish nominee page", "pubNom", n.id)) : "") + "</div>" + (["shortlisted", "finalist", "winner"].indexOf(n.status) >= 0 ? '<p class="small muted">The nominee page is public only after you press Publish (the normal publish step; no flag, D-037). Public: <strong>' + (n.pub ? "yes" : "no") + "</strong>.</p>" : "") +
      '<p class="small muted">Named approvers: screening ' + e(n.screenedBy || "—") + "; shortlist " + e(n.shortlistBy || "—") + "; final " + e(n.finalBy || "—") + ". Each step is a separate recorded action (REQ-202).</p></div></div>" +
      '<div class="stack"><div class="infobox"><strong>No commercial data here.</strong> There is no data path from payments, subscriptions or sponsorship into screening, shortlisting or judging (REQ-199).</div>' + W.notesBox(n.id) + "</div></div>";
  });
  W.act.nomStep = function (a) {
    var s = a.split("|"), n = W.find("nominations", s[0]), me = W.persona();
    if (s[1] === "screen") { n.status = "screening"; n.screenedBy = me; }
    else if (s[1] === "eligible") { n.status = "invited"; n.screenedBy = me; var p = W.S.profiles.filter(function (x) { return x.name.toLowerCase() === n.nominee.toLowerCase(); })[0]; if (p) n.profileId = p.id; W.mail(n.nomineeEmail || "Nominee (no email on file)", "You have been nominated", "Accept, decline or correct your details. Nothing is public.", "#/nominee/" + n.token); }
    else if (s[1] === "assess") n.status = "assessment";
    else if (s[1] === "shortlist") { if (!W.hasCap("nominations")) { W.toast("You do not have the nominations capability (Editor and Administrator by default)."); return; } n.status = "shortlisted"; n.shortlistBy = me; }
    else if (s[1] === "final") { if (!W.hasCap("final_approval")) { W.toast("Final approval needs the final_approval capability, Administrator by default (stands in for the Venture CEO)."); return; } n.status = "finalist"; n.finalBy = me; }
    else if (s[1] === "winner") { n.status = "winner"; }
    else if (s[1] === "ineligible") n.status = "ineligible";
    W.log("nomination." + s[1], n.ref, me); W.save(); W.render();
  };
  mini("/admin/awards", "A30", "awards", function () {
    var A = W.S.awards, sum = A.criteria.reduce(function (t, c) { return t + c.weight; }, 0);
    return head("Awards setup", "Everything here is configuration. Nothing is hard-coded (REQ-200). Sample values; the Venture CEO decides (N8).") + '<form class="card stack-s" data-form="awardcfg">' + W.field("Eligibility criteria", "eligibility", { type: "textarea", value: A.eligibility }) + W.field("Shortlist size per category", "shortlistSize", { type: "number", value: A.shortlistSize }) + W.field("Judges on the panel per category", "judgesPerCategory", { type: "number", value: A.judgesPerCategory }) + "<h2>Scoring criteria and weights</h2>" + A.criteria.map(function (c, i) { return '<div class="row"><label class="sr" for="cn' + i + '">Name</label><input id="cn' + i + '" name="cn' + i + '" value="' + e(c.name) + '" style="flex:1"><label class="sr" for="cw' + i + '">Weight</label><input id="cw' + i + '" type="number" name="cw' + i + '" value="' + c.weight + '" style="width:6rem"></div>'; }).join("") + '<p class="' + (sum === 100 ? "" : "err") + '">Weights total ' + sum + "%." + (sum === 100 ? "" : " Must total 100.") + '</p><button class="btn">Save</button></form><div class="card"><h2>Categories (' + W.cats().length + ")</h2>" + A.groups.map(function (g) { return "<h3>" + e(g.name) + "</h3><p>" + g.cats.map(e).join("; ") + "</p>"; }).join("") + "</div>";
  });
  W.forms.awardcfg = function (d) {
    var A = W.S.awards; A.eligibility = d.eligibility; A.shortlistSize = Number(d.shortlistSize); A.judgesPerCategory = Number(d.judgesPerCategory);
    A.criteria.forEach(function (c, i) { c.name = d["cn" + i]; c.weight = Number(d["cw" + i]) || 0; }); W.log("awards.config", "awards", "saved"); W.save(); W.render(); W.toast("Saved.");
  };
  mini("/admin/judges", "A31", "awards", function () {
    var asg = W.S.judgeAssign, names = Object.keys(asg);
    return head("Judging panel and conflicts", "Judges are names on a panel, not users: they have no account and no access to this site. An Editor records assignments and conflicts they declare offline (REQ-036, 234).") + W.table(["Judge (name only)", "Assigned", "Conflicts recorded"], names.map(function (j) { return [e(j), (asg[j] || []).map(function (id) { var n = W.find("nominations", id); return e(n.ref + " " + n.nominee); }).join("<br>") || "none", (W.S.judgeCoi[j] || []).map(function (id) { return e(W.find("nominations", id).ref); }).join(", ") || "none"]; })) +
      '<div class="grid c2"><form class="card stack-s" data-form="assign"><h2>Assign a nomination to a judge</h2>' + W.field("Judge", "judge", { type: "select", options: names }) + W.field("Nomination", "n", { type: "select", options: W.S.nominations.filter(function (n) { return ["assessment", "shortlisted", "finalist", "accepted"].indexOf(n.status) >= 0; }).map(function (n) { return [n.id, n.ref + " " + n.nominee]; }) }) + '<button class="btn">Assign</button></form>' +
      '<form class="card stack-s" data-form="judgecoi"><h2>Record a conflict a judge declared</h2>' + W.field("Judge", "judge", { type: "select", options: names }) + W.field("Nomination", "n", { type: "select", options: W.S.nominations.map(function (n) { return [n.id, n.ref + " " + n.nominee]; }) }) + W.field("What the judge told us", "why", { type: "textarea", req: true }) + '<button class="btn alt">Record conflict</button></form></div>' +
      '<form class="card stack-s" data-form="staffcoi"><h2>Staff conflict declaration</h2>' + W.field("Staff member", "who", { value: W.persona() }) + W.field("Nomination", "n", { type: "select", options: W.S.nominations.map(function (n) { return [n.id, n.ref + " " + n.nominee]; }) }) + '<button class="btn alt">Declare</button></form>';
  });
  W.forms.assign = function (d) { var a = W.S.judgeAssign[d.judge] = W.S.judgeAssign[d.judge] || []; if (a.indexOf(d.n) < 0) a.push(d.n); W.log("judge.assign", d.n, d.judge); W.save(); W.render(); };
  W.forms.judgecoi = function (d) {
    var c = W.S.judgeCoi[d.judge] = W.S.judgeCoi[d.judge] || []; if (c.indexOf(d.n) < 0) c.push(d.n);
    var sc = W.S.scores[d.n]; if (sc && sc.by && sc.by[d.judge]) delete sc.by[d.judge];
    W.log("coi.judge", d.n, d.judge + " declared (entered by " + W.persona() + "): " + d.why); W.save(); W.render(); W.toast("Recorded. " + d.judge + " can no longer be scored on this nomination.");
  };
  W.forms.staffcoi = function (d) { W.log("coi.staff", d.n, d.who + " declared a conflict"); W.save(); W.toast("Recorded. " + d.who + " is excluded from this nomination."); };
  mini("/admin/announcements", "A32", "awards", function () {
    var A = W.S.awards, listed = W.S.nominations.filter(function (n) { return ["shortlisted", "finalist", "winner"].indexOf(n.status) >= 0; }), wins = W.S.nominations.filter(function (n) { return n.status === "winner"; });
    return head("Shortlist and winners", "Shortlist, finalist and winner pages go public through the normal publish step: publish each nominee page, then publish the winners announcement. There is no feature flag for this (D-037, REQ-038). Nothing is visible to visitors until you publish.") +
      "<h2>Nominee pages</h2>" + W.table(["Ref", "Nominee", "Category", "Stage", "Public", ""], listed.map(function (n) { return [e(n.ref), e(n.nominee), e(n.category), W.stPill(STAT[n.status] || n.status), n.pub ? '<span class="pill ok">published</span>' : '<span class="pill gray">not published</span>', n.pub ? W.btn("Unpublish", "unpubNom", n.id, "sm alt bad") : W.btn("Publish page", "pubNom", n.id, "sm")]; }), "No one is shortlisted yet.") +
      "<h2>Winners</h2>" + W.table(["Category", "Winner", "Notified"], wins.map(function (n) { return [e(n.category), e(n.nominee), n.notified ? "Yes" : W.btn("Prepare email", "winMail", n.id, "sm alt")]; }), "No winners selected yet. Mark one on the score page or the nomination.") + "<p>" + W.btn(A.published ? "Announcement published" : "Publish winners announcement", "publishWin", "", "", (wins.length && !A.published) ? "" : " disabled") + (A.published ? " " + W.btn("Unpublish announcement", "unpubWin", "", "alt bad") : "") + "</p>" + (A.published ? '<p><a href="#/awards/winners">View public winners page</a></p>' : "");
  });
  W.act.pubNom = function (id) { var n = W.find("nominations", id); if (["shortlisted", "finalist", "winner"].indexOf(n.status) < 0) return; n.pub = true; W.log("nomination.publish", n.ref, "nominee page published"); W.save(); W.render(); W.toast("Nominee page published."); };
  W.act.unpubNom = function (id) { var n = W.find("nominations", id); n.pub = false; W.purge("/awards/nominee/" + n.ref, "nominee page unpublished"); W.log("nomination.unpublish", n.ref, "nominee page unpublished"); W.save(); W.render(); W.toast("Nominee page unpublished."); };
  W.act.unpubWin = function () { W.S.awards.published = false; W.purge("/awards/winners", "announcement unpublished"); W.log("announcement.unpublish", "awards", ""); W.save(); W.render(); };
  W.act.winMail = function (id) { var n = W.find("nominations", id); n.notified = true; W.mail(n.nomineeEmail || "Winner", "Congratulations", "You are a winner in " + n.category + ". Private until announcement.", ""); W.save(); W.render(); };
  W.act.publishWin = function () { var A = W.S.awards; A.winners = W.S.nominations.filter(function (n) { return n.status === "winner"; }).map(function (n) { return { cat: n.category, name: n.nominee, ref: n.ref }; }); A.published = true; W.log("announcement.publish", "awards", A.winners.length + " winners"); W.save(); W.render(); };

  /* ---------- platform ---------- */
  capRoute("nominations_flag", "/admin/flags", "A26", function () {
    var F = W.S.flags.nominations;
    return head("Feature flag", "There is one feature flag: Nominations (D-037). It switches the public nomination form on or off. Turning it ON needs the Administrator and the Venture CEO\u2019s approval reference, and is audited (REQ-194).") +
      W.table(["Flag", "What it controls", "State", "Approval ref", ""], [["nominations", "The Nominate page. OFF: \u201cNominations open soon\u201d with an email sign-up. ON: the full nomination form.", '<span class="pill ' + (F.on ? "flag-on" : "flag-off") + '">' + (F.on ? "ON" : "OFF") + "</span>", e(F.ref || "\u2014"), F.on ? W.btn("Turn OFF", "flagOff", "nominations", "sm alt") : W.btn("Turn ON\u2026", "flagOnDlg", "nominations", "sm")]]) +
      '<p class="small muted">Not flags: bulk unpublish by filter replaced the old global switch (D-043); judging is offline and staff enter the scores; shortlist and winner pages are published by staff through the normal publish step; AI drafting belongs to the acquisition pipeline, not the website.</p>';
  });
  W.act.flagOnDlg = function (k) { W.openModal("Turn on: " + k, '<form data-form="flagon" class="stack-s"><input type="hidden" name="k" value="' + e(k) + '"><p>Public pages change immediately. Only turn this on with the Venture CEO’s explicit approval.</p>' + W.field("Approval reference (email, minute or ticket)", "ref", { req: true }) + '<button class="btn">Turn ON</button></form>'); };
  W.forms.flagon = function (d) { W.S.flags[d.k] = { on: true, ref: d.ref }; W.log("flag.on", d.k, "approval: " + d.ref); W.ui.modal = null; W.save(); W.render(); W.toast(d.k + " is ON. Check the Nominate and Awards pages."); };
  W.act.flagOff = function (k) { W.S.flags[k] = { on: false, ref: "" }; W.log("flag.off", k, ""); W.save(); W.render(); W.toast(k + " is OFF."); };
  capRoute("audit_log", "/admin/audit", "A33", function () {
    var A = W.S.audit, bad = -1, prev = "00000000";
    return head("Audit log", "Append-only. Each row stores the hash of the one before, so tampering is detectable (REQ-051, 188). Exported nightly off-host.") + W.table(["#", "When", "Actor", "Action", "Target", "Detail", "Hash"], A.slice().reverse().slice(0, 60).map(function (a) { return [a.n, e(a.at), e(a.actor), e(a.action), e(a.target), e(a.detail), '<span class="mono">' + e(a.hash) + "</span>"]; }), "No actions yet. Do something as staff.") + "<p>" + W.btn("Verify chain", "verifyChain", "", "alt") + "</p>";
  });
  W.act.verifyChain = function () { var prev = "00000000", ok = true; W.S.audit.forEach(function (a) { if (a.prev !== prev) ok = false; prev = a.hash; }); W.toast(ok ? "Chain intact: " + W.S.audit.length + " rows verified." : "CHAIN BROKEN: alert raised."); };
  capRoute("users_roles", "/admin/users", "A34", function () {
    return head("Users and roles", "Only staff have accounts. There are three staff roles: Reviewer, Editor and Administrator. Judge is not a role, and the public are visitors with no account (D-044). Two-factor sign-in is required for every one (REQ-047, 083, 235). Only the Administrator manages users.") + W.table(["User", "Email", "Role (one per user)", "Two-factor", "Last seen", ""], W.S.users.map(function (u) { var me = u.email === W.S.user; return [e(u.name) + (me ? ' <span class="pill gray">you</span>' : ""), e(u.email), e(W.roleName(u.rk)), u.mfa ? '<span class="pill ok">on</span>' : '<span class="pill bad">OFF</span>', e(u.last), (u.mfa ? "" : W.btn("Require two-factor", "mfa", u.name, "sm") + " ") + (me ? '<span class="small muted">cannot change your own role</span>' : ["reviewer", "editor", "admin"].filter(function (r) { return r !== u.rk; }).map(function (r) { return W.btn("Make " + W.roleName(r), "setRole", u.email + "|" + r, "sm alt"); }).join(" "))]; })) + '<form class="card stack-s" data-form="addUser" style="max-width:28rem"><h2>Add user</h2><p class="small muted">An email address belongs to exactly one user, and a user has exactly one role (REQ-245). Capability grants are separate (Users and capabilities).</p>' + W.field("Name", "name", { req: true }) + W.field("Email", "email", { type: "email", req: true }) + W.field("Role (one)", "role", { type: "select", options: [["reviewer", "Reviewer"], ["editor", "Editor"], ["admin", "Administrator"]] }) + (W.ui.userErr ? '<p class="err" role="alert">' + e(W.ui.userErr) + "</p>" : "") + '<button class="btn">Add user</button></form>' + '<p><a class="btn alt" href="#/admin/roles">See what each role can open</a> <a class="btn alt" href="#/admin/capabilities">Users and capabilities</a> ' + W.btn("Deactivate a leaver (sample)", "leaver", "", "alt") + '</p><p class="small muted">Leavers are deactivated the same day and the change is audited (REQ-235). Front-end registration is closed (REQ-229).</p>';
  });
  W.forms.addUser = function (d) {
    var em = String(d.email || "").trim().toLowerCase();
    if (!W.hasCap("users_roles")) { W.toast("Only the Administrator manages users."); return; }
    if (W.S.users.some(function (u) { return u.email.toLowerCase() === em; })) { W.ui.userErr = "This email already belongs to a user; an email can have only one role."; W.render(); return; }
    W.ui.userErr = "";
    W.S.users.push({ name: d.name.trim(), email: em, rk: d.role, mfa: false, last: "never" });
    W.log("user.add", em, "role " + W.roleName(d.role) + "; two-factor required at first sign-in"); W.save(); W.render(); W.toast("User added with one role. Two-factor is enforced at first sign-in.");
  };
  W.act.setRole = function (a) {
    var s = a.split("|"), u = W.S.users.filter(function (x) { return x.email === s[0]; })[0];
    if (!u || !W.hasCap("users_roles")) return;
    if (u.email === W.S.user) { W.toast("You cannot change your own role."); return; }
    var was = u.rk; u.rk = s[1];
    W.log("user.role", u.email, W.roleName(was) + " to " + W.roleName(u.rk) + " (replaces the old role; capability grants stay)"); W.save(); W.render(); W.toast(u.name + " now has the single role " + W.roleName(u.rk) + ".");
  };
  W.act.leaver = function () { W.log("user.deactivate", "Sample Leaver", "same-day deactivation"); W.save(); W.toast("Account deactivated and logged (sample)."); };
  W.act.mfa = function (n) { W.S.users.filter(function (u) { return u.name === n; })[0].mfa = true; W.log("user.mfa", n, "required"); W.save(); W.render(); };
  mini("/admin/compliance", "A37", "dsr", function () {
    return head("Compliance", "Processor register, retention and breach log.") + "<h2>Processors (REQ-065)</h2>" + W.table(["Processor", "Role", "Location", "DPA"], W.S.processors.map(function (p) { return [e(p.name), e(p.role), e(p.location), e(p.dpa)]; })) + "<h2>Lawful basis by category of person (REQ-057; Legal's written position requested by 13 Nov)</h2>" + W.table(["Category", "Basis (default)", "Status"], [["Public figures published without consent", "Legitimate interests, right to object", "Awaiting Legal"], ["Profile owners and customers", "Contract / consent", "Awaiting Legal"], ["Nominators and nominees", "Consent / legitimate interests", "Awaiting Legal"], ["Email sign-ups", "Consent (stored with policy version)", "Built"]]) + '<div class="card stack-s"><h2>DPIA and data-protection contact (REQ-072)</h2><p>DPIA: <span class="pill warn">in progress</span> · Contact: <span class="mono">dpo@wiwi.africa (sample)</span></p><p class="small muted">If Legal objects, bulk unpublish by filter is the response (REQ-193, D-043).</p></div>' + "<h2>Retention schedule (REQ-063; defaults pending Legal)</h2>" + W.table(["Data", "Keep for (D-023 defaults; rows marked sample are not decided)", "Then"], [["Subscriber data", "6 years after last payment", "Delete or anonymise"], ["Nominations", "2 years", "Delete"], ["Suppression record", "Indefinitely (name only)", "Keep"], ["Enquiries (sample)", "Not decided", "To decide"]]) + '<h2>Breach log (REQ-066)</h2><form class="card stack-s" data-form="breach">' + W.field("Describe what happened", "d", { type: "textarea", req: true }) + '<button class="btn alt">Log breach and start the response checklist</button></form>' + (W.S.breaches ? W.table(["Logged", "Description"], W.S.breaches.map(function (b) { return [e(b.at), e(b.d)]; })) : "");
  });
  W.forms.breach = function (d) { (W.S.breaches = W.S.breaches || []).push({ at: W.TODAY, d: d.d }); W.log("breach.log", "incident", d.d); W.save(); W.render(); W.toast("Logged. Checklist: contain, assess, notify the regulator and affected people within the legal deadline."); };
  mini("/admin/purges", "A48", "purges", function () {
    return head("Purge and unpublish log", "Each unpublish (single or bulk), removal and decline queues a CDN purge (REQ-209). The origin already answers 410 + noindex.") + W.table(["URL", "Why", "Date", "Status"], W.S.purges.map(function (p) { return ['<span class="mono">' + e(p.url) + "</span>", e(p.why), e(p.at), e(p.status)]; }), "Nothing purged yet. Unpublish a profile to see an entry.");
  });
  mini("/admin/exports", "A41", "exports", function () {
    return head("Exports", "CSV and open formats for CaromHQ and data portability (REQ-077, 087, 131).") + '<div class="grid c3">' + [["register", "Register with counting-rule status"], ["leads", "Leads"], ["customers", "Customers"], ["nominations", "Nominations"], ["profiles", "All profiles (open format)"], ["enquiries", "Enquiries"]].map(function (x) { return '<div class="card stack-s"><h2>' + e(x[1]) + "</h2>" + W.btn("Download CSV", "dl", x[0], "alt") + "</div>"; }).join("") + '</div><p class="small muted">Exports of personal data are Administrator-only and every export is logged (REQ-069, D-040). Reviewers and all other roles cannot export.</p>';
  });
  W.act.dl = function (k) {
    if (!W.hasCap("exports")) { W.log("export.denied", k, "role " + W.S.role); W.save(); W.toast("Exports of personal data need the exports capability, Administrator by default (REQ-069)."); return; }
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
  mini("/admin/emails", "A40", "emails", function () {
    return head("Email log", "Every notification the system sends (REQ-094). Provider bounces will be recorded here.") + W.table(["Date", "To", "Subject", "Link"], W.S.emails.map(function (m) { return [e(m.at), e(m.to), e(m.subject), m.link ? '<a href="' + e(m.link) + '">open</a>' : ""]; }), "No emails sent yet.");
  });
  capRoute("settings", "/admin/settings", "A47", function () {
    var t = W.S.thresholds;
    return head("Settings") + '<form class="card stack-s" data-form="settings"><h2>Sprint thresholds</h2>' + W.field("Paid subscriptions needed", "subs", { type: "number", value: t.subs }) + W.field("Confirmed revenue needed (NGN)", "rev", { type: "number", value: t.revenue / 100 }) + "<h2>Default revenue stream per arrangement type</h2>" + Object.keys(W.LISTS.arrTypes).map(function (k) { return W.field(W.LISTS.arrTypes[k], "st_" + k, { type: "select", options: Object.keys(W.LISTS.streams).map(function (s) { return [s, W.LISTS.streams[s]]; }), value: W.LISTS.defaultStream[k] }); }).join("") + '<button class="btn">Save</button></form>';
  });
  W.forms.settings = function (d) { W.S.thresholds = { subs: Number(d.subs), revenue: Math.round(Number(d.rev) * 100) }; Object.keys(W.LISTS.arrTypes).forEach(function (k) { W.LISTS.defaultStream[k] = d["st_" + k]; }); W.log("settings.save", "thresholds", ""); W.save(); W.toast("Saved."); W.render(); };

  /* ---------- offline judging: score entry and results (REQ-234) ---------- */
  var SCORABLE = ["accepted", "assessment", "shortlisted", "finalist", "winner"];
  function calc(v) { var A = W.S.awards, t = 0; A.criteria.forEach(function (c) { t += (Number(v[c.id]) || 0) * c.weight / 100; }); return t.toFixed(1); }
  W.calcScore = calc;
  W.coi = function (judge, nomId) { return (W.S.judgeCoi[judge] || []).indexOf(nomId) >= 0; };
  W.nomJudges = function (nomId) { return Object.keys(W.S.judgeAssign).filter(function (j) { return (W.S.judgeAssign[j] || []).indexOf(nomId) >= 0; }); };
  W.nomAverage = function (nomId) { var by = (W.S.scores[nomId] || {}).by || {}, ts = Object.keys(by).map(function (j) { return Number(calc(by[j].v)); }); return ts.length ? (ts.reduce(function (a, b) { return a + b; }, 0) / ts.length).toFixed(1) : "\u2014"; };
  W.recordScore = function (nomId, judge, v) {
    if (!W.hasCap("score_entry")) return "only a user with the score_entry capability (Editor or Administrator by default) records scores";
    if (W.nomJudges(nomId).indexOf(judge) < 0) return judge + " is not assigned to this nomination";
    if (W.coi(judge, nomId)) return "conflict recorded for " + judge + "; this judge cannot be scored on this nomination";
    var bad = W.S.awards.criteria.some(function (c) { var x = v[c.id]; return x === "" || x == null || isNaN(Number(x)) || Number(x) < 0 || Number(x) > 10; });
    if (bad) return "enter a score from 0 to 10 for every criterion";
    var n = {}; W.S.awards.criteria.forEach(function (c) { n[c.id] = Number(v[c.id]); });
    var sc = W.S.scores[nomId] = W.S.scores[nomId] || { by: {} }; sc.by[judge] = { v: n, by: W.persona(), at: W.TODAY };
    W.log("scores.enter", nomId, judge + " scored; entered by " + W.persona()); return "";
  };
  mini("/admin/scores", "A51", "scores", function () {
    var rows = W.S.nominations.filter(function (n) { return SCORABLE.indexOf(n.status) >= 0; });
    var ranked = rows.slice().sort(function (a, b) { return (a.category === b.category ? 0 : a.category < b.category ? -1 : 1) || (Number(W.nomAverage(b.id)) || 0) - (Number(W.nomAverage(a.id)) || 0); });
    return head("Scores and results", "Judging is offline. Judges score on paper or by email; an Editor enters their scores here. Judges have no account. No payment, subscription or sponsorship data appears on these screens (REQ-037, 234).") +
      W.table(["Ref", "Nominee", "Category", "Panel", "Scores in", "Weighted average (/10)", "Status", ""], ranked.map(function (n) { var js = W.nomJudges(n.id), ok = js.filter(function (j) { return !W.coi(j, n.id); }), got = Object.keys((W.S.scores[n.id] || {}).by || {}).length; return [e(n.ref), e(n.nominee), e(n.category), e(js.join(", ") || "no panel yet"), got + " of " + ok.length + (ok.length < js.length ? ' <span class="pill warn">' + (js.length - ok.length) + " conflict</span>" : ""), e(W.nomAverage(n.id)), W.stPill(STAT[n.status] || n.status), '<a class="btn sm" href="#/admin/scores/' + e(n.id) + '">Enter / view scores</a>']; }), "No nominations are ready for scoring.") +
      '<p class="small muted">Results are sorted by category and score. Shortlist and winner marking happen on each nomination\u2019s score page and reach the public shortlist and winners pages only when staff publish them (Shortlist and winners, D-037).</p>';
  });
  mini("/admin/scores/:id", "A52", "scores", function (par) {
    var n = W.find("nominations", par.id); if (!n) return "<h1>Not found</h1>";
    var A = W.S.awards, js = W.nomJudges(n.id), by = (W.S.scores[n.id] || {}).by || {}, on = true;
    var actions = (n.status === "assessment" ? (W.hasCap("nominations") ? W.btn("Shortlist (recorded approver)", "nomStep", n.id + "|shortlist") : '<span class="small muted">Shortlisting needs the nominations capability, Editor or Administrator by default (REQ-202).</span>') : "") + (n.status === "finalist" ? W.btn("Mark winner", "nomStep", n.id + "|winner") : "") + (n.status === "shortlisted" ? '<span class="small muted">Final approval is by the Administrator standing in for the Venture CEO. <a href="#/admin/nominations/' + e(n.id) + '">Open the nomination</a></span>' : "");
    return '<p><a href="#/admin/scores">\u2190 Scores and results</a></p>' + head(n.ref + ": " + n.nominee, e(n.category)) +
      '<div class="card"><h2>Evidence</h2><p>' + e(n.evidence) + '</p><p class="small muted">The nominator is not shown. No payment, subscription or sponsorship data is available here.</p></div>' + 
      (js.length ? '<form class="card stack-s" data-form="scoregrid"><input type="hidden" name="id" value="' + e(n.id) + '"><h2>Scores by judge (0 to 10 per criterion)</h2><div class="tablewrap"><table class="t"><thead><tr><th scope="col">Judge</th>' + A.criteria.map(function (c) { return '<th scope="col">' + e(c.name) + " (" + c.weight + "%)</th>"; }).join("") + '<th scope="col">Weighted total</th></tr></thead><tbody>' + js.map(function (j, ji) {
        if (W.coi(j, n.id)) return '<tr><th scope="row">' + e(j) + '</th><td colspan="' + (A.criteria.length + 1) + '"><span class="pill warn">Conflict recorded</span> This judge cannot be scored on this nomination (REQ-036).</td></tr>';
        var sc = by[j] || { v: {} };
        return '<tr><th scope="row">' + e(j) + "</th>" + A.criteria.map(function (c) { return '<td><label class="sr" for="s_' + ji + "_" + c.id + '">' + e(j + " " + c.name) + '</label><input id="s_' + ji + "_" + c.id + '" name="s_' + ji + "_" + c.id + '" type="number" min="0" max="10" step="1" value="' + (sc.v[c.id] == null ? "" : sc.v[c.id]) + '"' + (on ? "" : " disabled") + ' style="width:5rem"></td>'; }).join("") + "<td>" + (by[j] ? e(calc(sc.v)) : "\u2014") + "</td></tr>";
      }).join("") + '</tbody></table></div><p><strong>Weighted average of recorded scores: ' + e(W.nomAverage(n.id)) + ' / 10</strong></p><div class="row"><button class="btn"' + (on ? "" : " disabled") + ">Save scores</button>" + actions + '</div><p class="small muted">Each saved score records who entered it and when (audit log). Criteria and weights come from Awards setup.</p></form>' : W.empty("No panel assigned", "Assign judges in the Judging panel screen first.", '<a class="btn" href="#/admin/judges">Judging panel</a>'));
  });
  W.forms.scoregrid = function (d) {
    var js = W.nomJudges(d.id), msgs = [], saved = 0;
    js.forEach(function (j, ji) {
      var v = {}, any = false; W.S.awards.criteria.forEach(function (c) { var x = d["s_" + ji + "_" + c.id]; v[c.id] = x; if (x !== "" && x != null) any = true; });
      if (!any) return;
      var err = W.recordScore(d.id, j, v); if (err) msgs.push(err); else saved++;
    });
    W.save(); W.render(); W.toast(msgs.length ? "Not saved: " + msgs.join("; ") + "." : saved ? saved + " judge score(s) saved and logged." : "Nothing to save.");
  };
  /* ---------- roles and permissions preview (REQ-235) ---------- */
  mini("/admin/roles", "A54", "users_roles", function () {
    var roles = [["reviewer", "Reviewer"], ["editor", "Editor"], ["admin", "Administrator"]];
    var grp = "", rows = [];
    W.CAPS.forEach(function (c) {
      if (c[2] !== grp) { grp = c[2]; rows.push(['<strong>' + e(grp) + "</strong>", "", "", ""]); }
      rows.push([e(c[1]) + ' <code class="small">' + e(c[0]) + "</code>"].concat(roles.map(function (r) { return W.hasCap(c[0], r[0], true) ? '<span class="pill ok">Yes</span>' : '<span class="muted">\u2014</span>'; })));
    });
    return head("Roles and permissions", "Preview of the role defaults and left menus the WordPress build must enforce and test (REQ-235, 240, 244; D-044, D-045). A role is a default bundle of capabilities; the Administrator can add or remove single capabilities for one user (<a href=\"#/admin/capabilities\">Users and capabilities</a>). There are three staff roles. Visitors have no account.") +
      W.table(["Capability"].concat(roles.map(function (r) { return r[1]; })), rows) +
      "<h2>Left menu by role (defaults)</h2>" + W.table(["Role", "Screens in the left menu"], roles.map(function (r) { var m = W.menuFor(r[0], true); return [e(r[1]), m.length + ": " + m.map(e).join(" \u00b7 ")]; })) +
      '<div class="grid c2"><div class="card stack-s"><h2>Rules</h2><ul><li>Two-factor sign-in is required for every staff role (REQ-047).</li><li>Reviewers see only the review and directory screens. By default they can assign existing terms and bulk unpublish by filter (D-045), but cannot edit, publish, import, edit terms or export.</li><li>Editors edit, publish and unpublish single profiles, but do not bulk unpublish unless the Administrator grants it.</li><li>Judge is not a role. Judges have no account and no access to the site; an Editor enters their scores.</li><li>Only the Administrator installs plugins, changes users, roles or capabilities.</li><li>Exports of personal data are Administrator-only and logged.</li><li>Leavers are deactivated the same day; every change is audited.</li><li>Profile owners, nominators and nominees have no account at all (REQ-229).</li></ul></div><div class="card stack-s"><h2>Try it</h2><p>Use <strong>Preview as</strong> in the top bar to switch between Visitor, Reviewer, Editor and Administrator. Each role gets its own dashboard and left menu. Open a page your role is not allowed to see (for example Taxonomies as Reviewer) to see the 403 page. As Administrator, grant a capability on <a href="#/admin/capabilities">Users and capabilities</a>, switch to that user, and watch the menu change.</p></div></div>';
  });

  /* ---------- users and capabilities (REQ-244, D-045) ---------- */
  var CUSERS = [["reviewer", "Sample Reviewer 1"], ["editor", "Sample Editor"], ["admin", "Sample Admin"]];
  mini("/admin/capabilities", "A60", "users_roles", function () {
    var out = head("Users and capabilities", "A role is a default bundle of capabilities. The Administrator can add a capability to one user, or take one away, whatever the user\u2019s role: for example give an Editor <code>bulk_unpublish</code>, or a Reviewer <code>edit_profiles</code>. Changes are audited and take effect at once. Switch <strong>Preview as</strong> to that role to see the menu and 403 pages change. Capabilities marked locked stay with Administrators (proposed limit, Q72).");
    CUSERS.forEach(function (u) {
      var role = u[0], g = W.S.caps[role], eff = W.CAPS.filter(function (c) { return W.hasCap(c[0], role); }).length, menu = W.menuFor(role).length, isAdmin = role === "admin";
      var rows = W.CAPS.map(function (c) {
        var def = W.capDefault(c[0], role), on = W.hasCap(c[0], role), tag = !isAdmin && on && !def ? ' <span class="pill warn">granted</span>' : (!isAdmin && !on && def ? ' <span class="pill bad">revoked</span>' : "");
        var act = isAdmin ? '<span class="small muted">always</span>' : (c[4] && !on ? '<span class="small muted">locked: proposed limit, Q72</span>' : W.btn(on ? "Revoke" : "Grant", "capToggle", role + "|" + c[0], "sm " + (on ? "bad" : "")));
        return [e(c[1]) + ' <code class="small">' + e(c[0]) + "</code>", e(c[2]), def ? "Yes" : "\u2014", on ? '<span class="pill ok">Yes</span>' + tag : '<span class="muted">\u2014</span>' + tag, act];
      });
      out += '<div class="card stack-s"><div class="row between"><h2>' + e(u[1]) + " \u00b7 " + e(W.roleName(role)) + "</h2>" + (isAdmin ? "" : W.btn("Reset to role defaults", "capReset", role, "sm alt")) + "</div><p class=\"small muted\">" + eff + " of " + W.CAPS.length + " capabilities \u00b7 " + menu + " left-menu items" + (isAdmin ? " \u00b7 the Administrator always has every capability" : " \u00b7 " + g.grant.length + " granted, " + g.revoke.length + " revoked") + ".</p>" + W.table(["Capability", "Group", "Role default", "This user", ""], rows) + "</div>";
    });
    return out;
  });
  W.act.capToggle = function (a) {
    if (!W.hasCap("users_roles")) { W.toast("Only the Administrator changes capabilities."); return; }
    var s = a.split("|"), role = s[0], cap = s[1], c = W.CAPIDX[cap], g = W.S.caps[role]; if (!c || !g || role === "admin") return;
    var on = W.hasCap(cap, role), def = W.capDefault(cap, role), who = W.PERSONA[role];
    if (on) {
      g.grant = g.grant.filter(function (x) { return x !== cap; });
      if (def && g.revoke.indexOf(cap) < 0) g.revoke.push(cap);
      W.log("capability.revoke", who, cap);
    } else {
      if (c[4]) { W.toast("Locked: " + cap + " stays with Administrators (proposed limit, Q72)."); return; }
      g.revoke = g.revoke.filter(function (x) { return x !== cap; });
      if (!def && g.grant.indexOf(cap) < 0) g.grant.push(cap);
      W.log("capability.grant", who, cap);
    }
    W.save(); W.render(); W.toast((on ? "Revoked " : "Granted ") + cap + " " + (on ? "from " : "to ") + who + ". Logged; effective at once.");
  };
  W.act.capReset = function (role) {
    if (!W.hasCap("users_roles")) return;
    W.S.caps[role] = { grant: [], revoke: [] }; W.log("capability.reset", W.PERSONA[role], "role defaults"); W.save(); W.render(); W.toast(W.PERSONA[role] + " is back to the role defaults.");
  };
})();
