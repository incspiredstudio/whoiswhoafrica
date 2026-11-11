/* Staff console part 2: import, taxonomies, commercial, content, awards, platform. Flow preview only. */
(function () {
  var W = window.W, e = W.esc, ACL = W.ACL, ALL = W.ALLSTAFF;
  function head(t, sub) { return "<h1>" + e(t) + "</h1>" + (sub ? '<p class="muted">' + sub + "</p>" : ""); }
  function mini(path, inv, key, fn) { W.staffRoute(path, inv, key, fn); }
  function capRoute(cap, path, inv, fn) { W.route(path, inv, fn, { area: "staff", cap: cap, roles: W.rolesWithCap(cap) }); }

  /* ---------- import, suppression ---------- */
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
  mini("/admin/suppression", "A22", "supprView", function () {
    var edit = W.can("supprEdit");
    return head("Suppression list", "Permanent. No import or sync can bring these back. There is no remove button on purpose (REQ-147, 207)." + (edit ? "" : " Read-only for your role.")) + W.table(["Name", "Reason type", "Date", "Detail"], W.S.suppression.map(function (s) { return [e(s.name), e(s.kind), e(s.at), e(s.reason)]; })) + (edit ? '<form class="card stack-s" data-form="supp"><h2>Add manually</h2>' + W.field("Name", "name", { req: true }) + W.field("Reason", "reason", { req: true }) + '<button class="btn">Add</button></form>' : '<p class="small muted">A valid removal request adds a name here automatically (Support inbox). Adding by hand is for the Administrator; a valid removal handled by an Editor adds the name automatically.</p>');
  });
  W.forms.supp = function (d) { W.S.suppression.unshift({ id: W.uid("sp"), name: d.name, kind: "Manual", at: W.TODAY, reason: d.reason }); W.log("suppression.add", d.name, d.reason); W.save(); W.render(); };
  /* ---------- claims (D-057) ---------- */
  mini("/admin/claims", "A61", "review_claims", function () {
    var cards = (W.S.claims || []).map(function (c) {
      var p = W.profile(c.profileId), open = c.status === "new", late = open && c.due && c.due < W.TODAY, soon = open && c.remindAt && c.remindAt <= W.TODAY;
      var act = open ? '<div class="row">' + W.btn("Approve", "claimOk", c.id, "sm") + " " + W.btn("Reject", "claimNo", c.id, "sm alt") + "</div>" : "";
      return '<article class="card stack-s"><div class="row between"><h2 style="margin:0">' + e(c.id) + " · " + (p ? '<a href="#/admin/profiles/' + e(p.id) + '">' + e(p.name) + "</a>" : "(removed)") + "</h2>" + W.stPill(open ? "pending" : c.status === "approved" ? "approved" : "rejected") + "</div>" +
        '<dl class="kv"><div><dt>Claimant</dt><dd>' + e(c.name) + '</dd></div><div><dt>Relationship</dt><dd>' + e(c.relationship) + '</dd></div><div><dt>Role</dt><dd>' + e(c.role || "—") + '</dd></div><div><dt>Work email</dt><dd>' + e(c.email) + '</dd></div><div><dt>Phone</dt><dd>' + e(c.phone || "—") + '</dd></div><div><dt>Consent to be contacted</dt><dd>' + (c.consent === false ? "No" : "Yes") + '</dd></div><div><dt>Received</dt><dd>' + e(c.at) + '</dd></div><div><dt>Decide by</dt><dd>' + e(c.due || "—") + (late ? " <strong>(overdue)</strong>" : soon ? " (reminder sent)" : "") + '</dd></div></dl>' +
        '<div><strong>How they can be checked</strong><p>' + e(c.evidence) + "</p></div>" + (c.reason ? '<p class="small muted">Decision note: ' + e(c.reason) + "</p>" : "") + act + "</article>";
    });
    return head("Claims", "Requests from a person or organisation to claim a profile. Each claim must be decided within 5 days; reviewers and administrators get a reminder on day 3 and again when it is overdue (REQ-256). Approving sets the profile Claimed (a checkmark for staff, and the Claim button disappears from the public page) and lets you assign a user to keep it up to date (REQ-251 to 253).") + (cards.length ? '<div class="stack">' + cards.join("") + "</div>" : W.empty("No claims", "Claims from the public form appear here."));
  });
  W.act.claimOk = function (id) {
    var c = (W.S.claims || []).filter(function (x) { return x.id === id; })[0], p = c && W.profile(c.profileId); if (!c || !p || !W.can("review_claims")) return;
    if (W.isPending(p)) { W.toast("Review the profile first: a pending profile cannot be claimed (REQ-226)."); return; }
    c.status = "approved"; c.by = W.persona(); p.claimed = true; p.claimedAt = W.TODAY;
    p.history.push({ at: W.TODAY, by: W.persona(), what: "Claim approved; profile is Claimed" });
    W.log("claim.approve", p.id, id + " approved; Claimed set"); W.mail(c.email, "Your claim is approved", "You can now ask us to update the profile for " + p.name + ".", ""); W.save(); W.render(); W.toast("Claim approved. Assign a user on the profile if needed.");
  };
  W.act.claimNo = function (id) {
    var c = (W.S.claims || []).filter(function (x) { return x.id === id; })[0]; if (!c || !W.can("review_claims")) return;
    c.status = "rejected"; c.by = W.persona(); c.reason = "Evidence did not match";
    W.log("claim.reject", c.profileId, id + " rejected"); W.mail(c.email, "About your claim", "We could not confirm the claim from the evidence sent. You can send it again with more.", ""); W.save(); W.render(); W.toast("Claim rejected.");
  };

  /* ---------- customers ---------- */
  W.act.newCustomer = function () { W.openModal("New customer", '<form data-form="customer" class="stack-s">' + W.field("Name", "name", { req: true }) + W.field("Segment", "segment", { type: "select", options: ["Individual", "Business", "Institution"] }) + W.field("Contact", "contact", { req: true }) + '<button class="btn">Add</button></form>'); };
  W.forms.customer = function (d) { var id = W.uid("c"); W.S.customers.push({ id: id, name: d.name, segment: d.segment || "Business", contact: d.contact, ext: "CUS-" + ("000" + (W.S.customers.length + 1)).slice(-4), profileId: "" }); W.log("customer.create", id, d.name); W.ui.modal = null; W.save(); W.render(); W.toast("Customer added."); };
  mini("/admin/customers", "A14", "customers", function () {
    return '<div class="row between"><h1>Customers</h1>' + W.btn("New customer", "newCustomer", "", "alt") + "</div>" + W.table(["External ID", "Name", "Segment", "Contact", "Sponsored stories", ""], W.S.customers.map(function (c) { var n = W.S.arrangements.filter(function (a) { return a.customerId === c.id; }).length; return [e(c.ext), e(c.name), e(c.segment), e(c.contact), n, W.btn("Send profile invitation", "invite", c.id, "sm alt")]; })) + '<p class="small muted">Customers and enquiries have stable external IDs and change timestamps for a later CaromHQ link (REQ-205).</p>';
  });

  /* ---------- Stories (blog articles) ---------- */
  mini("/admin/stories", "A62", "articles", function (par, q) {
    var forDeal = q && q.for ? W.find("arrangements", q.for) : null;
    var rows = W.S.articles.slice().sort(function (x, y) { return x.date < y.date ? 1 : -1; }).map(function (a) {
      var sp = W.sponsorOfArticle(a), ev = a.relatedEvent ? W.find("events", a.relatedEvent) : null;
      return ['<a href="#/stories/' + e(a.slug) + '">' + e(a.title) + "</a>", e(a.type), e(a.status), e(a.date), ev ? e(ev.title) : '<span class="muted">-</span>', sp ? "Sponsored by " + e(sp) : '<span class="muted">-</span>'];
    });
    return head("Stories", "Blog articles: interviews, features, news and more. In WordPress these are ordinary posts. Events are a separate post type on the Events screen (REQ-262); a story can link one event, which shows at the end of the story (REQ-265). Comments on a story are moderated with the WordPress defaults (REQ-258). A story is made Sponsored on the Sponsored stories screen, never here.") +
      (forDeal ? '<div class="infobox" role="status">Creating the story for the sponsored story deal <strong>' + e(forDeal.id) + "</strong> (sponsor: " + e((W.find("customers", forDeal.customerId) || {}).name) + "). When you save it, it is linked to that deal. Publish it, then schedule the slot on the deal.</div>" : "") +
      W.table(["Title", "Type", "Status", "Published", "Related event", "Paid slot"], rows) +
      '<form class="card stack-s" data-form="story"><h2>New story</h2>' + (forDeal ? '<input type="hidden" name="dealId" value="' + e(forDeal.id) + '">' : "") +
      W.field("Title", "title", { req: true }) + W.field("Type", "type", { type: "select", options: ["Interview", "Feature", "Spotlight", "Opinion", "Announcement", "News"], req: true }) +
      W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "Choose" }) + W.field("Author", "author", { value: "WHOISWHO Stories" }) +
      W.field("Summary", "lede", { type: "textarea", req: true }) + W.field("Body", "body", { type: "textarea" }) +
      W.field("Related event (optional)", "relatedEvent", { type: "select", options: (W.S.events || []).map(function (v) { return [v.id, v.title + " (" + v.status + ")"]; }), blank: "None", hint: "The event shows as a card at the end of the story." }) +
      W.field("Status", "status", { type: "select", options: ["draft", "scheduled", "published"] }) + W.field("Publish date", "date", { type: "date", value: W.TODAY }) +
      '<button class="btn">Save story</button></form>';
  });
  W.forms.story = function (d) {
    var slug = d.title.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    var a = { id: W.uid("a"), slug: slug, seed: slug, type: d.type, title: d.title, industry: d.industry || "", author: d.author || "WHOISWHO Stories", date: d.date || W.TODAY, status: d.status || "draft", sponsor: null, profiles: [], lede: d.lede, body: d.body || d.lede, paras: [], quote: "" };
    if (d.relatedEvent && W.find("events", d.relatedEvent)) a.relatedEvent = d.relatedEvent;
    W.S.articles.push(a); var dl = d.dealId ? W.find("arrangements", d.dealId) : null; if (dl) { dl.linkedArticle = a.id; W.log("deal.story", dl.id, "story " + a.id + " created for the deal"); }
    W.log("story.add", a.id, a.type + ": " + a.title + " (" + a.status + ")" + (a.relatedEvent ? ", links event " + a.relatedEvent : "")); W.save(); W.render(); W.toast("Saved. " + (a.status === "published" ? "It is public." : "It is not public until published.")); if (dl) W.go("#/admin/sponsors/" + dl.id);
  };
  W.act.storyEvent = function (id, ev) { var a = W.find("articles", id); if (!a) return; if (ev) a.relatedEvent = ev; else delete a.relatedEvent; W.log("story.event", id, ev ? "links event " + ev : "event link removed"); W.save(); W.render(); };

  /* ---------- Events: their own post type (REQ-262, REQ-264) ---------- */
  mini("/admin/events", "A63", "events", function () {
    var rows = (W.S.events || []).slice().sort(function (x, y) { return x.start < y.start ? 1 : -1; }).map(function (v) {
      return ['<a href="#/events/' + e(v.slug) + '">' + e(v.title) + "</a>", e(W.eventWhen(v)), e(W.eventWhere(v)), e(v.organiser), '<span class="pill ' + (v.status === "published" ? "ok" : "warn") + '">' + e(v.status) + "</span> " + e(v.status === "published" ? W.eventState(v) : ""), W.btn(v.status === "published" ? "Unpublish" : "Publish", "evToggle", v.id, "alt sm")];
    });
    return head("Events", "Events are their own post type, separate from stories (REQ-262). They appear on the Events page, on the Home page when upcoming, and at the end of any story that links them. An event is information only: registration and any fee are on the organiser's site, and WHOISWHO takes no payment (D-005). Events cannot be Sponsored.") +
      W.table(["Event", "When", "Where", "Organiser", "Status", ""], rows, "No events yet.") +
      '<form class="card stack-s" data-form="event"><h2>New event</h2>' +
      W.field("Title", "title", { req: true }) + W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "Choose" }) +
      W.field("Start date", "start", { type: "date", req: true }) + W.field("Start time", "time", { type: "time" }) + W.field("End date (optional)", "end", { type: "date" }) +
      W.field("Venue (or Online)", "venue", { req: true }) + W.field("City", "city") + W.field("Country", "country") + W.field("Organiser", "organiser", { req: true }) +
      W.field("Summary", "summary", { type: "textarea", req: true }) + W.field("Body", "body", { type: "textarea" }) +
      W.field("Image", "image", { hint: "Chosen from the media library in WordPress. A placeholder is used here." }) +
      W.field("Registration link (the organiser's site)", "regUrl", { type: "url", req: true, hint: "Information only. WHOISWHO takes no payment and sells no tickets (D-005)." }) + W.field("Price note", "price", { hint: "Text only, for example: Free to attend." }) +
      W.field("Status", "status", { type: "select", options: ["draft", "published"] }) +
      '<button class="btn">Save event</button></form>';
  });
  W.forms.event = function (d) {
    if (!d.start || !d.venue || !d.organiser || !d.regUrl) { W.toast("An event needs a start date, a venue (or Online), an organiser and a registration link."); return; }
    if (d.end && d.end < d.start) { W.toast("The end date is before the start date."); return; }
    var slug = d.title.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "");
    var v = { id: W.uid("ev"), slug: slug, seed: d.image || slug, title: d.title, industry: d.industry || "", status: d.status || "draft", summary: d.summary, body: d.body || d.summary, paras: [], start: d.start + (d.time ? "T" + d.time : ""), end: d.end || "", venue: d.venue, city: d.city || "", country: d.country || "", organiser: d.organiser, regUrl: d.regUrl, price: d.price || "" };
    (W.S.events = W.S.events || []).push(v);
    W.log("event." + (v.status === "published" ? "publish" : "add"), v.id, v.title + " (" + v.status + ")"); W.save(); W.render(); W.toast("Saved. " + (v.status === "published" ? "It is public." : "It is not public until published."));
  };
  W.act.evToggle = function (id) {
    var v = W.find("events", id); if (!v) return;
    v.status = v.status === "published" ? "draft" : "published";
    W.log(v.status === "published" ? "event.publish" : "event.unpublish", id, v.title); W.save(); W.render(); W.toast(v.status === "published" ? "Published." : "Unpublished. It is no longer public; stories that link it no longer show it.");
  };

  /* ---------- Sponsored stories: one deal, four steps (REQ-263, REQ-269, D-068) ---------- */
  var DEALSTAT = [["needs payment", "Needs payment"], ["needs story", "Needs story"], ["ready to schedule", "Ready to schedule"], ["scheduled", "Scheduled"], ["live", "Live"], ["ended", "Ended"], ["cancelled", "Cancelled"]];
  var STEPNAMES = ["Deal agreed", "Payment received", "Story ready", "Placement"];
  function money() { return W.hasCap("commercial_register"); }
  function sponsorName(a) { var c = W.find("customers", a.customerId); return c ? c.name : "?"; }
  function dots(a) { var s = W.dealSteps(a), n = s.filter(Boolean).length; return '<span class="mono" role="img" aria-label="' + n + ' of 4 steps done">' + s.map(function (x) { return x ? "●" : "○"; }).join("") + "</span>"; }
  /* The first step that is not done, as one short instruction. */
  function nextAction(a) {
    if (a.cancelled) return "-";
    var s = W.dealSteps(a), pl = W.dealPlacement(a);
    if (!s[1]) return money() ? "Record the payment" : "Waiting for the payment record (Administrator)";
    if (!s[2]) return W.dealStory(a) ? "Publish the story" : "Choose or create the story";
    if (!s[3]) return "Schedule the slot";
    return pl && W.placementStatus(pl) === "active" ? "Running. End now if needed" : "-";
  }
  function dealRows(q) {
    var rows = W.S.arrangements.filter(function (a) { return !q.s || W.dealStatus(a) === q.s; });
    return rows.map(function (a) {
      var st = W.dealStory(a), cells = [e(sponsorName(a)), st ? '<a href="#/stories/' + e(st.slug) + '">' + e(st.title) + "</a>" + (st.status !== "published" ? ' <span class="pill gray">' + e(st.status) + "</span>" : "") : '<span class="muted">not chosen</span>'];
      if (money()) cells.push(W.money(a.amount, a.currency));
      cells.push(dots(a), W.stPill(W.dealStatus(a)), e(nextAction(a)), '<a class="btn sm alt" href="#/admin/sponsors/' + e(a.id) + '">Open</a>');
      return cells;
    });
  }
  mini("/admin/sponsors", "A18", "sponsors", function (par, q) {
    var heads = ["Sponsor", "Story"].concat(money() ? ["Amount"] : []).concat(["Progress", "Status", "Next step", ""]);
    var counted = W.S.arrangements.filter(W.counts).length;
    return '<div class="row between"><h1>Sponsored stories</h1>' + (money() ? '<a class="btn" href="#/admin/sponsors/new">New sponsored story</a>' : "") + "</div>" +
      '<div class="infobox"><strong>How a sponsored story works (REQ-263, REQ-269).</strong> The deal is agreed and paid offline; this site only records it. Four steps: 1 deal agreed, 2 payment recorded, 3 story ready (published), 4 placement on Home, Stories or both between two dates. A live placement is labelled &quot;Sponsored by&quot; with the sponsor&#39;s name. It never changes the order of other stories, search results, profiles, Featured, nominations or winners. <strong>Counting rule (REQ-022):</strong> a deal counts toward the sprint target when it is agreed and its payment record is complete (amount, currency, method, reference, date received, confirmed by). ' + (money() ? "Counted now: " + counted + "." : "The money details are Administrator-only.") + '</div>' +
      '<form class="filters" data-filter="#/admin/sponsors">' + W.field("Status", "s", { type: "select", options: DEALSTAT, blank: "Any", value: q.s }) + "</form>" +
      W.table(heads, dealRows(q), "No sponsored stories match.") +
      '<h2 style="margin-top:2rem">Supported by (Awards page)</h2><p class="small muted">The sponsor name, link and logo shown as &quot;Supported by&quot; on the Awards page. A sponsor record has no effect on nominations or winners (REQ-186, REQ-199).</p>' +
      W.table(["Sponsor", "Link", "Logo", "Attached to"], W.S.sponsors.map(function (s) { return [e(s.name), e(s.link), e(s.logo), e(s.attached.join("; ") || "nothing")]; })) + '<form class="card stack-s" data-form="sponsor2"><h2>Add sponsor record</h2>' + W.field("Name", "name", { req: true }) + W.field("Link", "link") + W.field("Attach to", "to", { type: "select", options: ["Awards: Maiden Edition", "Category page", "Stories page", "Not yet"] }) + '<button class="btn">Add</button></form>';
  });
  W.forms.sponsor2 = function (d) { W.S.sponsors.push({ id: W.uid("s"), name: d.name, link: d.link, logo: "Logo placeholder", attached: d.to === "Not yet" ? [] : [d.to] }); W.log("sponsor.add", d.name, d.to); W.save(); W.render(); };

  function payFields(a) {
    return W.field("Payment method", "method", { type: "select", options: ["Bank transfer", "Cash", "Cheque", "Other"], blank: "Not yet received", value: a.method }) + W.field("Payment reference", "ref", { value: a.ref }) + W.field("Date received", "received", { type: "date", value: a.received }) + W.field("Confirmed by (named admin)", "confirmedBy", { value: a.confirmedBy });
  }
  function readPay(d) { return { method: d.method || "", ref: d.ref || "", received: d.received || "", confirmedBy: d.confirmedBy || "" }; }
  mini("/admin/sponsors/new", "A18", "sponsors", function () {
    if (!money()) return '<p><a href="#/admin/sponsors">← Sponsored stories</a></p><h1>New sponsored story</h1><p>Only an Administrator records a new deal and its money details.</p>';
    return '<p><a href="#/admin/sponsors">← Sponsored stories</a></p>' + head("New sponsored story", "Steps 1 and 2 in one go. Step 2 can wait until the money arrives. The deal was agreed and paid offline; nothing is charged here (D-005).") +
      '<form class="card stack-s" data-form="dealNew"><h2>1. Deal agreed</h2>' +
      W.field("Sponsor", "customerId", { type: "select", options: W.S.customers.map(function (c) { return [c.id, c.name + " (" + c.segment + ")"]; }).concat([["__new", "New customer…"]]), req: true }) +
      W.field("New customer name (only if you chose New customer)", "newName") + W.field("New customer contact", "newContact") +
      W.field("Agreed amount (NGN, whole naira)", "amount", { type: "number", req: true }) + W.field("Currency", "currency", { value: "NGN" }) + W.field("Notes", "notes", { type: "textarea" }) +
      "<h2>2. Payment received (optional now)</h2>" + payFields({}) + '<button class="btn">Create the deal</button></form>';
  });
  W.forms.dealNew = function (d) {
    if (!money()) { W.toast("Only an Administrator can record a deal."); return; }
    var cid = d.customerId;
    if (cid === "__new") {
      if (!d.newName || !d.newContact) { W.toast("Enter the new customer's name and contact, or choose an existing sponsor."); return; }
      cid = W.uid("c"); W.S.customers.push({ id: cid, name: d.newName, segment: "Sponsor", contact: d.newContact, ext: "CUS-" + ("000" + (W.S.customers.length + 1)).slice(-4), profileId: "" }); W.log("customer.create", cid, "from a new sponsored story");
    }
    var amt = Math.round((Number(d.amount) || 0) * 100);
    if (!(amt > 0)) { W.toast("Enter the agreed amount."); return; }
    var a = Object.assign({ id: W.uid("arr"), type: "sponsored_post", customerId: cid, linkedArticle: "", amount: amt, currency: d.currency || "NGN", agreed: true, cancelled: false, notes: d.notes || "" }, readPay(d));
    W.S.arrangements.push(a); W.log("deal.create", a.id, "customer " + cid + (W.dealPaid(a) ? ", payment recorded" : ", payment not yet recorded")); W.save(); W.go("#/admin/sponsors/" + a.id);
  };
  mini("/admin/sponsors/:id", "A18", "sponsors", function (par) {
    var a = W.find("arrangements", par.id); if (!a) return "<h1>Not found</h1>";
    var s = W.dealSteps(a), cur = s.indexOf(false), st = W.dealStory(a), pl = W.dealPlacement(a), plSt = pl ? W.placementStatus(pl) : "", adm = money();
    var stories = W.S.articles.map(function (x) { return [x.id, x.title + " (" + x.type + ", " + x.status + ")"]; });
    var s1 = '<div class="card stack-s"><h2>1. Deal agreed ' + (s[0] ? '<span class="pill ok">done</span>' : '<span class="pill warn">open</span>') + "</h2>" +
      (adm ? '<form data-form="dealEdit" class="stack-s"><input type="hidden" name="id" value="' + e(a.id) + '">' + W.field("Sponsor", "customerId", { type: "select", options: W.S.customers.map(function (c) { return [c.id, c.name + " (" + c.segment + ")"]; }), value: a.customerId }) + W.field("Agreed amount (NGN, whole naira)", "amount", { type: "number", value: a.amount / 100 }) + W.field("Currency", "currency", { value: a.currency }) + W.field("Notes", "notes", { type: "textarea", value: a.notes }) + '<button class="btn alt">Save the deal</button></form>' : "<p>Sponsor: <strong>" + e(sponsorName(a)) + "</strong>. The amount is Administrator-only.</p>") + "</div>";
    var s2 = '<div class="card stack-s"><h2>2. Payment received ' + (s[1] ? '<span class="pill ok">recorded</span>' : '<span class="pill warn">not recorded</span>') + "</h2>" +
      (adm ? '<p class="small muted">Recorded here after the money arrives offline. Nothing is charged or processed here (D-005). Counts toward the sprint target when complete: <strong>' + (W.counts(a) ? "Yes" : "No") + "</strong>" + (s[1] ? "" : "; missing: " + e(W.arrMissing(a).join(", "))) + '.</p><form data-form="dealPay" class="stack-s"><input type="hidden" name="id" value="' + e(a.id) + '">' + payFields(a) + '<button class="btn alt">Save the payment record</button></form>' : "<p>" + (s[1] ? "Payment is recorded." : "Payment is not recorded yet. An Administrator records it.") + "</p>") + "</div>";
    var s3 = '<div class="card stack-s"><h2>3. Story ready ' + (s[2] ? '<span class="pill ok">published</span>' : st ? '<span class="pill warn">' + e(st.status) + "</span>" : '<span class="pill warn">not chosen</span>') + "</h2>" +
      (st ? '<p>Story: <a href="#/stories/' + e(st.slug) + '">' + e(st.title) + "</a> (" + e(st.status) + ")." + (st.status !== "published" ? " It must be published before the slot can be scheduled." : "") + "</p>" : "<p>No story chosen yet.</p>") +
      '<form data-form="dealStory" class="stack-s"><input type="hidden" name="id" value="' + e(a.id) + '">' + W.field("Story", "storyId", { type: "select", options: stories, blank: "Choose a story", value: a.linkedArticle }) + '<div class="row"><button class="btn alt">Use this story</button><a class="btn alt" href="#/admin/stories?for=' + e(a.id) + '">Create the story</a></div></form></div>';
    var plBlock = pl ? "<p>Slot on <strong>" + e(pl.placement) + "</strong> from " + e(pl.start) + " to " + e(pl.end) + ": " + W.stPill(plSt === "expired" ? "ended" : plSt) + ". Labelled &quot;Sponsored by " + e(pl.sponsor) + "&quot; while live.</p>" + (plSt === "active" || plSt === "scheduled" ? W.btn("End now", "plEnd", pl.id, "alt sm") : "") : "";
    var canSlot = s[2] && !(pl && (plSt === "active" || plSt === "scheduled"));
    var s4 = '<div class="card stack-s"><h2>4. Placement ' + (pl ? W.stPill(plSt === "expired" ? "ended" : plSt) : '<span class="pill warn">not scheduled</span>') + "</h2>" + plBlock +
      (!s[1] ? '<p class="infobox" role="status">Payment not recorded. You can still schedule the slot; the deal counts toward the sprint target only when the payment is recorded.</p>' : "") +
      (canSlot ? '<form data-form="dealSlot" class="stack-s"><input type="hidden" name="id" value="' + e(a.id) + '">' + W.field("Slot on", "placement", { type: "select", options: ["Home", "Stories", "Both"], req: true }) + W.field("Start date", "start", { type: "date", req: true }) + W.field("End date", "end", { type: "date", req: true }) + '<p class="small muted">One paid slot per page at a time. Only a published story can fill a slot.</p><button class="btn">' + (pl ? "Schedule again" : "Schedule the slot") + "</button></form>" : (s[2] ? "" : '<p class="small muted">Choose and publish the story first (step 3).</p>')) + "</div>";
    return '<p><a href="#/admin/sponsors">← Sponsored stories</a></p>' + head("Sponsored story: " + sponsorName(a), a.id + ". Status: " + (DEALSTAT.filter(function (x) { return x[0] === W.dealStatus(a); })[0] || ["", ""])[1] + ". Next: " + nextAction(a) + ".") + W.steps(STEPNAMES, cur < 0 ? 4 : cur) + '<div class="stack">' + s1 + s2 + s3 + s4 + "</div>" +
      (adm && !a.cancelled ? '<div class="row">' + W.btn("Cancel this deal", "dealCancel", a.id, "alt bad") + "</div>" : "") + W.notesBox(a.id);
  });
  W.forms.dealEdit = function (d) {
    if (!money()) { W.toast("Only an Administrator can change the money details."); return; }
    var a = W.find("arrangements", d.id); if (!a) return; var amt = Math.round((Number(d.amount) || 0) * 100); if (!(amt > 0)) { W.toast("Enter the agreed amount."); return; }
    a.customerId = d.customerId; a.amount = amt; a.currency = d.currency || "NGN"; a.notes = d.notes || "";
    W.log("deal.edit", a.id, "customer " + a.customerId); W.save(); W.render(); W.toast("Saved.");
  };
  W.forms.dealPay = function (d) {
    if (!money()) { W.toast("Only an Administrator can record a payment."); return; }
    var a = W.find("arrangements", d.id); if (!a) return; Object.assign(a, readPay(d));
    W.log("deal.payment", a.id, W.dealPaid(a) ? "payment record complete" : "payment record incomplete: " + W.arrMissing(a).join(", ")); W.save(); W.render(); W.toast(W.dealPaid(a) ? "Saved. It now counts toward the sprint target." : "Saved. Still missing: " + W.arrMissing(a).join(", ") + ".");
  };
  W.forms.dealStory = function (d) {
    var a = W.find("arrangements", d.id), s = W.find("articles", d.storyId); if (!a) return; if (!s) { W.toast("Choose a story."); return; }
    a.linkedArticle = s.id; W.log("deal.story", a.id, "story " + s.id + " (" + s.status + ")"); W.save(); W.render(); W.toast(s.status === "published" ? "Story chosen." : "Story chosen. It must be published before the slot can be scheduled.");
  };
  /* Adds a dated paid slot after the checks every slot needs. Returns the slot, or null after a message. */
  function addPlacement(sponsor, storyId, where, start, end, dealId) {
    var a = W.find("articles", storyId);
    if (!a) { W.toast("Choose a story."); return null; }
    if (a.status !== "published") { W.toast("Only a published story can fill a paid slot."); return null; }
    if (!start || !end || end < start) { W.toast("Check the dates: the end must be on or after the start."); return null; }
    var clash = (W.S.placements || []).filter(function (o) { var st = W.placementStatus(o); return (st === "active" || st === "scheduled") && o.start <= end && start <= o.end && (o.placement === "Both" || where === "Both" || o.placement === where); })[0];
    if (clash) { W.toast("That slot is taken by " + clash.sponsor + " from " + clash.start + " to " + clash.end + "."); return null; }
    var pl = { id: W.uid("pl"), sponsor: sponsor, storyId: storyId, placement: where, start: start, end: end, ended: false };
    if (dealId) pl.dealId = dealId;
    W.S.placements.push(pl);
    var sr = W.S.sponsors.filter(function (s) { return s.name === sponsor; })[0]; if (sr) sr.attached.push("Story: " + a.title);
    W.log("placement.add", pl.id, sponsor + " on " + where + " for " + a.title + ", " + start + " to " + end); W.save(); W.render(); W.toast("Slot added. It shows from " + start + " and is labelled Sponsored by " + sponsor + ".");
    return pl;
  }
  W.forms.placement = function (d) { addPlacement(d.sponsor, d.storyId, d.placement, d.start, d.end, d.dealId); };
  W.forms.dealSlot = function (d) {
    var a = W.find("arrangements", d.id); if (!a) return; if (!W.dealStory(a)) { W.toast("Choose the story first (step 3)."); return; }
    addPlacement(sponsorName(a), a.linkedArticle, d.placement, d.start, d.end, a.id);
  };
  W.act.plEnd = function (id) { var pl = W.find("placements", id); pl.ended = true; W.log("placement.end", id, "ended by staff"); W.save(); W.render(); W.toast("Ended. The label and the slot are gone; the story stays."); };
  W.act.dealCancel = function (id) { var a = W.find("arrangements", id); if (!money()) return; a.cancelled = true; (W.S.placements || []).forEach(function (pl) { if (pl.dealId === id) pl.ended = true; }); W.log("deal.cancel", id, "recorded; any refund is handled offline and recorded by an Administrator"); W.save(); W.render(); W.toast("Cancelled. The slot is ended; nothing is deleted."); };
  W.forms.sponsor2 = function (d) { W.S.sponsors.push({ id: W.uid("s"), name: d.name, link: d.link, logo: "Logo placeholder", attached: d.to === "Not yet" ? [] : [d.to] }); W.log("sponsor.add", d.name, d.to); W.save(); W.render(); };

  /* ---------- awards ---------- */
  /* Nominations (REQ-267): a nomination has a Stage (Nominated or Winner) and a Status (Live or Not live). Live nominations are public. */
  var STAGES = { nominated: "Nominated", winner: "Winner" };
  function profLabel(p) { return p.name + " (" + p.id + ")"; }
  function livePill(n) { return n.live ? '<span class="pill ok">Live</span>' : '<span class="pill gray">Not live</span>'; }
  /* The contact email is backend only (personal data): never shown publicly, never exported, never logged. */
  function mailLine(n) { return n.mailedAt ? '<p class="small muted">Nomination email sent on ' + e(n.mailedAt) + " by " + e(n.mailedBy || "staff") + ".</p>" : ""; }
  function mailBtn(n, cls) { return n.nomineeEmail ? W.btn(n.mailedAt ? "Send nomination email again" : "Send nomination email", "nomMail", n.id, cls || "alt") : W.btn("Send nomination email", "nomMail", n.id, cls || "alt", ' disabled title="Add a contact email first"') + ' <span class="small muted">No contact email on file.</span>'; }
  mini("/admin/nominations", "A28", "awards", function (par, q) {
    var rows = W.S.nominations.filter(function (n) { return (!q.c || n.category === q.c) && (!q.s || n.stage === q.s) && (!q.l || (n.live ? "1" : "0") === q.l); }), on = W.nomOpen(), F = W.S.flags.nominations;
    var sw = '<div class="card row between" id="nomSwitch"><div><h2>Nominations page: <span class="pill ' + (on ? "flag-on" : "flag-off") + '">' + (on ? "Open" : "Closed") + '</span></h2><p class="small muted">' + (on ? "Visitors see the nomination form at /nominate." : "Visitors see “Nominations are closed”, with a link to the nominees or winners once those are live.") + (F.ref ? " Last reason: " + e(F.ref) + "." : "") + '</p></div><div>' + W.btn(on ? "Close nominations" : "Open nominations", "nomSwitch", on ? "close" : "open", on ? "alt" : "") + "</div></div>";
    return '<div class="row between"><h1>Nominations</h1><div class="row">' + W.btn("Add profile to nominations", "addNomProfile", "", "alt") + "</div></div>" + sw +
      '<form class="filters" data-filter="#/admin/nominations">' + W.field("Category", "c", { type: "select", options: W.cats().map(function (c) { return c.name; }), blank: "Any", value: q.c }) + W.field("Stage", "s", { type: "select", options: Object.keys(STAGES).map(function (k) { return [k, STAGES[k]]; }), blank: "Any", value: q.s }) + W.field("Status", "l", { type: "select", options: [["1", "Live"], ["0", "Not live"]], blank: "Any", value: q.l }) + "</form>" +
      W.table(["Ref", "Nominee", "Category", "Stage", "Status", ""], rows.map(function (n) {
        var pr = n.profileId ? W.profile(n.profileId) : null;
        return [e(n.ref), (pr ? '<a href="#/admin/profiles/' + e(pr.id) + '">' + e(n.nominee) + "</a>" : e(n.nominee) + ' <span class="pill gray">no profile</span>'), e(n.category), W.stPill(STAGES[n.stage] || n.stage), livePill(n),
          W.btn(n.live ? "Make not live" : "Make live", "nomLive", n.id, "sm" + (n.live ? " alt" : "")) + " " + W.btn(n.stage === "winner" ? "Set Nominated" : "Set Winner", "nomStage", n.id, "sm alt") + " " + (n.nomineeEmail ? W.btn("Send email", "nomMail", n.id, "sm alt") : "") + ' <a class="btn sm alt" href="#/admin/nominations/' + e(n.id) + '">Open</a>'];
      }), "No nominations.");
  });
  W.act.nomSwitch = function (to) {
    W.openModal(to === "open" ? "Open nominations" : "Close nominations", '<form data-form="nomswitch" class="stack-s"><input type="hidden" name="to" value="' + e(to) + '"><p>The public Nominate page changes at once. This is recorded in the audit log with your name.</p>' + W.field("Reason (optional)", "reason") + '<button class="btn">' + (to === "open" ? "Open nominations" : "Close nominations") + "</button></form>");
  };
  W.forms.nomswitch = function (d) {
    var on = d.to === "open"; W.S.flags.nominations = { on: on, ref: d.reason || "" };
    W.log(on ? "nominations.open" : "nominations.close", "nominations", (d.reason || "no reason given") + " (" + W.persona() + ")");
    W.purge("/nominate", on ? "nominations opened" : "nominations closed"); W.ui.modal = null; W.save(); W.render(); W.toast(on ? "Nominations are open." : "Nominations are closed.");
  };
  W.act.addNomProfile = function () {
    var opts = W.S.profiles.map(function (p) { return '<option value="' + e(profLabel(p)) + '">' + e(p.type === "individual" ? "Person" : "Organisation") + (p.status === "published" ? "" : ", " + e(p.status)) + "</option>"; }).join("");
    W.openModal("Add profile to nominations", '<form data-form="nomprofile" class="stack-s"><div class="field"><label for="f-nprof">Profile <span class="muted" aria-hidden="true">*</span> <span class="hint" id="f-nprof-h"> Type a name and pick from the list. If the nominee has no profile, create the profile first.</span></label><input id="f-nprof" name="profile" list="nprof-list" required autocomplete="off" aria-describedby="f-nprof-h"><datalist id="nprof-list">' + opts + "</datalist></div>" +
      W.field("Category", "category", { type: "select", options: W.cats().map(function (c) { return c.name; }), req: true }) + W.field("Contact email (optional)", "email", { type: "email", hint: "Staff only. Never shown publicly. Used to send the nomination email." }) + W.field("Note (optional)", "note", { type: "textarea" }) + '<button class="btn">Add nomination</button></form>');
  };
  W.forms.nomprofile = function (d) {
    var m = /\(([^()]+)\)\s*$/.exec(d.profile || ""), p = m && W.profile(m[1]);
    if (!p) { W.toast("Pick a profile from the list. If there is none, create the profile first."); return; }
    var ref = "NOM-" + ("000" + (W.S.nominations.length + 1)).slice(-4);
    W.S.nominations.push({ id: W.uid("n"), ref: ref, nominator: "Staff (" + W.persona() + ")", nominatorEmail: "", verified: true, nominee: p.name, nomineeType: p.type === "individual" ? "individual" : "business", nomineeEmail: (d.email || "").trim(), category: d.category, evidence: d.note || "Added by staff.", stage: "nominated", live: false, profileId: p.id });
    W.log("nomination.add", ref, p.name + " in " + d.category); W.ui.modal = null; W.save(); W.render(); W.toast("Added " + ref + ". It is Not live until you make it live.");
  };
  mini("/admin/nominations/:id", "A29", "awards", function (par) {
    var n = W.find("nominations", par.id); if (!n) return "<h1>Not found</h1>"; var pr = n.profileId ? W.profile(n.profileId) : null;
    var link = pr ? '<p>Profile: <a href="#/admin/profiles/' + e(pr.id) + '">' + e(pr.name) + "</a></p>" : '<form data-form="nomlink" class="row"><input type="hidden" name="id" value="' + e(n.id) + '">' + W.field("Link a profile", "pid", { type: "select", options: W.S.profiles.map(function (p) { return [p.id, p.name]; }), blank: "Choose a profile" }) + '<button class="btn sm">Link</button></form>';
    return '<p><a href="#/admin/nominations">← Nominations</a></p>' + head(n.ref + ": " + n.nominee, e(n.category)) +
      '<div class="grid side"><div class="stack"><form class="card stack-s" data-form="nomstage"><input type="hidden" name="id" value="' + e(n.id) + '"><h2>Stage and status</h2>' + W.field("Stage", "stage", { type: "select", options: Object.keys(STAGES).map(function (k) { return [k, STAGES[k]]; }), value: n.stage }) + W.field("Status", "live", { type: "select", options: [["1", "Live"], ["0", "Not live"]], value: n.live ? "1" : "0" }) + '<button class="btn">Save</button><p class="small muted">Judging happens outside the site. A Live nomination is public: a Nominated one on the nominees page, a Winner on the winners page. Your name is recorded and the change is audited (REQ-202).</p></form>' +
      '<div class="card stack-s"><h2>Details</h2><p>Nominator: ' + e(n.nominator) + " (" + (n.verified ? "email verified" : "unverified") + ")</p><p>Evidence: " + e(n.evidence) + "</p>" + (n.coi ? "<p>Conflict declared by the nominator: " + e(n.coi) + "</p>" : "") + link +
      "<p>Contact email (staff only): " + (n.nomineeEmail ? e(n.nomineeEmail) : '<span class="muted">none</span>') + "</p>" +
      '<div class="row">' + mailBtn(n) + (n.stage === "winner" ? " " + (n.notified ? '<span class="pill ok">winner emailed</span>' : W.btn("Prepare winner email", "winMail", n.id, "alt", n.nomineeEmail ? "" : ' disabled title="Add a contact email first"')) : "") + " " + W.btn("Remove nomination", "nomRemove", n.id, "alt bad") + "</div>" + mailLine(n) + "</div></div>" +
      '<div class="stack"><div class="infobox"><strong>No commercial data here.</strong> No payment, subscription or sponsorship record reaches nominations or winners (REQ-199).</div>' + W.notesBox(n.id) + "</div></div>";
  });
  W.forms.nomstage = function (d) {
    var n = W.find("nominations", d.id), me = W.persona(); if (!n || !STAGES[d.stage]) return;
    n.stage = d.stage; n.live = d.live === "1"; n.stageBy = me;
    W.purge("/awards/nominee/" + n.ref, "nomination changed");
    W.log("nomination.stage", n.ref, STAGES[n.stage] + ", " + (n.live ? "Live" : "Not live") + " by " + me); W.save(); W.render(); W.toast("Saved: " + STAGES[n.stage] + ", " + (n.live ? "Live" : "Not live") + ".");
  };
  W.act.nomLive = function (id) { var n = W.find("nominations", id); n.live = !n.live; n.stageBy = W.persona(); W.purge("/awards/nominee/" + n.ref, "nomination changed"); W.log(n.live ? "nomination.live" : "nomination.notlive", n.ref, (n.live ? "Live" : "Not live") + " by " + W.persona()); W.save(); W.render(); W.toast(n.live ? "Live. It is public now." : "Not live. It is no longer public."); };
  W.act.nomStage = function (id) { var n = W.find("nominations", id); n.stage = n.stage === "winner" ? "nominated" : "winner"; n.stageBy = W.persona(); W.purge("/awards/nominee/" + n.ref, "nomination changed"); W.log("nomination.stage", n.ref, STAGES[n.stage] + " by " + W.persona()); W.save(); W.render(); W.toast("Stage: " + STAGES[n.stage] + "."); };
  W.act.nomRemove = function (id) { var n = W.find("nominations", id); W.openModal("Remove " + n.ref + "?", "<p>This removes the nomination for " + e(n.nominee) + " in " + e(n.category) + '. It is no longer public. The removal is recorded in the audit log.</p><div class="row" style="margin-top:1rem"><button class="btn bad" data-act="nomRemoveYes" data-arg="' + e(id) + '">Remove</button><button class="btn alt" data-act="closeModal">Cancel</button></div>'); };
  W.act.nomRemoveYes = function (id) { var n = W.find("nominations", id), i = W.S.nominations.indexOf(n); if (i < 0) return; W.S.nominations.splice(i, 1); W.purge("/awards/nominee/" + n.ref, "nomination removed"); W.log("nomination.remove", n.ref, n.nominee + " in " + n.category + " by " + W.persona()); W.ui.modal = null; W.save(); W.go("#/admin/nominations"); W.toast("Removed."); };
  W.forms.nomlink = function (d) { var n = W.find("nominations", d.id), p = d.pid && W.profile(d.pid); if (!n || !p) { W.toast("Choose a profile."); return; } n.profileId = p.id; W.log("nomination.link", n.ref, p.name); W.save(); W.render(); };
  /* The address is used for the send and is never written to the log or shown outside the staff screens. */
  W.act.nomMail = function (id) {
    var n = W.find("nominations", id); if (!n || !n.nomineeEmail) { W.toast("Add a contact email first."); return; }
    W.mail(n.nomineeEmail, "You have been nominated", "You have been put forward for " + n.category + " in the awards. Nothing is public until our team decides.", "");
    n.mailedAt = W.TODAY; n.mailedBy = W.persona(); W.log("nomination.email", n.ref, "nomination email sent by " + W.persona()); W.save(); W.render(); W.toast("Nomination email sent.");
  };
  W.act.winMail = function (id) { var n = W.find("nominations", id); if (!n || !n.nomineeEmail) { W.toast("Add a contact email first."); return; } n.notified = true; W.mail(n.nomineeEmail, "Congratulations", "You are a winner in " + n.category + ". Private until our team makes it live.", ""); W.log("winner.email", n.ref, "winner email prepared by " + W.persona()); W.save(); W.render(); };
  /* Award categories (REQ-266): the only award setting in the admin. Eligibility and judging text are page copy, edited in the page editor (REQ-260). */
  function catCount(name) { return W.S.nominations.filter(function (n) { return n.category === name; }).length; }
  mini("/admin/awards", "A30", "awards", function () {
    var A = W.S.awards;
    return head("Award categories", "Add, rename, reorder and remove the categories shown on the Awards and Nominate pages and in the nomination form (REQ-266). Sample values; the Venture CEO decides the final list (N8).") +
      A.groups.map(function (g, gi) {
        return '<div class="card stack-s"><h2>' + e(g.name) + "</h2>" + (g.cats.length ? W.table(["Category", "Nominations", ""], g.cats.map(function (c, ci) {
          var n = catCount(c);
          return ['<form data-form="catrename" class="row"><input type="hidden" name="g" value="' + gi + '"><input type="hidden" name="c" value="' + ci + '"><label class="sr" for="cr' + gi + "-" + ci + '">Category name</label><input id="cr' + gi + "-" + ci + '" name="name" value="' + e(c) + '" style="flex:1" required><button class="btn sm alt">Rename</button></form>', String(n),
            W.btn("Up", "catMove", gi + "|" + ci + "|-1", "sm alt", ci === 0 ? " disabled" : "") + " " + W.btn("Down", "catMove", gi + "|" + ci + "|1", "sm alt", ci === g.cats.length - 1 ? " disabled" : "") + " " + (n ? '<span class="small muted">Has nominations, cannot be removed</span>' : W.btn("Remove", "catRemove", gi + "|" + ci, "sm alt bad"))];
        }), "") : "<p class=\"muted\">No categories in this group.</p>") +
        '<form data-form="catadd" class="row"><input type="hidden" name="g" value="' + gi + '"><label class="sr" for="ca' + gi + '">New category in ' + e(g.name) + '</label><input id="ca' + gi + '" name="name" placeholder="New category name" style="flex:1" required><button class="btn sm">Add category</button></form></div>';
      }).join("") + '<p class="small muted">' + W.cats().length + " categories in " + A.groups.length + " groups. The five groups are fixed brand groups.</p>";
  });
  function catName(v) { return String(v || "").replace(/\s+/g, " ").trim(); }
  W.forms.catadd = function (d) {
    var A = W.S.awards, g = A.groups[Number(d.g)], nm = catName(d.name); if (!g || !nm) return;
    if (W.cats().some(function (c) { return c.name.toLowerCase() === nm.toLowerCase(); })) { W.toast("That category already exists."); return; }
    g.cats.push(nm); W.log("category.add", nm, g.name); W.purge("/awards", "categories changed"); W.save(); W.render(); W.toast("Added.");
  };
  W.forms.catrename = function (d) {
    var A = W.S.awards, g = A.groups[Number(d.g)], old = g && g.cats[Number(d.c)], nm = catName(d.name); if (!old || !nm || nm === old) return;
    if (W.cats().some(function (c) { return c.name.toLowerCase() === nm.toLowerCase() && c.name !== old; })) { W.toast("That category already exists."); return; }
    g.cats[Number(d.c)] = nm; W.S.nominations.forEach(function (n) { if (n.category === old) n.category = nm; });
    W.log("category.rename", old, "to " + nm); W.purge("/awards", "categories changed"); W.save(); W.render(); W.toast("Renamed.");
  };
  W.act.catMove = function (a) {
    var s = a.split("|"), g = W.S.awards.groups[Number(s[0])], i = Number(s[1]), j = i + Number(s[2]); if (!g || j < 0 || j >= g.cats.length) return;
    var t = g.cats[i]; g.cats[i] = g.cats[j]; g.cats[j] = t; W.log("category.move", t, g.name); W.save(); W.render();
  };
  W.act.catRemove = function (a) {
    var s = a.split("|"), g = W.S.awards.groups[Number(s[0])], name = g && g.cats[Number(s[1])]; if (!name) return;
    if (catCount(name)) { W.toast("This category has nominations and cannot be removed."); return; }
    g.cats.splice(Number(s[1]), 1); W.log("category.remove", name, g.name); W.purge("/awards", "categories changed"); W.save(); W.render(); W.toast("Removed.");
  };

  /* ---------- platform ---------- */
  capRoute("audit_log", "/admin/audit", "A33", function () {
    var A = W.S.audit, bad = -1, prev = "00000000";
    return head("Audit log", "Append-only. Each row stores the hash of the one before, so tampering is detectable (REQ-051, 188). Exported nightly off-host.") + W.table(["#", "When", "Actor", "Action", "Target", "Detail", "Hash"], A.slice().reverse().slice(0, 60).map(function (a) { return [a.n, e(a.at), e(a.actor), e(a.action), e(a.target), e(a.detail), '<span class="mono">' + e(a.hash) + "</span>"]; }), "No actions yet. Do something as staff.") + "<p>" + W.btn("Verify chain", "verifyChain", "", "alt") + "</p>";
  });
  W.act.verifyChain = function () { var prev = "00000000", ok = true; W.S.audit.forEach(function (a) { if (a.prev !== prev) ok = false; prev = a.hash; }); W.toast(ok ? "Chain intact: " + W.S.audit.length + " rows verified." : "CHAIN BROKEN: alert raised."); };
  mini("/admin/purges", "A48", "purges", function () {
    return head("Purge and unpublish log", "Each unpublish (single or bulk), removal and decline queues a CDN purge (REQ-209). The origin already answers 410 + noindex.") + W.table(["URL", "Why", "Date", "Status"], W.S.purges.map(function (p) { return ['<span class="mono">' + e(p.url) + "</span>", e(p.why), e(p.at), e(p.status)]; }), "Nothing purged yet. Unpublish a profile to see an entry.");
  });
  mini("/admin/emails", "A40", "emails", function () {
    return head("Email log", "Every notification the system sends (REQ-094). Provider bounces will be recorded here.") + W.table(["Date", "To", "Subject", "Link"], W.S.emails.map(function (m) { return [e(m.at), e(m.to), e(m.subject), m.link ? '<a href="' + e(m.link) + '">open</a>' : ""]; }), "No emails sent yet.");
  });
  capRoute("settings", "/admin/thresholds", "A47", function () {
    var t = W.S.thresholds;
    return head("Sprint thresholds", "The targets the dashboard measures progress against. Either one is enough.") + '<form class="card stack-s" data-form="sprintsw"><h2>Show sprint tracking (REQ-272)</h2><p class="small muted">In WordPress this is a theme setting (Theme settings > Sprint tracking). Off hides the sprint target card on the Administrator dashboard; nothing is deleted and the thresholds below are kept.</p>' + W.field("Show sprint tracking", "on", { type: "checkbox", value: W.S.sprintOn !== false }) + '<button class="btn alt">Save switch</button></form><form class="card stack-s" data-form="thresholds">' + W.field("Paid subscriptions needed", "subs", { type: "number", value: t.subs }) + W.field("Confirmed revenue needed (NGN)", "rev", { type: "number", value: t.revenue / 100 }) + '<button class="btn">Save</button></form>';
  });
  W.forms.sprintsw = function (d) { W.S.sprintOn = !!d.on; W.log("settings.sprint_tracking", "theme setting", W.S.sprintOn ? "on" : "off"); W.save(); W.toast(W.S.sprintOn ? "Sprint tracking is on." : "Sprint tracking is off."); W.render(); };
  W.forms.thresholds = function (d) { W.S.thresholds = { subs: Number(d.subs), revenue: Math.round(Number(d.rev) * 100) }; W.log("settings.save", "thresholds", ""); W.save(); W.toast("Saved."); W.render(); };

})();
