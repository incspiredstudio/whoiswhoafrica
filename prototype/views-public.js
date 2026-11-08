/* Public site screens (visitor). */
(function () {
  var W = window.W, e = W.esc;
  function page(html) { return '<div class="wrap section stack">' + html + "</div>"; }
  var LIST = { people: ["individual", "People", "#/people", "Discover People"], businesses: ["business", "Businesses", "#/businesses", "Discover Businesses"], institutions: ["institution", "Institutions", "#/institutions", "Discover Institutions"] };

  W.card = function (p) {
    var sp = W.sponsorOf(p);
    return '<a class="card link" href="#/profile/' + e(p.slug) + '"><div class="row" style="flex-wrap:nowrap">' + W.ph(p.type === "individual" ? "Photo" : "Logo", "avatar") + '<div><h3 style="margin:0">' + e(p.name) + '</h3><div class="muted small">' + e(p.title || "") + (p.org ? " · " + e(p.org) : "") + "</div></div></div>" +
      '<div class="tagrow">' + W.trustPill(p) + W.spPill(sp) + '</div><div class="small muted">' + e(p.industry) + " · " + e(p.city) + "</div></a>";
  };
  W.articleCard = function (a) {
    var sp = W.sponsorOfArticle(a);
    return '<a class="card link" href="#/stories/' + e(a.slug) + '">' + W.ph("Hero image", "") + '<div class="tagrow"><span class="pill gray">' + e(a.type) + "</span>" + W.spPill(sp) + '</div><h3>' + e(a.title) + '</h3><div class="small muted">' + e(a.industry) + " · " + e(a.date) + "</div></a>";
  };
  function pager(base, q, total, per) {
    var pages = Math.max(1, Math.ceil(total / per)), cur = Math.min(pages, Number(q.page) || 1);
    if (pages === 1) return "";
    var out = '<nav class="pager" aria-label="Pages">';
    for (var i = 1; i <= pages; i++) { var nq = {}; Object.keys(q).forEach(function (k) { nq[k] = q[k]; }); nq.page = i === 1 ? "" : i; out += '<a class="btn sm ' + (i === cur ? "" : "alt") + '" href="' + base + W.qs(nq) + '"' + (i === cur ? ' aria-current="page"' : "") + ">" + i + "</a>"; }
    return out + "</nav>";
  }
  function filterForm(base, q, extra) {
    return '<form class="filters" data-filter="' + base + '" role="search" aria-label="Filter"><div class="field" style="margin:0"><label for="fq">Name or keyword</label><input id="fq" name="q" value="' + e(q.q || "") + '"></div>' +
      W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "All industries", value: q.industry }) +
      W.field("City", "city", { type: "select", options: W.LISTS.cities, blank: "All cities", value: q.city }) +
      W.field("Status", "trust", { type: "select", options: [["claimed", "Claimed"], ["verified", "Verified"], ["featured", "Featured"], ["unclaimed", "Unclaimed"]], blank: "Any status", value: q.trust }) + (extra || "") +
      '<div><button class="btn" type="submit">Apply</button> <a class="btn alt" href="' + base + '">Clear</a></div></form>';
  }
  function directory(key) {
    return function (params, q) {
      var L = LIST[key], per = 6;
      var rows = W.publicProfiles().filter(function (p) {
        return p.type === L[0] && (!q.industry || p.industry === q.industry) && (!q.city || p.city === q.city) && (!q.trust || p.trust === q.trust) && (!q.profession || p.profession === q.profession) && (!q.orgType || p.orgType === q.orgType) &&
          (!q.q || (p.name + " " + (p.org || "") + " " + p.title + " " + p.bio).toLowerCase().indexOf(q.q.toLowerCase()) >= 0);
      }).sort(function (a, b) { return a.name < b.name ? -1 : 1; });
      var cur = Math.min(Math.max(1, Math.ceil(rows.length / per)), Number(q.page) || 1), slice = rows.slice((cur - 1) * per, cur * per);
      var killNote = key === "people" && W.S.kill.on ? '<p class="warnbox">Unclaimed individual profiles are hidden because the kill switch is on (REQ-193).</p>' : "";
      return page('<h1>' + e(L[3]) + "</h1><p class=\"muted\">Reviewed profiles only. Each shows one status: Unclaimed, Claimed, Verified or Featured. <a href=\"#/how-we-compile\">What the labels mean</a>.</p>" + killNote + filterForm(L[2], q, key === "people" ? W.field("Profession", "profession", { type: "select", options: W.LISTS.professions, blank: "Any profession", value: q.profession }) : key === "institutions" ? W.field("Organisation type", "orgType", { type: "select", options: W.LISTS.orgTypes, blank: "Any type", value: q.orgType }) : W.field("Country", "country", { type: "select", options: ["Nigeria"], value: "Nigeria", attrs: "disabled" })) +
        '<p role="status">' + rows.length + " profile" + (rows.length === 1 ? "" : "s") + " found.</p>" +
        (slice.length ? '<div class="grid c3">' + slice.map(W.card).join("") + "</div>" : W.empty("No matching profiles", "Try removing a filter or searching a different word.", '<a class="btn" href="' + L[2] + '">Clear filters</a>')) + pager(L[2], q, rows.length, per));
    };
  }
  W.route("/people", "V03", directory("people")); W.route("/businesses", "V04", directory("businesses")); W.route("/institutions", "V05", directory("institutions"));

  /* ---------- home ---------- */
  W.route("/", "V01", function () {
    var feat = W.publicProfiles().filter(function (p) { return p.trust === "featured" || p.trust === "verified"; }).slice(0, 3);
    var arts = W.publicArticles().slice(0, 3);
    var nomState = W.nomOpen() ? '<p><span class="pill ok">Nominations are open</span></p><a class="btn" href="#/nominate">Nominate someone</a>' : '<p><span class="pill gray">Nominations open soon</span></p><a class="btn alt" href="#/nominate">Get notified</a>';
    return '<section class="hero-block"><div class="wrap grid side"><div class="stack"><h1>' + e(W.brand.name) + ": who is who in Africa.</h1><p style=\"font-size:1.15rem\">A directory of reviewed profiles of people, businesses and institutions, with editorial stories and a recognition programme. <em>" + e(W.brand.tagline) + "</em></p>" +
      '<div class="row"><a class="btn" href="#/subscribe">Get listed or featured</a><a class="btn alt" href="#/people">Discover people</a><a class="btn alt" href="#/nominate">' + (W.nomOpen() ? "Nominate" : "Nominate (opens soon)") + "</a></div></div>" + W.ph("3D hero element, built later (see design notes)", "hero") + "</div></section>" +
      '<section class="section alt"><div class="wrap"><h2>What it is, who it is for, how to get in</h2><div class="grid c3"><div class="card"><h3>What it is</h3><p>A reviewed directory. Nothing is public until a person on our team has approved it.</p></div><div class="card"><h3>Who it is for</h3><p>Professionals, businesses and institutions that want an accurate, credible public profile.</p></div><div class="card"><h3>How to get in</h3><p>Be compiled from public sources, claim a profile, enquire about being listed or featured, or be nominated.</p></div></div></div></section>' +
      '<section class="section"><div class="wrap"><h2>Four ways in</h2><div class="grid c4"><a class="card link" href="#/subscribe"><h3>Get listed</h3><p class="muted small">Send an enquiry. We agree terms offline.</p></a><a class="card link" href="#/businesses"><h3>Claim a profile</h3><p class="muted small">Find your organisation and claim it.</p></a><a class="card link" href="#/stories"><h3>Be featured</h3><p class="muted small">Editorial stories and spotlights.</p></a><a class="card link" href="#/awards"><h3>Be recognised</h3><p class="muted small">The Maiden Edition awards.</p></a></div></div></section>' +
      '<section class="section"><div class="wrap"><h2>Statuses and labels</h2><div class="row"><span><span class="pill t-unclaimed">Unclaimed</span> compiled from public sources</span><span><span class="pill t-claimed">Claimed</span> owner proved identity</span><span><span class="pill t-verified">✓ Verified</span> checked by our team</span><span><span class="pill t-featured">★ Featured</span> editors’ choice</span><span><span class="pill sponsored">Sponsored</span> paid disclosure, never a status</span></div><p class="small muted">Verified and Featured can never be bought. <a href="#/how-we-compile">How we compile profiles</a></p></div></section>' +
      '<section class="section alt"><div class="wrap"><div class="row between"><h2>Featured and verified</h2><a href="#/people">See all people</a></div>' + (feat.length ? '<div class="grid c3">' + feat.map(W.card).join("") + "</div>" : W.empty("No featured profiles yet", "Editors have not featured anyone.")) + "</div></section>" +
      '<section class="section"><div class="wrap"><div class="row between"><h2>Latest stories</h2><a href="#/stories">All stories</a></div><div class="grid c3">' + arts.map(W.articleCard).join("") + "</div></div></section>" +
      '<section class="section alt"><div class="wrap"><h2>' + e(W.brand.edition) + "</h2><p>Categories, eligibility and the judging process are published on the Awards page.</p>" + nomState + ' <a class="btn alt" href="#/awards">Awards overview</a></div></section>';
  });

  W.route("/about", "V02", function () {
    return page("<h1>About " + e(W.brand.name) + "</h1><p>Sample about copy. " + e(W.brand.name) + " is a directory and editorial platform. Mission, vision and values text is supplied by Brand (N1).</p><div class=\"grid c3\"><div class=\"card\"><h3>Influence</h3><p>Sample text.</p></div><div class=\"card\"><h3>Achievement</h3><p>Sample text.</p></div><div class=\"card\"><h3>Legacy</h3><p>Sample text.</p></div></div><p><a class=\"btn\" href=\"#/how-we-compile\">How we compile profiles</a> <a class=\"btn alt\" href=\"#/contact\">Contact us</a></p>");
  });

  /* ---------- industries, search ---------- */
  W.route("/industries", "V06", function () {
    return page("<h1>Industries</h1><div class=\"grid c3\">" + W.LISTS.industries.map(function (i) {
      var n = W.publicProfiles().filter(function (p) { return p.industry === i; }).length;
      return '<a class="card link" href="#/industries/' + encodeURIComponent(i) + '"><h3>' + e(i) + '</h3><p class="muted small">' + n + " reviewed profile" + (n === 1 ? "" : "s") + "</p></a>";
    }).join("") + "</div>");
  });
  W.route("/industries/:name", "V07", function (p) {
    var ps = W.publicProfiles().filter(function (x) { return x.industry === p.name; }), as = W.publicArticles().filter(function (a) { return a.industry === p.name; });
    return page(W.crumbs([["Industries", "#/industries"], [p.name]]) + "<h1>" + e(p.name) + "</h1><h2>Profiles</h2>" + (ps.length ? '<div class="grid c3">' + ps.map(W.card).join("") + "</div>" : W.empty("No profiles in this industry yet", "Check back soon, or browse all industries.", '<a class="btn" href="#/industries">All industries</a>')) + "<h2>Stories</h2>" + (as.length ? '<div class="grid c3">' + as.map(W.articleCard).join("") + "</div>" : "<p class=\"muted\">No stories yet.</p>"));
  });
  W.route("/search", "V08", function (p, q) {
    var t = (q.q || "").toLowerCase().trim();
    var ps = !t ? [] : W.publicProfiles().filter(function (x) { return (x.name + " " + (x.org || "") + " " + x.title + " " + x.industry + " " + x.bio).toLowerCase().indexOf(t) >= 0; }).sort(function (a, b) { return (b.name.toLowerCase().indexOf(t) >= 0) - (a.name.toLowerCase().indexOf(t) >= 0); });
    var as = !t ? [] : W.publicArticles().filter(function (a) { return (a.title + a.body).toLowerCase().indexOf(t) >= 0; });
    return page("<h1>Search</h1><form class=\"search\" role=\"search\" data-form=\"search\"><label class=\"sr\" for=\"sq\">Search</label><input id=\"sq\" name=\"q\" value=\"" + e(q.q || "") + "\" type=\"search\"><button class=\"btn\">Search</button></form>" +
      (!t ? W.empty("Type a name, organisation or keyword", "Results show reviewed profiles and stories only.") : '<p role="status">' + (ps.length + as.length) + " results for “" + e(q.q) + "” (ranked: name matches first).</p>" +
        (ps.length || as.length ? (ps.length ? "<h2>Profiles</h2><div class=\"grid c3\">" + ps.map(W.card).join("") + "</div>" : "") + (as.length ? "<h2>Stories</h2><div class=\"grid c3\">" + as.map(W.articleCard).join("") + "</div>" : "") : W.empty("No results", "Nothing public matches. Unreviewed, removed and hidden profiles never appear here (REQ-208, 209).", '<a class="btn" href="#/people">Browse the directory</a>'))));
  });

  /* ---------- profile ---------- */
  function extra(p) {
    if (p.type === "individual") {
      return (p.source === "subscriber" ? '<h2>Career history</h2><p class="muted">Sample career history entries supplied by the owner.</p><h2>Education</h2><p class="muted">Sample education entries.</p><h2>Media appearances</h2><p class="muted">Sample media appearances (links only).</p>' : "") + (p.achievements ? "<h2>Awards and achievements</h2><p>" + e(p.achievements) + "</p>" : "");
    }
    var out = "";
    if (p.type === "institution") out += "<h2>Mandate</h2><p>" + e(p.mandate || "") + "</p><h2>History</h2><p>" + e(p.longHistory || "") + "</p>";
    if (p.leadership && p.leadership.length) out += "<h2>Leadership</h2><ul>" + p.leadership.map(function (l) { return "<li>" + e(l) + "</li>"; }).join("") + "</ul>";
    if (p.products) out += "<h2>Products and services</h2><p>" + e(p.products) + "</p>";
    if (p.achievements) out += "<h2>Achievements</h2><p>" + e(p.achievements) + "</p>";
    return out;
  }
  function profileBody(p, staffPrev) {
    var sp = W.sponsorOf(p), arts = W.publicArticles().filter(function (a) { return a.profiles.indexOf(p.id) >= 0; });
    var nom = W.S.nominations.filter(function (n) { return n.profileId === p.id && ["shortlisted", "finalist", "winner"].indexOf(n.status) >= 0 && W.ok("judging"); })[0];
    var enq = p.enquiries ? W.btn("Send an enquiry", "enquiry", p.id) : '<p class="muted small">The owner has switched enquiries off.</p>';
    var types = { individual: "Person", business: "Business", institution: "Institution" };
    return (staffPrev ? '<div class="banner staffprev"><div class="wrap">Staff preview. This profile is <strong>' + e(p.status) + "</strong> and is NOT public. Visitors would see " + (W.vis(p) === "gone" ? "410 Gone" : "404") + ".</div></div>" : "") +
      page(W.crumbs([[LIST[p.type === "individual" ? "people" : p.type === "business" ? "businesses" : "institutions"][3], LIST[p.type === "individual" ? "people" : p.type === "business" ? "businesses" : "institutions"][2]], [p.name]]) +
        '<div class="grid side"><div class="stack"><div class="row" style="flex-wrap:nowrap;align-items:flex-start">' + W.ph(p.type === "individual" ? "Photo (owner-supplied or licensed only)" : "Logo", "avatar") + "<div><h1 style=\"margin-bottom:4px\">" + e(p.name) + '</h1><div class="muted">' + e(p.title || "") + (p.org ? " · " + e(p.org) : "") + "</div><div class=\"tagrow\">" + W.trustPill(p) + W.spPill(sp) + '<span class="pill gray">' + types[p.type] + "</span></div></div></div>" +
        (p.trust === "unclaimed" ? '<div class="infobox"><strong>Unclaimed.</strong> ' + e(W.brand.name) + ' compiled this profile from public sources. Last reviewed ' + e(p.lastReviewed || "n/a") + '. <a href="#/how-we-compile">How we compile profiles</a></div>' : "") +
        (sp ? '<div class="warnbox"><strong>Sponsored.</strong> ' + e(sp) + " paid for this placement. Sponsorship does not affect verification, status or awards.</div>" : "") +
        "<h2>About</h2><p>" + e(p.bio) + "</p>" + extra(p) +
        "<h2>Key facts and sources</h2><table class=\"facts\"><tbody>" + p.facts.filter(function (f) { return f.src; }).map(function (f) { return "<tr><th scope=\"row\">" + e(f.label) + "</th><td>" + e(f.value) + '<span class="src">Source: ' + e(f.src) + " · retrieved " + e(f.retrieved) + "</span></td></tr>"; }).join("") + "</tbody></table>" +
        (p.website && p.public.website ? '<p>Website: <a href="' + e(p.website) + '" rel="nofollow noopener">' + e(p.website) + "</a></p>" : "") +
        (nom ? '<div class="okbox"><strong>Shortlisted:</strong> ' + e(nom.category) + ' <a href="#/awards/nominee/' + e(nom.ref) + '">Nominee page</a></div>' : "") +
        (arts.length ? "<h2>Related stories</h2><div class=\"grid c2\">" + arts.map(W.articleCard).join("") + "</div>" : "") +
        '<p class="small muted">Last reviewed: ' + e(p.lastReviewed || "n/a") + ". Corrections made after publication are logged internally (REQ-164).</p></div>" +
        '<aside class="stack" aria-label="Actions"><div class="card stack-s"><h2>Contact</h2><p class="small muted">Contact details are not shown. Enquiries are relayed.</p>' + enq + "</div>" +
        '<div class="card stack-s"><h2>This profile</h2>' + (p.trust === "unclaimed" ? '<a class="btn" href="#/claim/' + e(p.slug) + '">Claim this profile</a>' : "") + '<a class="btn alt" href="#/correct/' + e(p.slug) + '">Suggest a correction</a><a class="btn alt" href="#/remove/' + e(p.slug) + '">Request removal</a>' + W.btn("Report this profile", "report", p.id, "alt") + '<p class="small muted">No account or payment is needed to correct, remove or report.</p></div>' +
        '<div class="card"><h2>Share preview</h2><div class="ph" style="min-height:3rem">Share image</div><strong>' + e(p.name) + (sp ? " · Sponsored" : "") + '</strong><div class="small muted">' + e(W.brand.name) + " · " + e(p.title || p.industry) + '</div><p class="small muted">Open Graph and schema.org ' + (p.type === "individual" ? "Person" : "Organization") + " markup in the build (REQ-006, 082).</p></div></aside></div>");
  }
  W.route("/profile/:slug", "V09", function (par, q, m) {
    var p = W.bySlug(par.slug); var inv = { individual: "V09", business: "V10", institution: "V11" };
    var v = W.vis(p), staff = ["reviewer", "editor", "sales", "finance", "eic", "awards", "admin"].indexOf(W.S.role) >= 0;
    if (!p) { m.inv = "V13"; return W.notFound.fn(); }
    if (p.slug !== par.slug) return { redirect: "#/profile/" + p.slug };
    if (v === "public") { m.inv = inv[p.type]; return profileBody(p, false); }
    if (staff) { m.inv = inv[p.type]; return profileBody(p, true); }
    if (v === "gone") { m.inv = "V12"; return page('<h1>410 · This profile has been removed</h1><p>This page is no longer available and will not return. Search engines are asked not to index it (noindex).</p><p class="small muted">Wireframe: the server answers 410 Gone with an X-Robots-Tag: noindex header, the URL is dropped from the sitemap and search, and the CDN copy is purged (REQ-209).</p><p><a class="btn" href="#/people">Discover people</a> <a class="btn alt" href="#/complaints">Questions about removals</a></p>'); }
    m.inv = "V13"; return W.notFound.fn();
  });

  /* ---------- profile actions ---------- */
  W.act.enquiry = function (id) {
    var p = W.profile(id);
    if (W.S.rate.enquiry >= 3) { W.openModal("Too many enquiries", '<div class="errbox"><p>You have sent several enquiries in a short time. Please try again later.</p><p class="small">Wireframe: rate limit per IP and per form (REQ-018, 122). Use Reset to clear.</p></div>'); return; }
    W.openModal("Enquire: " + p.name, '<form data-form="enquiry" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Your name", "name", { req: true }) + W.field("Your email", "email", { type: "email", req: true, hint: "Only used to relay the reply." }) + W.field("Message", "message", { type: "textarea", req: true }) + W.bot() + '<p class="small muted">The owner’s email and phone are never shown. <a href="#/privacy">Privacy notice</a></p><button class="btn">Send enquiry</button></form>');
  };
  W.forms.enquiry = function (d) {
    var p = W.profile(d.id); W.S.rate.enquiry++;
    W.S.enquiries.unshift({ id: W.uid("e"), profileId: d.id, name: d.name, email: d.email, message: d.message, at: W.TODAY, read: false });
    W.mail("Staff inbox", "New enquiry via " + W.brand.name + " for " + p.name, "From " + d.name + ". " + (p.trust === "unclaimed" ? "Unclaimed profile: staff keep it." : "Staff forward it to the contact address held for this profile.") + " The visitor's email is shown to staff only.", "#/admin/inbox");
    W.save(); W.closeModal(); W.render(); W.toast("Enquiry sent to the team. They pass it on or reply by email.");
  };
  W.act.report = function (id) {
    W.openModal("Report this profile", '<form data-form="report" class="stack-s"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Reason", "reason", { type: "select", options: ["Inaccurate information", "Defamatory or harmful", "Privacy concern", "Copyright", "Other"], req: true }) + W.field("Details", "detail", { type: "textarea", req: true }) + W.bot() + '<button class="btn">Send report</button></form>');
  };
  W.forms.report = function (d) {
    W.S.reports.unshift({ id: W.uid("r"), profileId: d.id, reason: d.reason, detail: d.detail, at: W.TODAY, status: "open", owner: "" });
    W.save(); W.closeModal(); W.toast("Report received. It enters the staff abuse queue.");
  };
  var NEEDS = ["Correct a fact", "Add a right-of-reply note", "Change which contact details are public", "Switch enquiries on or off", "Send me the view count or the sources used"];
  var PARTS = [["name", "Name"], ["title", "Title"], ["org", "Organisation"], ["achievements", "Achievements and awards"], ["bio", "Biography"], ["other", "Something else"]];
  W.route("/correct/:slug", "V39", function (par) {
    var p = W.bySlug(par.slug); if (!p) return W.notFound.fn();
    return page(W.crumbs([[p.name, "#/profile/" + p.slug], ["Correction request"]]) + "<h1>Correction request</h1><p>No account, login or payment. Staff handle every request in the admin through the usual review (REQ-232). We acknowledge within 1 business day and resolve within 7 days (REQ-158). Owners use this same form to ask for changes, to set which contact details show, to switch enquiries on or off, or to get the view count and sources (REQ-236).</p><form data-form=\"correct\" class=\"card stack-s\"><input type=\"hidden\" name=\"id\" value=\"" + e(p.id) + "\">" + W.field("Who are you?", "who", { type: "select", options: ["The person named on this profile", "An authorised representative of the organisation", "Someone else (a visitor)"], req: true }) + W.field("What do you need?", "need", { type: "select", options: NEEDS, req: true }) + W.field("Which part of the profile?", "part", { type: "select", options: PARTS }) + W.field("New value or details", "detail", { type: "textarea", req: true }) + W.field("Source or evidence (link)", "evidence") + W.field("Your email", "contact", { type: "email", req: true, hint: "Owners confirm it by a single-use link." }) + W.bot() + "<button class=\"btn\">Send request</button></form>");
  });
  W.forms.correct = function (d) {
    var owner = d.who.indexOf("Someone else") !== 0, id = W.uid("cr"), tok = "t-" + W.uid("");
    W.S.corrections.unshift({ id: id, profileId: d.id, kind: owner ? "Owner correction request" : "Correction", requesterRole: d.who, need: d.need, field: d.need === NEEDS[0] ? d.part : "", newValue: d.detail, detail: d.need + (d.need === NEEDS[0] ? " (" + d.part + ")" : "") + ": " + d.detail, evidence: d.evidence || "", contact: d.contact, emailVerified: !owner, token: tok, at: W.TODAY, due: "2026-11-14", status: owner ? "awaiting email check" : "open" });
    if (owner) W.mail(d.contact, "Confirm your email to send your correction request", "Single-use link. Your request enters the staff queue only after you click.", "#/confirm/" + tok);
    else W.mail(d.contact, "We received your correction", "Reference " + id + ". We will respond within 7 days.", "");
    W.save(); W.go("#/done?kind=" + (owner ? "correctionowner" : "correction") + "&ref=" + id);
  };
  W.route("/remove/:slug", "V40", function (par) {
    var p = W.bySlug(par.slug); if (!p) return W.notFound.fn();
    return page(W.crumbs([[p.name, "#/profile/" + p.slug], ["Request removal"]]) + "<h1>Request removal</h1><p>No account or payment needed. We acknowledge within 1 business day and hide the profile within 2 business days of a valid request from the subject (REQ-158).</p><form data-form=\"remove\" class=\"card stack-s\"><input type=\"hidden\" name=\"id\" value=\"" + e(p.id) + "\">" + W.field("Who are you in relation to this profile?", "who", { type: "select", options: ["I am the person", "I represent the organisation", "Other"], req: true }) + W.field("Why should it be removed?", "detail", { type: "textarea", req: true }) + W.field("Your email", "contact", { type: "email", req: true }) + W.bot() + "<button class=\"btn bad\">Request removal</button></form>");
  });
  W.forms.remove = function (d) {
    var id = W.uid("rm"); W.S.removals.unshift({ id: id, profileId: d.id, kind: "Removal", detail: d.detail, contact: d.contact, at: W.TODAY, due: "2026-11-09", status: "open", who: d.who });
    W.mail(d.contact, "We received your removal request", "Reference " + id + ". Acknowledged within 1 business day.", "");
    W.save(); W.go("#/done?kind=removal&ref=" + id);
  };
  W.route("/done", "V41", function (p, q) {
    var t = { correction: ["Correction received", "We will acknowledge within 1 business day and resolve within 7 days."], removal: ["Removal request received", "We will acknowledge within 1 business day. A valid request from the subject hides the profile within 2 business days."], contact: ["Message sent", "A staff member will reply. The message is logged."], lead: ["Enquiry received", "Our team will contact you. Terms are agreed offline; nothing is charged on this site."], dsr: ["Request received", "We will respond within the legal deadline. A reference was emailed."], claim: ["Check your email to finish your claim request", "We sent a single-use link to confirm your email. Your request enters the staff queue only after you click it. Staff then check your evidence and set the profile to Claimed. If someone else is also claiming, we contact both parties."], correctionowner: ["Check your email to finish your correction request", "We sent a single-use link to confirm your email. After you click it, staff handle the request in the usual review. Nothing changes on the public page until a person approves it."], signup: ["You are on the list", "We emailed you to confirm. You can unsubscribe at any time."] }[q.kind] || ["Done", ""];
    return page('<div class="okbox"><h1>' + e(t[0]) + "</h1><p>" + e(t[1]) + "</p>" + (q.ref ? "<p>Reference: <strong class=\"mono\">" + e(q.ref) + "</strong></p>" : "") + '</div><p><a class="btn" href="#/">Home</a> <button class="btn alt" data-act="outbox">See the email sent</button></p>');
  });

  /* ---------- request to claim (no account) ---------- */
  W.route("/claim/:slug", "V42", function (par) {
    var p = W.bySlug(par.slug); if (!p || !W.isPublic(p)) return W.notFound.fn();
    var org = p.type !== "individual";
    var rl = W.S.rate.claim >= 3 ? '<div class="errbox" role="alert"><strong>Too many claim requests from this device.</strong> Please try again later (rate limit, REQ-230). Use Reset to clear.</div>' : "";
    return page(W.crumbs([[p.name, "#/profile/" + p.slug], ["Request to claim"]]) + "<h1>Request to claim " + e(p.name) + "</h1><p>No account is created. You send evidence, staff check it, and staff set the profile to Claimed (REQ-230, 231). " + (org ? "Organisations: prove authority by an email at the organisation\u2019s website domain, or by a document." : "Individuals: a document and manual approval.") + "</p>" + (rl || '<form data-form="claim" class="card stack-s"><input type="hidden" name="slug" value="' + e(p.slug) + '">' + W.field("Your name", "name", { req: true }) + W.field("Your relationship to this profile", "relation", { type: "select", options: org ? ["Authorised representative of the organisation", "Other (cannot claim; use the correction request)"] : ["The person named on this profile", "Other (cannot claim; use the correction request)"], req: true }) + W.field("Your email", "email", { type: "email", req: true, hint: "We send a single-use link to confirm it." }) + W.field("Phone (optional)", "phone", { hint: "Staff only, never public." }) + W.field("How will you prove it?", "route", { type: "select", options: org ? ["Domain email", "Document"] : ["Document"], req: true }) + W.field("Evidence upload (PDF, JPG or PNG, max 5 MB)", "doc", { type: "file", hint: "Stored privately and deleted after the decision (retention-limited). Wireframe: nothing is uploaded." }) + W.field("Short statement", "statement", { type: "textarea", req: true }) + W.field("I confirm I am authorised to claim this profile and that what I send is accurate and licensed for use (REQ-126).", "ok", { type: "checkbox", req: true }) + W.bot() + '<button class="btn">Send request to claim</button></form>'));
  });
  W.forms.claim = function (d) {
    var p = W.bySlug(d.slug);
    if (d.relation.indexOf("Other") === 0) { W.toast("Only the person or an authorised representative can claim. Use the correction request instead."); return; }
    if (W.S.rate.claim >= 3) { W.render(); return; }
    W.S.rate.claim++;
    var ref = "CLM-" + ("000" + (W.S.claims.length + 1)).slice(-4), tok = "t-" + W.uid("");
    W.S.claims.unshift({ id: W.uid("cl"), ref: ref, profileId: p.id, claimant: d.name, relation: d.relation, email: d.email, phone: d.phone || "", route: d.route, evidence: d.route === "Document" ? "uploaded-evidence.pdf (simulated)" : "(none: domain email)", statement: d.statement, emailVerified: false, token: tok, status: "awaiting email check", at: W.TODAY });
    W.mail(d.email, "Confirm your email to send your claim request", "Single-use link. Your request for " + p.name + " (" + ref + ") enters the staff queue only after you click.", "#/confirm/" + tok);
    W.save(); W.go("#/done?kind=claim&ref=" + ref);
  };
  W.route("/confirm/:token", "V51", function (par) {
    var c = W.S.claims.filter(function (x) { return x.token === par.token; })[0], r = W.S.corrections.filter(function (x) { return x.token === par.token; })[0];
    if (c) {
      if (!c.emailVerified) { c.emailVerified = true; c.status = "pending"; W.S.used[par.token] = true; W.log("claim.email", c.ref, "email confirmed by signed link"); W.mail("Reviewer queue", "Claim to verify: " + (W.profile(c.profileId) || {}).name, c.ref + " is ready for evidence checks.", "#/admin/claims/" + c.id); W.save(); }
      return page('<div class="okbox"><h1>Email confirmed</h1><p>Your claim request <strong class="mono">' + e(c.ref) + '</strong> is in the staff queue. Staff will check your evidence and email you. This link no longer works a second time (single use).</p></div><a class="btn" href="#/">Home</a>');
    }
    if (r) {
      if (!r.emailVerified) { r.emailVerified = true; r.status = "open"; W.S.used[par.token] = true; W.log("correction.email", r.id, "email confirmed by signed link"); W.save(); }
      return page('<div class="okbox"><h1>Email confirmed</h1><p>Your request is in the staff queue. Nothing changes on the public page until a person approves it. This link no longer works a second time (single use).</p></div><a class="btn" href="#/">Home</a>');
    }
    return W.expiredPage();
  });

  /* ---------- stories ---------- */
  W.route("/stories", "V16", function (p, q) {
    var types = ["Interview", "Feature", "Spotlight", "Opinion", "Announcement", "News"];
    var rows = W.publicArticles().filter(function (a) { return (!q.type || a.type === q.type) && (!q.industry || a.industry === q.industry); });
    return page("<h1>Stories</h1><form class=\"filters\" data-filter=\"#/stories\" aria-label=\"Filter stories\">" + W.field("Type", "type", { type: "select", options: types, blank: "All types", value: q.type }) + W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "All industries", value: q.industry }) + "<div><a class=\"btn alt\" href=\"#/stories\">Clear</a></div></form>" +
      (rows.length ? '<div class="grid c3">' + rows.map(W.articleCard).join("") + "</div>" : W.empty("No stories match", "Try another type or industry.", '<a class="btn" href="#/stories">Clear filters</a>')));
  });
  W.route("/stories/:slug", "V17", function (par, q, m) {
    var a = W.S.articles.filter(function (x) { return x.slug === par.slug; })[0], staff = W.S.role !== "visitor";
    if (!a || (a.status !== "published" && !staff)) { m.inv = "V13"; return W.notFound.fn(); }
    var sp = W.sponsorOfArticle(a);
    return (a.status !== "published" ? '<div class="banner staffprev"><div class="wrap">Staff preview: ' + e(a.status) + " article, not public.</div></div>" : "") + page(W.crumbs([["Stories", "#/stories"], [a.title]]) + '<article class="stack"><div class="tagrow"><span class="pill gray">' + e(a.type) + "</span>" + W.spPill(sp) + "</div><h1>" + e(a.title) + '</h1><p class="muted">By ' + e(a.author) + " · " + e(a.date) + " · " + e(a.industry) + "</p>" + (sp ? '<div class="warnbox"><strong>Sponsored content.</strong> Paid for by ' + e(sp) + ". Editorial independence statement placeholder.</div>" : "") + W.ph("Hero image") + "<p>" + e(a.body) + "</p><p>Sample paragraph two. Placeholder text only.</p>" +
      (a.profiles.length ? "<h2>Profiles in this story</h2><div class=\"grid c3\">" + a.profiles.map(W.profile).filter(W.isPublic).map(W.card).join("") + "</div>" : "") + '<div class="card"><strong>Share preview</strong><div class="ph" style="min-height:3rem">Share image</div>' + e(a.title) + (sp ? " · Sponsored" : "") + "</div></article>");
  });

  /* ---------- subscribe, contact ---------- */
  W.route("/subscribe", "V24", function () {
    return page("<h1>Get listed or featured</h1><p>Tell us what you are looking for. We reply with terms agreed offline. <strong>Nothing is charged on this site</strong>, and every listing has the same format.</p><div class=\"grid c2\"><div class=\"stack\"><div class=\"card\"><h2>What we can do</h2><ul><li>Create or improve your listing</li><li>Sponsored posts and sponsored profiles (always labelled)</li><li>Media and editorial work</li><li>Awards sponsorship</li></ul></div><div class=\"infobox\"><strong>What money cannot buy.</strong> Verified and Featured are never for sale, and sponsorship never affects awards. <a href=\"#/how-we-compile\">Read why</a></div></div><form class=\"card stack-s\" data-form=\"lead\"><h2>Enquiry</h2>" + W.field("Your name", "name", { req: true }) + W.field("Organisation", "org") + W.field("Interested in", "interest", { type: "select", options: ["Listing a profile", "Sponsored profile", "Sponsored post", "Media / editorial work", "Awards sponsorship", "Not sure"], req: true }) + W.field("Email or phone", "contact", { req: true }) + W.field("Anything else?", "msg", { type: "textarea" }) + W.field("I have read the <a href=\"#/privacy\">privacy notice</a> and agree to be contacted about this enquiry.", "consent", { type: "checkbox", req: true }) + W.bot() + "<button class=\"btn\">Send enquiry</button></form></div>");
  });
  W.forms.lead = function (d) {
    var id = "LEAD-" + ("000" + (W.S.leads.length + 1)).slice(-4);
    W.S.leads.unshift({ id: W.uid("l"), ext: id, name: d.name, org: d.org || "", interest: d.interest, contact: d.contact, status: "new", owner: "", notes: d.msg || "", at: W.TODAY });
    W.mail("Sales inbox (staff)", "New lead: " + d.name, d.interest + " from " + (d.org || "an individual") + ".", "#/admin/leads"); W.mail(d.contact, "We received your enquiry", "Reference " + id + ".", "");
    W.save(); W.go("#/done?kind=lead&ref=" + id);
  };
  W.route("/contact", "V26", function () {
    return page("<h1>Contact</h1><form class=\"card stack-s\" data-form=\"contact\">" + W.field("Name", "name", { req: true }) + W.field("Email", "email", { type: "email", req: true }) + W.field("Message", "msg", { type: "textarea", req: true }) + W.bot() + "<button class=\"btn\">Send message</button></form>");
  });
  W.forms.contact = function (d) {
    var id = W.uid("msg"); W.S.enquiries.unshift({ id: id, profileId: "", name: d.name, email: d.email, message: d.msg, at: W.TODAY, read: false, general: true });
    W.mail("Staff inbox", "Contact form: " + d.name, d.msg, "#/admin/inbox"); W.save(); W.go("#/done?kind=contact&ref=" + id);
  };

  /* ---------- legal and trust pages ---------- */
  function legal(inv, path, title, intro, secs) {
    W.route(path, inv, function () { return page("<h1>" + e(title) + "</h1><p class=\"preview-note\">Placeholder text. Legal supplies the final wording (N9).</p><p>" + intro + "</p>" + secs.map(function (s) { return "<h2>" + e(s[0]) + "</h2><p>" + s[1] + "</p>"; }).join("")); });
  }
  legal("V29", "/privacy", "Privacy policy", "Sample privacy policy. Version 0.1 (the version is stored with every consent, REQ-058).", [["Whose data we hold", "Sample list: profile owners, people profiled from public sources, nominators, enquirers."], ["Lawful basis", "Sample: legitimate interests with a right to object, for people profiled from public sources. Legal confirms."], ["Your rights", "Access, correction, erasure, objection, portability. <a href=\"#/data-rights\">Make a request</a>."], ["Retention", "Sample retention schedule by data class."], ["Processors", "Hosting, email, analytics, AI provider (flag off). Register held internally."]]);
  legal("V30", "/terms", "Terms and conditions", "Sample terms.", [["Using the site", "Sample text."], ["Submitted content", "You grant a licence and warrant accuracy (REQ-126)."], ["Liability", "Sample text."]]);
  legal("V31", "/cookies", "Cookie policy", "Essential cookies always; analytics only after consent (REQ-062).", [["Essential", "Session and security."], ["Analytics", "Privacy-friendly visit counts, after consent."]]);
  legal("V32", "/commercial-terms", "Commercial terms", "Sample terms for arrangements agreed offline. There is no online payment. Sponsorship never buys Verified, Featured or award outcomes.", [["How arrangements work", "Quoted per customer; payment made offline to Finance; recorded by staff."], ["Refunds and renewals", "Handled offline by Finance."]]);
  legal("V28", "/faq", "Frequently asked questions", "Sample FAQ.", [["Is there a fee to be listed?", "Sample: arrangements are agreed offline."], ["Can I remove my profile?", "Yes. <a href=\"#/complaints\">Request removal</a>; no account or payment needed."], ["Can I pay for Verified?", "No."], ["How do nominations work?", "See <a href=\"#/awards\">Awards</a>."]]);
  W.route("/how-we-compile", "V34", function () {
    return page("<h1>How " + e(W.brand.name) + " compiles profiles and what each label means</h1><p class=\"preview-note\">Placeholder wording; Legal decides the AI disclosure (REQ-170).</p><h2>Where information comes from</h2><p>Approved public sources such as registries, regulators, exchange listings, Wikidata and official websites. Never social platforms, news text or scraped photographs.</p><h2>Every profile is reviewed by a person</h2><p>Nothing is public until a human reviewer approves it. Reviewers check each fact against its source and apply a neutral-content checklist: dated, sourced, professional facts only.</p><h2>The status ladder</h2><ol><li><span class=\"pill t-unclaimed\">Unclaimed</span> compiled from public sources.</li><li><span class=\"pill t-claimed\">Claimed</span> the person or an authorised representative proved their identity.</li><li><span class=\"pill t-verified\">✓ Verified</span> passed our checklist. Independent of payment.</li><li><span class=\"pill t-featured\">★ Featured</span> an editorial decision. Cannot be bought.</li></ol><h2>Sponsored</h2><p><span class=\"pill sponsored\">Sponsored</span> is a separate disclosure for paid placements. It never implies verification.</p><h2>AI-assisted drafting</h2><p>Some profiles are drafted with AI from fetched source text. Each claim must match a stored source passage or it is dropped, and a person approves every profile.</p><h2>Corrections and removal</h2><p><a href=\"#/complaints\">Tell us about a problem</a>. No account or payment needed.</p>");
  });
  W.route("/complaints", "V35", function () {
    return page("<h1>Complaints and takedown</h1><p>Defamation, privacy, copyright or accuracy concerns can be raised without an account or payment.</p><ul><li>We acknowledge within 1 business day.</li><li>We hide a profile within 2 business days of a valid removal request from the subject.</li><li>We resolve corrections within 7 days.</li></ul><p>Use “Suggest a correction”, “Request removal” or “Report” on any profile. Disputes escalate to the Editor-in-Chief.</p><p><a class=\"btn\" href=\"#/people\">Find a profile</a> <a class=\"btn alt\" href=\"#/contact\">Contact us</a></p>");
  });
  W.route("/data-rights", "V50", function () {
    return page("<h1>Your data rights</h1><p>Ask to access, correct, erase, object to or port your personal data. We track every request with a due date.</p><form class=\"card stack-s\" data-form=\"dsr\">" + W.field("Request type", "kind", { type: "select", options: ["Access", "Correction", "Erasure", "Objection", "Portability"], req: true }) + W.field("Your name", "name", { req: true }) + W.field("Your email", "email", { type: "email", req: true }) + W.field("Details", "detail", { type: "textarea" }) + W.bot() + "<button class=\"btn\">Submit request</button></form>");
  });
  W.forms.dsr = function (d) {
    var id = W.uid("d"); W.S.dsr.unshift({ id: id, kind: d.kind, who: d.name, at: W.TODAY, due: "2026-12-07", status: "open" });
    W.mail(d.email, "Data request received", "Reference " + id + ". Due 2026-12-07.", ""); W.save(); W.go("#/done?kind=dsr&ref=" + id);
  };
  W.route("/unsubscribe", "V45", function (p, q) {
    var done = q.done === "1";
    return page("<h1>Unsubscribe</h1>" + (done ? '<div class="okbox"><p>You are unsubscribed. We will not email you again about this list.</p></div>' : '<p>One click, no login.</p>' + W.btn("Unsubscribe me", "unsub", "", "")));
  });
  W.act.unsub = function () { W.S.signups = []; W.save(); W.go("#/unsubscribe?done=1"); };
  W.route("/sitemap.xml", "V48", function () {
    var urls = ["/", "/about", "/people", "/businesses", "/institutions", "/industries", "/awards", "/stories", "/subscribe", "/nominate", "/contact"]
      .concat(W.publicProfiles().map(function (p) { return "/profile/" + p.slug; })).concat(W.publicArticles().map(function (a) { return "/stories/" + a.slug; }));
    var excl = W.S.profiles.filter(function (p) { return !W.isPublic(p); });
    return page("<h1>sitemap.xml (preview)</h1><p class=\"muted\">Built from the same visibility rule as pages and search (REQ-208, 209). " + urls.length + " URLs listed.</p><div class=\"grid c2\"><div><h2>Listed</h2><pre class=\"mono card\" style=\"overflow:auto\">" + urls.map(function (u) { return "&lt;url&gt;&lt;loc&gt;https://" + e(W.brand.domain) + e(u) + "&lt;/loc&gt;&lt;/url&gt;"; }).join("\n") + "</pre></div><div><h2>Excluded (" + excl.length + ")</h2>" + W.table(["Profile", "Status", "Visitors get"], excl.map(function (p) { return [e(p.name), W.stPill(W.isKilled(p) ? "hidden by kill switch" : p.status), W.vis(p) === "gone" ? "410 + noindex" : "404"]; })) + "<h2>robots.txt</h2><pre class=\"mono card\">User-agent: *\nAllow: /\nSitemap: https://" + e(W.brand.domain) + "/sitemap.xml</pre></div></div>");
  });
})();
