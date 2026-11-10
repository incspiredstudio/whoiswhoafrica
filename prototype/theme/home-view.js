/* Home page markup. Works as a complete static page; theme/home.js adds the pinned scenes and motion on top.
   Pending profiles never appear here (REQ-218), so the label explainer uses a generic specimen, not a real entry. */
(function () {
  var W = window.W, e = W.esc;
  function line(t, cls) { return '<span class="h-line' + (cls ? " " + cls : "") + '"><span>' + t + "</span></span>"; }
  function mark(name, cls, eager) { return '<img class="h-mark ' + (cls || "") + '" src="theme/assets/mark-' + name + '.webp" alt="" data-img="brand-mark"' + (eager ? "" : ' loading="lazy"') + ' decoding="async">'; }
  var ACTS = [
    { key: "blue", mark: "star", word: "Influence.", text: "The people and organisations making a difference and shaping the world around them.", link: ["Discover the people", "#/people"] },
    { key: "orange", mark: "comb", word: "Achievement.", text: "The milestones, contributions and innovations that deserve to be on the record.", link: ["See how recognition works", "#/awards"] },
    { key: "purple", mark: "spiral", word: "Legacy.", text: "The lasting impact people and institutions leave behind, kept accurate over time.", link: ["Read the stories", "#/stories"] }
  ];
  var DOORS = [
    { key: "blue", mark: "star", title: "People", text: "Leaders and professionals on the public record", href: "#/people" },
    { key: "orange", mark: "comb", title: "Entrepreneurs", text: "Founders and the ventures they built", href: "#/entrepreneurs" },
    { key: "green", mark: "link", title: "Businesses", text: "Companies, from startups to listed groups", href: "#/businesses" },
    { key: "purple", mark: "spiral", title: "Institutions", text: "Universities, associations and public bodies", href: "#/institutions" }
  ];
  function story(a, i) {
    var sp = W.sponsorOfArticle(a);
    return '<a class="h-story" href="#/stories/' + e(a.slug) + '" data-cursor="Read">' + W.img("story", a.seed || W.seedFor(a.slug), 1280, 720, "Placeholder image for the story: " + a.title, "h-story-img") + '<div class="h-story-text"><div class="story-meta"><b>' + e(a.type) + "</b><span>" + e(W.fmtDate(a.date)) + "</span>" + (sp ? W.badge.sponsored(sp) : "") + "</div><h3>" + e(a.title) + "</h3></div></a>";
  }
  function peekData(p) { return ' data-peek-name="' + e(p.name) + '" data-peek-tone="' + (p.type === "individual" ? "orange" : "green") + '"' + (p.photo ? ' data-peek-photo="' + e(p.photo) + '"' : "") + ' data-peek-mark="' + (p.type === "individual" ? "star" : p.type === "business" ? "link" : "spiral") + '"'; }

  /* The nominees on the home page: a grid of nominee posters with a group filter.
     Only once the nominees are published (REQ-033, REQ-198). People first, then organisations. */
  function noms(stage) {
    if (stage.key !== "nominees" && stage.key !== "winners") return "";
    var list = W.S.nominations.filter(function (n) { return W.nomPublic(n); });
    if (!list.length) return "";
    list = list.filter(function (n) { var p = W.profile(n.profileId); return p && p.photo; }).concat(list.filter(function (n) { var p = W.profile(n.profileId); return !(p && p.photo); }));
    var show = list.slice(0, 8), nCats = list.map(function (n) { return n.category; }).filter(function (c, i, a) { return a.indexOf(c) === i; }).length;
    var order = W.S.awards.groups.map(function (g) { return g.name; }).filter(function (g) { return show.some(function (n) { return W.groupOf(n.category).name === g; }); });
    return '<section class="h-noms" aria-labelledby="hNomsTitle"><div class="wrap" data-filter-scope><div class="h-noms-head"><div><p class="h-kicker">' + e(stage.key === "winners" ? "The winners and nominees" : "The nominees") + '</p><h2 class="h-big h-rv" id="hNomsTitle">' + line("Meet the") + line("nominees.") + '</h2></div><div class="h-noms-side"><p>' + list.length + " nominees in " + nCats + ' categories. Independent judges choose the winners.</p><a class="btn h-magnetic" href="#/awards/nominees">See all nominees</a></div></div>' +
      '<div class="h-nom-filter" role="group" aria-label="Show nominees by group"><button type="button" class="chip" data-act="nomFilter" data-arg="all" aria-pressed="true">All</button>' + order.map(function (g) { return '<button type="button" class="chip" data-act="nomFilter" data-arg="' + e(W.slugify(g)) + '" aria-pressed="false">' + e(g) + "</button>"; }).join("") + "</div>" +
      '<ul class="hn-grid">' + show.map(function (n, i) { var p = W.profile(n.profileId), g = W.groupOf(n.category); return '<li class="nom-card hn-item" data-group="' + e(W.slugify(g.name)) + '" style="--d:' + i + '"><a class="hn-card" href="#/awards/nominee/' + e(n.ref) + '">' + W.nomPoster(n) + '<span class="hn-body"><span class="hn-cat">' + (n.status === "winner" ? "Winner \u00b7 " : "") + e(n.category) + '</span><span class="hn-name">' + e(n.nominee) + '</span><span class="hn-role">' + e(p ? (p.title || "") + (p.org && p.type === "individual" ? ", " + p.org : "") : "") + "</span></span></a></li>"; }).join("") + "</ul>" +
      '<p class="h-note">Prototype. The nominees and citations are fictional; the portraits are stock placeholders (Freepik).</p></div></section>';
  }
  W.route("/", "V01", function () {
    /* On the record: people with a portrait first, then organisations. No trust label picks them (D-055). */
    var pool = W.publicProfiles().filter(function (p) { return !W.isPending(p); });
    var feat = pool.filter(function (p) { return p.photo; }).concat(pool.filter(function (p) { return !p.photo; })).slice(0, 8);
    var arts = W.publicArticles().slice().sort(function (a, b) { return a.date < b.date ? 1 : -1; });
    var A = W.S.awards, nom = W.nomOpen(), stage = W.awardStage();
    var cta = stage.key === "open" ? ["Nominate someone", "#/nominate"] : stage.key === "soon" ? ["Get notified", "#/nominate"] : stage.key === "nominees" ? ["See the nominees", "#/awards/nominees"] : ["See the winners", "#/awards/winners"];
    return '<div class="home">' +
      '<div class="h-stage" aria-hidden="true"><div class="h-bg"></div><div class="h-canvas"></div></div>' +
      '<section class="h-hero"><div class="h-w-bg" aria-hidden="true"><div class="h-vids">' + [1, 2, 3, 4].map(function (i) { return '<div class="h-vid"><video src="theme/video/hero-' + i + '.mp4" poster="theme/video/hero-' + i + '.jpg" autoplay muted loop playsinline preload="auto" tabindex="-1"></video></div>'; }).join("") + '</div></div>' +
      '<div class="wrap h-hero-in"><h1 class="h-title">' + line("Who is who") + " " + line("in Africa.", "h-line--r") + "</h1>" +
      '<div class="h-hero-foot"><p class="h-lede">Documenting the influence, achievements and legacy of the people and institutions shaping Africa.</p>' +
      '<button type="button" class="h-cue" data-act="hScroll" aria-label="Scroll to explore"><span class="h-cue-label" aria-hidden="true">Scroll</span><span class="h-cue-line" aria-hidden="true"></span></button></div></div></section>' +

      '<section class="h-acts" aria-label="' + e(W.brand.tagline) + '"><div class="h-acts-pin">' + ACTS.map(function (a, i) {
        return '<article class="h-act h-act--' + a.key + '" data-act-i="' + i + '">' + mark(a.mark, "h-act-mark") + '<div class="wrap h-act-in"><p class="h-act-n">0' + (i + 1) + " / 03</p><h2>" + line(e(a.word)) + '</h2><div class="h-act-foot"><p>' + e(a.text) + '</p><a class="h-link" href="' + a.link[1] + '">' + e(a.link[0]) + W.ico("arrow") + "</a></div></div></article>";
      }).join("") + "</div></section>" +

      '<section class="h-record"><div class="wrap"><div class="h-sec-head"><p class="h-kicker">On the record</p><a class="h-link h-link--dark" href="#/directory">Open the directory' + W.ico("arrow") + '</a></div><ul class="h-index" id="hIndex">' + feat.map(function (p, i) {
        /* trust labels and coordinates stay on the profile; a paid placement is always marked, so Sponsored stays */
        return '<li class="h-rv-fade" style="--d:' + Math.min(i, 5) + '"><a href="#/profile/' + e(p.slug) + '"' + peekData(p) + ' data-cursor="Open"><span class="h-index-name">' + e(p.name) + '</span><span class="h-index-meta"><span>' + e((p.title || "") + (p.org ? ", " + p.org : "")) + '</span><span class="meta">' + e(p.city) + "</span></span>" + "</a></li>";
      }).join("") + '</ul>' +
        /* phones and tablets: a swipeable rail of posters instead of the hover list */
        '<ul class="h-rail" aria-label="Profiles on the record">' + feat.map(function (p) { return '<li class="h-rail-item"><a class="h-rail-card" href="#/profile/' + e(p.slug) + '">' + W.profilePoster(p) + '<span class="h-rail-ind">' + e(p.industry || "") + '</span><span class="h-rail-name">' + e(p.name) + '</span><span class="h-rail-role">' + e((p.title || "") + (p.org && p.org !== p.name ? ", " + p.org : "")) + "</span></a></li>"; }).join("") + '<li class="h-rail-item h-rail-end"><a class="h-rail-all" href="#/directory">Open the directory</a></li></ul>' +
        '<p class="h-note">Prototype. Every person and organisation named here is fictional.</p></div></section>' +

      '<section class="h-doors" aria-label="The directory">' + DOORS.map(function (d, i) { return '<a class="h-door h-rv-clip h-door--' + d.key + '" style="--d:' + i + '" href="' + d.href + '" data-cursor="Browse">' + mark(d.mark, "h-door-mark") + '<span class="h-door-text"><strong>' + e(d.title) + "</strong><span>" + e(d.text) + "</span></span></a>"; }).join("") + "</section>" +

      '<section class="h-awards"><div class="h-marquee" aria-hidden="true"><div class="h-marquee-in">' + [0, 1].map(function () { return "<span>The Maiden Edition</span><span>" + e(stage.label) + "</span>" + A.groups.map(function (g) { return "<span>" + e(g.name) + "</span>"; }).join(""); }).join("") + '</div></div><div class="wrap h-awards-in"><div><p class="h-kicker">Recognition</p><h2 class="h-big h-rv">' + line("WHOISWHO") + line("in Africa:") + line("The Maiden Edition") + '</h2><p class="h-awards-text">Recognising the people and organisations shaping the continent, chosen by independent judges.</p><div class="h-awards-cta"><a class="btn h-magnetic" href="' + cta[1] + '">' + e(cta[0]) + '</a><a class="h-link" href="#/awards">Awards overview' + W.ico("arrow") + '</a></div></div><ul class="h-groups">' + A.groups.map(function (g, i) { return '<li><a href="#/awards#' + W.slugify(g.name) + '"><span class="h-groups-n">0' + (i + 1) + "</span><span>" + e(g.name) + "</span>" + W.ico("arrow") + "</a></li>"; }).join("") + "</ul></div></section>" +

      noms(stage) +

      '<section class="h-stories" id="hStories"><div class="h-stories-pin"><div class="wrap h-sec-head"><p class="h-kicker">Stories from the record</p><a class="h-link h-link--dark" href="#/stories">All stories' + W.ico("arrow") + '</a></div><div class="h-track-wrap" tabindex="0" role="group" aria-label="Latest stories"><div class="h-track" id="hTrack">' + arts.slice(0, 6).map(story).join("") + "</div></div></div></section>" +

      '<section class="h-final"><div class="wrap"><p class="h-kicker">Starting in Nigeria. Built for the continent.</p><h2 class="h-final-title h-rv">' + line("Who should") + line("the continent") + line("<em>know?</em>") + '</h2><div class="h-final-cta"><a class="btn h-magnetic" href="' + (nom ? "#/nominate" : "#/subscribe") + '">' + (nom ? "Nominate someone" : "Get listed") + '</a><a class="h-link" href="#/nominate">' + (nom ? "How nominations work" : "Nominations open soon") + W.ico("arrow") + '</a></div></div></section>' +

      '<div class="h-peek" id="hPeek" aria-hidden="true"></div><div class="h-cursor" id="hCursor" aria-hidden="true"><span></span></div></div>';
  });
})();
