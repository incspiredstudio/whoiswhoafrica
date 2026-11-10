/* Awards, nominate, nominee response screens (visitor, nominator, nominee), high fidelity. */
(function () {
  var W = window.W, e = W.esc;
  function cats() { var out = []; W.S.awards.groups.forEach(function (g) { g.cats.forEach(function (c) { out.push({ name: c, group: g.name, i: out.length }); }); }); return out; }
  W.cats = cats;
  /* Page copy, edited in the page editor and not in the admin (REQ-260). Shown on the Awards, Judging and Nominate pages. */
  var ELIGIBILITY = "Open to individuals and organisations in Nigeria first, then other African countries, with a professional record in the last five years. Final wording is set when the rules are approved.";
  var CRITERIA = [{ name: "Impact" }, { name: "Achievement" }, { name: "Credibility and integrity" }, { name: "Leadership or innovation" }];
  var GROUP_TEXT = {
    "Leadership & Public Impact": "People whose leadership has changed how an organisation, a sector or a community works.",
    "Entrepreneurship & Business": "Founders and companies that have built something durable.",
    "Technology & Innovation": "New products and approaches, judged on what they changed rather than on how new they are.",
    "Professional Excellence": "Practitioners whose record stands out within their profession.",
    "Industry & Institutional Recognition": "Institutions that serve their field or the public well, over time."
  };
  /* Where the edition stands. Only the nominations switch exists; nominees and winners follow the Live status staff set on each nomination (D-066). */
  W.awardStage = function () {
    if (W.S.nominations.some(function (n) { return W.nomPublic(n) && W.nomWinner(n); })) return { key: "winners", label: "Winners announced", now: 3 };
    if (W.S.nominations.some(W.nomPublic)) return { key: "nominees", label: "Nominees announced", now: 1 };
    if (W.nomOpen()) return { key: "open", label: "Nominations are open", now: 0 };
    return { key: "soon", label: "Nominations open soon", now: -1 };
  };
  /* Design review switch: moves the sample data between the four stages. */
  W.act.stage = function (arg) {
    var a = String(arg).split("|"), key = a[0], go = a[1], S = W.S;
    S.flags.nominations.on = key === "open";
    S.nominations.forEach(function (n) { n.stage = "nominated"; n.live = false; });
    if (key === "nominees" || key === "winners") S.nominations.forEach(function (n) { if (n.citation) n.live = true; });
    if (key === "winners") {
      var seen = {};
      S.nominations.forEach(function (n) { if (n.live && !seen[n.category]) { seen[n.category] = 1; n.stage = "winner"; } });
    }
    W.save(); W.toast("Sample data set to: " + W.awardStage().label + "."); W.go(go || "#/design-review");
  };
  function stageCta(stage, dark) {
    if (stage.key === "open") return '<a class="btn" href="#/nominate">Nominate someone</a>';
    if (stage.key === "soon") return '<a class="btn" href="#/nominate">Get notified</a>';
    if (stage.key === "nominees") return '<a class="btn" href="#/awards/nominees">See the nominees</a>';
    return '<a class="btn" href="#/awards/winners">See the winners</a>';
  }

  /* What the public sees of the process. Screening, assessment and shortlisting are staff steps (D-055). */
  var PUBLIC_STAGES = ["Nominations open", "Nominees announced", "Judging", "Winners announced", "Awards presentation"];
  /* Awards overview: the edition, where it stands, the five groups in their colours, and how judges score. */
  W.route("/awards", "V18", function () {
    var A = W.S.awards, st = W.awardStage(), all = cats(), pub = W.S.nominations.filter(function (n) { return W.nomPublic(n); });
    var sp = W.S.sponsors.filter(function (x) { return x.attached.some(function (a) { return /^Awards/.test(a); }); });
    return '<header class="np-hero aw-hero"><div class="wrap"><p class="np-kicker">' + e(W.brand.edition) + '</p><h1 class="np-title"><span>The</span> <span>awards</span></h1>' +
      '<div class="np-intro"><div class="aw-intro"><p>Recognising the people and organisations shaping the continent. Independent judges decide, against criteria published before nominations open.</p><div class="row">' + stageCta(st) + '<a class="btn alt aw-btn-alt" href="#/awards/rules">Read the rules</a></div></div>' +
      '<dl class="np-facts"><div><dt>Groups</dt><dd>' + A.groups.length + "</dd></div><div><dt>Categories</dt><dd>" + all.length + "</dd></div>" + (pub.length ? "<div><dt>Nominees</dt><dd>" + pub.length + "</dd></div>" : "") + "</dl></div></div>" +
      "</header>" +
      '<section class="section aw-stage" aria-labelledby="awStage"><div class="wrap"><div class="aw-head" data-reveal><p class="np-kicker aw-kick">Where we are</p><h2 id="awStage">' + e(st.label) + '</h2></div><ol class="aw-steps" data-reveal>' + PUBLIC_STAGES.map(function (x, i) { return '<li class="' + (i < st.now ? "is-done" : i === st.now ? "is-now" : "") + '"' + (i === st.now ? ' aria-current="step"' : "") + '><span class="aw-step-n">0' + (i + 1) + '</span><span class="aw-step-t">' + e(x) + "</span>" + (i === st.now ? '<span class="aw-step-here">Now</span>' : "") + "</li>"; }).join("") + "</ol></div></section>" +
      '<section class="section aw-cats" id="categories" aria-labelledby="awCats"><div class="wrap"><div class="aw-head" data-reveal><p class="np-kicker aw-kick">Categories</p><h2 id="awCats">Five groups, ' + all.length + ' categories</h2><p class="muted">Proposed list. The final categories are confirmed before nominations open.</p></div><div class="aw-groups">' + A.groups.map(function (g, gi) {
        var t = W.groupOf(g.cats[0]);
        return '<section class="aw-group poster poster--' + t.tone + '" id="' + W.slugify(g.name) + '" aria-labelledby="awg' + gi + '" data-reveal style="--i:' + gi + '"><span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--' + t.mark + '" aria-hidden="true"></span><div class="aw-group-in"><span class="aw-group-n">0' + (gi + 1) + '</span><h3 id="awg' + gi + '">' + e(g.name) + "</h3><p>" + e(GROUP_TEXT[g.name] || "") + '</p><ul>' + g.cats.map(function (c) { var ci = all.filter(function (x) { return x.name === c; })[0].i, k = pub.filter(function (n) { return n.category === c; }).length; return '<li><a href="#/awards/category/' + ci + '"><span>' + e(c) + "</span>" + (k ? '<span class="aw-count">' + k + '<span class="sr"> nominees</span></span>' : "") + W.ico("arrow") + "</a></li>"; }).join("") + "</ul></div></section>";
      }).join("") + "</div></div></section>" +
      '<section class="section aw-judge" aria-labelledby="awJudge"><div class="wrap aw-judge-in"><div data-reveal><p class="np-kicker aw-kick">Judging</p><h2 id="awJudge">How judges score</h2><p class="aw-elig">' + e(ELIGIBILITY) + '</p><a class="more" href="#/awards/rules">Full awards rules' + W.ico("arrow") + '</a></div><ul class="aw-crit" data-reveal>' + CRITERIA.map(function (k) { return '<li><span class="aw-crit-t">' + e(k.name) + '</span></li>'; }).join("") + "</ul></div>" +
      (sp.length ? '<div class="wrap aw-sponsors" data-reveal><h2>Supported by</h2><div class="sponsors">' + sp.map(function (x) { return '<span class="sponsor">' + W.monogram(x.name, "business", "2.5rem") + e(x.name) + "</span>"; }).join("") + '</div><p class="small muted">Sponsors do not choose, score or influence nominees or winners.</p></div>' : "") + "</section>";
  });
  W.route("/awards/category/:i", "V19", function (p) {
    var c = cats()[Number(p.i)]; if (!c) return W.notFound.fn();
    var A = W.S.awards, noms = W.S.nominations.filter(function (n) { return n.category === c.name && W.nomPublic(n); });
    var win = noms.filter(W.nomWinner)[0] || null;
    var rest = noms.filter(function (n) { return n !== win; });
    var siblings = cats().filter(function (x) { return x.group === c.group && x.name !== c.name; });
    return W.band({ tone: "purple", crumbs: [["Awards", "#/awards"], [c.name]], eyebrow: c.group, title: c.name, lede: e(GROUP_TEXT[c.group] || "") + " Anyone may nominate once nominations open." }) +
      '<section class="section"><div class="wrap split split--rail"><div class="stack-l">' +
      (win ? '<div data-reveal><div class="eyebrow" style="margin-bottom:.75rem">Winner</div><div class="nom-grid">' + W.citation(win, true) + "</div></div>" : "") +
      '<div data-reveal><h2 style="font-size:var(--t-xl);margin-bottom:1rem">Nominees</h2>' + (rest.length ? '<div class="nom-grid">' + rest.map(function (n) { return W.citation(n); }).join("") + "</div>" : win ? '<p class="muted">The other nominees are listed once published.</p>' : W.empty("No nominees yet", "Nominee pages appear only once nominees are announced, after screening and the nominee’s own consent.")) + "</div>" +
      '<div data-reveal><p>' + (W.nomOpen() ? '<a class="btn" href="#/nominate">Nominate in this category</a>' : W.nomClosed() ? '' : '<a class="btn alt" href="#/nominate">Nominations open soon: get notified</a>') + '</p></div></div><aside class="profile-rail"><div class="panel panel--ruled"><h2>How it is judged</h2><div class="bars">' + CRITERIA.map(function (k) { return '<p class="bar-row"><span>' + e(k.name) + '</span></p>'; }).join("") + '</div></div><div class="panel"><h2>In this group</h2><ul style="list-style:none">' + siblings.map(function (s) { return '<li style="padding-block:.25rem"><a class="small" href="#/awards/category/' + s.i + '">' + e(s.name) + "</a></li>"; }).join("") + "</ul></div></aside></div></section>";
  });
  W.route("/awards/nominee/:ref", "V20", function (p, q, m) {
    var n = W.S.nominations.filter(function (x) { return x.ref === p.ref; })[0];
    var vis = n && W.nomPublic(n);
    var staff = ["reviewer", "editor", "admin"].indexOf(W.S.role) >= 0;
    if (!n || (!vis && !staff)) { m.inv = "V13"; return W.notFound.fn(); }
    var pr = W.profile(n.profileId), prOK = pr && W.isPublic(pr), won = W.nomWinner(n), g = W.groupOf(n.category);
    var ci = cats().filter(function (x) { return x.name === n.category; })[0];
    var others = W.S.nominations.filter(function (x) { return x !== n && x.category === n.category && W.nomPublic(x); });
    return (vis ? "" : W.staffBanner("Staff preview: not public until staff publish the nominee page (nominees announced, nominee accepted).")) +
      '<header class="nom-hero nom-hero--' + g.tone + '"><div class="wrap nom-hero-in"><div class="nom-hero-text"><nav class="crumbs" aria-label="Breadcrumb"><a href="#/awards">Awards</a><span aria-hidden="true">/</span><a href="#/awards/shortlist">Nominees</a></nav>' +
      '<p class="nom-hero-tag">' + (won ? "Winner" : "Nominee") + " \u00b7 " + e(W.brand.edition.replace(/^.*?:\s*/, "")) + "</p><h1>" + e(n.nominee) + '</h1><p class="nom-hero-cat"><a href="#/awards/category/' + (ci ? ci.i : 0) + '">' + e(n.category) + "</a></p>" + (n.citation ? '<blockquote class="nom-hero-cite">' + e(n.citation) + "</blockquote>" : "") + "</div>" + W.nomPortrait(n, "nom-hero-photo") + "</div></header>" +
      '<section class="section"><div class="wrap split split--rail"><div class="stack-l">' +
      (prOK ? '<div class="stack"><h2 style="font-size:var(--t-xl)">On the record</h2><p>' + e(pr.bio || "") + '</p><p><a class="more" href="#/profile/' + e(pr.slug) + '">Read the full profile' + W.ico("arrow") + "</a></p></div>" : '<p class="muted">The profile is not yet public.</p>') +
      (others.length ? '<div><h2 style="font-size:var(--t-xl);margin-bottom:1rem">Other nominees in ' + e(n.category) + '</h2><div class="nom-grid">' + others.map(function (x) { return W.citation(x, W.nomWinner(x)); }).join("") + "</div></div>" : "") +
      '</div><aside class="profile-rail"><div class="panel panel--ruled"><h2>About this page</h2><p class="small">Built from the reviewed profile and sourced facts. The nominator is never named.</p></div><div class="panel"><h2>Category</h2><p class="small">' + e(g.name) + '</p><p style="margin-top:.75rem"><a class="more" href="#/awards/category/' + (ci ? ci.i : 0) + '">All nominees in ' + e(n.category) + W.ico("arrow") + '</a></p></div><div class="panel"><h2>Judging</h2><p class="small">Independent judges score every nominee against the published criteria.</p><p style="margin-top:.75rem"><a class="more" href="#/awards/rules">Awards rules' + W.ico("arrow") + "</a></p></div></aside></div></section>";
  });
  W.route("/awards/nominees", "V55", function () {
    var A = W.S.awards, list = W.S.nominations.filter(function (n) { return W.nomPublic(n); }), all = cats();
    if (!list.length) return W.band({ tone: "purple", crumbs: [["Awards", "#/awards"], ["Nominees"]], title: "Nominees" }) + '<section class="section"><div class="wrap">' + W.empty("Nominees are not published yet", "It appears here once our team makes nominees live. Nobody is listed without staff approval.", '<a class="btn alt" href="#/awards">Awards overview</a>') + "</div></section>";
    var groups = A.groups.map(function (g, gi) { var ns = list.filter(function (n) { return g.cats.indexOf(n.category) >= 0; }); return { g: g, gi: gi, ns: ns, tone: W.groupOf(g.cats[0]), cs: g.cats.filter(function (c) { return ns.some(function (n) { return n.category === c; }); }).map(function (c) { return { c: c, i: all.filter(function (x) { return x.name === c; })[0].i }; }) }; }).filter(function (x) { return x.ns.length; });
    var people = list.filter(function (n) { return n.nomineeType === "individual"; }).length, nCats = list.map(function (n) { return n.category; }).filter(function (c, i, a) { return a.indexOf(c) === i; }).length;
    var reel = list.map(function (n) { return '<a class="np-reel-item" href="#/awards/nominee/' + e(n.ref) + '" tabindex="-1">' + W.nomPortrait(n) + "</a>"; }).join("");
    return '<header class="np-hero"><div class="wrap"><nav class="crumbs" aria-label="Breadcrumb"><a href="#/awards">Awards</a><span aria-hidden="true">/</span><span>Nominees</span></nav><p class="np-kicker">' + e(W.brand.edition) + '</p><h1 class="np-title"><span>The</span> <span>nominees</span></h1>' +
      '<div class="np-intro"><p>Every nominee here was put forward and confirmed by our team.</p><dl class="np-facts"><div><dt>Nominees</dt><dd>' + list.length + '</dd></div><div><dt>People</dt><dd>' + people + '</dd></div><div><dt>Organisations</dt><dd>' + (list.length - people) + '</dd></div><div><dt>Categories</dt><dd>' + nCats + "</dd></div></dl></div></div>" +
      '<div class="np-reel" aria-hidden="true"><div class="np-reel-in">' + reel + reel + "</div></div></header>" +
      '<section class="section nom-page"><div class="wrap" data-filter-scope>' +
      '<div class="np-tabs" role="group" aria-label="Show nominees by group"><button type="button" class="np-tab" data-act="nomFilter" data-arg="all" aria-pressed="true">All <span>' + list.length + "</span></button>" + groups.map(function (x) { return '<button type="button" class="np-tab" data-act="nomFilter" data-arg="' + e(W.slugify(x.g.name)) + '" aria-pressed="false">' + e(x.g.name) + " <span>" + x.ns.length + "</span></button>"; }).join("") + "</div>" +
      groups.map(function (x) { var id = "ng-" + W.slugify(x.g.name); return '<section class="np-group np-tone--' + x.tone.tone + ' nom-group" data-group="' + e(W.slugify(x.g.name)) + '" aria-labelledby="' + id + '"><header class="np-group-head" data-reveal><span class="np-num" aria-hidden="true">0' + (x.gi + 1) + '</span><div><h2 id="' + id + '">' + e(x.g.name) + "</h2><p>" + e(GROUP_TEXT[x.g.name] || "") + '</p></div><img class="np-mark" data-img="brand-mark" src="theme/assets/mark-' + x.tone.mark + '.webp" alt="" loading="lazy" decoding="async"></header>' +
        '<div class="np-grid" data-reveal>' + x.ns.map(function (n) { return W.citation(n, W.nomWinner(n)); }).join("") + "</div>" +
        '<p class="nom-cats">In this group: ' + x.cs.map(function (c) { return '<a href="#/awards/category/' + c.i + '">' + e(c.c) + "</a>"; }).join(", ") + "</p></section>"; }).join("") +
      '<div class="nom-how" data-reveal><div><h2>How nominees are chosen</h2><ol class="nom-steps"><li><strong>Nominated</strong><span>Anyone can put someone forward, with evidence.</span></li><li><strong>Checked</strong><span>Our team checks eligibility against the published rules.</span></li><li><strong>Listed</strong><span>Only nominees our team has made live are shown.</span></li><li><strong>Judged</strong><span>Independent judges decide, outside this site, against the published criteria.</span></li></ol></div><div class="nom-how-side"><a class="btn" href="#/awards/rules">Read the rules</a><p class="small muted">Placeholder portraits are stock images (Freepik); the people and citations are fictional.</p></div></div>' +
      "</div></section>";
  });
  /* Group filter, used on the nominees page and the home page. It acts inside the nearest [data-filter-scope]. */
  W.act.nomFilter = function (key, btn) {
    var scope = (btn && btn.closest && btn.closest("[data-filter-scope]")) || document;
    scope.querySelectorAll("[data-act=nomFilter]").forEach(function (b) { b.setAttribute("aria-pressed", b === btn ? "true" : "false"); });
    scope.querySelectorAll(".nom-group, .nom-card").forEach(function (g) { g.hidden = key !== "all" && g.getAttribute("data-group") !== key; });
  };
  /* Winners: every edition, filterable by year, group, category, type and country. Filters live in the address, like the directory. */
  W.route("/awards/winners", "V21", function (params, q) {
    var A = W.S.awards, yr = Number(W.TODAY.slice(0, 4)), liveWins = W.S.nominations.filter(function (n) { return W.nomPublic(n) && W.nomWinner(n); }).map(function (n) { var pr = n.profileId ? W.profile(n.profileId) : null; return { id: "cw-" + n.ref, year: yr, category: n.category, nominee: n.nominee, profileId: n.profileId || null, nomineeType: n.nomineeType, country: pr ? pr.country : "", citation: n.citation || "" }; }), all = liveWins.concat(A.archive || []).map(function (w) { var g = W.groupOf(w.category); return Object.assign({}, w, { group: g.name, tone: g.tone, mark: g.mark }); });
    var years = all.map(function (w) { return w.year; }).filter(function (y, i, a) { return a.indexOf(y) === i; }).sort(function (a, b) { return b - a; });
    var countries = all.map(function (w) { return w.country; }).filter(function (c, i, a) { return c && a.indexOf(c) === i; }).sort();
    var t = (q.q || "").toLowerCase(), per = 12, LP = "#/awards/winners";
    var rows = all.filter(function (w) {
      return (!q.year || String(w.year) === q.year) && (!q.group || w.group === q.group) && (!q.category || w.category === q.category) && (!q.type || w.nomineeType === q.type || (q.type === "organisation" && w.nomineeType !== "individual")) && (!q.country || w.country === q.country) &&
        (!t || (w.nominee + " " + w.category + " " + w.citation).toLowerCase().indexOf(t) >= 0);
    }).sort(function (a, b) { return q.sort === "name" ? (a.nominee < b.nominee ? -1 : 1) : (b.year - a.year) || (a.category < b.category ? -1 : 1); });
    var cur = Math.min(Math.max(1, Math.ceil(rows.length / per)), Number(q.page) || 1), slice = rows.slice((cur - 1) * per, cur * per);
    var opt = function (v, label, sel) { return '<option value="' + e(v) + '"' + (sel ? " selected" : "") + ">" + e(label) + "</option>"; };
    var catOpts = A.groups.map(function (g) { return '<optgroup label="' + e(g.name) + '">' + g.cats.map(function (c) { return opt(c, c, q.category === c); }).join("") + "</optgroup>"; }).join("");
    var FL = { year: "Year", group: "Group", category: "Category", type: "Type", country: "Country", q: "Search" };
    var active = Object.keys(FL).filter(function (k) { return q[k]; });
    var bar = W.filterBar({ path: LP, label: "Filter winners", search: { name: "q", value: q.q, placeholder: "Search winners", label: "Search winners" },
      controls: [
        { name: "year", label: "Year", blank: "All years", value: q.year, body: years.map(function (y) { return opt(y, y + " (" + all.filter(function (w) { return w.year === y; }).length + ")", q.year === String(y)); }).join("") },
        { name: "group", label: "Group", blank: "All groups", value: q.group, body: A.groups.map(function (g) { return opt(g.name, g.name, q.group === g.name); }).join("") },
        { name: "category", label: "Category", blank: "All categories", value: q.category, body: catOpts },
        { name: "type", label: "Winner is", blank: "People and organisations", value: q.type, body: opt("individual", "A person", q.type === "individual") + opt("organisation", "An organisation", q.type === "organisation") },
        { name: "country", label: "Country", blank: "All countries", value: q.country, body: countries.map(function (c) { return opt(c, c, q.country === c); }).join("") }],
      sort: { name: "sort", value: q.sort || "", options: [["", "Newest first"], ["name", "Name, A to Z"]] }, count: rows.length + " winner" + (rows.length === 1 ? "" : "s"), clear: active.length > 0 });
    var byYear = {}; slice.forEach(function (w) { (byYear[w.year] = byYear[w.year] || []).push(w); });
    var card = function (w) {
      var p = w.profileId ? W.profile(w.profileId) : null, pub = p && W.isPublic(p), sub = p ? (p.title || "") + (p.org && p.org !== p.name ? ", " + p.org : "") : [w.nomineeType === "individual" ? "" : w.nomineeType === "institution" ? "Institution" : "Business", w.country].filter(Boolean).join(" · ");
      var name = pub ? '<a class="stretched" href="#/profile/' + e(p.slug) + '">' + e(w.nominee) + "</a>" : e(w.nominee);
      return '<article class="pcard pcard--nom win-card">' + W.nomPoster(w, "pcard-media") + '<span class="award-tag award-tag--won pcard-award">' + W.ico("star") + '<span class="award-tag-t"><b>Winner</b> <span class="award-tag-c">' + w.year + "</span></span></span>" +
        '<div class="pcard-body"><p class="pcard-kick">' + e(w.category) + '</p><h3 class="pcard-name">' + name + "</h3>" + (sub ? '<p class="pcard-sub">' + e(sub) + "</p>" : "") + '<p class="win-cite">' + e(w.citation) + "</p>" + (pub ? '<span class="pcard-go" aria-hidden="true">' + W.ico("arrow") + "</span>" : "") + "</div></article>";
    };
    var pg = ""; var pages = Math.ceil(rows.length / per); if (pages > 1) { pg = '<nav class="pager" aria-label="Pages">'; for (var k = 1; k <= pages; k++) { var nq = {}; Object.keys(q).forEach(function (x) { nq[x] = q[x]; }); nq.page = k === 1 ? "" : k; pg += '<a class="btn sm ' + (k === cur ? "" : "alt") + '" href="' + LP + W.qs(nq) + '"' + (k === cur ? ' aria-current="page"' : "") + ">" + k + "</a>"; } pg += "</nav>"; }
    var body = rows.length ? Object.keys(byYear).sort(function (a, b) { return q.sort === "name" ? 0 : b - a; }).map(function (y) { return '<section class="win-year"' + (q.sort === "name" ? "" : ' aria-labelledby="wy' + y + '"') + ">" + (q.sort === "name" ? "" : '<h2 class="win-year-h" id="wy' + y + '"><span>' + y + "</span></h2>") + '<div class="grid grid--4">' + byYear[y].map(function (w) { return '<div>' + card(w) + "</div>"; }).join("") + "</div></section>"; }).join("") : W.empty("No winners match", "Try removing a filter or searching for a different word.", '<a class="btn" href="' + LP + '">Clear filters</a>');
    return '<header class="np-hero win-hero"><div class="wrap"><nav class="crumbs" aria-label="Breadcrumb"><a href="#/awards">Awards</a><span aria-hidden="true">/</span><span>Winners</span></nav><h1 class="np-title"><span>The</span> <span>winners</span></h1>' +
      '<div class="np-intro"><p>Chosen by independent judges against published criteria. Filter by year, group, category or country.</p><dl class="np-facts"><div><dt>Winners</dt><dd>' + all.length + "</dd></div><div><dt>Editions</dt><dd>" + years.length + "</dd></div><div><dt>Categories</dt><dd>" + all.map(function (w) { return w.category; }).filter(function (c, i, a) { return a.indexOf(c) === i; }).length + "</dd></div><div><dt>Countries</dt><dd>" + countries.length + "</dd></div></dl></div></div></header>" +
      '<section class="section win-body"><div class="wrap">' + bar + body + pg + '<p class="small muted win-note">Prototype. The winners, years and citations are sample data; the portraits are stock placeholders (Freepik).</p></div></section>';
  });
  W.route("/awards/rules", "V33", function () {
    var A = W.S.awards;
    return W.band({ tone: "purple", crumbs: [["Awards", "#/awards"], ["Rules"]], title: "Awards rules", lede: "Published before nominations open, so everyone plays by the same rules." }) +
      '<section class="section"><div class="wrap split split--rail"><div class="prose">' + W.draftNote("Draft. Governance and the conflict-of-interest policy come from the Venture CEO.") + "<h2>Eligibility</h2><p>" + e(ELIGIBILITY) + "</p><h2>Nominations</h2><p>Anyone may nominate. Our team checks each nomination against the published rules, and only nominees our team makes live are listed.</p><h2>Judging</h2><p>Independent judges score against published criteria. Judges declare conflicts and never see payment or sponsorship data.</p><h2>No public voting</h2><p>Outcomes are decided by judges, not by votes or payments.</p></div>" +
      '<aside class="profile-rail"><div class="panel panel--ruled"><h2>Criteria</h2><div class="bars">' + CRITERIA.map(function (k) { return '<p class="bar-row"><span>' + e(k.name) + '</span></p>'; }).join("") + "</div></div></aside></div></section>";
  });

  /* ---------- nominate ---------- */
  W.ui.nom = { step: 0, d: {} };
  var NSTEPS = ["Your details", "Verify email", "Nominee", "Category and evidence", "Conflict declaration", "Review and submit"];
  /* Guidance shown on the Nominate page, before and after nominations open (REQ-250). */
  function nominateInfo() {
    var A = W.S.awards;
    var steps = [["Nominate", "Anyone can put someone forward: a person or an organisation, with a short statement of why."], ["Screening", "Our team checks the nomination against the published eligibility rules."], ["Confirmed", "Our team confirms each nominee before they are listed. Nothing is public until then."], ["Independent judging", "Judges score every nominee against the published criteria."], ["Announced", "Nominees, then winners, are published on the site."]];
    var prep = ["The nominee's full name, and whether it is a person or an organisation", "The category that fits best", "Two or three sentences of evidence: what they did, when, and the result", "A way to check it, such as a public report, award or news item (a link helps)", "Any link between you and the nominee, so we can handle it fairly"];
    var faq = [["Does it cost anything?", "No. Nominating is free and needs no account."], ["Will the nominee know it was me?", "No. Your name and email are never shown to the nominee or the public."], ["Can I nominate myself or my own company?", "You can, but say so in the conflict declaration. Judges see only the evidence, not who sent it."], ["Can I nominate more than one person?", "Yes. Send one nomination for each person or organisation, in the category that fits."], ["What if the nominee says no?", "They are removed and not contacted again about this edition."]];
    return '<section class="nm-sec" aria-labelledby="nmHow"><div class="wrap"><p class="nm-kick">How it works</p><h2 id="nmHow">From nomination to announcement</h2><ol class="nm-steps">' + steps.map(function (x, i) { return '<li data-reveal style="--i:' + i + '"><span class="nm-n">0' + (i + 1) + '</span><strong>' + e(x[0]) + '</strong><span>' + e(x[1]) + "</span></li>"; }).join("") + "</ol></div></section>" +
      '<section class="nm-sec nm-sec--alt" aria-labelledby="nmWho"><div class="wrap nm-two"><div><p class="nm-kick">Eligibility</p><h2 id="nmWho">Who can be nominated</h2><p class="nm-lede">' + e(ELIGIBILITY) + '</p><p><a class="h-link h-link--dark" href="#/awards/rules">Read the full rules' + W.ico("arrow") + '</a></p></div><div><p class="nm-kick">Judged on</p><h2>The criteria</h2><div class="bars">' + CRITERIA.map(function (k) { return '<p class="bar-row"><span>' + e(k.name) + '</span></p>'; }).join("") + "</div></div></div></section>" +
      '<section class="nm-sec" aria-labelledby="nmCats"><div class="wrap"><p class="nm-kick">Categories</p><h2 id="nmCats">Pick the category that fits</h2><ul class="nm-cats">' + A.groups.map(function (g) { return '<li data-reveal><a href="#/awards#' + e(W.slugify(g.name)) + '"><strong>' + e(g.name) + '</strong><span>' + g.cats.length + ' categories</span></a></li>'; }).join("") + '</ul><p><a class="h-link h-link--dark" href="#/awards#categories">All categories' + W.ico("arrow") + "</a></p></div></section>" +
      '<section class="nm-sec nm-sec--alt" aria-labelledby="nmPrep"><div class="wrap nm-two"><div><p class="nm-kick">Before you start</p><h2 id="nmPrep">What to have ready</h2><ul class="nm-list">' + prep.map(function (x) { return "<li>" + e(x) + "</li>"; }).join("") + '</ul><p class="small muted">Please leave out phone numbers, home addresses and sensitive personal details. We remove them.</p></div><div><p class="nm-kick">Questions</p><h2>Good to know</h2><div class="nm-faq">' + faq.map(function (x) { return "<details><summary>" + e(x[0]) + "</summary><p>" + e(x[1]) + "</p></details>"; }).join("") + "</div></div></div></section>";
  }
  W.route("/nominate", "V22", function (p, q, m) {
    if (W.nomClosed()) {
      /* The Nominations switch (A26) decides open or closed. What follows (nominees, winners) is published content. */
      var st = W.awardStage().key, won = st === "winners", noms = st === "nominees";
      return W.band({ tone: "purple", eyebrow: W.brand.edition, title: "Nominations are closed", lede: won ? "The winners are announced." : noms ? "The nominees are announced, and the judges are scoring them." : "We are not taking nominations at the moment. The rules and the criteria stay here." }) +
        '<section class="section"><div class="wrap"><div class="nm-closed">' + (won ? '<a class="btn" href="#/awards/winners">See the winners</a>' : noms ? '<a class="btn" href="#/awards/nominees">See the nominees</a>' : "") + '<a class="btn alt" href="#/awards/rules">Read the rules</a><a class="btn alt" href="#/awards">Awards overview</a></div></div></section>' + nominateInfo();
    }
    m.inv = "N01";
    var n = W.ui.nom, d = n.d, body = "";
    var back = '<button type="button" class="btn alt" data-act="nomBack">Back</button>';
    if (n.step === 0) body = '<form data-form="nom0" class="form">' + W.field("Your name", "name", { req: true, value: d.name }) + W.field("Your email", "email", { type: "email", req: true, value: d.email, hint: "We verify it. It is never public." }) + W.field("Your relationship to the nominee", "rel", { type: "select", options: ["Colleague", "Customer or partner", "Member of the public", "Other"], value: d.rel, req: true }) + W.field('I have read the <a href="#/privacy">privacy notice</a>.', "ok", { type: "checkbox", req: true }) + W.bot() + '<div><button class="btn">Send verification link</button></div></form>';
    else if (n.step === 1) body = '<div class="form"><h2 style="font-size:var(--t-xl)">Check your email</h2><p>We sent a single-use link to <strong>' + e(d.email) + '</strong>. Open it to verify your email, then you continue. No account is created.</p><p class="small muted">Prototype: open the outbox and click the link.</p><div class="row">' + back + '<button type="button" class="btn" data-act="outbox">Open email outbox</button></div></div>';
    else if (n.step === 2) body = '<form data-form="nom2" class="form">' + (n.dup ? '<div class="warnbox" role="alert"><strong>This nominee may already be nominated.</strong> You can still continue; staff merge duplicates. We do not show who nominated them.</div>' : "") + W.field("Nominee name", "nominee", { req: true, value: d.nominee }) + W.field("Nominee is a", "nomineeType", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]], value: d.nomineeType, req: true }) + W.field("Nominee email (optional)", "nomineeEmail", { type: "email", value: d.nomineeEmail, hint: "Used only after screening, to invite them to respond." }) + '<div class="row">' + back + '<button class="btn">' + (n.dup ? "Continue anyway" : "Continue") + "</button></div></form>";
    else if (n.step === 3) body = '<form data-form="nom3" class="form">' + W.field("Category", "category", { type: "select", options: cats().map(function (c) { return [c.name, c.name + " (" + c.group + ")"]; }), value: d.category, req: true }) + W.field("Why should they be recognised? Evidence", "evidence", { type: "textarea", req: true, value: d.evidence, hint: "Professional facts only. Do not include phone numbers, emails, ages, health, religion, politics or allegations." }) + (n.err ? '<p class="err" role="alert">' + e(n.err) + "</p>" : "") + W.field("Link to evidence (optional)", "link", { value: d.link }) + '<div class="row">' + back + '<button class="btn">Continue</button></div></form>';
    else if (n.step === 4) body = '<form data-form="nom4" class="form"><fieldset style="border-top:0;padding-top:0"><legend>Conflict of interest</legend><label class="check"><input type="radio" name="coi" value="none" required' + (d.coi === "none" ? " checked" : "") + '> <span>I have no conflict of interest with the nominee</span></label><label class="check"><input type="radio" name="coi" value="yes"' + (d.coi === "yes" ? " checked" : "") + "> <span>I have a relationship I should declare</span></label></fieldset>" + W.field("If yes, describe", "coiText", { type: "textarea", value: d.coiText }) + '<div class="row">' + back + '<button class="btn">Continue</button></div></form>';
    else body = '<form data-form="nom5" class="form"><h2 style="font-size:var(--t-xl)">Check your nomination</h2><table class="facts"><tbody>' + [["Nominator", d.name + " (" + d.email + ", verified)"], ["Nominee", d.nominee + " (" + d.nomineeType + ")"], ["Category", d.category], ["Evidence", d.evidence], ["Conflict", d.coi === "yes" ? d.coiText : "None declared"]].map(function (r) { return '<tr><th scope="row">' + e(r[0]) + "</th><td>" + e(r[1]) + "</td></tr>"; }).join("") + '</tbody></table><div class="infobox">Your nomination stays private until eligibility screening is complete. Nominees can then accept, decline or correct.</div>' + W.bot() + '<div class="row">' + back + '<button class="btn">Submit nomination</button></div></form>';
    var rl = W.S.rate.nominate >= 3 ? '<div class="errbox" role="alert"><strong>Too many nominations from this device.</strong> Please try again later.</div>' : "";
    return W.band({ tone: "purple", eyebrow: W.brand.edition, title: "Nominate someone", lede: "It takes a few minutes. Your name is never shown, and nothing is public until screening is complete." }) + '<section class="section--tight"><div class="wrap wrap--text section--tight"><div class="stack">' + W.steps(NSTEPS, n.step) + (rl || body) + "</div></div></section>" + nominateInfo();
  });
  /* A new step starts at the top of the form, not wherever the last one was scrolled to. */
  function stepView() { W.render(); var s = document.querySelector(".steps"); if (s && s.scrollIntoView) s.scrollIntoView({ block: "start" }); }
  W.act.nomBack = function () { var n = W.ui.nom; n.err = ""; n.dup = false; n.step = Math.max(0, n.step - 1); if (n.step === 1) n.step = 0; stepView(); };
  W.forms.signup = function (d) { W.S.signups.push({ email: d.email, at: W.TODAY, policy: "0.1" }); W.mail(d.email, "Confirm: tell me when nominations open", "Click to confirm. You can unsubscribe at any time.", "#/unsubscribe"); W.save(); W.go("#/nominate?signed=1"); };
  W.forms.nom0 = function (d) { var n = W.ui.nom; n.d = Object.assign(n.d, d); n.step = 1; n.err = ""; W.mail(d.email, "Verify your email to continue your nomination", "Single-use link. It works once and expires.", "#/nominate/verify"); W.save(); stepView(); };
  W.route("/nominate/verify", "N12", function () {
    var n = W.ui.nom;
    if (n.step === 1) { n.d.verified = true; n.step = 2; n.err = ""; W.log("nominator.verify", n.d.email || "nominator", "email verified by signed link"); W.save(); return { redirect: "#/nominate" }; }
    if (n.d && n.d.verified) return { redirect: "#/nominate" };
    return W.expiredPage();
  });
  W.forms.nom2 = function (d) {
    var n = W.ui.nom, name = d.nominee.trim().toLowerCase(); n.d = Object.assign(n.d, d);
    var dup = W.S.nominations.some(function (x) { return x.nominee.toLowerCase() === name; }) || W.S.profiles.some(function (p) { return p.name.toLowerCase() === name && p.status === "suppressed"; });
    if (dup && !n.dup) { n.dup = true; stepView(); return; }
    n.dup = false; n.step = 3; stepView();
  };
  W.forms.nom3 = function (d) {
    var n = W.ui.nom; n.d = Object.assign(n.d, d);
    if (/(\d{4}[- ]?\d{3}|@|\bage\s*\d|health|religio|politic|allegation)/i.test(d.evidence)) { n.err = "Please remove personal contact details or sensitive information from the evidence."; stepView(); return; }
    n.err = ""; n.step = 4; stepView();
  };
  W.forms.nom4 = function (d) { var n = W.ui.nom; n.d = Object.assign(n.d, d); n.step = 5; stepView(); };
  W.forms.nom5 = function () {
    var n = W.ui.nom, d = n.d, ref = "NOM-" + ("000" + (W.S.nominations.length + 1)).slice(-4);
    if (W.S.rate.nominate >= 3) { W.render(); return; }
    W.S.rate.nominate++;
    W.S.nominations.push({ id: W.uid("n"), ref: ref, nominator: d.name, nominatorEmail: d.email, verified: true, nominee: d.nominee, nomineeType: d.nomineeType, nomineeEmail: d.nomineeEmail || "", category: d.category, evidence: d.evidence, coi: d.coi === "yes" ? d.coiText : "", stage: "nominated", live: false, invite: "", profileId: "", token: "t-" + W.uid("") });
    W.mail(d.email, "Nomination received: " + ref, "Your nomination is private until our team makes it live.", "");
    W.log("nomination.submit", ref, "public form"); W.ui.nom = { step: 0, d: {} }; W.save(); W.go("#/nominate/done?ref=" + ref);
  };
  W.route("/nominate/done", "N04", function (p, q) {
    return W.okPage("Nomination submitted", "It is private until our team checks it and makes it live. Nothing is published without staff approval.", q.ref || "", '<a class="btn" href="#/awards">Awards overview</a><a class="btn alt" href="#/nominate">Nominate another</a>');
  });

})();
