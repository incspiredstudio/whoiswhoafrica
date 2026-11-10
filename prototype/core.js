/* Core: state store, selectors, helpers, router, chrome, events. No framework, no build. */
(function () {
  var W = (window.W = window.W || {});
  var KEY = "wiw-wireframe-v19";
  W.act = {}; W.forms = {}; W.live = {};
  W.ui = { flows: false, notes: false, nav: false, modal: null, cookie: false, outbox: false };

  /* ---------- helpers ---------- */
  var ESC = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };
  W.esc = function (s) { return String(s == null ? "" : s).replace(/[&<>"']/g, function (c) { return ESC[c]; }); };
  var e = W.esc;
  W.uid = function (p) { return p + Math.random().toString(36).slice(2, 7); };
  W.money = function (k, c) { var n = (Number(k) || 0) / 100; return (c === "NGN" || !c ? "₦" : c + " ") + n.toLocaleString("en-NG", { maximumFractionDigits: 0 }); };
  W.find = function (arr, id) { return (W.S[arr] || []).filter(function (x) { return x.id === id; })[0]; };
  W.profile = function (id) { return W.find("profiles", id); };
  W.bySlug = function (slug) { return W.S.profiles.filter(function (p) { return p.slug === slug; })[0] || W.S.profiles.filter(function (p) { return (p.redirects || []).indexOf(slug) >= 0; })[0]; };
  W.cap = function (s) { s = String(s || ""); return s.charAt(0).toUpperCase() + s.slice(1); };
  function hash(str) { var h = 2166136261; for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); } return (h >>> 0).toString(16).padStart(8, "0"); }

  /* ---------- store ---------- */
  W.load = function () {
    var raw = null;
    try { raw = JSON.parse(localStorage.getItem(KEY)); } catch (x) { raw = null; }
    W.S = raw && raw.v === 9 ? raw : W.seed();
    if (W.S.role === undefined) W.S.role = "visitor";
    W.syncLists();
  };
  W.save = function () { try { localStorage.setItem(KEY, JSON.stringify(W.S)); } catch (x) { /* storage blocked: state lives for this page only */ } };
  W.reset = function () { try { localStorage.removeItem(KEY); } catch (x) { } W.S = W.seed(); W.syncLists(); W.save(); };
  W.log = function (action, target, detail) {
    var A = W.S.audit, prev = A.length ? A[A.length - 1].hash : "00000000";
    var row = { n: A.length + 1, at: W.TODAY + " " + ("0" + (9 + (A.length % 9))).slice(-2) + ":" + ("0" + ((A.length * 7) % 60)).slice(-2), actor: W.S.role === "visitor" ? "system" : (W.persona ? W.persona() : W.roleName(W.S.role)), action: action, target: target || "", detail: detail || "", prev: prev };
    row.hash = hash(prev + JSON.stringify([row.n, row.actor, row.action, row.target, row.detail]));
    A.push(row);
  };
  W.roleName = function (r) { return (W.LISTS.roles[r] || r).replace(/ \(.*\)/, ""); };
  W.mail = function (to, subject, body, link) {
    W.S.emails.unshift({ id: W.uid("m"), to: to, subject: subject, body: body, link: link || "", at: W.TODAY });
  };
  W.purge = function (url, why) { W.S.purges.unshift({ id: W.uid("pg"), url: url, why: why, at: W.TODAY, status: "purged (sample: about 5 s)" }); };

  /* ---------- selectors ---------- */
  /* Origin of a profile: "import" (arrived through the acquisition import) or "customer" (submitted by, or created with the consent of, the person or organisation). */
  W.origin = function (p) { return p.origin || (p.source === "acquired" ? "import" : "customer"); };
  W.vis = function (p) {
    if (!p) return "none";
    if (p.status === "published") return "public";
    if (p.status === "unpublished" || p.status === "suppressed") return "gone";
    return "hidden";
  };
  W.isPublic = function (p) { return W.vis(p) === "public"; };
  W.publicProfiles = function () { return W.S.profiles.filter(W.isPublic); };
  W.inWindow = function (a) { return (!a.start || a.start <= W.TODAY) && (!a.end || a.end >= W.TODAY); };
  W.arrMissing = function (a) {
    var m = [];
    if (a.complimentary) { if (!a.confirmedBy) m.push("confirmed-by"); if (!a.onboarding) m.push("onboarding started"); if (!a.consent && a.linkedProfile) m.push("customer consent (REQ-175)"); return m; }
    if (!(a.amount > 0)) m.push("amount"); if (!a.currency) m.push("currency"); if (!a.method) m.push("payment method");
    if (!a.ref) m.push("payment reference"); if (!a.received) m.push("date received"); if (!a.confirmedBy) m.push("confirmed-by"); if (!a.onboarding) m.push("onboarding started");
    return m;
  };
  W.arrStatus = function (a) {
    if (a.cancelled) return "cancelled";
    var complete = W.arrMissing(a).length === 0;
    if (complete && a.agreed) { if (a.endedManual || (a.end && a.end < W.TODAY)) return "ended"; return "confirmed"; }
    if (a.endedManual) return "ended";
    if (a.agreed && (a.received || a.ref || a.method)) return "awaiting payment (incomplete)";
    if (a.agreed) return "awaiting payment";
    return "proposed";
  };
  W.counts = function (a) { var s = W.arrStatus(a); return !a.complimentary && (s === "confirmed" || s === "ended") && W.arrMissing(a).length === 0; };
  /* Profiles cannot be Sponsored (D-055): that label is for stories only. Kept so older call sites read as "none". */
  W.sponsorOf = function () { return null; };
  /* Paid story placement (REQ-263, D-060): a sponsor pays for a labelled slot for a story, for set dates, on the Home page, the Stories page or both.
     The slot never changes the order of other stories, search ranking, profiles, Featured, nominations or winners. Payment is agreed offline and recorded in the Register. */
  W.placementStatus = function (pl) { if (pl.ended) return "ended"; if (pl.start && pl.start > W.TODAY) return "scheduled"; if (pl.end && pl.end < W.TODAY) return "expired"; return "active"; };
  W.activePlacements = function (where) {
    return (W.S.placements || []).filter(function (pl) { return W.placementStatus(pl) === "active" && (!where || pl.placement === "Both" || pl.placement === where); }).sort(function (x, y) { return x.start < y.start ? -1 : 1; });
  };
  W.sponsorOfArticle = function (a) {
    var hit = (W.S.placements || []).filter(function (pl) { return pl.storyId === a.id && W.placementStatus(pl) === "active"; })[0];
    return hit ? hit.sponsor : null;
  };
  /* The one story in the paid slot for a surface ("Home" or "Stories"), or null. Only a published story can fill it. */
  W.slotFor = function (where) {
    var pls = W.activePlacements(where);
    for (var i = 0; i < pls.length; i++) { var a = W.find("articles", pls[i].storyId); if (a && a.status === "published") return a; }
    return null;
  };
  /* Events are their own post type (REQ-262), not stories. An event has a date, a place and an organiser. Registration is on the organiser's site; WHOISWHO takes no payment (D-005). */
  W.eventState = function (v) { var last = (v.end || v.start || "").slice(0, 10); return last && last < W.TODAY ? "past" : "upcoming"; };
  W.storyDate = function (a) { return a.date; };
  W.eventWhen = function (v) {
    var s = v.start || "", d = W.fmtDate(s.slice(0, 10)), tm = s.length > 10 ? s.slice(11, 16) : "";
    return d + (tm ? " at " + tm : "") + (v.end ? " to " + W.fmtDate(v.end.slice(0, 10)) : "");
  };
  W.eventWhere = function (v) { return v.venue === "Online" ? "Online" : [v.venue, v.city, v.country].filter(Boolean).join(", "); };
  W.publicEvents = function () { return (W.S.events || []).filter(function (v) { return v.status === "published"; }); };
  /* Upcoming events, soonest first; past events, most recent first. */
  W.upcomingEvents = function () { return W.publicEvents().filter(function (v) { return W.eventState(v) === "upcoming"; }).sort(function (a, b) { return a.start < b.start ? -1 : 1; }); };
  W.pastEvents = function () { return W.publicEvents().filter(function (v) { return W.eventState(v) === "past"; }).sort(function (a, b) { return a.start < b.start ? 1 : -1; }); };
  /* Stories that link to an event (REQ-265). */
  W.storiesOfEvent = function (v) { return W.publicArticles().filter(function (a) { return a.relatedEvent === v.id; }); };
  W.publicArticles = function () { return W.S.articles.filter(function (a) { return a.status === "published"; }); };
  W.queue = function () { return W.S.profiles.filter(function (p) { return p.status === "pending" || p.edit || p.compiled; }); };
  W.ok = function (name) { return !!W.S.flags[name].on; };
  W.nomOpen = function () { return W.ok("nominations"); };
  /* Once nominees are announced, nominations for the edition are over: the Nominate page and its links say so, or step aside. */
  W.nomClosed = function () { return !W.nomOpen(); };
  /* A nomination has a stage (Nominated or Winner) and a status (Live or Not live). Live nominations are public; a Live Winner is public as a winner (D-066). */
  W.nomPublic = function (n) { return !!(n && n.live); };
  W.nomWinner = function (n) { return !!(n && n.stage === "winner"); };

  /* ---------- html bits ---------- */
  /* Claimed is shown to staff as a checkmark badge, the kind social platforms use (D-057). Public pages never show it (D-054). */
  W.vcheck = function (p, big) {
    if (!p || !p.claimed) return "";
    return '<span class="vcheck' + (big ? " vcheck--lg" : "") + '" title="Claimed. Staff only, never bought (REQ-150)."><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 1.8l2.4 1.9 3-.3 1.2 2.8 2.8 1.2-.3 3L22.9 12l-1.9 2.4.3 3-2.8 1.2-1.2 2.8-3-.3L12 22.9l-2.4-1.9-3 .3-1.2-2.8-2.8-1.2.3-3L1.1 12l1.9-2.4-.3-3 2.8-1.2 1.2-2.8 3 .3z"/><path fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" d="M7.6 12.3l3 3 5.8-6.2"/></svg><span class="sr">Verified' + (p.claimed ? ", claimed" : "") + "</span></span>";
  };
  W.trustPill = W.vcheck;
  W.PENDING_LABEL = "Compiled automatically from public sources. Human review pending.";
  W.statePill = function (p) { if (p.status === "published" && p.compiled) return '<span class="pill warn" title="Review state (D-033)">' + e(W.PENDING_LABEL) + "</span>"; return p.status === "published" && p.lastReviewed ? '<span class="pill ok" title="Review state (D-033)">Reviewed on ' + e(p.lastReviewed) + "</span>" : ""; };
  W.spPill = function (name) { return name ? ' <span class="pill sponsored" title="Paid placement disclosure. Not a trust status (REQ-155).">Sponsored by ' + e(name) + "</span>" : ""; };
  W.stPill = function (s) {
    var m = { published: "ok", confirmed: "ok", approved: "ok", pending: "warn", open: "warn", draft: "gray", proposed: "gray", rejected: "bad", unpublished: "bad", suppressed: "bad", cancelled: "bad", ended: "gray", new: "warn", contacted: "gray", qualified: "ok" };
    return '<span class="pill ' + (m[s] || (/awaiting/.test(s) ? "warn" : "gray")) + '">' + e(s) + "</span>";
  };
  W.ph = function (label, cls) { return '<div class="ph ' + (cls || "") + '" role="img" aria-label="Placeholder: ' + e(label) + '">' + e(label) + "</div>"; };
  W.empty = function (title, text, action) { return '<div class="empty"><h2>' + e(title) + "</h2><p class=\"muted\">" + e(text) + "</p>" + (action || "") + "</div>"; };
  W.field = function (label, name, o) {
    o = o || {}; var id = "f-" + name, type = o.type || "text", v = o.value == null ? "" : o.value;
    var hint = o.hint ? '<span class="hint" id="' + id + '-h"> ' + e(o.hint) + "</span>" : "";
    var common = ' id="' + id + '" name="' + name + '"' + (o.req ? " required" : "") + (o.hint ? ' aria-describedby="' + id + '-h"' : "") + (o.attrs ? " " + o.attrs : "");
    var input;
    if (type === "textarea") input = "<textarea" + common + ">" + e(v) + "</textarea>";
    else if (type === "select") input = "<select" + common + ">" + (o.blank ? '<option value="">' + e(o.blank) + "</option>" : "") + (o.options || []).map(function (op) { var val = Array.isArray(op) ? op[0] : op, lab = Array.isArray(op) ? op[1] : op; return '<option value="' + e(val) + '"' + (String(val) === String(v) ? " selected" : "") + ">" + e(lab) + "</option>"; }).join("") + "</select>";
    else if (type === "checkbox") return '<div class="field"><label class="check"><input type="checkbox"' + common + (v ? " checked" : "") + '><span>' + label + hint + "</span></label></div>";
    else input = '<input type="' + type + '"' + common + ' value="' + e(v) + '">';
    return '<div class="field"><label for="' + id + '">' + e(label) + (o.req ? ' <span class="muted" aria-hidden="true">*</span>' : "") + hint + "</label>" + input + "</div>";
  };
  W.bot = function () { return '<div class="bot small"><label class="check"><input type="checkbox" name="human" required> <span>I am human</span></label><span>Security check placeholder</span></div>'; };
  W.table = function (cols, rows, empty) {
    if (!rows.length) return W.empty(empty || "Nothing here", "No rows match. This is the empty state of the table.");
    return '<div class="tablewrap"><table class="t"><thead><tr>' + cols.map(function (c) { return "<th scope=\"col\">" + e(c) + "</th>"; }).join("") + "</tr></thead><tbody>" + rows.map(function (r) { return "<tr>" + r.map(function (c) { return "<td>" + c + "</td>"; }).join("") + "</tr>"; }).join("") + "</tbody></table></div>";
  };
  W.btn = function (label, act, arg, cls, extra) { return '<button type="button" class="btn ' + (cls || "") + '" data-act="' + act + '" data-arg="' + e(arg == null ? "" : arg) + '"' + (extra || "") + ">" + e(label) + "</button>"; };
  W.crumbs = function (items) { return '<nav class="crumbs" aria-label="Breadcrumb">' + items.map(function (i, k) { return k === items.length - 1 ? e(i[0]) : '<a href="' + i[1] + '">' + e(i[0]) + "</a> / "; }).join("") + "</nav>"; };
  W.steps = function (names, cur) { return '<ol class="steps" aria-label="Progress">' + names.map(function (n, i) { return '<li class="' + (i < cur ? "done" : i === cur ? "cur" : "") + '"' + (i === cur ? ' aria-current="step"' : "") + ">" + (i + 1) + ". " + e(n) + "</li>"; }).join("") + "</ol>"; };
  W.note = function (text) { return '<p class="preview-note">' + text + "</p>"; };
  W.formData = function (form) {
    var d = {}, fd = new FormData(form);
    fd.forEach(function (v, k) { if (d[k] !== undefined) { d[k] = [].concat(d[k], v); } else d[k] = v; });
    return d;
  };

  /* ---------- modal, toast ---------- */
  W.openModal = function (title, html) { W.ui.modal = { title: title, html: html }; W.paintModal(); };
  W.closeModal = function () { W.ui.modal = null; W.paintModal(); var l = W.ui.lastFocus; if (l && l.focus) l.focus(); };
  W.paintModal = function () {
    var r = document.getElementById("modal-root"); if (!r) return;
    var m = W.ui.modal;
    r.innerHTML = m ? '<div class="back" data-act="closeModal"></div><div class="dlg" role="dialog" aria-modal="true" aria-labelledby="dlg-t"><div class="row between"><h2 id="dlg-t">' + e(m.title) + '</h2><button type="button" class="btn alt sm" data-act="closeModal" aria-label="Close dialog">Close</button></div>' + m.html + "</div>" : "";
    if (m) { var f = r.querySelector('input:not([type="hidden"]),select,textarea,button.btn:not(.alt)') || r.querySelector("button"); if (f) f.focus(); }
  };
  var toastT;
  W.toast = function (msg) {
    var t = document.getElementById("toast"); if (!t) return; t.textContent = msg;
    clearTimeout(toastT); toastT = setTimeout(function () { t.textContent = ""; }, 4500);
  };
  W.go = function (h) { if (location.hash === h) W.render(); else location.hash = h; };

  /* ---------- router ---------- */
  W.routes = [];
  W.route = function (pattern, inv, fn, opts) {
    var keys = [], re = new RegExp("^" + pattern.replace(/:[a-z]+/gi, function (m) { keys.push(m.slice(1)); return "([^/]+)"; }) + "/?$");
    W.routes.push({ pattern: pattern, re: re, keys: keys, inv: inv, fn: fn, opts: opts || {} });
  };
  W.parse = function (hash) {
    var h = (hash || "").replace(/^#/, "") || "/"; var qi = h.indexOf("?"), path = qi < 0 ? h : h.slice(0, qi), q = {};
    if (qi >= 0) h.slice(qi + 1).split("&").forEach(function (kv) { if (!kv) return; var p = kv.split("="); q[decodeURIComponent(p[0])] = decodeURIComponent((p[1] || "").replace(/\+/g, " ")); });
    return { path: path || "/", q: q };
  };
  W.match = function (hash) {
    var hh = String(hash || ""), ai = hh.indexOf("#", 1), anchor = ai > 0 ? hh.slice(ai + 1) : "";
    if (ai > 0) hash = hh.slice(0, ai);
    var p = W.parse(hash);
    for (var i = 0; i < W.routes.length; i++) {
      var m = W.routes[i].re.exec(p.path);
      if (m) { var params = {}; W.routes[i].keys.forEach(function (k, j) { params[k] = decodeURIComponent(m[j + 1]); }); return { r: W.routes[i], params: params, q: p.q, path: p.path, anchor: anchor }; }
    }
    return { r: W.notFound, params: {}, q: p.q, path: p.path };
  };
  W.qs = function (q) { var s = Object.keys(q).filter(function (k) { return q[k] !== "" && q[k] != null; }).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(q[k]); }).join("&"); return s ? "?" + s : ""; };

  /* One quiet filter bar for every list page (Events, Stories, Directory, Winners). Borderless inline selects submit on change, so there is no Apply button
     (a visually hidden one stays for no-JS). Right side: the result count and a Clear link that shows only while a filter is on.
     o = { path, label, keep: html of hidden inputs, search: {name,value,placeholder,label}, controls: [{ name, label, blank, options, value, body }], sort: { name, value, options: [[v, label]] }, count, extra: html, clear: bool } */
  W.filterBar = function (o) {
    var sel = function (c) {
      var body = c.body != null ? c.body : (c.options || []).map(function (x) { var v = Array.isArray(x) ? x[0] : x, l = Array.isArray(x) ? x[1] : x; return '<option value="' + e(v) + '"' + (String(c.value || "") === String(v) ? " selected" : "") + ">" + e(l) + "</option>"; }).join("");
      return '<label class="fbar-sel' + (c.value ? " is-on" : "") + '"><span class="sr">' + e(c.label) + '</span><select name="' + e(c.name) + '" aria-label="' + e(c.label) + '">' + (c.blank ? '<option value="">' + e(c.blank) + "</option>" : "") + body + "</select></label>";
    };
    var search = o.search ? '<label class="fbar-q"><span class="sr">' + e(o.search.label || "Search") + '</span>' + W.ico("search") + '<input type="search" name="' + e(o.search.name) + '" value="' + e(o.search.value || "") + '" placeholder="' + e(o.search.placeholder || "Search") + '"></label>' : "";
    var sort = o.sort ? sel({ name: o.sort.name, label: "Sort by", value: o.sort.value, options: o.sort.options.map(function (x) { return [x[0], "Sort: " + x[1]]; }) }) : "";
    return '<form class="fbar" data-filter="' + e(o.path) + '" aria-label="' + e(o.label) + '"><div class="fbar-l">' + (o.keep || "") + search + (o.controls || []).map(sel).join("") + sort + '</div><div class="fbar-r">' + (o.extra || "") +
      (o.count != null ? '<span class="fbar-n" role="status">' + e(o.count) + "</span>" : "") + (o.clear ? '<a class="fbar-clear" href="' + e(o.path) + '">Clear</a>' : "") + '</div><button class="sr" type="submit">Apply filters</button></form>';
  };

  /* ---------- chrome ---------- */
  W.NAV = [["Home", "#/"], ["About", "#/about"], ["People", "#/people"], ["Businesses", "#/businesses"], ["Institutions", "#/institutions"], ["Industries", "#/industries"], ["Awards", "#/awards"], ["Stories", "#/stories"], ["Events", "#/events"], ["Get listed", "#/subscribe"], ["Nominate", "#/nominate"], ["Contact", "#/contact"]];

  function wfbar(hf) {
    var S = W.S, roles = W.LISTS.roles, staff = S.role !== "visitor";
    return '<div id="wfbar"><div class="wrap"><strong>' + (hf ? "REVIEW TOOLS" : "WIREFRAME") + '</strong><a class="btnlink lead" href="#/design-review">Design review</a><span class="tag">sample data</span>' +
      '<label title="Prototype preview tool only. The real site has no role selector: a user signs in with an email and has one role.">Preview as <select data-change="role" aria-label="Preview as (prototype tool, not part of the real site)">' + Object.keys(roles).map(function (k) { return '<option value="' + k + '"' + (S.role === k ? " selected" : "") + ">" + e(roles[k]) + "</option>"; }).join("") + "</select></label>" +
      (hf && S.flags.nominations.on ? '<span class="tag">Nominations open</span>' : "") +
      (hf && staff ? '<a class="btnlink" href="#/admin">Staff console</a><button type="button" data-act="signout">Sign out</button>' : "") +
      '<button type="button" data-act="outbox">Email outbox (' + S.emails.length + ')</button>' +
      '<a class="btnlink" href="#/inventory">Inventory</a><a class="btnlink" href="#/state">State</a>' +
      '<button type="button" data-act="reset">Reset sample data</button>' + (hf ? '<button type="button" data-act="toolsToggle">Hide tools</button>' : "") + "</div></div>";
  }
  function banners() {
    var S = W.S, out = "";
    var on = Object.keys(S.flags).filter(function (k) { return S.flags[k].on; });
    if (on.length) out += '<div class="banner flags" role="status"><div class="wrap">Nominations are open to the public. <a href="#/admin/nominations">Manage</a></div></div>';
    return out;
  }
  function header(cur) {
    var role = W.S.role, acct = role === "visitor" ? "" : '<a class="btn alt sm" href="#/admin">Staff console</a><button type="button" class="btn alt sm" data-act="signout">Sign out</button>';
    return '<a class="skip" href="#main" data-act="skip">Skip to content</a><header class="site-head"><div class="wrap"><a class="logo" href="#/">' + e(W.brand.name) + '<small>' + e(W.brand.tagline) + "</small></a>" +
      '<div class="head-actions"><form class="search" role="search" data-form="search"><label class="sr" for="hq">Search profiles and stories</label><input id="hq" name="q" type="search" placeholder="Search" style="width:9rem"><button class="btn sm" type="submit">Search</button></form>' + acct +
      '<button type="button" class="btn alt sm nav-toggle" data-act="navToggle" aria-expanded="' + (W.ui.nav ? "true" : "false") + '" aria-controls="mainnav">Menu</button></div>' +
      '<nav class="main-nav' + (W.ui.nav ? " open" : "") + '" id="mainnav" aria-label="Main"><ul>' + W.NAV.map(function (n) {
        var label = n[0] === "Nominate" && !W.nomOpen() ? (W.nomClosed() ? "Nominations closed" : "Nominate (opens soon)") : n[0];
        return '<li><a href="' + n[1] + '"' + (cur === n[1].slice(1) ? ' aria-current="page"' : "") + ">" + e(label) + "</a></li>";
      }).join("") + "</ul></nav></div></header>";
  }
  function footer() {
    return '<footer class="site-foot"><div class="wrap grid c4"><div><strong>' + e(W.brand.name) + '</strong><p class="small">' + e(W.brand.tagline) + '</p><p class="small">Wireframe. Not a live site.</p></div>' +
      '<div><h2 style="font-size:1rem">Trust and rules</h2><ul><li><a href="#/awards/rules">Awards rules</a></li><li><a href="#/complaints">Complaints and takedown</a></li><li><a href="#/faq">FAQ</a></li></ul></div>' +
      '<div><h2 style="font-size:1rem">Legal</h2><ul><li><a href="#/privacy">Privacy policy</a></li><li><a href="#/terms">Terms</a></li><li><a href="#/cookies">Cookie policy</a></li><li><a href="#/data-rights">Your data rights</a></li></ul></div>' +
      '<div><h2 style="font-size:1rem">More</h2><ul><li><a href="#/stories">Stories</a></li><li><a href="#/events">Events</a></li><li><a href="#/contact">Contact</a></li><li><a href="#/sitemap.xml">Sitemap (preview)</a></li><li><a href="#/unsubscribe">Unsubscribe</a></li><li><button type="button" class="btn alt sm" data-act="cookiePrefs" style="color:var(--c-bar-ink);border-color:var(--c-bar-ink)">Cookie preferences</button></li></ul></div></div></footer>';
  }
  /* A small card that appears once the visitor has scrolled a little (see motion.js). */
  function cookieBar() {
    if (W.S.consent) return "";
    return '<div class="cookie" role="region" aria-label="Cookie consent" data-cookie><p>Essential cookies only, unless you accept analytics. <a href="#/cookies">Policy</a></p><div class="cookie-act"><button type="button" class="cookie-btn" data-act="consent" data-arg="essential">Decline</button><button type="button" class="cookie-btn cookie-btn--yes" data-act="consent" data-arg="all">Accept</button></div></div>';
  }
  function flowsUI() {
    var btn = '<button type="button" id="flows-btn" class="btn" data-act="flows" aria-expanded="' + (W.ui.flows ? "true" : "false") + '" aria-controls="flows">Flows</button>';
    var nb = '<button type="button" id="notes-btn" class="btn alt" data-act="notes" aria-expanded="' + (W.ui.notes ? "true" : "false") + '" style="' + (W.ui.notes ? "bottom:" : "") + '">Design notes</button>';
    var show = W.fid === "hf" && W.toolsHidden() ? '<button type="button" id="tools-show" data-act="toolsToggle">Review tools</button>' : "";
    return btn + (W.ui.flows && W.flowsHtml ? W.flowsHtml() : "") + nb + (W.ui.notes ? notesPanel() : "") + show;
  }
  function notesPanel() {
    var cur = W.cur, inv = cur && cur.r && W.invById ? W.invById(cur.inv || cur.r.inv) : null;
    if (!inv) return '<aside id="notes-panel" aria-label="Design notes"><h2>Design notes</h2><p>No inventory entry for this route.</p></aside>';
    return '<aside id="notes-panel" aria-label="Design notes" style="bottom:3.6rem"><h2>' + e(inv.id) + " · " + e(inv.name) + '</h2><p><span class="pill gray">' + e(inv.tag) + "</span> " + e(inv.reqs) + "</p><p>" + e(inv.note || "") + '</p><p class="small"><a href="#/inventory/' + e(inv.id) + '">See in inventory</a></p></aside>';
  }

  /* Public pages use theme/*.css; staff and tool pages keep styles.css. Both stay loaded and switch through media, so nothing flashes. */
  /* The home page opens with the review tools folded away so it can be judged as the real site. */
  W.toolsHidden = function () { return W.ui.toolsHidden === undefined ? !!(W.cur && W.cur.path === "/") : !!W.ui.toolsHidden; };
  function setFidelity(hf) {
    W.fid = hf ? "hf" : "wf";
    if (typeof document === "undefined" || !document.documentElement) return;
    var on = function (id, yes) { var n = document.getElementById(id); if (n) n.media = yes ? "all" : "not all"; };
    on("css-wf", !hf); on("tokens", !hf); on("css-tokens", hf); on("css-site", hf); on("css-home", hf); on("css-tools", hf);
    var c = document.documentElement.classList;
    c.toggle("tools-hidden", hf && W.toolsHidden()); c.toggle("has-cookie", hf && !W.S.consent);
  }
  W.render = function () {
    var app = document.getElementById("app"); if (!app) return;
    var y = window.scrollY, prev = W.cur && W.cur.path;
    var m = W.match(location.hash); W.cur = m;
    var r = m.r, body, area = r.opts.area || "pub", denied = false;
    var roles = r.opts.roles, cap = r.opts.cap;
    var role = W.S.role;
    if (cap ? !W.hasCap(cap) : (roles && roles.indexOf(role) < 0 && role !== "admin")) {
      denied = true; body = W.forbidden ? W.forbidden(m, roles, cap) : "<p>Not authorised.</p>"; area = r.opts.area === "staff" ? "pub" : area;
    } else {
      body = r.fn(m.params, m.q, m);
      if (body && body.redirect) { W.go(body.redirect); return; }
    }
    var inner;
    if (area === "staff") inner = W.appShell(area, body, m.path);
    else inner = body;
    var invId = m.inv || r.inv, inv = W.invById ? W.invById(invId) : null;
    var hf = !denied && (!!r.opts.hf || /^[VNO]/.test(invId));
    setFidelity(hf);
    document.title = (inv ? inv.name + " · " : "") + W.brand.name + (hf ? "" : " wireframe");
    app.innerHTML = hf ? wfbar(true) + W.siteHeader(m.path) + '<main id="main" tabindex="-1">' + inner + "</main>" + W.siteFooter() + W.siteCookie() + flowsUI()
      : wfbar(false) + banners() + header(m.path) + '<main id="main" tabindex="-1">' + inner + "</main>" + footer() + cookieBar() + flowsUI();
    var fresh = m.path !== prev;
    if (fresh) { window.scrollTo(0, 0); var h = document.querySelector("#main h1"); if (h) { h.setAttribute("tabindex", "-1"); h.focus({ preventScroll: true }); } }
    else window.scrollTo(0, y);
    W.paintModal();
    if (hf) { if (W.afterPublic) W.afterPublic({ fresh: fresh }); } else if (W.afterTool) W.afterTool();
    if (m.anchor) { var t = document.getElementById(m.anchor); if (t) t.scrollIntoView(); }
  };

  /* ---------- events ---------- */
  W.boot = function () {
    W.load();
    document.addEventListener("click", function (ev) {
      var el = ev.target.closest && ev.target.closest("[data-act]"); if (!el) return;
      var name = el.getAttribute("data-act"); var fn = W.act[name]; if (!fn) return;
      W.ui.lastFocus = el; ev.preventDefault();
      fn(el.getAttribute("data-arg"), el, ev);
    });
    document.addEventListener("submit", function (ev) {
      var ff = ev.target.closest && ev.target.closest("form[data-filter]"); if (ff) { ev.preventDefault(); var fd = W.formData(ff); fd.page = ""; W.go(ff.getAttribute("data-filter") + W.qs(fd)); return; }
      var f = ev.target.closest && ev.target.closest("form[data-form]"); if (!f) return;
      ev.preventDefault(); var fn = W.forms[f.getAttribute("data-form")]; if (fn) { fn(W.formData(f), f, ev); }
    });
    document.addEventListener("input", function (ev) { var f = ev.target.closest && ev.target.closest("form[data-live]"); if (f && W.live[f.getAttribute("data-live")]) W.live[f.getAttribute("data-live")](f, ev); });
    document.addEventListener("change", function (ev) {
      var t = ev.target; var c = t.getAttribute && t.getAttribute("data-change");
      if (c && W.act[c]) { W.act[c](t.value, t, ev); return; }
      var f = t.closest && t.closest("form[data-filter]");
      if (f) { var d = W.formData(f); d.page = ""; W.go(f.getAttribute("data-filter") + W.qs(d)); return; }
      var lf = t.closest && t.closest("form[data-live]"); if (lf && W.live[lf.getAttribute("data-live")]) W.live[lf.getAttribute("data-live")](lf, ev);
    });
    document.addEventListener("keydown", function (ev) { if (ev.key === "Escape" && W.ui.modal) W.closeModal(); else if (ev.key === "Escape" && W.ui.flows) { W.ui.flows = false; W.render(); } });
    window.addEventListener("hashchange", function () {
      var next = W.parse(String(location.hash).replace(/(.)#.*$/, "$1")).path;
      if (W.transition && W.fid === "hf" && W.cur && next !== W.cur.path && !/^\/(admin|staff|inventory|state)/.test(next)) W.transition(W.render); else W.render();
    });
    W.render();
  };

  /* ---------- shared actions ---------- */
  W.act.go = function (h) { W.go(h); };
  W.act.closeModal = function () { W.closeModal(); };
  W.act.navToggle = function () { W.ui.nav = !W.ui.nav; W.render(); };
  W.act.skip = function () { var m = document.getElementById("main"); if (m) m.focus(); };
  W.act.flows = function () { W.ui.flows = !W.ui.flows; W.render(); };
  W.act.notes = function () { W.ui.notes = !W.ui.notes; W.render(); };
  W.act.toolsToggle = function () { W.ui.toolsHidden = !W.toolsHidden(); W.render(); };
  W.act.reset = function () { W.reset(); W.ui = { flows: false, notes: false, nav: false, modal: null, nom: { step: 0, d: {} }, img: "", toolsHidden: W.ui.toolsHidden }; location.hash = "#/"; W.render(); W.toast("Sample data reset."); };
  W.act.role = function (v) { W.S.role = v; var pu = (W.S.users || []).filter(function (u) { return u.rk === v; })[0]; W.S.user = pu ? pu.email : null; W.save(); var area = W.cur && W.cur.r.opts.area; if (v !== "visitor" && area !== "staff") location.hash = "#/admin"; else if (v === "visitor" && area === "staff") location.hash = "#/"; else W.render(); };
  W.act.signout = function () { W.S.role = "visitor"; W.S.user = null; W.save(); W.toast("Signed out."); location.hash = "#/"; W.render(); };
  W.act.consent = function (v) { W.S.consent = v; W.save(); W.render(); W.toast(v === "all" ? "Analytics accepted. Consent stored with timestamp and policy version." : "Essential cookies only."); };
  W.act.cookiePrefs = function () { W.S.consent = null; W.save(); W.render(); };
  W.act.outbox = function () {
    var em = W.S.emails;
    W.openModal("Sample email outbox", '<p class="small muted">Every email the system would send appears here. Open the link to act as the recipient.</p>' + (em.length ? em.map(function (m) { return '<div class="card" style="margin-bottom:8px"><strong>' + e(m.subject) + '</strong><div class="small muted">To: ' + e(m.to) + '</div><p class="small">' + e(m.body) + "</p>" + (m.link ? '<a class="btn sm" href="' + e(m.link) + '" data-act="closeModal-go" data-arg="' + e(m.link) + '">Open link in email</a>' : "") + "</div>"; }).join("") : W.empty("No emails yet", "Do something that sends mail (nominate, send a correction, submit, approve).")));
  };
  W.act["closeModal-go"] = function (h) { W.ui.modal = null; W.go(h); };
  W.forms.search = function (d) { W.go("#/search" + W.qs({ q: d.q })); };
  W.forbidden = function (m, roles, cap) {
    return '<div class="wrap section"><div class="errbox"><h1>403 \u00b7 Not authorised</h1><p>Your role (<strong>' + e(W.roleName(W.S.role)) + '</strong>) does not have ' + (cap ? 'the capability <strong>' + e(cap) + '</strong>' : 'access to this page') + '. By default it belongs to: ' + (roles || []).map(function (r) { return e(W.roleName(r)); }).join(", ") + '. The Administrator can grant a capability to one user (Users and capabilities).</p><p><a class="btn" href="#/">Go home</a></p></div></div>';
  };
  W.notFound = { inv: "V13", opts: {}, fn: function () { return '<div class="wrap error-page"><p class="error-code" aria-hidden="true">404</p><h1>Page not found</h1><p class="muted">This page may have moved, or it may not be public. We do not say which.</p><div class="row" style="margin-top:1.5rem"><a class="btn" href="#/directory">Search the directory</a><a class="btn alt" href="#/">Back to home</a></div></div>'; } };
})();
