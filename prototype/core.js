/* Core: state store, selectors, helpers, router, chrome, events. No framework, no build. */
(function () {
  var W = (window.W = window.W || {});
  var KEY = "wiw-wireframe-v3";
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
    W.S = raw && raw.v === 3 ? raw : W.seed();
    if (W.S.role === undefined) W.S.role = "visitor";
    if (W.S.lists) Object.keys(W.S.lists).forEach(function (k) { W.LISTS[k] = W.S.lists[k]; });
  };
  W.save = function () { try { localStorage.setItem(KEY, JSON.stringify(W.S)); } catch (x) { /* storage blocked: state lives for this page only */ } };
  W.reset = function () { try { localStorage.removeItem(KEY); } catch (x) { } W.S = W.seed(); W.LISTS.industries = W.S.lists.industries.slice(); W.LISTS.cities = W.S.lists.cities.slice(); W.LISTS.orgTypes = W.S.lists.orgTypes.slice(); W.LISTS.professions = W.S.lists.professions.slice(); W.save(); };
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
  W.isKilled = function (p) { return !!(W.S.kill.on && p.type === "individual" && p.trust === "unclaimed" && p.status === "published"); };
  W.vis = function (p) {
    if (!p) return "none";
    if (p.status === "published") return W.isKilled(p) ? "gone" : "public";
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
  W.sponsorOf = function (p) {
    if (p.sponsored) return p.sponsored;
    var hit = W.S.arrangements.filter(function (a) { return a.type === "sponsored_profile" && a.linkedProfile === p.id && W.arrStatus(a) === "confirmed" && W.inWindow(a); })[0];
    if (hit) { var c = W.find("customers", hit.customerId); return c ? c.name : "Sponsor"; }
    return null;
  };
  W.sponsorOfArticle = function (a) {
    if (a.sponsor) return a.sponsor;
    var hit = W.S.arrangements.filter(function (x) { return x.type === "sponsored_post" && x.linkedArticle === a.id && W.arrStatus(x) === "confirmed" && W.inWindow(x); })[0];
    if (hit) { var c = W.find("customers", hit.customerId); return c ? c.name : "Sponsor"; }
    return null;
  };
  W.publicArticles = function () { return W.S.articles.filter(function (a) { return a.status === "published"; }); };
  W.queue = function () { return W.S.profiles.filter(function (p) { return p.status === "pending" || p.edit; }); };
  W.ok = function (name) { return !!W.S.flags[name].on; };
  W.nomOpen = function () { return W.ok("nominations"); };
  W.shortlistVisible = function () { return W.ok("judging") || W.S.nominations.some(function (n) { return n.status === "shortlisted" && n.publicOK; }); };
  W.nomStage = function (n) {
    var m = { submitted: 2, screening: 4, invited: 4, accepted: 5, assessment: 5, shortlisted: 6, finalist: 7, winner: 9, ineligible: 4, declined: 4 };
    return m[n.status] || 2;
  };

  /* ---------- html bits ---------- */
  W.trustPill = function (p) { var t = p.trust; return '<span class="pill t-' + t + '" title="Trust status (REQ-024)">' + (t === "verified" ? "✓ " : t === "featured" ? "★ " : "") + W.cap(t) + "</span>"; };
  W.spPill = function (name) { return name ? ' <span class="pill sponsored" title="Paid placement disclosure. Not a trust status (REQ-155).">Sponsored · ' + e(name) + "</span>" : ""; };
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
  W.bot = function () { return '<p class="bot small">Bot challenge placeholder (shown on every public form, REQ-081). <label class="check" style="display:inline-flex"><input type="checkbox" name="human" required> I am human</label></p>'; };
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
    if (m) { var f = r.querySelector("input,select,textarea,button.btn:not(.alt)") || r.querySelector("button"); if (f) f.focus(); }
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
    var p = W.parse(hash);
    for (var i = 0; i < W.routes.length; i++) {
      var m = W.routes[i].re.exec(p.path);
      if (m) { var params = {}; W.routes[i].keys.forEach(function (k, j) { params[k] = decodeURIComponent(m[j + 1]); }); return { r: W.routes[i], params: params, q: p.q, path: p.path }; }
    }
    return { r: W.notFound, params: {}, q: p.q, path: p.path };
  };
  W.qs = function (q) { var s = Object.keys(q).filter(function (k) { return q[k] !== "" && q[k] != null; }).map(function (k) { return encodeURIComponent(k) + "=" + encodeURIComponent(q[k]); }).join("&"); return s ? "?" + s : ""; };

  /* ---------- chrome ---------- */
  W.NAV = [["Home", "#/"], ["About", "#/about"], ["People", "#/people"], ["Businesses", "#/businesses"], ["Institutions", "#/institutions"], ["Industries", "#/industries"], ["Awards", "#/awards"], ["Stories", "#/stories"], ["Get Featured", "#/subscribe"], ["Nominate", "#/nominate"], ["Contact", "#/contact"]];

  function wfbar() {
    var S = W.S, roles = W.LISTS.roles;
    return '<div id="wfbar"><div class="wrap"><strong>WIREFRAME</strong><span class="tag">sample data</span>' +
      '<label>Viewing as <select data-change="role" aria-label="Viewing as (role simulator)">' + Object.keys(roles).map(function (k) { return '<option value="' + k + '"' + (S.role === k ? " selected" : "") + ">" + e(roles[k]) + "</option>"; }).join("") + "</select></label>" +
      '<button type="button" data-act="outbox">Email outbox (' + S.emails.length + ')</button>' +
      '<a class="btnlink" href="#/inventory">Inventory</a><a class="btnlink" href="#/state">State</a>' +
      '<button type="button" data-act="reset">Reset sample data</button></div></div>';
  }
  function banners() {
    var S = W.S, out = "";
    if (S.kill.on) out += '<div class="banner kill" role="status"><div class="wrap"><strong>Kill switch is ON.</strong> All unclaimed individual profiles return 410 and are hidden from listings, search and the sitemap. <a href="#/admin/killswitch" style="color:inherit">Manage</a></div></div>';
    var on = Object.keys(S.flags).filter(function (k) { return S.flags[k].on; });
    if (on.length) out += '<div class="banner flags" role="status"><div class="wrap">Feature flags ON: ' + on.map(function (k) { return "<strong>" + e(k) + "</strong>"; }).join(", ") + '. <a href="#/admin/flags">Manage</a></div></div>';
    return out;
  }
  function header(cur) {
    var role = W.S.role, acct = role === "visitor" ? '<a class="btn alt sm" href="#/login">Sign in</a>' : role === "owner" ? '<a class="btn alt sm" href="#/account">My profile</a>' : role === "judge" ? '<a class="btn alt sm" href="#/judge">Judge area</a>' : '<a class="btn alt sm" href="#/admin">Staff console</a>';
    return '<a class="skip" href="#main" data-act="skip">Skip to content</a><header class="site-head"><div class="wrap"><a class="logo" href="#/">' + e(W.brand.name) + '<small>' + e(W.brand.tagline) + "</small></a>" +
      '<div class="head-actions"><form class="search" role="search" data-form="search"><label class="sr" for="hq">Search profiles and stories</label><input id="hq" name="q" type="search" placeholder="Search" style="width:9rem"><button class="btn sm" type="submit">Search</button></form>' + acct +
      '<button type="button" class="btn alt sm nav-toggle" data-act="navToggle" aria-expanded="' + (W.ui.nav ? "true" : "false") + '" aria-controls="mainnav">Menu</button></div>' +
      '<nav class="main-nav' + (W.ui.nav ? " open" : "") + '" id="mainnav" aria-label="Main"><ul>' + W.NAV.map(function (n) {
        var label = n[0] === "Nominate" && !W.nomOpen() ? "Nominate (opens soon)" : n[0];
        return '<li><a href="' + n[1] + '"' + (cur === n[1].slice(1) ? ' aria-current="page"' : "") + ">" + e(label) + "</a></li>";
      }).join("") + "</ul></nav></div></header>";
  }
  function footer() {
    return '<footer class="site-foot"><div class="wrap grid c4"><div><strong>' + e(W.brand.name) + '</strong><p class="small">' + e(W.brand.tagline) + '</p><p class="small">Wireframe. Not a live site.</p></div>' +
      '<div><h2 style="font-size:1rem">Trust and rules</h2><ul><li><a href="#/how-we-compile">How we compile profiles</a></li><li><a href="#/awards/rules">Awards rules</a></li><li><a href="#/complaints">Complaints and takedown</a></li><li><a href="#/faq">FAQ</a></li></ul></div>' +
      '<div><h2 style="font-size:1rem">Legal</h2><ul><li><a href="#/privacy">Privacy policy</a></li><li><a href="#/terms">Terms</a></li><li><a href="#/cookies">Cookie policy</a></li><li><a href="#/commercial-terms">Commercial terms</a></li><li><a href="#/data-rights">Your data rights</a></li></ul></div>' +
      '<div><h2 style="font-size:1rem">More</h2><ul><li><a href="#/contact">Contact</a></li><li><a href="#/sitemap.xml">Sitemap (preview)</a></li><li><a href="#/unsubscribe">Unsubscribe</a></li><li><button type="button" class="btn alt sm" data-act="cookiePrefs" style="color:var(--c-bar-ink);border-color:var(--c-bar-ink)">Cookie preferences</button></li></ul></div></div></footer>';
  }
  function cookieBar() {
    if (W.S.consent) return "";
    return '<div class="cookie" role="region" aria-label="Cookie consent"><div class="wrap row between"><p style="margin:0">We use essential cookies. Analytics load only if you accept (REQ-062). <a href="#/cookies">Cookie policy</a></p><div class="row"><button type="button" class="btn alt sm" data-act="consent" data-arg="essential">Essential only</button><button type="button" class="btn sm" data-act="consent" data-arg="all">Accept analytics</button></div></div></div>';
  }
  function flowsUI() {
    var btn = '<button type="button" id="flows-btn" class="btn" data-act="flows" aria-expanded="' + (W.ui.flows ? "true" : "false") + '" aria-controls="flows">Flows</button>';
    var nb = '<button type="button" id="notes-btn" class="btn alt" data-act="notes" aria-expanded="' + (W.ui.notes ? "true" : "false") + '" style="' + (W.ui.notes ? "bottom:" : "") + '">Design notes</button>';
    return btn + (W.ui.flows && W.flowsHtml ? W.flowsHtml() : "") + nb + (W.ui.notes ? notesPanel() : "");
  }
  function notesPanel() {
    var cur = W.cur, inv = cur && cur.r && W.invById ? W.invById(cur.inv || cur.r.inv) : null;
    if (!inv) return '<aside id="notes-panel" aria-label="Design notes"><h2>Design notes</h2><p>No inventory entry for this route.</p></aside>';
    return '<aside id="notes-panel" aria-label="Design notes" style="bottom:3.6rem"><h2>' + e(inv.id) + " · " + e(inv.name) + '</h2><p><span class="pill gray">' + e(inv.tag) + "</span> " + e(inv.reqs) + "</p><p>" + e(inv.note || "") + '</p><p class="small"><a href="#/inventory/' + e(inv.id) + '">See in inventory</a></p></aside>';
  }

  W.render = function () {
    var app = document.getElementById("app"); if (!app) return;
    var y = window.scrollY, prev = W.cur && W.cur.path;
    var m = W.match(location.hash); W.cur = m;
    var r = m.r, body, area = r.opts.area || "pub";
    var roles = r.opts.roles;
    var role = W.S.role;
    if (area === "own" && role !== "owner") {
      body = W.loginGate ? W.loginGate(m) : "<p>Sign in required.</p>"; area = "pub";
    } else if (roles && roles.indexOf(role) < 0 && (role !== "admin" || area === "judge")) {
      body = W.forbidden ? W.forbidden(m, roles) : "<p>Not authorised.</p>"; area = r.opts.area === "staff" ? "pub" : area;
    } else {
      body = r.fn(m.params, m.q, m);
      if (body && body.redirect) { W.go(body.redirect); return; }
    }
    var inner, tab = "";
    if (area === "staff" || area === "judge") inner = W.appShell(area, body, m.path);
    else if (area === "own") inner = '<div class="wrap section">' + W.ownerTabs(m.path) + body + "</div>";
    else inner = body;
    var inv = W.invById ? W.invById(m.inv || r.inv) : null;
    document.title = (inv ? inv.name + " · " : "") + W.brand.name + " wireframe";
    app.innerHTML = wfbar() + banners() + header(m.path) + '<main id="main" tabindex="-1">' + inner + "</main>" + footer() + cookieBar() + flowsUI();
    if (m.path !== prev) { window.scrollTo(0, 0); var h = document.querySelector("#main h1"); if (h) { h.setAttribute("tabindex", "-1"); h.focus({ preventScroll: true }); } }
    else window.scrollTo(0, y);
    W.paintModal();
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
    window.addEventListener("hashchange", W.render);
    W.render();
  };

  /* ---------- shared actions ---------- */
  W.act.go = function (h) { W.go(h); };
  W.act.closeModal = function () { W.closeModal(); };
  W.act.navToggle = function () { W.ui.nav = !W.ui.nav; W.render(); };
  W.act.skip = function () { var m = document.getElementById("main"); if (m) m.focus(); };
  W.act.flows = function () { W.ui.flows = !W.ui.flows; W.render(); };
  W.act.notes = function () { W.ui.notes = !W.ui.notes; W.render(); };
  W.act.reset = function () { W.reset(); W.ui = { flows: false, notes: false, nav: false, modal: null }; location.hash = "#/"; W.render(); W.toast("Sample data reset."); };
  W.act.role = function (v) { W.S.role = v; W.save(); var p = W.cur && W.cur.path; var area = W.cur && W.cur.r.opts.area; if (v === "judge") location.hash = "#/judge"; else if (["reviewer", "editor", "sales", "finance", "eic", "admin"].indexOf(v) >= 0 && area !== "staff") location.hash = "#/admin"; else if (v === "owner" && area !== "own") location.hash = "#/account"; else if (v === "visitor" && (area === "staff" || area === "own" || area === "judge")) location.hash = "#/"; else W.render(); };
  W.act.consent = function (v) { W.S.consent = v; W.save(); W.render(); W.toast(v === "all" ? "Analytics accepted. Consent stored with timestamp and policy version." : "Essential cookies only."); };
  W.act.cookiePrefs = function () { W.S.consent = null; W.save(); W.render(); };
  W.act.outbox = function () {
    var em = W.S.emails;
    W.openModal("Sample email outbox", '<p class="small muted">Every email the system would send appears here. Open the link to act as the recipient.</p>' + (em.length ? em.map(function (m) { return '<div class="card" style="margin-bottom:8px"><strong>' + e(m.subject) + '</strong><div class="small muted">To: ' + e(m.to) + '</div><p class="small">' + e(m.body) + "</p>" + (m.link ? '<a class="btn sm" href="' + e(m.link) + '" data-act="closeModal-go" data-arg="' + e(m.link) + '">Open link in email</a>' : "") + "</div>"; }).join("") : W.empty("No emails yet", "Do something that sends mail (sign up, submit, approve).")));
  };
  W.act["closeModal-go"] = function (h) { W.ui.modal = null; W.go(h); };
  W.forms.search = function (d) { W.go("#/search" + W.qs({ q: d.q })); };
  W.loginGate = function (m) {
    return '<div class="wrap section"><div class="infobox"><h1>Sign in to continue</h1><p>This page is for profile owners. You are viewing as <strong>' + e(W.LISTS.roles[W.S.role]) + '</strong>.</p><p><a class="btn" href="#/login">Go to sign in</a> <button class="btn alt" data-act="role" data-arg="owner">Wireframe shortcut: view as owner</button></p></div></div>';
  };
  W.forbidden = function (m, roles) {
    return '<div class="wrap section"><div class="errbox"><h1>403 · Not authorised</h1><p>Your role (<strong>' + e(W.roleName(W.S.role)) + '</strong>) cannot open this page. Roles that can: ' + roles.map(function (r) { return e(W.roleName(r)); }).join(", ") + ', Admin.</p><p><a class="btn" href="#/">Go home</a></p></div></div>';
  };
  W.notFound = { inv: "V13", opts: {}, fn: function () { return '<div class="wrap section"><h1>404 · Page not found</h1><p>The page may not exist, or it is not public. Nothing is revealed about unpublished items (REQ-208).</p><p><a class="btn" href="#/">Home</a> <a class="btn alt" href="#/people">Discover people</a></p></div>'; } };
})();
