/* Public site screens (visitor), high fidelity. Components live in theme/components.js, styles in theme/site.css. */
(function () {
  var W = window.W, e = W.esc;
  var LIST = {
    directory: { path: "#/directory", label: "All", title: "The directory", lede: "The people, businesses and institutions shaping Africa, with a source beside every fact.", match: function () { return true; } },
    people: { path: "#/people", label: "People", title: "People", lede: "Leaders and professionals on the public record. An individual is public only after a person on our team has approved the profile.", match: function (p) { return p.type === "individual"; } },
    entrepreneurs: { path: "#/entrepreneurs", label: "Entrepreneurs", title: "Entrepreneurs", lede: "Founders and the ventures they built, reviewed by a person before they appear here.", match: function (p) { return W.isEntrepreneur(p); } },
    businesses: { path: "#/businesses", label: "Businesses", title: "Businesses", lede: "Companies from startups to listed groups, with sources for every fact.", match: function (p) { return p.type === "business"; } },
    institutions: { path: "#/institutions", label: "Institutions", title: "Institutions", lede: "Universities, professional associations, public bodies and foundations.", match: function (p) { return p.type === "institution"; } }
  };
  var ORDER = ["directory", "people", "entrepreneurs", "businesses", "institutions"];

  /* ---------- directory ---------- */
  function keep(q, skip) { return Object.keys(q).filter(function (k) { return (skip || []).indexOf(k) < 0 && k !== "page"; }).map(function (k) { return '<input type="hidden" name="' + e(k) + '" value="' + e(q[k]) + '">'; }).join(""); }
  function without(base, q, key) { var n = {}; Object.keys(q).forEach(function (k) { if (k !== key && k !== "page") n[k] = q[k]; }); return base + W.qs(n); }
  function pager(base, q, total, per) {
    var pages = Math.max(1, Math.ceil(total / per)), cur = Math.min(pages, Number(q.page) || 1);
    if (pages === 1) return "";
    var out = '<nav class="pager" aria-label="Pages">';
    for (var i = 1; i <= pages; i++) { var nq = {}; Object.keys(q).forEach(function (k) { nq[k] = q[k]; }); nq.page = i === 1 ? "" : i; out += '<a class="btn sm ' + (i === cur ? "" : "alt") + '" href="' + base + W.qs(nq) + '"' + (i === cur ? ' aria-current="page"' : "") + ">" + i + "</a>"; }
    return out + "</nav>";
  }
  var FILTER_LABELS = { industry: "Industry", city: "City", country: "Country", trust: "Label", profession: "Profession", orgType: "Organisation type", q: "Search" };
  function directory(key) {
    return function (params, q) {
      var L = LIST[key], per = 16, list = q.view === "list";
      var t = (q.q || "").toLowerCase();
      var rows = W.publicProfiles().filter(function (p) {
        return L.match(p) && !W.isPending(p) && (!q.industry || p.industry === q.industry) && (!q.city || p.city === q.city) && (!q.trust || p.trust === q.trust) && (!q.country || p.country === q.country) && (!q.profession || p.profession === q.profession) && (!q.orgType || p.orgType === q.orgType) &&
          (!t || (p.name + " " + (p.org || "") + " " + p.title + " " + p.bio).toLowerCase().indexOf(t) >= 0);
      }).sort(function (a, b) { if (q.sort === "recent") return (b.lastReviewed || "") < (a.lastReviewed || "") ? -1 : 1; return a.name < b.name ? -1 : 1; });
      var cur = Math.min(Math.max(1, Math.ceil(rows.length / per)), Number(q.page) || 1), slice = rows.slice((cur - 1) * per, cur * per);
      var tabs = '<nav class="tabs" aria-label="Directory sections">' + ORDER.map(function (k) { return '<a href="' + LIST[k].path + '"' + (k === key ? ' aria-current="page"' : "") + ">" + e(LIST[k].label) + "</a>"; }).join("") + "</nav>";
      var search = '<form class="dir-search" role="search" data-filter="' + L.path + '" aria-label="Search this section">' + keep(q, ["q"]) + '<label class="sr" for="dq">Name or keyword</label><input id="dq" name="q" type="search" value="' + e(q.q || "") + '" placeholder="Name, organisation or keyword"><button class="btn">Search</button></form>';
      var active = Object.keys(q).filter(function (k) { return FILTER_LABELS[k] && q[k]; });
      var extra = (key === "people" || key === "entrepreneurs") ? { name: "profession", label: "Profession", blank: "Any profession", options: W.LISTS.professions, value: q.profession } : key === "institutions" || key === "businesses" ? { name: "orgType", label: "Organisation type", blank: "Any type", options: W.LISTS.orgTypes, value: q.orgType } : null;
      var vq = function (v) { var n = {}; Object.keys(q).forEach(function (k) { n[k] = q[k]; }); n.view = v; n.page = ""; return L.path + W.qs(n); };
      var toggle = '<div class="view-toggle" role="group" aria-label="View as"><a href="' + vq("") + '" aria-current="' + (!list) + '">' + W.ico("grid") + 'Grid</a><a href="' + vq("list") + '" aria-current="' + list + '">' + W.ico("list") + "List</a></div>";
      var results = slice.length ? (list ? '<div class="results--list">' + slice.map(W.card).join("") + "</div>" : W.grid(slice.map(W.card), "grid--4")) : W.empty("No matching profiles", "Try removing a filter or searching for a different word.", '<a class="btn" href="' + L.path + '">Clear filters</a>');
      var bar = W.filterBar({ path: L.path, label: "Filter results", keep: keep(q, ["industry", "city", "country", "profession", "orgType", "sort", "page"]),
        controls: [{ name: "industry", label: "Industry", blank: "All industries", options: W.LISTS.industries, value: q.industry }, { name: "country", label: "Country", blank: "All countries", options: W.LISTS.countries, value: q.country }, { name: "city", label: "City", blank: "All cities", options: W.LISTS.cities, value: q.city }].concat(extra ? [extra] : []),
        sort: { name: "sort", value: q.sort || "", options: [["", "Name, A to Z"], ["recent", "Recently updated"]] }, count: rows.length + " profile" + (rows.length === 1 ? "" : "s"), clear: active.length > 0, extra: toggle });
      return W.band({ compact: true, title: L.title, lede: L.lede, aside: search, extra: tabs }) +
        '<div class="wrap dir-layout">' + bar + results + pager(L.path, q, rows.length, per) + "</div>";
    };
  }
  W.route("/directory", "V52", directory("directory")); W.route("/people", "V03", directory("people")); W.route("/entrepreneurs", "V53", directory("entrepreneurs")); W.route("/businesses", "V04", directory("businesses")); W.route("/institutions", "V05", directory("institutions"));

  /* The home page lives in theme/home-view.js. */
  /* About: what the record is, the three words behind it, what you will find here. Sample wording until Brand signs it off. */
  W.route("/about", "V02", function () {
    var pub = W.publicProfiles().filter(function (p) { return !W.isPending(p); }), faces = pub.filter(function (p) { return p.photo; }).slice(0, 3);
    var countries = pub.map(function (p) { return p.country; }).filter(function (c, i, a) { return c && a.indexOf(c) === i; }).length;
    /* Copy follows the brief: purpose (2.3), the three words (section 1), positioning (2.7) and tone (11.1). */
    var PILLARS = [["blue", "star", "Influence", "The people and organisations making a difference and shaping the world around them."], ["orange", "comb", "Achievement", "The milestones, contributions and innovations that deserve to be recognised."], ["green", "spiral", "Legacy", "The lasting impact people and institutions leave behind."]];
    var DO = [["#/directory", "The directory", "Profiles of people, businesses and institutions, with the facts and the sources behind each one."], ["#/stories", "Stories", "Interviews, features and news about the people and ideas on the record."], ["#/awards", "The awards", "Recognition decided by independent judges, under rules published before nominations open."]];
    var HOW = [["Sourced", "Every fact shows where it came from, so you can check it yourself."], ["Reviewed by people", "Our team reads what we publish. Nothing about a person is added without a source."], ["Corrected in the open", "Anyone can ask for a correction or a removal, with no account and no fee."]];
    return '<header class="ab-hero"><div class="wrap ab-hero-in"><div class="ab-hero-text"><p class="np-kicker">About ' + e(W.brand.name) + '</p><h1 class="ab-title">Africa’s influence, <span class="ab-accent">on the record.</span></h1><p class="ab-lede">' + e(W.brand.name) + ' is an African discovery, recognition and media platform. We document the people, businesses and institutions shaping the continent, tell the stories behind them, and recognise achievement through independent awards.</p></div>' +
      (faces.length ? '<div class="ab-stack" aria-hidden="true">' + faces.map(function (p, i) { return '<span class="ab-stack-item" style="--i:' + i + '">' + W.profilePoster(p) + "</span>"; }).join("") + "</div>" : "") + "</div></header>" +
      '<section class="ab-purpose" aria-labelledby="abPurpose"><div class="wrap"><p class="np-kicker" id="abPurpose">Why we exist</p><p class="ab-purpose-text" data-reveal>To make African influence visible, celebrate meaningful achievement and preserve the stories and legacies that shape the continent.</p></div></section>' +
      '<section class="section ab-why"><div class="wrap ab-why-in"><div data-reveal><p class="np-kicker aw-kick">The gap</p><p class="ab-pull">Who runs what, who built what and who achieved what is scattered across registries, annual reports and press releases.</p></div><div class="ab-prose" data-reveal><p>It is hard to find, and harder to trust. ' + e(W.brand.name) + ' brings it together in one place, as structured profiles with a source beside every fact.</p><p>Around the record we publish stories that explain it, and an awards programme that recognises what it shows. We are a curated destination, not another social network.</p><p class="small muted">Draft copy for review. The final mission, vision and values come from Brand.</p></div></div></section>' +
      '<section class="ab-pillars" aria-labelledby="abWords"><div class="wrap"><h2 class="sr" id="abWords">What we stand for</h2><div class="ab-pillar-grid">' + PILLARS.map(function (x, i) { return '<div class="ab-pillar poster poster--' + x[0] + '" data-reveal style="--i:' + i + '"><span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--' + x[1] + '" aria-hidden="true"></span><div class="ab-pillar-in"><span class="ab-num">0' + (i + 1) + "</span><h3>" + e(x[2]) + "</h3><p>" + e(x[3]) + "</p></div></div>"; }).join("") + "</div></div></section>" +
      '<section class="section ab-do" aria-labelledby="abDo"><div class="wrap"><div class="ab-do-head" data-reveal><p class="np-kicker">What you will find here</p><h2 id="abDo">Three ways into the record</h2></div><ol class="ab-do-list">' + DO.map(function (x, i) { return '<li data-reveal style="--i:' + i + '"><a href="' + x[0] + '"><span class="ab-num">0' + (i + 1) + '</span><span class="ab-do-t">' + e(x[1]) + '</span><span class="ab-do-d">' + e(x[2]) + '</span><span class="ab-do-go" aria-hidden="true">' + W.ico("arrow") + "</span></a></li>"; }).join("") + "</ol></div></section>" +
      '<section class="section ab-how" aria-labelledby="abHow"><div class="wrap"><div class="ab-do-head" data-reveal><p class="np-kicker">How we keep it credible</p><h2 id="abHow">Built to be checked</h2></div><ul class="ab-how-list">' + HOW.map(function (x, i) { return '<li data-reveal style="--i:' + i + '"><h3>' + e(x[0]) + "</h3><p>" + e(x[1]) + "</p></li>"; }).join("") + "</ul>" +
      '<dl class="ab-figs" data-reveal><div><dt>Profiles</dt><dd>' + pub.length + "</dd></div><div><dt>Industries</dt><dd>" + W.LISTS.industries.length + "</dd></div><div><dt>Countries</dt><dd>" + countries + "</dd></div><div><dt>Stories</dt><dd>" + W.publicArticles().length + "</dd></div></dl></div></section>" +
      '<section class="ab-cta"><div class="wrap ab-cta-in"><h2 data-reveal>Questions, a correction or a story to tell?</h2><div class="row" data-reveal><a class="btn" href="#/contact">Contact us</a><a class="btn alt" href="#/faq">Read the questions</a></div></div></section>';
  });

  /* ---------- industries, search ---------- */
  W.route("/industries", "V06", function () {
    return W.band({ eyebrow: "Directory", title: "Industries", lede: "Browse profiles and stories by the sector they work in." }) +
      '<section class="section"><div class="wrap"><ul class="index-list" data-reveal>' + W.LISTS.industries.map(function (i) {
        var n = W.publicProfiles().filter(function (p) { return p.industry === i && !W.isPending(p); }).length;
        return '<li><a href="#/industries/' + encodeURIComponent(i) + '"><span>' + e(i) + '</span><span class="row"><span class="mono">' + n + '<span class="sr"> ' + (n === 1 ? "profile" : "profiles") + "</span></span>" + W.ico("arrow") + "</span></a></li>";
      }).join("") + "</ul></div></section>";
  });
  W.route("/industries/:name", "V07", function (p) {
    var ps = W.publicProfiles().filter(function (x) { return x.industry === p.name && !W.isPending(x); }), as = W.publicArticles().filter(function (a) { return a.industry === p.name; });
    return W.band({ crumbs: [["Industries", "#/industries"], [p.name]], title: p.name }) + '<section class="section"><div class="wrap">' + W.sectionHead("", "Profiles") + (ps.length ? W.grid(ps.map(W.card)) : W.empty("No profiles in this industry yet", "Check back soon, or browse all industries.", '<a class="btn" href="#/industries">All industries</a>')) + "</div></section>" +
      '<section class="section section--alt"><div class="wrap">' + W.sectionHead("", "Stories") + (as.length ? W.grid(as.map(function (a) { return W.articleCard(a); })) : '<p class="muted">No stories in this industry yet.</p>') + "</div></section>";
  });
  /* Public search: profiles (name matches first) and stories. Used by the results page and the header search panel. */
  W.searchHits = function (q) {
    var t = (q || "").toLowerCase().trim();
    if (!t) return { t: t, ps: [], as: [] };
    var ps = W.publicProfiles().filter(function (x) { return (x.name + " " + (x.org || "") + " " + x.title + " " + x.industry + " " + x.bio).toLowerCase().indexOf(t) >= 0; }).sort(function (a, b) { return (b.name.toLowerCase().indexOf(t) >= 0) - (a.name.toLowerCase().indexOf(t) >= 0); });
    var as = W.publicArticles().filter(function (a) { return (a.title + a.body).toLowerCase().indexOf(t) >= 0; });
    return { t: t, ps: ps, as: as };
  };
  W.route("/search", "V08", function (p, q) {
    var hits = W.searchHits(q.q), t = hits.t, ps = hits.ps, as = hits.as;
    var form = '<form class="dir-search" role="search" data-form="search"><label class="sr" for="sq">Search</label><input id="sq" name="q" value="' + e(q.q || "") + '" type="search" placeholder="Name, organisation or keyword"><button class="btn">Search</button></form>';
    return W.band({ eyebrow: "Search", title: t ? "Results for “" + q.q + "”" : "Search the directory", extra: form }) + '<section class="section--tight"><div class="wrap section--tight">' +
      (!t ? W.empty("Type a name, organisation or keyword", "Results show public profiles and stories only.") : '<p role="status" style="font-weight:600;margin-bottom:1.5rem">' + (ps.length + as.length) + " result" + (ps.length + as.length === 1 ? "" : "s") + ", name matches first</p>" +
        (ps.length || as.length ? (ps.length ? '<h2 style="margin-bottom:1rem;font-size:var(--t-xl)">Profiles</h2>' + W.grid(ps.map(W.card)) : "") + (as.length ? '<h2 style="margin-block:2rem 1rem;font-size:var(--t-xl)">Stories</h2>' + W.grid(as.map(function (a) { return W.articleCard(a); })) : "") : W.empty("No results", "Nothing public matches that search. Check the spelling or try fewer words.", '<a class="btn" href="#/directory">Browse the directory</a>'))) + "</div></section>";
  });

  /* ---------- profile ---------- */
  function timeline(rows) { return '<ul class="timeline-simple">' + rows.map(function (r) { return '<li><span class="mono">' + e(r.when) + "</span><span>" + e(r.what) + "</span></li>"; }).join("") + "</ul>"; }
  function sec(title, html) { return "<section><h2>" + e(title) + "</h2>" + html + "</section>"; }
  function extra(p) {
    var out = "";
    if (W.isEntrepreneur(p) && p.ventures) out += sec("Ventures", W.grid(p.ventures.map(function (v) { return '<div class="panel panel--ruled"><h3>' + e(v.name) + '</h3><p class="muted small">' + e(v.role) + " since " + e(v.since) + " · " + e(v.sector) + '</p><p class="small" style="margin-top:.5rem">' + e(v.note) + "</p></div>"; }), "grid--2"));
    if (p.type === "institution") { if (p.mandate) out += sec("Mandate", "<p>" + e(p.mandate) + "</p>"); if (p.longHistory) out += sec("History", "<p>" + e(p.longHistory) + "</p>"); }
    if (p.career) out += sec("Career", timeline(p.career));
    if (p.education) out += sec("Education", '<ul class="timeline-simple">' + p.education.map(function (x) { return "<li style=\"grid-template-columns:1fr\"><span>" + e(x) + "</span></li>"; }).join("") + "</ul>");
    if (p.milestones) out += sec("Milestones", timeline(p.milestones));
    if (p.leadership && p.leadership.length) out += sec("Leadership", '<ul class="timeline-simple">' + p.leadership.map(function (l) { return "<li style=\"grid-template-columns:1fr\"><span>" + e(l) + "</span></li>"; }).join("") + "</ul>");
    if (p.products) out += sec("Products and services", "<p>" + e(p.products) + "</p>");
    if (p.achievements) out += sec(p.type === "individual" ? "Awards and achievements" : "Achievements", "<p>" + e(p.achievements) + "</p>");
    if (p.media) out += sec("Media", '<ul class="timeline-simple">' + p.media.map(function (x) { return "<li style=\"grid-template-columns:1fr\"><span>" + e(x) + "</span></li>"; }).join("") + "</ul>");
    return out;
  }
  function profileBody(p, staffPrev) {
    var pending = W.isPending(p), arts = W.publicArticles().filter(function (a) { return a.profiles.indexOf(p.id) >= 0; });
    var nom = W.nomFor(p), ng = nom && W.groupOf(nom.category);
    var enq = p.enquiries ? W.btn("Send an enquiry", "enquiry", p.id, "wide") : '<p class="muted small">Enquiries are switched off for this profile.</p>';
    var types = { individual: W.isEntrepreneur(p) ? "Entrepreneur" : "Person", business: "Business", institution: "Institution" };
    var section = W.isEntrepreneur(p) ? "entrepreneurs" : p.type === "individual" ? "people" : p.type === "business" ? "businesses" : "institutions";
    var person = p.type === "individual", facts = p.facts.filter(function (f) { return f.src; });
    var head = '<div class="wrap" style="padding-block:.75rem">' + W.crumbs([[LIST[section].title, LIST[section].path], [p.name]]) + "</div>" +
      '<div class="wrap"><div class="profile-id">' + W.monogram(p.name, p.type, null, p.photo) + "<div><h1>" + e(p.name) + '</h1><p class="profile-role">' + e(p.title || "") + (p.org ? " · " + e(p.org) : "") + "</p>" + (nom ? W.awardTag(nom, "profile-award-tag") : "") + W.labels(p) +
      '<p class="profile-loc meta">' + W.ico("pin") + " " + e(p.city) + ", " + e(p.country) + " · " + e(p.industry) + " · " + types[p.type] + '</p><p style="margin-top:.5rem"><span class="badge badge--fiction">Fictional sample profile</span></p></div></div></div>';
    return (staffPrev ? W.staffBanner("Staff preview. This profile is " + e(p.status) + " and is not public. Visitors would see " + (W.vis(p) === "gone" ? "410 Gone" : "404") + ".") : "") +
      '<article><header class="profile-head">' + head + '</header><div class="wrap split split--rail profile-body"><div class="profile-main">' +
      (nom ? '<section class="prof-award poster poster--' + ng.tone + '" aria-labelledby="profAward"><span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--' + ng.mark + '" aria-hidden="true"></span><div class="prof-award-in"><p class="prof-award-k">' + W.ico("star") + e(W.brand.edition) + '</p><h2 id="profAward">' + (nom.status === "winner" ? "Winner, " : "Nominated for ") + e(nom.category) + "</h2>" + (nom.citation ? '<p class="prof-award-cite">' + e(nom.citation) + "</p>" : "") + '<a class="btn prof-award-btn" href="#/awards/nominee/' + e(nom.ref) + '">See the nomination' + W.ico("arrow") + "</a></div></section>" : "") +
      (pending ? '<div class="notice notice--pending">' + W.ico("clock") + "<div><strong>" + e(W.PENDING_LABEL) + '</strong> <a href="#/correct/' + e(p.slug) + '">Correct</a> or <a href="#/remove/' + e(p.slug) + '">remove</a> this profile.</div></div>' : "") +
      sec("About", '<p style="font-size:var(--t-md)">' + e(p.bio) + "</p>") + extra(p) +
      sec("Key facts and sources", facts.length ? '<table class="facts"><tbody>' + facts.map(function (f) { return '<tr><th scope="row">' + e(f.label) + "</th><td>" + e(f.value) + '<span class="src">Source: ' + e(f.src) + " · retrieved " + e(W.fmtDate(f.retrieved)) + "</span></td></tr>"; }).join("") + "</tbody></table>" : '<p class="muted">No sourced facts are listed yet.</p>') +
      (p.website && p.public.website ? '<p style="margin-top:1rem">Website: <a href="' + e(p.website) + '" rel="nofollow noopener">' + e(p.website.replace(/^https?:\/\//, "")) + "</a></p>" : "") +
      (arts.length ? sec("Related stories", W.grid(arts.map(function (a) { return W.articleCard(a); }), "grid--2")) : "") +
      "</div>" +
      '<aside class="profile-rail" aria-label="Actions"><div class="panel panel--ruled"><h2>Contact</h2><p class="small muted" style="margin-bottom:.75rem">Contact details are not shown. We pass enquiries on.</p>' + enq + "</div>" +
      '<div class="panel"><h2>Something wrong?</h2><div class="rail-actions">' + (p.claimed || W.isPending(p) ? "" : '<a class="btn alt sm" href="#/claim/' + e(p.slug) + '">' + W.ico("check") + 'Claim this profile</a>') + '<a class="btn alt sm" href="#/correct/' + e(p.slug) + '">' + W.ico("edit") + 'Suggest a correction</a><a class="btn alt sm" href="#/remove/' + e(p.slug) + '">' + W.ico("trash") + "Request removal</a>" + W.btn("Report this profile", "report", p.id, "alt sm") + '</div><p class="small muted" style="margin-top:.75rem">No account or payment needed.</p></div>' +
      '<div class="panel"><h2>When shared</h2><div class="share">' + W.img("cover", W.seedFor(p.slug), 600, 240, "Placeholder share image") + "<div><strong>" + e(p.name) + '</strong><span class="muted">' + e(W.brand.name) + " · " + e(p.title || p.industry) + "</span>" + (pending ? '<p class="pending-note" style="margin-top:.25rem">' + e(W.PENDING_LABEL) + "</p>" : "") + "</div></div></div></aside></div></article>";
  }
  W.route("/profile/:slug", "V09", function (par, q, m) {
    var p = W.bySlug(par.slug); var inv = { individual: "V09", business: "V10", institution: "V11" };
    var v = W.vis(p), staff = ["reviewer", "editor", "admin"].indexOf(W.S.role) >= 0;
    if (!p) { m.inv = "V13"; return W.notFound.fn(); }
    if (p.slug !== par.slug) return { redirect: "#/profile/" + p.slug };
    var id = W.isPending(p) ? "V56" : W.isEntrepreneur(p) ? "V54" : inv[p.type];
    if (v === "public") { m.inv = id; return profileBody(p, false); }
    if (staff) { m.inv = id; return profileBody(p, true); }
    if (v === "gone") { m.inv = "V12"; return '<div class="wrap error-page"><p class="error-code" aria-hidden="true">410</p><h1>This profile has been removed</h1><p class="muted">This page is no longer available and will not return. Search engines are asked not to index it.</p><div class="row" style="margin-top:1.5rem"><a class="btn" href="#/directory">Search the directory</a><a class="btn alt" href="#/complaints">Questions about removals</a></div></div>'; }
    m.inv = "V13"; return W.notFound.fn();
  });

  /* ---------- profile actions ---------- */
  W.act.enquiry = function (id) {
    var p = W.profile(id);
    if (W.S.rate.enquiry >= 3) { W.openModal("Too many enquiries", '<div class="errbox"><p>You have sent several enquiries in a short time. Please try again later.</p></div>'); return; }
    W.openModal("Enquire: " + p.name, '<form data-form="enquiry" class="stack"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Your name", "name", { req: true }) + W.field("Your email", "email", { type: "email", req: true, hint: "Only used to relay the reply." }) + W.field("Message", "message", { type: "textarea", req: true }) + W.bot() + '<p class="small muted">The owner’s email and phone are never shown. <a href="#/privacy">Privacy notice</a></p><button class="btn">Send enquiry</button></form>');
  };
  W.forms.enquiry = function (d) {
    var p = W.profile(d.id); W.S.rate.enquiry++;
    W.S.enquiries.unshift({ id: W.uid("e"), profileId: d.id, name: d.name, email: d.email, message: d.message, at: W.TODAY, read: false });
    W.mail("Staff inbox", "New enquiry via " + W.brand.name + " for " + p.name, "From " + d.name + ". " + (W.origin(p) === "import" ? "Imported profile: staff keep it." : "Staff forward it to the contact address held for this profile.") + " The visitor's email is shown to staff only.", "#/admin/inbox");
    W.save(); W.closeModal(); W.render(); W.toast("Enquiry sent to the team. They pass it on or reply by email.");
  };
  W.act.report = function (id) {
    W.openModal("Report this profile", '<form data-form="report" class="stack"><input type="hidden" name="id" value="' + e(id) + '">' + W.field("Reason", "reason", { type: "select", options: ["Inaccurate information", "Defamatory or harmful", "Privacy concern", "Copyright", "Other"], req: true }) + W.field("Details", "detail", { type: "textarea", req: true }) + W.bot() + '<button class="btn">Send report</button></form>');
  };
  W.forms.report = function (d) {
    W.S.reports.unshift({ id: W.uid("r"), profileId: d.id, reason: d.reason, detail: d.detail, at: W.TODAY, status: "open", owner: "" });
    W.save(); W.closeModal(); W.toast("Report received. A member of staff will look at it.");
  };
  var NEEDS = ["Correct a fact", "Add a right-of-reply note", "Change which contact details are public", "Switch enquiries on or off", "Send me the view count or the sources used"];
  var PARTS = [["name", "Name"], ["title", "Title"], ["org", "Organisation"], ["achievements", "Achievements and awards"], ["bio", "Biography"], ["other", "Something else"]];
  function promises(items) { return '<ul class="timeline-simple">' + items.map(function (t) { return '<li><span class="mono">' + e(t[0]) + "</span><span>" + e(t[1]) + "</span></li>"; }).join("") + "</ul>"; }
  W.route("/claim/:slug", "V57", function (par) {
    var p = W.bySlug(par.slug); if (!p) return W.notFound.fn();
    if (W.isPending(p)) return W.band({ crumbs: [[p.name, "#/profile/" + p.slug], ["Claim"]], title: "Not yet open for claims", lede: "This profile has not been reviewed yet. Once it has, it can be claimed. To fix something now, ask for a correction." }) + '<section class="section--tight"><div class="wrap section--tight"><p><a class="btn" href="#/correct/' + e(p.slug) + '">Suggest a correction</a></p></div></section>';
    if (p.claimed) return W.band({ crumbs: [[p.name, "#/profile/" + p.slug], ["Claim"]], title: "Already claimed", lede: "A claim for this profile has already been approved. To change something, ask for a correction." }) + '<section class="section--tight"><div class="wrap section--tight"><p><a class="btn" href="#/correct/' + e(p.slug) + '">Suggest a correction</a></p></div></section>';
    return W.band({ crumbs: [[p.name, "#/profile/" + p.slug], ["Claim"]], title: "Claim this profile", lede: "Tell us who you are and how we can check it. A member of staff reviews every claim before anything changes. No account is needed to ask." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside prose"><h2 style="margin-top:0">What happens next</h2>' + promises([["1 day", "We acknowledge your claim."], ["5 days", "We decide, after checking your evidence."]]) + '<p class="small muted">If we approve it, a named person on your side can ask us to keep the profile up to date, and staff check every change before it appears. A claim never changes how the profile ranks, and it cannot be bought.</p></div>' +
      '<form data-form="claim" class="form"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Who are you?", "relationship", { type: "select", options: ["The person named on this profile", "An authorised representative of the organisation"], req: true }) + W.field("Your name", "name", { req: true }) + W.field("Your role", "role", { hint: "For example Chief Executive or Communications Manager." }) + W.field("Work email", "email", { type: "email", req: true, hint: "Use an address at the organisation's own domain if you can. It is never public." }) + W.field("Phone (optional)", "phone") + W.field("How can we check this?", "evidence", { type: "textarea", req: true, hint: "A page on the official website that names you, a registration number, or a letter on letterhead." }) + W.field("I agree that WHOISWHO may contact me about this claim.", "consent", { type: "checkbox", req: true }) + W.bot() + '<button class="btn">Send claim</button></form></div></section>';
  });
  W.forms.claim = function (d) {
    var id = "CLM-" + ("0000" + ((W.S.claims || []).length + 1)).slice(-4); W.S.claims = W.S.claims || [];
    W.S.claims.unshift({ id: id, profileId: d.id, name: d.name, relationship: d.relationship, email: d.email, phone: d.phone || "", role: d.role || "", evidence: d.evidence, consent: !!d.consent, status: "new", at: W.TODAY, due: new Date(new Date(W.TODAY).getTime() + 5 * 86400000).toISOString().slice(0, 10), remindAt: new Date(new Date(W.TODAY).getTime() + 3 * 86400000).toISOString().slice(0, 10), by: "", reason: "" });
    W.mail("Support inbox (Editor)", "New claim " + id, d.name + " asks to claim a profile.", "#/admin/claims"); W.mail(d.email, "We received your claim", "Reference " + id + ". We will reply within 5 days.", "");
    W.save(); W.go("#/done?kind=claim&ref=" + id);
  };
  W.route("/correct/:slug", "V39", function (par) {
    var p = W.bySlug(par.slug); if (!p) return W.notFound.fn();
    return W.band({ crumbs: [[p.name, "#/profile/" + p.slug], ["Correction request"]], title: "Ask for a correction", lede: "No account or payment. A member of staff reads every request and edits the profile directly, and every edit is logged." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside prose"><h2 style="margin-top:0">What happens next</h2>' + promises([["1 day", "We acknowledge your request within one business day."], ["7 days", "We resolve corrections within seven days."]]) + '<p class="small muted">Use this form to fix a fact, add a right-of-reply note, choose which contact details show, switch enquiries on or off, or ask for the view count and the sources used.</p></div>' +
      '<form data-form="correct" class="form"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Who are you?", "who", { type: "select", options: ["The person named on this profile", "An authorised representative of the organisation", "Someone else (a visitor)"], req: true }) + W.field("What do you need?", "need", { type: "select", options: NEEDS, req: true }) + W.field("Which part of the profile?", "part", { type: "select", options: PARTS }) + W.field("New value or details", "detail", { type: "textarea", req: true }) + W.field("Source or evidence (link)", "evidence") + W.field("Your email", "contact", { type: "email", req: true, hint: "If you are the person or their representative, we confirm your email with a single-use link." }) + W.bot() + '<button class="btn">Send request</button></form></div></section>';
  });
  W.forms.correct = function (d) {
    var owner = d.who.indexOf("Someone else") !== 0, id = W.uid("cr"), tok = "t-" + W.uid("");
    W.S.corrections.unshift({ id: id, profileId: d.id, kind: "Correction", requesterRole: d.who, need: d.need, detail: d.need + (d.need === NEEDS[0] ? " (" + d.part + ")" : "") + ": " + d.detail, evidence: d.evidence || "", contact: d.contact, emailVerified: !owner, token: tok, at: W.TODAY, due: "2026-11-14", status: owner ? "awaiting email check" : "open" });
    if (owner) W.mail(d.contact, "Confirm your email to send your correction request", "Single-use link. Your request enters the staff queue only after you click.", "#/confirm/" + tok);
    else W.mail(d.contact, "We received your correction", "Reference " + id + ". We will respond within 7 days.", "");
    W.save(); W.go("#/done?kind=" + (owner ? "correctionowner" : "correction") + "&ref=" + id);
  };
  W.route("/remove/:slug", "V40", function (par) {
    var p = W.bySlug(par.slug); if (!p) return W.notFound.fn();
    return W.band({ crumbs: [[p.name, "#/profile/" + p.slug], ["Request removal"]], title: "Ask us to remove a profile", lede: "No account or payment needed. If you are the person or organisation named, this is your right." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside prose"><h2 style="margin-top:0">What happens next</h2>' + promises([["1 day", "We acknowledge your request within one business day."], ["2 days", "We hide the profile within two business days of a valid request from the subject."]]) + '<p class="small muted">Once a valid removal is done, the page returns 410 Gone and leaves search and the sitemap. We keep the name on a list so it is not added again.</p></div>' +
      '<form data-form="remove" class="form"><input type="hidden" name="id" value="' + e(p.id) + '">' + W.field("Who are you in relation to this profile?", "who", { type: "select", options: ["I am the person", "I represent the organisation", "Other"], req: true }) + W.field("Why should it be removed?", "detail", { type: "textarea", req: true }) + W.field("Your email", "contact", { type: "email", req: true }) + W.bot() + '<button class="btn bad">Request removal</button></form></div></section>';
  });
  W.forms.remove = function (d) {
    var id = W.uid("rm"); W.S.removals.unshift({ id: id, profileId: d.id, kind: "Removal", detail: d.detail, contact: d.contact, at: W.TODAY, due: "2026-11-09", status: "open", who: d.who });
    W.mail(d.contact, "We received your removal request", "Reference " + id + ". Acknowledged within 1 business day.", "");
    W.save(); W.go("#/done?kind=removal&ref=" + id);
  };
  function okPage(title, text, ref, actions) { return '<div class="wrap wrap--text section"><div class="stack"><div class="eyebrow">Done</div><h1>' + e(title) + '</h1><p style="font-size:var(--t-md)">' + e(text) + "</p>" + (ref ? '<p>Your reference: <strong class="mono" style="font-size:var(--t-sm)">' + e(ref) + "</strong></p>" : "") + '<div class="row">' + actions + "</div></div></div>"; }
  W.okPage = okPage;
  W.route("/done", "V41", function (p, q) {
    var t = { correction: ["Correction received", "We will acknowledge within 1 business day and resolve within 7 days."], removal: ["Removal request received", "We will acknowledge within 1 business day. A valid request from the subject hides the profile within 2 business days."], contact: ["Message sent", "A member of staff will reply. The message is logged."], claim: ["Claim received", "We will acknowledge within 1 business day and decide within 5 days. Nothing changes on the profile until a person checks your evidence."], digest: ["You are subscribed", "We will email you new stories. Every email has an unsubscribe link."], enquiry: ["Enquiry received", "Our team will contact you. Terms are agreed offline; nothing is charged on this site."], dsr: ["Request received", "We will respond within the legal deadline. A reference was emailed."], correctionowner: ["Check your email to finish your request", "We sent a single-use link to confirm your email. After you click it, the request reaches staff. Nothing changes on the profile until they act on it."], signup: ["You are on the list", "We emailed you to confirm. You can unsubscribe at any time."] }[q.kind] || ["Done", ""];
    return okPage(t[0], t[1], q.ref, '<a class="btn" href="#/">Back to home</a><button type="button" class="btn alt" data-act="outbox">See the email sent</button>');
  });
  W.route("/confirm/:token", "V51", function (par) {
    var r = W.S.corrections.filter(function (x) { return x.token === par.token; })[0];
    if (r) {
      if (!r.emailVerified) { r.emailVerified = true; r.status = "open"; W.S.used[par.token] = true; W.log("correction.email", r.id, "email confirmed by signed link"); W.save(); }
      return okPage("Email confirmed", "Your request has reached our staff. Nothing changes on the public page until a person acts on it. This link no longer works a second time.", "", '<a class="btn" href="#/">Back to home</a>');
    }
    return W.expiredPage();
  });

  /* ---------- stories ---------- */
  var TYPES = ["Interview", "Feature", "Spotlight", "Opinion", "Announcement", "News"];
  function metaTail(a) { return "<span>" + readMins(a) + " min read</span>"; }
  /* The stories page reads like a modern magazine or newsletter: a masthead with an email sign-up, filter pills, one lead story, a three-column grid, and a sign-up block part-way down (D-056). */
  function readMins(a) { var w = [a.body].concat(a.paras || []).join(" ").split(/\s+/).length; return Math.max(2, Math.round(w / 180)); }
  function blCard(a, i) {
    var sp = W.sponsorOfArticle(a);
    return '<li class="bl-card" data-reveal style="--i:' + (i % 3) + '"><a href="#/stories/' + e(a.slug) + '">' + W.img("story", a.seed || W.seedFor(a.slug), 640, 420, "Placeholder image for the story: " + a.title, "bl-img") +
      '<span class="bl-meta"><b>' + e(a.type) + "</b><span>" + e(W.fmtDate(W.storyDate(a))) + "</span>" + metaTail(a) + (sp ? W.badge.sponsored(sp) : "") + '</span><h3 class="bl-title">' + e(a.title) + '</h3><p class="bl-lede">' + e(a.lede || "") + '</p><span class="bl-by">By ' + e(a.author) + "</span></a></li>";
  }
  function digestForm(cls) {
    var id = "bl-mail" + (cls ? "2" : "");
    return '<form class="bl-sub ' + (cls || "") + '" data-form="digest" aria-label="Get the stories by email"><label class="sr" for="' + id + '">Email address</label><input id="' + id + '" name="email" type="email" required autocomplete="email" placeholder="Your email address"><button class="btn" type="submit">Subscribe</button></form>';
  }
  W.route("/stories", "V16", function (p, q) {
    var all = W.publicArticles().filter(function (a) { return (!q.type || a.type === q.type) && (!q.industry || a.industry === q.industry); });
    /* The paid slot is one labelled card above the grid. The other stories keep exactly the order they would have without it (REQ-263). */
    var slot = W.slotFor("Stories"); if (slot && all.indexOf(slot) < 0) slot = null;
    var rows = all.filter(function (a) { return !slot || a.id !== slot.id; }).sort(function (a, b) {
      return a.date < b.date ? 1 : -1;
    });
    var slotHtml = slot ? '<aside class="bl-slot" aria-label="Sponsored story"><a href="#/stories/' + e(slot.slug) + '">' + W.img("story", slot.seed || W.seedFor(slot.slug), 900, 600, "Placeholder image for the story: " + slot.title, "bl-slot-img") + '<span class="bl-slot-text"><span class="bl-slot-label">' + W.ico("tag") + "Sponsored by " + e(W.sponsorOfArticle(slot)) + '</span><span class="bl-meta"><b>' + e(slot.type) + "</b><span>" + e(W.fmtDate(W.storyDate(slot))) + "</span>" + metaTail(slot) + '</span><span class="bl-slot-title">' + e(slot.title) + '</span><span class="bl-lede">' + e(slot.lede || "") + "</span></span></a></aside>" : "";
    var pills = '<nav class="bl-pills" aria-label="Story types"><a href="#/stories' + (q.industry ? W.qs({ industry: q.industry }) : "") + '"' + (!q.type ? ' aria-current="page"' : "") + ">All</a>" + TYPES.map(function (t) { return '<a href="#/stories' + W.qs({ type: t, industry: q.industry || "" }) + '"' + (q.type === t ? ' aria-current="page"' : "") + ">" + e(t) + "</a>"; }).join("") + "</nav>";
    var filter = W.filterBar({ path: "#/stories", label: "Filter stories", keep: keep(q, ["industry"]), controls: [{ name: "industry", label: "Industry", blank: "All industries", options: W.LISTS.industries, value: q.industry }], count: all.length + " stor" + (all.length === 1 ? "y" : "ies"), clear: !!(q.type || q.industry) });
    var mosaic = rows.slice(0, 3), rest = rows.slice(3), cut = Math.min(6, rest.length), first = rest.slice(0, cut), later = rest.slice(cut);
    function tile(a, big) {
      var sp = W.sponsorOfArticle(a);
      return '<article class="bl-tile' + (big ? " bl-tile--big" : "") + '" data-reveal><a href="#/stories/' + e(a.slug) + '">' + W.img("story", a.seed || W.seedFor(a.slug), big ? 1200 : 700, big ? 1000 : 520, "Placeholder image for the story: " + a.title, "bl-tile-img") +
        '<span class="bl-tile-text"><span class="bl-meta"><b>' + e(a.type) + "</b><span>" + e(W.fmtDate(W.storyDate(a))) + "</span>" + metaTail(a) + (sp ? W.badge.sponsored(sp) : "") + '</span><span class="bl-tile-title">' + e(a.title) + "</span>" + (big ? '<span class="bl-tile-lede">' + e(a.lede || "") + "</span>" : "") + "</span></a></article>";
    }
    var mid = '<aside class="bl-news" aria-label="Stories by email"><p class="bl-kick">The stories, by email</p><h2>Get new stories in your inbox.</h2><p>Interviews, features and news from the record, when they are published. No spam, and you can leave at any time.</p>' + digestForm("bl-sub--dark") + "</aside>";
    return '<section class="bl-body"><div class="wrap"><div class="bl-top"><h1>Stories</h1>' + pills + "</div>" + filter + slotHtml +
      (rows.length ? '<div class="bl-mosaic">' + mosaic.map(function (a, i) { return tile(a, i === 0); }).join("") + "</div>" + (first.length ? '<ul class="bl-grid">' + first.map(blCard).join("") + "</ul>" : "") + mid + (later.length ? '<ul class="bl-grid">' + later.map(function (a, i) { return blCard(a, i); }).join("") + "</ul>" : "") : W.empty("No stories match", "Try another type or industry.", '<a class="btn" href="#/stories">Clear filters</a>')) + "</div></section>";
  });
  W.forms.digest = function (d) {
    var id = "SUB-" + ("000" + (W.S.subscribers.length + 1)).slice(-4);
    W.S.subscribers.unshift({ id: W.uid("s"), ext: id, email: d.email, at: W.TODAY });
    W.mail(d.email, "You are subscribed to the stories", "Sample email. Reference " + id + ".", ""); W.save(); W.go("#/done?kind=digest&ref=" + id);
  };
  var COMMENTS = [["Adaeze O.", "2 days ago", "This is the clearest account of same-day settlement I have read. The point about small merchants waiting days for their own money is the right one."], ["Kwame B.", "yesterday", "Would love to see the sources for the merchant figures. Otherwise a useful read."]];
  W.forms.comment = function (d) { (W.S.comments = W.S.comments || []).push({ story: d.story, name: d.name, email: d.email, text: d.text, at: W.TODAY, status: "awaiting moderation" }); W.mail(d.email, "We received your comment", "It will appear once our team has approved it.", ""); W.save(); W.toast("Thank you. Your comment will appear once it is approved."); W.render(); };
  W.route("/stories/:slug", "V17", function (par, q, m) {
    var a = W.S.articles.filter(function (x) { return x.slug === par.slug; })[0], staff = W.S.role !== "visitor";
    if (!a || (a.status !== "published" && !staff)) { m.inv = "V13"; return W.notFound.fn(); }
    var sp = W.sponsorOfArticle(a), others = W.publicArticles().filter(function (x) { return x.id !== a.id; }).slice(0, 3);
    var people = a.profiles.map(W.profile).filter(function (x) { return x && W.isPublic(x); });
    return (a.status !== "published" ? W.staffBanner("Staff preview: " + e(a.status) + " article, not public.") : "") + '<article><header class="wrap wrap--text article-head">' + W.crumbs([["Stories", "#/stories"], [a.type]]) + '<div class="story-meta" style="margin-top:1rem"><b>' + e(a.type) + "</b><span>" + e(W.fmtDate(W.storyDate(a))) + "</span><span>" + e(a.industry) + "</span>" + (sp ? W.badge.sponsored(sp) : "") + "</div><h1>" + e(a.title) + '</h1><p class="article-lede">' + e(a.lede || "") + '</p><p class="muted small" style="margin-top:.75rem">By ' + e(a.author) + "</p></header>" +
      '<div class="wrap">' + W.img("story", a.seed || W.seedFor(a.slug), 1400, 620, "Placeholder image for the story: " + a.title, "article-cover ratio-21x9") + '</div><div class="wrap wrap--text article-body">' +
      (sp ? '<div class="notice notice--sponsored" style="font-size:var(--t-sm);line-height:1.5">' + W.ico("tag") + "<div><strong>Sponsored by " + e(sp) + ".</strong> The sponsor paid for this story's placement. The sponsor did not write it and has no influence over any label, ranking or award.</div></div>" : "") +
      "<p>" + e(a.body) + "</p>" + (a.paras || []).slice(0, 1).map(function (x) { return "<p>" + e(x) + "</p>"; }).join("") + (a.quote ? "<blockquote>" + e(a.quote) + "</blockquote>" : "") + (a.paras || []).slice(1).map(function (x) { return "<p>" + e(x) + "</p>"; }).join("") + "</div></article>" +
      relatedEvent(a) +
      (people.length ? '<section class="section section--alt"><div class="wrap">' + W.sectionHead("On the record", "Profiles in this story") + W.grid(people.map(W.card)) + "</div></section>" : "") +
      '<section class="section st-comments" aria-labelledby="stCm"><div class="wrap wrap--text"><p class="np-kicker">Join in</p><h2 id="stCm">Comments</h2><ol class="st-cm-list">' + COMMENTS.map(function (c) { return '<li><div class="st-cm-av" aria-hidden="true">' + e(c[0].charAt(0)) + '</div><div><p class="st-cm-by"><strong>' + e(c[0]) + '</strong> <span class="muted small">' + e(c[1]) + '</span></p><p>' + e(c[2]) + '</p></div></li>'; }).join("") + '</ol><form class="form st-cm-form" data-form="comment"><input type="hidden" name="story" value="' + e(a.id) + '"><h3 style="font-size:var(--t-lg)">Leave a comment</h3>' + W.field("Comment", "text", { type: "textarea", req: true }) + W.field("Name", "name", { req: true }) + W.field("Email", "email", { type: "email", req: true, hint: "Never shown. Used to tell you when your comment is approved." }) + W.field("I agree that WHOISWHO stores my name and email to show and manage this comment.", "consent", { type: "checkbox", req: true }) + W.bot() + '<p class="small muted">Comments are checked by our team before they appear. We remove anything abusive, defamatory or off topic. Comments run on the wpDiscuz plugin in the WordPress build.</p><button class="btn">Post comment</button></form></div></section>' +
      '<section class="section"><div class="wrap">' + W.sectionHead("Keep reading", "More stories", ["All stories", "#/stories"]) + W.grid(others.map(function (x) { return W.articleCard(x); })) + "</div></section>";
  });

  /* ---------- events (REQ-262, REQ-264, REQ-265) ---------- */
  /* An event is its own post type. It has a date, a place and an organiser. Registration is on the organiser's site; WHOISWHO takes no payment (D-005). */
  function evDate(v) { var d = v.start.slice(0, 10); return '<span class="ev-date" aria-hidden="true"><b>' + Number(d.slice(8, 10)) + "</b>" + e(W.fmtDate(d).split(" ")[1]) + "</span>"; }
  var MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
  function evStatus(v) { return W.eventState(v) === "past" ? "Past" : v.venue === "Online" ? "Online" : "Upcoming"; }
  /* One agenda row, used on the Events page, under an event, and on Home. A big date, the title, where and who, a status tag. */
  function evRow(v, compact) {
    var d = v.start.slice(0, 10), past = W.eventState(v) === "past", st = evStatus(v);
    return '<li class="ag-row' + (past ? " is-past" : "") + (compact ? " ag-row--compact" : "") + '"><a class="ag-link" href="#/events/' + e(v.slug) + '" data-cursor="Open"><span class="ag-date" aria-hidden="true"><b>' + Number(d.slice(8, 10)) + "</b>" + e(MONTHS[Number(d.slice(5, 7)) - 1].slice(0, 3)) + '</span><span class="ag-main"><span class="ag-title">' + e(v.title) + '</span><span class="ag-meta">' + e(W.eventWhere(v)) + " · " + e((v.organiser || "").replace(/ \(sample organiser\)/, "")) + '</span></span><span class="ag-tag ag-tag--' + st.toLowerCase() + '">' + st + "</span>" +
      (compact ? "" : '<span class="ag-thumb" aria-hidden="true">' + W.img("story", v.seed || W.seedFor(v.slug), 240, 160, "", "ag-img") + "</span>") + W.ico("arrow") + "</a></li>";
  }
  W.evRow = evRow;
  /* Rows grouped by month, each month under its own heading. */
  function evAgenda(list) {
    var groups = [], idx = {};
    list.forEach(function (v) { var k = v.start.slice(0, 7); if (!(k in idx)) { idx[k] = groups.length; groups.push({ k: k, rows: [] }); } groups[idx[k]].rows.push(v); });
    return groups.map(function (g) { return '<section class="ag-month" aria-label="' + e(MONTHS[Number(g.k.slice(5, 7)) - 1] + " " + g.k.slice(0, 4)) + '"><h3 class="ag-month-h"><span>' + e(MONTHS[Number(g.k.slice(5, 7)) - 1]) + "</span> " + e(g.k.slice(0, 4)) + '</h3><ul class="ag-list">' + g.rows.map(function (v) { return evRow(v); }).join("") + "</ul></section>"; }).join("");
  }
  /* The Related event card at the end of a story that links one (REQ-265). A draft event is never shown to the public. */
  function relatedEvent(a) {
    var v = a.relatedEvent ? W.find("events", a.relatedEvent) : null;
    if (!v || (v.status !== "published" && W.S.role === "visitor")) return "";
    var past = W.eventState(v) === "past";
    return '<aside class="section ev-related" aria-labelledby="evRel"><div class="wrap wrap--text"><h2 id="evRel" class="np-kicker">Related event</h2><a class="ev-rel-card" href="#/events/' + e(v.slug) + '">' + evDate(v) + '<span class="ev-what"><strong>' + e(v.title) + "</strong><span>" + e(W.eventWhen(v)) + " · " + e(W.eventWhere(v)) + "</span><span>" + (past ? "This event has ended" : "Organised by " + e(v.organiser)) + "</span></span>" + W.ico("arrow") + "</a></div></aside>";
  }
  W.route("/events", "V58", function (p, q) {
    var ok = function (v) { return (!q.city || v.city === q.city) && (!q.industry || v.industry === q.industry); };
    var up = W.upcomingEvents().filter(ok), past = W.pastEvents().filter(ok), n = up.length + past.length;
    var cities = W.publicEvents().map(function (v) { return v.city; }).filter(function (c, i, a) { return c && a.indexOf(c) === i; }).sort();
    var inds = W.publicEvents().map(function (v) { return v.industry; }).filter(function (c, i, a) { return c && a.indexOf(c) === i; }).sort();
    var next = up[0];
    var hero = '<section class="ev-hero"><div class="wrap ev-hero-in"><div class="ev-hero-text"><p class="ev-kick">Calendar</p><h1>Events</h1><p class="ev-lede">Conferences, forums and open days across the continent. Each event links to its organiser, who handles registration and any fee.</p></div>' +
      (next ? '<a class="ev-next" href="#/events/' + e(next.slug) + '" data-cursor="Open"><span class="ev-next-k">Next up</span><span class="ev-next-d"><b>' + Number(next.start.slice(8, 10)) + "</b>" + e(MONTHS[Number(next.start.slice(5, 7)) - 1]) + '</span><span class="ev-next-t">' + e(next.title) + '</span><span class="ev-next-m">' + e(W.eventWhere(next)) + '</span><span class="ev-next-go">View event' + W.ico("arrow") + "</span></a>" : "") + "</div></section>";
    var bar = W.filterBar({ path: "#/events", label: "Filter events", controls: [{ name: "city", label: "City", blank: "All cities", options: cities, value: q.city }, { name: "industry", label: "Industry", blank: "All industries", options: inds, value: q.industry }], count: n + " event" + (n === 1 ? "" : "s"), clear: !!(q.city || q.industry) });
    return hero + '<section class="ev-body"><div class="wrap">' + bar +
      (up.length ? '<h2 class="ev-h">Upcoming</h2>' + evAgenda(up) : W.empty("No upcoming events match", "Try another city or industry.", '<a class="btn" href="#/events">Clear filters</a>')) +
      (past.length ? '<h2 class="ev-h ev-h--past">Past events</h2><div class="ag-past">' + evAgenda(past) + "</div>" : "") + "</div></section>";
  });
  /* Event details: when, where, who runs it, and a link to register on the organiser's site. No payment is taken here (D-005). */
  function evBlock(v) {
    var past = W.eventState(v) === "past";
    return '<aside class="ev-card" aria-label="Event details"><h2>' + (past ? "This event has ended" : "Event details") + '</h2><dl class="ev-dl"><div><dt>When</dt><dd>' + e(W.eventWhen(v)) + "</dd></div><div><dt>Where</dt><dd>" + e(W.eventWhere(v)) + "</dd></div><div><dt>Organiser</dt><dd>" + e(v.organiser) + "</dd></div>" + (v.price ? "<div><dt>Cost</dt><dd>" + e(v.price) + "</dd></div>" : "") + "</dl>" +
      (past ? "" : '<a class="btn ev-reg" href="' + e(v.regUrl) + '" target="_blank" rel="noopener noreferrer">Register on the organiser’s site' + W.ico("arrow") + "</a>") + '<p class="small muted">Registration and any fee are handled by the organiser. WHOISWHO takes no payment and does not sell tickets.</p></aside>';
  }
  W.route("/events/:slug", "V59", function (par, q, m) {
    var v = (W.S.events || []).filter(function (x) { return x.slug === par.slug; })[0], staff = W.S.role !== "visitor";
    if (!v || (v.status !== "published" && !staff)) { m.inv = "V13"; return W.notFound.fn(); }
    var rel = W.storiesOfEvent(v), more = W.upcomingEvents().filter(function (x) { return x.id !== v.id; }).slice(0, 3), past = W.eventState(v) === "past", d = v.start.slice(0, 10);
    var poster = '<header class="ev-poster' + (past ? " is-past" : "") + '"><div class="ev-poster-img" aria-hidden="true">' + W.img("story", v.seed || W.seedFor(v.slug), 900, 700, "", "ev-poster-pic") + '</div><div class="wrap ev-poster-in">' + W.crumbs([["Events", "#/events"], [v.title]]) +
      '<div class="ev-poster-row"><div class="ev-poster-date" aria-label="' + e(W.fmtDate(d)) + '"><b>' + Number(d.slice(8, 10)) + "</b><span>" + e(MONTHS[Number(d.slice(5, 7)) - 1]) + " " + e(d.slice(0, 4)) + '</span></div><div class="ev-poster-main"><span class="ag-tag ag-tag--' + evStatus(v).toLowerCase() + '">' + evStatus(v) + "</span><h1>" + e(v.title) + '</h1><p class="ev-poster-meta">' + e(W.eventWhere(v)) + " · Organised by " + e(v.organiser) + "</p></div></div></div></header>";
    return (v.status !== "published" ? W.staffBanner("Staff preview: " + e(v.status) + " event, not public.") : "") + poster +
      '<section class="ev-layout"><div class="wrap ev-layout-in"><div class="ev-side">' + evBlock(v) + '</div><div class="ev-text prose"><p class="ev-sum">' + e(v.summary || "") + "</p><p>" + e(v.body) + "</p>" + (v.paras || []).map(function (x) { return "<p>" + e(x) + "</p>"; }).join("") +
      (rel.length ? '<h2 class="ev-h2">Related stories</h2><ul class="ev-rel-list">' + rel.map(function (x) { return '<li><a href="#/stories/' + e(x.slug) + '">' + e(x.title) + '</a><span class="muted small"> ' + e(x.type) + "</span></li>"; }).join("") + "</ul>" : "") + "</div></div></section>" +
      (more.length ? '<section class="section ev-more"><div class="wrap">' + W.sectionHead("More to come", "More events", ["All events", "#/events"]) + '<ul class="ag-list">' + more.map(function (x) { return evRow(x); }).join("") + "</ul></div></section>" : "");
  });

  /* ---------- get listed, contact ---------- */
  W.route("/subscribe", "V24", function () {
    return W.band({ eyebrow: "Work with us", title: "Get listed", lede: "Tell us what you are looking for. We reply with terms agreed offline. Nothing is charged on this site, and every listing has the same format." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside"><div class="prose"><h2 style="margin-top:0">What we can do</h2><ul><li>Create or improve your listing</li><li>Sponsored stories, always labelled</li><li>Media and editorial work</li><li>Awards sponsorship</li></ul></div></div><form class="form" data-form="bizenquiry"><h2 style="font-size:var(--t-xl)">Send an enquiry</h2>' + W.field("Your name", "name", { req: true }) + W.field("Organisation", "org") + W.field("Interested in", "interest", { type: "select", options: ["Listing a profile", "Sponsored story", "Media / editorial work", "Awards sponsorship", "Not sure"], req: true }) + W.field("Email or phone", "contact", { req: true }) + W.field("Anything else?", "msg", { type: "textarea" }) + W.field('I have read the <a href="#/privacy">privacy notice</a> and agree to be contacted about this enquiry.', "consent", { type: "checkbox", req: true }) + W.bot() + '<button class="btn">Send enquiry</button></form></div></section>';
  });
  W.forms.bizenquiry = function (d) {
    var id = "ENQ-" + ("000" + (W.S.enquiries.filter(function (x) { return x.business; }).length + 1)).slice(-4);
    W.S.enquiries.unshift({ id: W.uid("e"), ref: id, business: true, kind: d.interest, profileId: "", name: d.name, org: d.org || "", email: d.contact, message: d.msg || "", at: W.TODAY, read: false, general: true });
    W.mail("Support inbox (Editor)", "New enquiry: " + d.name, d.interest + " from " + (d.org || "an individual") + ".", "#/admin/inbox"); W.mail(d.contact, "We received your enquiry", "Reference " + id + ".", "");
    W.save(); W.go("#/done?kind=enquiry&ref=" + id);
  };
  W.route("/contact", "V26", function () {
    return W.band({ eyebrow: "Contact", title: "Talk to the team", lede: "A person reads every message. To change a profile, the quickest way is the correction form on that profile." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside prose"><h2 style="margin-top:0">Looking for something specific?</h2><ul><li><a href="#/search">Find a profile</a> to correct or remove it</li><li><a href="#/subscribe">Get listed</a></li><li><a href="#/complaints">Complaints and takedown</a></li><li><a href="#/data-rights">Your data rights</a></li></ul></div><form class="form" data-form="contact">' + W.field("Name", "name", { req: true }) + W.field("Email", "email", { type: "email", req: true }) + W.field("Message", "msg", { type: "textarea", req: true }) + W.bot() + '<button class="btn">Send message</button></form></div></section>';
  });
  W.forms.contact = function (d) {
    var id = W.uid("msg"); W.S.enquiries.unshift({ id: id, profileId: "", name: d.name, email: d.email, message: d.msg, at: W.TODAY, read: false, general: true });
    W.mail("Staff inbox", "Contact form: " + d.name, d.msg, "#/admin/inbox"); W.save(); W.go("#/done?kind=contact&ref=" + id);
  };

  /* ---------- legal and trust pages ---------- */
  var LEGAL_NOTE = "Draft sample text for the prototype. Legal supplies the final wording.";
  function legal(inv, path, title, intro, secs) {
    W.route(path, inv, function () { return W.band({ light: true, eyebrow: "Legal", title: title, lede: intro }) + '<section class="section--tight"><div class="wrap section--tight"><div class="prose">' + W.draftNote(LEGAL_NOTE) + secs.map(function (s) { return "<h2>" + e(s[0]) + "</h2><p>" + s[1] + "</p>"; }).join("") + "</div></div></section>"; });
  }
  legal("V29", "/privacy", "Privacy policy", "What we hold, why we hold it, and what you can ask us to do. Version 0.1.", [["Whose data we hold", "People and organisations named in the directory, whether compiled from public sources or supplied by them; people who nominate, enquire or contact us; and staff."], ["Why we hold it", "To publish an accurate professional record, on the basis of legitimate interests with a right to object. Legal will confirm the basis for each group."], ["Your rights", 'You can ask for access, correction, erasure, objection and portability. <a href="#/data-rights">Make a request</a>.'], ["How long we keep it", "We keep each kind of data for a set period, published here once the retention schedule is agreed."], ["Who processes it", "Our web host, email provider and analytics provider. A full list is held internally and shared on request."]]);
  legal("V30", "/terms", "Terms and conditions", "The rules for using this site and submitting content to it.", [["Using the site", "You may read, search and link to the directory. Bulk copying and automated collection are not allowed."], ["Submitted content", "If you send us content, you confirm you have the right to do so and that it is accurate, and you grant us a licence to publish it."], ["Liability", "We take care to check the record, and we correct it when it is wrong. We cannot promise it is free of error."]]);
  legal("V31", "/cookies", "Cookie policy", "Essential cookies always. Analytics only after you agree.", [["Essential", "These keep the site working and secure, and they remember your cookie choice."], ["Analytics", "Privacy-friendly visit counts. They load only after you accept, and you can change your mind using Cookie preferences in the footer."]]);
  W.route("/faq", "V28", function () {
    var G = [
      ["Being listed", [
        ["How do I get listed?", 'We compile profiles from public sources, and you can ask us to create or improve yours with <a href="#/subscribe">a short enquiry</a>. Our team reviews each profile. A person’s profile goes public only after that review.'],
        ["Does it cost anything?", "Being listed is free. Paid options, such as a subscription or a sponsored story, are agreed with our team and never on this site. They do not change how a profile is ranked, and they do not affect who can be nominated or who wins."],
        ["There is a profile of me that I did not create. Why?", "Many profiles are compiled from public sources such as company registries, official websites and annual reports. You can correct it, claim it, or ask for it to be removed, with no account and no fee."],
        ["How do I claim my profile?", 'Open your profile and choose “Claim this profile”. Tell us who you are and how we can check it. We reply within five days. Once a claim is approved, a named person on your side can ask us to keep the profile up to date, and our team checks every change.']
      ]],
      ["Accuracy and removal", [
        ["Something on a profile is wrong. How do I fix it?", "Open the profile and choose “Suggest a correction”. We acknowledge it within one business day and resolve it within seven days. No account is needed."],
        ["Where do your facts come from?", "From public sources: registries, official websites, annual reports and news coverage. Each fact on a profile shows its source and the date we retrieved it, so you can check it yourself."],
        ["What does “Compiled automatically” mean?", "Some organisation profiles go online before a person has reviewed them. Until our team has checked each fact against its source, the profile carries the label “Compiled automatically from public sources. Human review pending.”"],
        ["Can I have my profile removed?", 'Yes. Open the profile and choose “Request removal”, or read <a href="#/complaints">our complaints and takedown policy</a>. When the request comes from the person or their representative, we hide the profile within two business days and keep the name on a list so it is not added again.'],
        ["Can I pay for a better position in search or lists?", "No. Results are ordered by relevance or by name, never by payment."]
      ]],
      ["Awards", [
        ["Who can nominate someone?", "Anyone. Nominating is free and needs no account. We check your email address, and your name is never shown to the nominee or the public. The nominee is asked to accept before anything is published."],
        ["How are winners chosen?", 'Nominations are screened against the published rules. Independent judges then score each nominee on impact, achievement, credibility and integrity, and leadership or innovation. <a href="#/awards/rules">Read the rules and criteria</a>.'],
        ["Can I pay to be nominated or to win?", "No. Judges never see payment, subscription or sponsorship information, and sponsors have no influence over who is nominated or who wins."]
      ]],
      ["Stories", [
        ["What is a sponsored story?", "A story whose placement a sponsor paid for: a labelled slot on the home page or the stories page for set dates. It is always marked “Sponsored by” with the sponsor’s name. The sponsor does not write it. It never changes the order of other stories, search results, profiles, labels, nominations or winners."]
      ]],
      ["Your data", [
        ["Do I need an account?", "No. There are no public accounts. Everything on this site, including corrections, claims and nominations, works without one."],
        ["What do you hold about me, and how do I see or change it?", 'We hold what is on your public profile and the sources behind it, plus any messages you send us. You can ask to see, correct, erase or object to it on <a href="#/data-rights">your data rights</a> page. We track every request with a due date.']
      ]]
    ];
    var n = 0;
    return W.band({ light: true, eyebrow: "Questions", title: "Frequently asked questions", lede: "Short answers about listing, accuracy, awards and your data." }) + '<section class="section--tight"><div class="wrap section--tight"><div class="faq faq--groups prose" style="max-width:48rem">' + G.map(function (g) {
      return '<section class="faq-group"><h2>' + e(g[0]) + "</h2>" + g[1].map(function (x) { return "<details" + (n++ === 0 ? " open" : "") + "><summary>" + e(x[0]) + W.ico("chevron") + "</summary><div><p>" + x[1] + "</p></div></details>"; }).join("") + "</section>";
    }).join("") + '<p class="faq-more">Still have a question? <a href="#/contact">Contact the team</a>.</p></div></div></section>';
  });
  W.route("/complaints", "V35", function () {
    return W.band({ tone: "blue", eyebrow: "Trust", title: "Complaints and takedown policy", lede: "Defamation, privacy, copyright or accuracy concerns can be raised without an account and without paying." }) +
      '<section class="section"><div class="wrap split split--even"><div class="prose"><h2 style="margin-top:0">Our commitments</h2>' + promises([["1 day", "We acknowledge every complaint within one business day."], ["2 days", "We hide a profile within two business days of a valid removal request from the subject."], ["7 days", "We resolve corrections within seven days."]]) + '</div><div class="prose"><h2 style="margin-top:0">How to raise a concern</h2><p>Open the profile and use Suggest a correction, Request removal or Report this profile. Disputes go to an Editor.</p><p>A valid removal returns 410 Gone, removes the page from search and the sitemap, and keeps the name on a list so it is not added again.</p><div class="row"><a class="btn" href="#/directory">Find a profile</a><a class="btn alt" href="#/contact">Contact us</a></div><p class="small muted">Draft sample text. Legal supplies the final wording.</p></div></div></section>';
  });
  W.route("/data-rights", "V50", function () {
    return W.band({ tone: "blue", eyebrow: "Trust", title: "Your data rights", lede: "Ask to access, correct, erase, object to or port your personal data. We track every request with a due date." }) +
      '<section class="section"><div class="wrap form-grid"><div class="form-aside prose"><h2 style="margin-top:0">Before you start</h2><p>To change or remove something on a public profile, the fastest route is the form on that profile.</p><p class="small muted">Draft sample text. Legal supplies the final wording.</p></div><form class="form" data-form="dsr">' + W.field("Request type", "kind", { type: "select", options: ["Access", "Correction", "Erasure", "Objection", "Portability"], req: true }) + W.field("Your name", "name", { req: true }) + W.field("Your email", "email", { type: "email", req: true }) + W.field("Details", "detail", { type: "textarea" }) + W.bot() + '<button class="btn">Submit request</button></form></div></section>';
  });
  W.forms.dsr = function (d) {
    var id = W.uid("d"); W.S.dsr.unshift({ id: id, kind: d.kind, who: d.name, at: W.TODAY, due: "2026-12-07", status: "open" });
    W.mail(d.email, "Data request received", "Reference " + id + ". Due 2026-12-07.", ""); W.save(); W.go("#/done?kind=dsr&ref=" + id);
  };
  W.route("/unsubscribe", "V45", function (p, q) {
    if (q.done === "1") return okPage("You are unsubscribed", "We will not email you again about this list.", "", '<a class="btn" href="#/">Back to home</a>');
    return '<div class="wrap wrap--text section"><div class="stack"><div class="eyebrow">Emails</div><h1>Unsubscribe</h1><p>One click. No account needed.</p>' + W.btn("Unsubscribe me", "unsub", "", "") + "</div></div>";
  });
  W.act.unsub = function () { W.S.signups = []; W.save(); W.go("#/unsubscribe?done=1"); };
  W.route("/sitemap.xml", "V48", function () {
    var pubs = W.publicProfiles().filter(function (p) { return !W.isPending(p); });
    var urls = ["/", "/about", "/directory", "/people", "/entrepreneurs", "/businesses", "/institutions", "/industries", "/awards", "/stories", "/events", "/subscribe", "/nominate", "/contact"].concat(pubs.map(function (p) { return "/profile/" + p.slug; })).concat(W.publicArticles().map(function (a) { return "/stories/" + a.slug; })).concat(W.publicEvents().map(function (v) { return "/events/" + v.slug; }));
    var excl = W.S.profiles.filter(function (p) { return !W.isPublic(p) || W.isPending(p); });
    return W.band({ light: true, eyebrow: "Preview", title: "sitemap.xml", lede: urls.length + " URLs listed, built from the same visibility rule as pages and search." }) +
      '<section class="section--tight"><div class="wrap section--tight split split--even"><div><h2 style="font-size:var(--t-xl);margin-bottom:.75rem">Listed</h2><pre class="mono panel" style="max-height:30rem" tabindex="0">' + urls.map(function (u) { return "&lt;url&gt;&lt;loc&gt;https://" + e(W.brand.domain) + e(u) + "&lt;/loc&gt;&lt;/url&gt;"; }).join("\n") + '</pre></div><div class="stack"><h2 style="font-size:var(--t-xl)">Left out (' + excl.length + ")</h2>" + W.table(["Profile", "Status", "Visitors get"], excl.map(function (p) { return [e(p.name), W.isPending(p) ? '<span class="pill">review pending</span>' : W.stPill(p.status), W.isPending(p) ? "200 + noindex" : W.vis(p) === "gone" ? "410 + noindex" : "404"]; })) + '<h2 style="font-size:var(--t-xl)">robots.txt</h2><pre class="mono panel">User-agent: *\nAllow: /\nSitemap: https://' + e(W.brand.domain) + "/sitemap.xml</pre></div></div></section>";
  });

  /* ---------- design review: every public page and state in one list ---------- */
  var REVIEW = [
    ["Home and about", [["V01", "Home", "#/"], ["V02", "About", "#/about"], ["V28", "Questions", "#/faq"], ["V26", "Contact", "#/contact"], ["V24", "Get listed", "#/subscribe"]]],
    ["Directory", [["V52", "All profiles: search, filters, grid", "#/directory"], ["V52", "All profiles: list view", "#/directory?view=list"], ["V03", "People", "#/people"], ["V53", "Entrepreneurs", "#/entrepreneurs"], ["V04", "Businesses", "#/businesses"], ["V05", "Institutions", "#/institutions"], ["V06", "Industries", "#/industries"], ["V07", "Industry page", "#/industries/Technology"], ["V08", "Search results", "#/search?q=lagos"], ["V38", "Search: no results", "#/search?q=zzzz"], ["V52", "Directory: empty state", "#/directory?q=zzzz"]]],
    ["Profile types", [["V09", "Individual profile", "#/profile/tunde-achebe-wanjala"], ["V09", "Individual, supplied by the person", "#/profile/ngozi-balogun-mensah"], ["V54", "Entrepreneur profile", "#/profile/chidi-sesay-abara"], ["V10", "Business profile", "#/profile/savanna-loop-agritech"], ["V11", "Institution profile", "#/profile/oyo-crest-university"]]],
    ["Profile states and labels", [["V56", "Compiled automatically, review pending", "#/profile/deltaline-cold-chain"], ["V56", "Pending: institution", "#/profile/lake-basin-water-institute"], ["V10", "Reviewed", "#/profile/bonny-reach-logistics"], ["V10", "Verified and Sponsored", "#/profile/tiderail-payments"], ["V54", "Verified entrepreneur", "#/profile/ifeoma-kariuki-bello"], ["V10", "Featured", "#/profile/confluence-health-network"], ["V54", "Featured entrepreneur", "#/profile/wanjiru-owusu-eze"], ["V10", "Sponsored, no trust label", "#/profile/osu-harbour-foods"], ["V12", "Removed (410)", "#/profile/emeka-diallo-hassan"], ["V13", "Not public (404)", "#/profile/harmattan-grid-energy"]]],
    ["Awards", [["V18", "Awards overview", "#/awards"], ["V19", "Category page", "#/awards/category/5"], ["V55", "Nominees", "#/awards/nominees"], ["V21", "Winners", "#/awards/winners"], ["V33", "Awards rules", "#/awards/rules"], ["V22", "Nominate", "#/nominate"], ["N04", "Nomination submitted", "#/nominate/done?ref=NOM-0099"]]],
    ["Stories", [["V16", "Stories list", "#/stories"], ["V17", "Story", "#/stories/building-for-the-smallholder"], ["V17", "Sponsored story", "#/stories/merchant-settlement-spotlight"], ["V17", "Story with a related event", "#/stories/building-for-the-smallholder"]]],
    ["Events", [["V58", "Events list", "#/events"], ["V59", "Event (upcoming)", "#/events/lagos-agri-finance-forum-2026"], ["V59", "Event (past)", "#/events/abuja-mediators-roundtable"]]],
    ["Forms", [["V39", "Correction request", "#/correct/savanna-loop-agritech"], ["V40", "Request removal", "#/remove/halima-osei-danjuma"], ["V44", "Report (button on any profile)", "#/profile/palmline-media-house"], ["V43", "Enquiry (button on any profile)", "#/profile/ngozi-balogun-mensah"], ["V50", "Your data rights", "#/data-rights"], ["V41", "Confirmation", "#/done?kind=correction&ref=cr1"], ["V45", "Unsubscribe", "#/unsubscribe"]]],
    ["Legal and system", [["V29", "Privacy policy", "#/privacy"], ["V30", "Terms", "#/terms"], ["V31", "Cookie policy", "#/cookies"], ["V35", "Complaints and takedown policy", "#/complaints"], ["V13", "404", "#/not-a-real-page"], ["V14", "500", "#/error"], ["V48", "Sitemap preview", "#/sitemap.xml"]]],
    ["Single-use link pages", [["O23", "Customer invitation", "#/invite/demo"], ["O06", "Customer profile form (press Start first)", "#/invite/demo"], ["O22", "Consent", "#/consent/demo"], ["O24", "Expired link", "#/expired"]]]
  ];
  W.route("/design-review", "X05", function () {
    var st = W.awardStage().key;
    var btn = function (key, label, go) { return '<button type="button" class="btn alt sm" data-act="stage" data-arg="' + key + "|" + go + '" aria-pressed="' + (st === key) + '">' + e(label) + "</button>"; };
    return W.band({ eyebrow: "Prototype tool", title: "Design review", lede: "Every public page and state in one list. Open each at 360, 768 and 1280 pixels. Staff screens keep the wireframe look on purpose." }) +
      '<div class="wrap section--tight"><div class="section--tight">' +
      '<div class="review-group"><h2 style="font-size:var(--t-xl)">Awards stage</h2><p class="muted small" style="margin-top:.5rem">The seed starts at “Nominations open soon”. Switch to see the other states of Home, Awards, Nominate, Nominees and Winners. This only changes your browser’s sample data, and Reset puts it back.</p><div class="stage-switch">' + btn("soon", "Opens soon", "#/nominate") + btn("open", "Nominations open (full form)", "#/nominate") + btn("nominees", "Nominees announced", "#/awards/nominees") + btn("winners", "Winners published", "#/awards/winners") + "</div></div>" +
      REVIEW.map(function (g) { return '<div class="review-group"><h2 style="font-size:var(--t-xl)">' + e(g[0]) + '</h2><ul class="review-links">' + g[1].map(function (l) { return '<li><a href="' + e(l[2]) + '"><span>' + e(l[1]) + '</span><span class="mono">' + e(l[0]) + "</span></a></li>"; }).join("") + "</ul></div>"; }).join("") +
      '<div class="review-group"><h2 style="font-size:var(--t-xl)">Design system</h2><div class="badges" style="margin-top:.75rem">' + W.badge.reviewed("2026-11-01") + W.badge.verified() + W.badge.sponsored("Amber Coast Insurance") + W.badge.pending() + '</div><p class="small muted" style="margin-top:.75rem">Direction: docs/design-direction.md. Tokens and components: docs/design-system.md and prototype/theme/tokens.css.</p></div>' +
      "</div></div>";
  }, { hf: true });
})();
