/* Reusable public-site components: icons, images, labels, cards, header, footer, page bands.
   Each one returns plain semantic HTML with classes from theme/site.css, ready to become a WordPress template part.
   Image slots carry data-img="profile-photo|logo|cover|story" so real images can replace the placeholders. */
(function () {
  var W = window.W, e = W.esc;

  /* ---------- icons ---------- */
  var ICONS = {
    search: '<circle cx="7" cy="7" r="5"/><path d="m11 11 4 4"/>', arrow: '<path d="M2 8h11M9 4l4 4-4 4"/>', chevron: '<path d="m3 6 5 5 5-5"/>',
    check: '<path d="m3 8.5 3.2 3L13 4.5"/>', star: '<path d="m8 2 1.8 3.9 4.2.5-3.1 2.9.8 4.2L8 11.5l-3.7 2 .8-4.2L2 6.4l4.2-.5z"/>',
    clock: '<circle cx="8" cy="8" r="6"/><path d="M8 4.5V8l2.5 1.5"/>', tag: '<path d="M2 2.5h5.5L14 9l-5 5-6.5-6.5z"/><circle cx="5.5" cy="5.5" r=".8"/>',
    pin: '<path d="M8 14.5s5-4.6 5-8.5a5 5 0 0 0-10 0c0 3.900 5 8.500 5 8.500z"/><circle cx="8" cy="6" r="1.700"/>', grid: '<path d="M2 2h5v5H2zM9 2h5v5H9zM2 9h5v5H2zM9 9h5v5H9z"/>', list: '<path d="M2 3.500h12M2 8h12M2 12.500h12"/>',
    menu: '<path d="M2 4.500h12M2 8h12M2 11.500h12"/>', close: '<path d="m3 3 10 10M13 3 3 13"/>', info: '<circle cx="8" cy="8" r="6"/><path d="M8 7.500v3.500M8 5v.2"/>',
    filter: '<path d="M2 3.500h12M4.500 8h7M6.500 12.500h3"/>', mail: '<path d="M2 3.500h12v9H2z"/><path d="m2 4 6 5 6-5"/>', flag: '<path d="M3.500 14.500V2M3.500 2.500h9l-2 3 2 3h-9"/>', edit: '<path d="M10.500 2.500 13.500 5.500 5.500 13.500H2.500V10.500z"/>', trash: '<path d="M2.500 4h11M6 4V2.500h4V4M4 4l.7 9.500h6.600L12 4"/>'
  };
  W.ico = function (n) { return '<svg class="ico" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[n] || "") + "</svg>"; };

  /* ---------- formatting ---------- */
  var MONTHS = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
  W.fmtDate = function (d) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(d || ""); return m ? Number(m[3]) + " " + MONTHS[Number(m[2]) - 1] + " " + m[1] : (d || ""); };
  function hash(s) { var h = 0; s = String(s); for (var i = 0; i < s.length; i++) h = (h * 31 + s.charCodeAt(i)) >>> 0; return h; }
  function initials(name) { return String(name || "?").replace(/^(Prof\.|Dr\.)\s+/, "").split(/[\s-]+/).filter(Boolean).slice(0, 2).map(function (w) { return w.charAt(0).toUpperCase(); }).join(""); }

  /* ---------- images ---------- */
  /* Seeds were checked by eye: no people, no real brands, no famous places. */
  W.SEEDS = ["indigo", "mesa", "canopy", "quarry", "mineral", "plateau", "delta", "laterite", "adire", "ridge", "harbour", "orchard", "dune", "harmattan", "atlas"];
  W.seedFor = function (key) { return W.SEEDS[hash(key) % W.SEEDS.length]; };
  var DIPS = ["blue", "green", "purple", "charcoal", "red"];
  W.img = function (kind, seed, w, h, alt, cls, dip) {
    return '<figure class="dip dip--' + (dip || DIPS[hash(seed) % DIPS.length]) + " " + (cls || "") + '" data-img="' + kind + '"><img src="https://picsum.photos/seed/' + e(seed) + "/" + w + "/" + h + '" width="' + w + '" height="' + h + '" alt="' + e(alt || "") + '" loading="lazy" decoding="async" onerror="this.style.display=\'none\'"></figure>';
  };
  W.monogram = function (name, type, size, photo) {
    var person = type === "individual", tone = (hash(name) % 6) + 1;
    if (photo) return '<div class="monogram monogram--' + (person ? "person" : "org") + ' monogram--photo"' + (size ? ' style="--size:' + size + '"' : "") + ' data-img="profile-photo"><img data-img="profile-photo" src="' + e(photo) + '" alt="Placeholder photo for ' + e(name) + ' (stock image)" width="640" height="800" loading="lazy" decoding="async"></div>';
    return '<div class="monogram monogram--' + (person ? "person" : "org") + " mono-" + tone + '"' + (size ? ' style="--size:' + size + '"' : "") + ' data-img="' + (person ? "profile-photo" : "logo") + '" role="img" aria-label="' + (person ? "Placeholder portrait: no photograph on file for " : "Placeholder logo for ") + e(name) + '"><svg viewBox="0 0 40 40" fill="none" stroke="currentColor" aria-hidden="true"><circle cx="20" cy="20" r="18"/><circle cx="20" cy="20" r="12"/></svg><span>' + e(initials(name)) + "</span></div>";
  };

  /* ---------- labels ---------- */
  W.isPending = function (p) { return !!(p && p.status === "published" && p.compiled); };
  W.badge = {
    verified: function () { return '<span class="badge badge--verified" title="Checked by our team against a published checklist. Never for sale.">' + W.ico("check") + "Verified</span>"; },
    reviewed: function (date) { return '<span class="badge badge--reviewed">Reviewed on ' + e(W.fmtDate(date)) + "</span>"; },
    pending: function () { return '<span class="badge badge--pending">' + W.ico("clock") + e(W.PENDING_LABEL) + "</span>"; },
    sponsored: function (name) { return '<span class="badge badge--sponsored" title="Paid placement. Not a trust label.">' + W.ico("tag") + "Sponsored" + (name ? " · " + e(name) : "") + "</span>"; }
  };
  W.labels = function (p, sponsorName) {
    var out = "";
    if (W.isPending(p)) return '<div class="badges">' + W.badge.pending() + "</div>";
    /* Public pages show no trust badges (Verified, Featured) and no review date: only the pending label. Sponsored is for stories (D-055). */
    /* Profiles are never labelled Sponsored (D-055): the brief limits that label to paid content. */
    return out ? '<div class="badges">' + out + "</div>" : "";
  };
  W.isEntrepreneur = function (p) { return !!(p && p.entrepreneur); };

  /* ---------- cards ---------- */
  /* A profile in a list: a poster (brand colour, hand-drawn pattern, one mark, and the photo or the initials),
     then industry, name and role. Lists carry only the pending label. */
  var CARD_TONES = ["blue", "purple", "green", "red", "orange"], CARD_MARKS = ["star", "comb", "spiral", "link"];
  /* The profile's public awards nomination, if any: nominee or winner, and only once the nominees are published.
     A nomination before that is never shown (REQ-033). A win comes first. Shortlisting is a staff step, not a public label (D-055). */
  W.nomFor = function (p) {
    if (!p || W.isPending(p)) return null;
    var ns = (W.S.nominations || []).filter(function (n) { return n.profileId === p.id && ["shortlisted", "finalist", "winner"].indexOf(n.status) >= 0 && W.nomPublic(n); });
    return ns.filter(function (n) { return n.status === "winner"; })[0] || ns[0] || null;
  };
  W.awardTag = function (n, cls) {
    var won = n.status === "winner";
    return '<a class="award-tag' + (won ? " award-tag--won" : "") + " " + (cls || "") + '" href="#/awards/nominee/' + e(n.ref) + '">' + W.ico("star") + '<span class="award-tag-t"><b>' + (won ? "Winner" : "Nominee") + '</b> <span class="award-tag-c">' + e(n.category) + "</span></span></a>";
  };
  /* A profile as a poster: colour and mark picked from its slug, so the same profile always looks the same. */
  W.profilePoster = function (p, cls) {
    var h = hash(p.slug || p.name), tone = CARD_TONES[h % CARD_TONES.length], mark = CARD_MARKS[(h >>> 3) % CARD_MARKS.length];
    return '<span class="poster poster--' + tone + (p.photo ? "" : " poster--org") + " " + (cls || "") + '"' + (p.photo ? "" : ' role="img" aria-label="' + (p.type === "individual" ? "Placeholder portrait: no photograph on file for " : "Placeholder logo for ") + e(p.name) + '"') + '><span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--' + mark + '" aria-hidden="true"></span>' +
      (p.photo ? '<img class="poster-photo" data-img="profile-photo" src="' + e(p.photo) + '" alt="Placeholder photo for ' + e(p.name) + ' (stock image)" width="640" height="800" loading="lazy" decoding="async">' : '<span class="poster-ini" aria-hidden="true">' + e(initials(p.name)) + "</span>") + "</span>";
  };
  W.card = function (p) {
    var pend = W.isPending(p), sub = (p.title || "") + (p.org && p.org !== p.name ? (p.title ? ", " : "") + p.org : "");
    var tags = pend ? W.badge.pending() : "", nom = W.nomFor(p), media = W.profilePoster(p, "pcard-media");
    return '<article class="pcard' + (pend ? " pcard--pending" : "") + (nom ? " pcard--nom" : "") + '">' + media + (nom ? W.awardTag(nom, "pcard-award") : "") +
      '<div class="pcard-body"><p class="pcard-kick">' + e(p.industry || "") + '</p><h3 class="pcard-name"><a class="stretched" href="#/profile/' + e(p.slug) + '">' + e(p.name) + "</a></h3>" + (sub ? '<p class="pcard-sub">' + e(sub) + "</p>" : "") +
      (tags ? '<div class="badges">' + tags + "</div>" : "") + '<span class="pcard-go" aria-hidden="true">' + W.ico("arrow") + "</span></div></article>";
  };
  W.articleCard = function (a, lead) {
    var sp = W.sponsorOfArticle(a);
    return '<a class="story' + (lead ? " story--lead" : "") + '" href="#/stories/' + e(a.slug) + '">' + W.img("story", a.seed || W.seedFor(a.slug), lead ? 1100 : 640, lead ? 660 : 400, "Placeholder image for the story: " + a.title, "ratio-3x2") +
      '<div class="story-text"><div class="story-meta"><b>' + e(a.type) + "</b><span>" + e(W.fmtDate(a.date)) + "</span>" + (sp ? W.badge.sponsored(sp) : "") + "</div><h3>" + e(a.title) + "</h3>" + (lead && a.lede ? '<p class="muted">' + e(a.lede) + "</p>" : "") + "</div></a>";
  };
  /* Awards groups each carry one brand colour and one mark, used on nominee portraits and tiles. */
  var GROUP_TONE = { "Leadership & Public Impact": ["blue", "star"], "Entrepreneurship & Business": ["orange", "comb"], "Technology & Innovation": ["purple", "spiral"], "Professional Excellence": ["green", "link"], "Industry & Institutional Recognition": ["red", "comb"] };
  W.groupOf = function (cat) { var g = (W.S.awards.groups.filter(function (x) { return x.cats.indexOf(cat) >= 0; })[0] || {}).name || ""; return { name: g, tone: (GROUP_TONE[g] || ["charcoal", "star"])[0], mark: (GROUP_TONE[g] || ["charcoal", "star"])[1] }; };
  /* A nominee's portrait: the profile photo, or for an organisation a colour tile with its initials and the group's mark. */
  W.nomPortrait = function (n, cls) {
    var p = W.profile(n.profileId), g = W.groupOf(n.category);
    if (p && p.photo) return '<figure class="nom-photo nom-photo--' + g.tone + " " + (cls || "") + '" data-img="profile-photo"><img src="' + e(p.photo) + '" alt="Placeholder photo for ' + e(n.nominee) + ' (stock image)" width="640" height="800" loading="lazy" decoding="async"></figure>';
    var ini = n.nominee.replace(/^(Prof|Dr|Mr|Mrs|Ms)\.?\s+/, "").split(/\s+/).filter(function (w) { return /^[A-Z]/.test(w); }).slice(0, 2).map(function (w) { return w[0]; }).join("");
    return '<figure class="nom-photo nom-photo--tile nom-photo--' + g.tone + " " + (cls || "") + '" data-img="logo" role="img" aria-label="Placeholder logo tile for ' + e(n.nominee) + '"><img class="nom-mark" src="theme/assets/mark-' + g.mark + '.webp" alt="" loading="lazy" decoding="async"><span>' + e(ini) + "</span></figure>";
  };
  /* A nominee as a poster, after the brand guide's social cards: the group's colour, its hand-drawn pattern in a darker
     shade, one large mark in an accent colour, and the portrait (or, for an organisation, its initials) on top. */
  W.nomPoster = function (n, cls) {
    var p = W.profile(n.profileId), g = W.groupOf(n.category), photo = p && p.photo;
    var ini = n.nominee.replace(/^(Prof|Dr|Mr|Mrs|Ms)\.?\s+/, "").split(/\s+/).filter(function (w) { return /^[A-Z]/.test(w); }).slice(0, 2).map(function (w) { return w[0]; }).join("");
    return '<span class="poster poster--' + g.tone + (photo ? "" : " poster--org") + " " + (cls || "") + '"' + (photo ? "" : ' role="img" aria-label="Placeholder logo tile for ' + e(n.nominee) + '"') + '><span class="poster-pat" aria-hidden="true"></span><span class="poster-mark poster-mark--' + g.mark + '" aria-hidden="true"></span>' +
      (photo ? '<img class="poster-photo" data-img="profile-photo" src="' + e(photo) + '" alt="Placeholder photo for ' + e(n.nominee) + ' (stock image)" width="640" height="800" loading="lazy" decoding="async">' : '<span class="poster-ini" aria-hidden="true">' + e(ini) + "</span>") + "</span>";
  };
  W.citation = function (n, won) {
    var p = W.profile(n.profileId), g = W.groupOf(n.category);
    return '<a class="citation nom-card nom-card--' + g.tone + (won ? " nom-card--won" : " citation--nominee") + '" href="#/awards/nominee/' + e(n.ref) + '" data-group="' + e(W.slugify(g.name)) + '"><span class="nom-media">' + W.nomPortrait(n) +
      (n.citation ? '<span class="nom-over" aria-hidden="true"><span class="nom-over-q">' + e(n.citation) + '</span><span class="nom-over-go">View nominee' + W.ico("arrow") + "</span></span>" : "") + "</span>" +
      '<span class="nom-body"><span class="tag">' + (won ? "Winner" : "Nominee") + " \u00b7 " + e(n.category) + "</span><h3>" + e(n.nominee) + '</h3><span class="nom-role">' + e(p ? (p.title || "") + (p.org && p.type === "individual" ? ", " + p.org : "") : "") + "</span>" + (n.citation ? '<p class="nom-cite">' + e(n.citation) + "</p>" : "") + "</span></a>";
  };
  /* Wraps cards in a responsive grid with staggered reveals. */
  W.grid = function (items, cls) {
    return '<div class="grid ' + (cls || "grid--3") + '">' + items.map(function (h, i) { return '<div data-reveal style="--i:' + Math.min(i, 5) + '">' + h + "</div>"; }).join("") + "</div>";
  };

  /* ---------- page parts ---------- */
  W.band = function (o) {
    var head = (o.crumbs ? W.crumbs(o.crumbs) : "") + (o.eyebrow ? '<div class="eyebrow">' + e(o.eyebrow) + "</div>" : "") + "<h1>" + e(o.title) + "</h1>" + (o.lede ? "<p>" + o.lede + "</p>" : "");
    /* compact: a short band with the title on the left and o.aside (e.g. a search box) on the right */
    if (o.compact) head = '<div class="band-row"><div class="band-main">' + head + "</div>" + (o.aside || "") + "</div>";
    return '<header class="title-band' + (o.compact ? " title-band--compact" : "") + (o.light ? " title-band--light" : " on-dark pattern" + (o.tone ? " tone-" + o.tone : "")) + '"><div class="wrap">' + head + (o.extra || "") + "</div></header>";
  };
  W.sectionHead = function (eyebrow, title, more, lede) {
    return '<div class="section-head" data-reveal><div>' + (eyebrow ? '<div class="eyebrow">' + e(eyebrow) + "</div>" : "") + "<h2>" + e(title) + "</h2>" + (lede ? "<p>" + lede + "</p>" : "") + "</div>" + (more ? '<a class="more" href="' + more[1] + '">' + e(more[0]) + W.ico("arrow") + "</a>" : "") + "</div>";
  };
  W.page = function (html, cls) { return '<div class="wrap section ' + (cls || "") + '"><div class="stack">' + html + "</div></div>"; };
  W.draftNote = function (t) { return '<p class="draft-note">' + W.ico("info") + "<span>" + e(t) + "</span></p>"; };
  W.staffBanner = function (t) { return '<div class="staff-banner"><div class="wrap">' + t + "</div></div>"; };

  /* ---------- header ---------- */
  /* Logo mark traced by hand from the brand guide. Replace it with the master vector file when supplied. */
  /* The W's facets, in the logo's 449 by 297 box. A hand trace from the brand guide: replace with the master vector when it arrives. */
  W.LOGO = {
    w: 449, h: 297,
    facets: [[[37, 65], [74, 0], [102, 0], [140, 65]], [[309, 65], [347, 0], [376, 0], [413, 65]], [[37, 65], [136, 237], [187, 151], [225, 215], [177, 297], [98, 297], [1, 127]], [[413, 65], [313, 237], [263, 151], [225, 215], [273, 297], [351, 297], [449, 127]], [[140, 65], [213, 65], [221, 79], [185, 142]], [[309, 65], [235, 65], [228, 77], [263, 142]]],
    africa: function () { return W.GEO.africa.map(function (p) { return [202 + (p[0] + 17.5) / 68.7 * 46, 124 + (37.3 - p[1]) / 72.1 * 52]; }); }
  };
  /* The brand mark (the W with Africa inside) from the supplied icon file, drawn as a mask so it takes the text colour. */
  W.logoSvg = function (cls) { return '<span class="wiw-w ' + (cls || "") + '" aria-hidden="true"></span>'; };
  function wordmark() { return W.logoSvg("logo-mark") + e(W.brand.name); }
  function nomLabel() { return W.nomOpen() ? "Nominate" : "Nominate (opens soon)"; }
  function megaLink(href, title, text) { return '<li><a class="mega-link" href="' + href + '"><strong>' + e(title) + "</strong><span>" + e(text) + "</span></a></li>"; }
  function plain(href, t) { return '<li><a class="mega-plain" href="' + href + '">' + e(t) + "</a></li>"; }
  /* Right-hand panel of the Directory menu: the nominees once they are published, otherwise the main cities. */
  function megaPromo() {
    var list = (W.S.nominations || []).filter(function (n) { var p = W.profile(n.profileId); return W.nomPublic(n) && p && p.photo; });
    if (list.length) {
      var n = list[(new Date().getDate()) % list.length];
      return '<a class="mega-promo" href="#/awards/nominees">' + W.nomPoster(n, "mega-poster") + '<span class="mega-promo-text"><span class="mega-promo-kick">The awards</span><strong>Meet the nominees</strong><span class="more">See all ' + W.S.nominations.filter(W.nomPublic).length + W.ico("arrow") + "</span></span></a>";
    }
    return "<h2>By city</h2><ul>" + ["Lagos", "Abuja", "Port Harcourt", "Accra", "Nairobi"].map(function (c) { return plain("#/directory?city=" + encodeURIComponent(c), c); }).join("") + plain("#/directory", "All cities") + "</ul>";
  }
  function megas() {
    var dir = '<div class="mega" id="mega-directory"><div class="wrap mega-grid"><div class="mega-col mega-col--wide"><h2>The directory</h2><ul class="grid grid--2">' +
      megaLink("#/people", "People", "Leaders and professionals on public record") + megaLink("#/entrepreneurs", "Entrepreneurs", "Founders and the ventures they built") + megaLink("#/businesses", "Businesses", "Companies, from startups to listed groups") + megaLink("#/institutions", "Institutions", "Universities, associations and public bodies") +
      '</ul></div><div class="mega-col"><h2>By industry</h2><ul>' + W.LISTS.industries.slice(0, 5).map(function (i) { return plain("#/industries/" + encodeURIComponent(i), i); }).join("") + plain("#/industries", "All industries") + '</ul></div><div class="mega-col">' + megaPromo() + "</div></div></div>";
    var aw = '<div class="mega" id="mega-awards"><div class="wrap mega-grid"><div class="mega-col mega-col--wide"><h2>' + e(W.brand.edition) + '</h2><ul class="grid grid--2">' +
      megaLink("#/awards", "Awards overview", "How recognition works, from nomination to announcement") + (W.nomClosed() ? "" : megaLink("#/nominate", nomLabel(), W.nomOpen() ? "Put forward someone who deserves it" : "Get notified when nominations open")) + megaLink("#/awards/nominees", "Nominees", "Everyone in the running, by category") + megaLink("#/awards/winners", "Winners", "Every edition, filterable by year and category") +
      '</ul></div><div class="mega-col"><h2>Categories</h2><ul>' + W.S.awards.groups.map(function (g) { return plain("#/awards#" + W.slugify(g.name), g.name); }).join("") + plain("#/awards#categories", "All categories") + '</ul></div><div class="mega-col"><h2>The rules</h2><ul>' + plain("#/awards/rules", "Eligibility and judging") + "</ul></div></div></div>";
    var ab = '<div class="mega" id="mega-about"><div class="wrap mega-grid"><div class="mega-col mega-col--wide"><h2>' + e(W.brand.name) + '</h2><ul class="grid grid--2">' +
      megaLink("#/about", "About", "What we are building, and why") + megaLink("#/faq", "FAQ", "Listings, corrections and removal") + megaLink("#/contact", "Contact", "Reach the team") +
      '</ul></div><div class="mega-col"><h2>Work with us</h2><ul>' + plain("#/subscribe", "Get listed or featured") + "</ul></div></div></div>";
    return dir + aw + ab;
  }
  function megaBtn(id, label, current) { return '<li><button type="button" class="nav-link" data-act="mega" data-arg="' + id + '" aria-expanded="false" aria-controls="' + id + '"' + (current ? ' data-current="true"' : "") + ">" + e(label) + W.ico("chevron") + "</button></li>"; }
  W.siteHeader = function (cur) {
    return '<a class="skip" href="#main" data-act="skip">Skip to content</a><p class="fiction-strip">Prototype. Every person and organisation shown is fictional.</p><header class="site-head"><div class="wrap head-bar"><a class="logo" href="#/" aria-label="' + e(W.brand.name) + ' home">' + wordmark() + "</a>" +
      '<nav class="nav-top" aria-label="Main"><ul>' + megaBtn("mega-directory", "Directory") + megaBtn("mega-awards", "Awards") + '<li><a class="nav-link" href="#/stories"' + (/^\/stories/.test(cur) ? ' aria-current="page"' : "") + ">Stories</a></li>" + megaBtn("mega-about", "About") + "</ul></nav>" +
      '<div class="head-tools"><button type="button" class="icon-btn search-link" data-act="searchOpen" aria-haspopup="dialog" aria-controls="searchOv" aria-label="Search the directory"' + (/^\/search/.test(cur) ? ' aria-current="page"' : "") + ">" + W.ico("search") + '</button><a class="btn sm head-cta" href="#/subscribe">Get listed</a><button type="button" class="icon-btn menu-btn" data-act="drawer" aria-expanded="false" aria-controls="drawer">' + W.ico("menu") + "Menu</button></div></div>" + megas() + "</header>" + drawer() + searchPanel();
  };
  /* ---------- search panel: opens from the header icon, shows results as you type ---------- */
  var QUICK = [["#/people", "People"], ["#/entrepreneurs", "Entrepreneurs"], ["#/businesses", "Businesses"], ["#/institutions", "Institutions"], ["#/awards", "Awards"], ["#/stories", "Stories"]];
  function searchPanel() {
    return '<div class="sov" id="searchOv" role="dialog" aria-modal="true" aria-label="Search" hidden><div class="wrap sov-in">' +
      '<div class="sov-top"><span class="sov-kicker">Search</span><button type="button" class="icon-btn sov-close" data-act="searchClose">' + W.ico("close") + "Close</button></div>" +
      '<form class="sov-form" role="search" data-form="search"><label class="sr" for="sovq">Search people, businesses, institutions and stories</label><input id="sovq" name="q" type="search" autocomplete="off" placeholder="Search a name or company"><button class="sov-go" aria-label="See all results">' + W.ico("arrow") + "</button></form>" +
      '<div class="sov-res" id="sovRes" aria-live="polite">' + W.searchPanelResults("") + "</div></div></div>";
  }
  W.searchPanelResults = function (q) {
    var h = W.searchHits ? W.searchHits(q) : { t: "", ps: [], as: [] };
    if (!h.t) return '<p class="sov-label">Browse</p><ul class="sov-quick">' + QUICK.map(function (l) { return '<li><a href="' + l[0] + '">' + e(l[1]) + "</a></li>"; }).join("") + "</ul>";
    if (!h.ps.length && !h.as.length) return '<p class="sov-label">No matches</p><p class="sov-none">Nothing public matches \u201c' + e(q.trim()) + '\u201d. Check the spelling or try fewer words.</p>';
    var tag = function (p) { return W.isPending(p) ? W.badge.pending() : ""; };
    return (h.ps.length ? '<p class="sov-label">Profiles</p><ul class="sov-list">' + h.ps.slice(0, 6).map(function (p) { return '<li><a class="sov-hit" href="#/profile/' + e(p.slug) + '"><span class="sov-name">' + e(p.name) + '</span><span class="sov-meta">' + e((p.title || "") + (p.city ? ", " + p.city : "")) + "</span>" + (tag(p) ? '<span class="badges">' + tag(p) + "</span>" : "") + "</a></li>"; }).join("") + "</ul>" : "") +
      (h.as.length ? '<p class="sov-label">Stories</p><ul class="sov-list">' + h.as.slice(0, 3).map(function (a) { return '<li><a class="sov-hit" href="#/stories/' + e(a.slug) + '"><span class="sov-name">' + e(a.title) + '</span><span class="sov-meta">' + e(a.type) + "</span></a></li>"; }).join("") + "</ul>" : "") +
      '<a class="sov-all" href="#/search' + W.qs({ q: q.trim() }) + '">See all ' + (h.ps.length + h.as.length) + " result" + (h.ps.length + h.as.length === 1 ? "" : "s") + W.ico("arrow") + "</a>";
  };
  function drawerGroup(title, links) { return "<details><summary>" + e(title) + W.ico("chevron") + "</summary><ul>" + links.filter(function (l) { return !(l[0] === "#/nominate" && W.nomClosed()); }).map(function (l) { return '<li><a href="' + l[0] + '">' + e(l[1]) + "</a></li>"; }).join("") + "</ul></details>"; }
  function drawer() {
    return '<div class="drawer" id="drawer" role="dialog" aria-modal="true" aria-label="Menu"><div class="drawer-head"><a class="logo" href="#/">' + wordmark() + '</a><button type="button" class="icon-btn" data-act="drawer" data-arg="close">' + W.ico("close") + "Close</button></div>" +
      '<form role="search" data-form="search"><label class="sr" for="hq2">Search</label><input id="hq2" name="q" type="search" placeholder="Search the directory"></form>' +
      drawerGroup("Directory", [["#/directory", "All profiles"], ["#/people", "People"], ["#/entrepreneurs", "Entrepreneurs"], ["#/businesses", "Businesses"], ["#/institutions", "Institutions"], ["#/industries", "Industries"]]) +
      drawerGroup("Awards", [["#/awards", "Awards overview"], ["#/nominate", nomLabel()], ["#/awards/nominees", "Nominees"], ["#/awards/winners", "Winners"], ["#/awards/rules", "Eligibility and judging"]]) +
      '<a class="drawer-link" href="#/stories">Stories</a>' +
      drawerGroup("About", [["#/about", "About"], ["#/faq", "FAQ"], ["#/contact", "Contact"]]) +
      '<a class="drawer-link" href="#/subscribe">Get listed or featured</a></div>';
  }

  /* ---------- footer, cookie ---------- */
  function col(title, links) { return "<div><h2>" + e(title) + "</h2><ul>" + links.filter(function (l) { return !(l[0] === "#/nominate" && W.nomClosed()); }).map(function (l) { return '<li><a href="' + l[0] + '">' + e(l[1]) + "</a></li>"; }).join("") + "</ul></div>"; }
  W.siteFooter = function () {
    var parts = W.brand.tagline.split(/\.\s*/).filter(Boolean);
    return '<footer class="site-foot on-dark pattern"><div class="wrap"><p class="foot-statement">' + parts.map(function (t) { return '<span aria-hidden="true">' + e(t) + ".</span>"; }).join("") + '<span class="sr">' + e(W.brand.tagline) + '</span></p><div class="foot-cols">' +
      col("Directory", [["#/people", "People"], ["#/entrepreneurs", "Entrepreneurs"], ["#/businesses", "Businesses"], ["#/institutions", "Institutions"], ["#/industries", "Industries"]]) +
      col("Recognition", [["#/awards", "Awards"], ["#/nominate", nomLabel()], ["#/awards/winners", "Winners"], ["#/awards/rules", "Awards rules"], ["#/stories", "Stories"]]) +
      col("Trust", [["#/complaints", "Complaints and takedown"], ["#/data-rights", "Your data rights"], ["#/faq", "FAQ"], ["#/contact", "Contact"]]) +
      '<div><h2>Legal</h2><ul><li><a href="#/privacy">Privacy policy</a></li><li><a href="#/terms">Terms</a></li><li><a href="#/cookies">Cookie policy</a></li><li><button type="button" class="foot-btn" data-act="cookiePrefs">Cookie preferences</button></li></ul></div></div>' +
      '<div class="foot-base"><span>© 2026 whoiswhoafrica. ' + e(W.brand.domain) + '</span><span>Design prototype with fictional sample content. <a href="#/sitemap.xml">Sitemap</a> · <a href="#/unsubscribe">Unsubscribe</a></span></div></div></footer>';
  };
  /* A small card that appears once the visitor has scrolled a little (cookieCheck in motion.js). */
  W.siteCookie = function () {
    if (W.S.consent) return "";
    return '<div class="cookie" role="region" aria-label="Cookie consent" data-cookie><p>Essential cookies only, unless you accept analytics. <a href="#/cookies">Policy</a></p><div class="cookie-act"><button type="button" class="cookie-btn" data-act="consent" data-arg="essential">Decline</button><button type="button" class="cookie-btn cookie-btn--yes" data-act="consent" data-arg="all">Accept</button></div></div>';
  };
})();
