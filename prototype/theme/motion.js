/* Motion and small interactions for the public pages: scroll reveals, page transitions, the mega nav and drawer.
   Nothing here is needed to read a page: with reduced motion or no IntersectionObserver, content is simply visible. */
(function () {
  var W = window.W; if (typeof document === "undefined" || !document.documentElement) return;
  var root = document.documentElement;
  function reduced() { return !!(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches); }
  var io = null;
  function reveal(fresh) {
    if (io) io.disconnect();
    var all = document.querySelectorAll("[data-reveal]");
    if (reduced() || !fresh || !("IntersectionObserver" in window)) { root.classList.remove("js-motion"); all.forEach(function (n) { n.classList.add("in"); }); return; }
    root.classList.add("js-motion");
    io = new IntersectionObserver(function (es) { es.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add("in"); io.unobserve(x.target); } }); }, { rootMargin: "0px 0px -6% 0px", threshold: .04 });
    all.forEach(function (n) { io.observe(n); });
    /* Safety net: nothing stays hidden if the observer never fires. */
    setTimeout(function () { document.querySelectorAll("[data-reveal]:not(.in)").forEach(function (n) { var r = n.getBoundingClientRect(); if (r.top < window.innerHeight) n.classList.add("in"); }); }, 1200);
  }
  /* Smooth wheel scrolling on every public page (the home page runs its own copy, see home.js). Touch and keyboard scrolling stay native. */
  var sm = null, smRaf = 0;
  function smPrevent(n) { return !!(n.closest && n.closest(".dlg, #flows, #notes-panel, .drawer, .mega, .sov, details.filters, textarea, select, .h-rail, .bl-pills, [data-lenis-prevent]")); }
  function smTick(t) { if (!sm) return; sm.raf(t); smRaf = requestAnimationFrame(smTick); }
  function smStop() { if (sm) { cancelAnimationFrame(smRaf); sm.destroy(); sm = null; } }
  function smSync(fresh) {
    var homeLive = !!document.querySelector(".home") && W.home && W.home.state && W.home.state() && W.home.state().live;
    if (homeLive || reduced() || !window.Lenis || !(window.matchMedia && matchMedia("(pointer: fine)").matches)) { smStop(); return; }
    if (!sm) { sm = new window.Lenis({ lerp: .1, wheelMultiplier: 1, smoothWheel: true, prevent: smPrevent }); smRaf = requestAnimationFrame(smTick); }
    if (fresh) sm.scrollTo(0, { immediate: true, force: true });
  }
  function pauseAll(yes) { if (W.home) W.home.pause(yes); if (sm) { if (yes) sm.stop(); else sm.start(); } }
  /* Called by W.render after each paint of a public page. */
  /* The cookie card stays hidden until the visitor has scrolled a little, or at once on a page that is too short to scroll. */
  function cookieCheck() {
    var c = document.querySelector(".cookie[data-cookie]"); if (!c || c.classList.contains("is-in")) return;
    var short = document.documentElement.scrollHeight - window.innerHeight < 360;
    if (window.scrollY > 320 || short) c.classList.add("is-in");
  }
  window.addEventListener("scroll", cookieCheck, { passive: true });
  /* "On the record" rail (phones and tablets): scrolls by itself, and the visitor can swipe, use the arrows, or pause it. */
  var railT = 0, railPause = 0, railOff = false;
  function railEl() { return document.querySelector(".h-rail"); }
  function railStep(dir) {
    var r = railEl(); if (!r || !r.offsetParent) return;
    var item = r.querySelector(".h-rail-item"), w = item ? item.getBoundingClientRect().width + 12 : 240;
    var end = r.scrollLeft + r.clientWidth >= r.scrollWidth - 4;
    if (dir > 0 && end) r.scrollTo({ left: 0, behavior: reduced() ? "auto" : "smooth" });
    else if (dir < 0 && r.scrollLeft <= 4) r.scrollTo({ left: r.scrollWidth, behavior: reduced() ? "auto" : "smooth" });
    else r.scrollBy({ left: dir * w, behavior: reduced() ? "auto" : "smooth" });
  }
  function railInit() {
    clearInterval(railT); railT = 0; railOff = reduced();
    var r = railEl(); if (!r) return;
    var hold = function () { railPause = Date.now() + 7000; };
    ["pointerdown", "touchstart", "wheel", "focusin"].forEach(function (t) { r.addEventListener(t, hold, { passive: true }); });
    railT = setInterval(function () {
      if (railOff || document.hidden || Date.now() < railPause || !r.isConnected || !r.offsetParent) return;
      var b = r.getBoundingClientRect(); if (b.bottom < 0 || b.top > window.innerHeight) return;
      railStep(1);
    }, 3200);
  }
  /* Confetti on the winners page: one short burst when the page is opened, in the brand colours (read from the tokens). Off for reduced motion. */
  function confetti() {
    if (reduced() || document.getElementById("confetti")) return;
    var cs = getComputedStyle(root), cols = ["--orange", "--blue", "--purple", "--green", "--red", "--orange-200"].map(function (v) { return cs.getPropertyValue(v).trim(); }).filter(Boolean);
    if (!cols.length) return;
    var c = document.createElement("canvas"); c.id = "confetti"; c.setAttribute("aria-hidden", "true");
    c.style.cssText = "position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:60";
    var dpr = Math.min(window.devicePixelRatio || 1, 2), w = window.innerWidth, h = window.innerHeight;
    c.width = w * dpr; c.height = h * dpr; document.body.appendChild(c);
    var x = c.getContext("2d"); x.scale(dpr, dpr);
    var bits = [], n = w < 600 ? 90 : 170;
    for (var i = 0; i < n; i++) {
      var left = i % 2 === 0, a = (left ? -1 : 1) * (.35 + Math.random() * .6), sp = 9 + Math.random() * 11;
      bits.push({ x: left ? -10 : w + 10, y: h * (.55 + Math.random() * .2), vx: -Math.sin(a) * sp * (left ? -1 : -1) * -1, vy: -Math.cos(a) * sp - 4, r: Math.random() * 6.28, vr: (Math.random() - .5) * .4, w: 6 + Math.random() * 7, h: 3 + Math.random() * 5, c: cols[i % cols.length], t: Math.random() * 6 });
    }
    bits.forEach(function (b, i) { b.vx = (i % 2 === 0 ? 1 : -1) * (3 + Math.random() * 8); });
    var t0 = performance.now(), last = t0;
    (function tick(now) {
      var dt = Math.min(32, now - last) / 16.7; last = now;
      x.clearRect(0, 0, w, h); var alive = 0, fade = Math.max(0, Math.min(1, (3800 - (now - t0)) / 900));
      bits.forEach(function (b) {
        b.vy += .32 * dt; b.vx *= .992; b.x += b.vx * dt; b.y += b.vy * dt; b.r += b.vr * dt; b.t += .12 * dt;
        if (b.y < h + 20) alive++;
        x.save(); x.globalAlpha = fade; x.translate(b.x, b.y); x.rotate(b.r); x.scale(1, Math.cos(b.t)); x.fillStyle = b.c; x.fillRect(-b.w / 2, -b.h / 2, b.w, b.h); x.restore();
      });
      if (alive && now - t0 < 4200 && c.isConnected) requestAnimationFrame(tick); else c.remove();
    })(t0);
  }
  W.confetti = confetti;
  W.afterPublic = function (info) {
    cookieCheck(); railInit();
    if (info.fresh && /^#\/awards\/winners(\?|$)/.test(location.hash)) confetti();
    closeMega();
    reveal(info.fresh);
    if (window.matchMedia && matchMedia("(min-width: 64rem)").matches) document.querySelectorAll("details[data-open-wide]").forEach(function (d) { d.open = true; });
    if (W.home) W.home.sync(!!document.querySelector(".home"), info.fresh);
    smSync(info.fresh);
    if (info.fresh && !reduced() && !document.startViewTransition) { root.classList.remove("page-enter"); void root.offsetWidth; root.classList.add("page-enter"); }
  };
  W.afterTool = function () { root.classList.remove("js-motion", "page-enter"); if (W.home) W.home.sync(false); smStop(); closeMega(); };
  /* View Transitions where supported; the fallback is the page-enter animation above. */
  W.transition = function (fn) {
    if (!document.startViewTransition || reduced()) { fn(); return; }
    var t = document.startViewTransition(fn), quiet = function () {};
    /* A new navigation skips the running transition; that is expected, not an error. */
    if (t && t.ready) t.ready.catch(quiet);
    if (t && t.finished) t.finished.catch(quiet);
  };
  /* ---------- mega nav ---------- */
  var closeT, byHover = false;
  function closeMega() {
    clearTimeout(closeT);
    document.querySelectorAll(".mega.open").forEach(function (m) { m.classList.remove("open"); });
    document.querySelectorAll('.nav-link[aria-expanded="true"]').forEach(function (b) { b.setAttribute("aria-expanded", "false"); });
  }
  function openMega(id, btn) {
    clearTimeout(closeT); closeMega();
    var m = document.getElementById(id); if (!m) return;
    m.classList.add("open"); btn.setAttribute("aria-expanded", "true");
  }
  W.act.mega = function (id, btn) {
    var open = btn.getAttribute("aria-expanded") === "true";
    /* The click that follows a hover keeps the menu open instead of toggling it shut. */
    if (open && byHover) { byHover = false; return; }
    if (open) closeMega(); else openMega(id, btn);
  };
  document.addEventListener("pointerover", function (ev) {
    if (ev.pointerType !== "mouse" || !ev.target.closest) return;
    var b = ev.target.closest(".nav-link[aria-controls]");
    if (b && b.getAttribute("aria-expanded") !== "true") { openMega(b.getAttribute("aria-controls"), b); byHover = true; return; }
    if (ev.target.closest(".mega, .nav-link[aria-controls]")) { clearTimeout(closeT); return; }
    if (document.querySelector(".mega.open")) { clearTimeout(closeT); closeT = setTimeout(closeMega, 180); }
  });
  document.addEventListener("click", function (ev) {
    if (!ev.target.closest) return;
    if (ev.target.closest(".mega a")) closeMega();
    else if (!ev.target.closest(".mega, .nav-link[aria-controls]")) closeMega();
  });
  document.addEventListener("keydown", function (ev) {
    if (ev.key !== "Escape") return;
    var open = document.querySelector('.nav-link[aria-expanded="true"]');
    if (open) { closeMega(); open.focus(); }
    var d = document.querySelector(".drawer.open"); if (d) W.act.drawer("close");
    if (document.querySelector(".sov.open")) W.act.searchClose();
    var f = document.querySelector("details.filters[open]"); if (f) { f.open = false; f.querySelector("summary").focus(); }
  });
  /* The directory's filter panel drops down over the results; a click outside it closes it. */
  document.addEventListener("click", function (ev) { var f = document.querySelector("details.filters[open]"); if (f && ev.target.closest && !ev.target.closest("details.filters")) f.open = false; });
  /* Keep Tab inside an open dialog or drawer. */
  document.addEventListener("keydown", function (ev) {
    if (ev.key !== "Tab") return;
    var box = document.querySelector("#modal-root .dlg") || document.querySelector(".drawer.open") || document.querySelector(".sov.open"); if (!box) return;
    var items = [].filter.call(box.querySelectorAll('a[href],button,input:not([type="hidden"]),select,textarea,summary'), function (n) { return n.offsetParent !== null; });
    if (!items.length) return;
    var first = items[0], last = items[items.length - 1], cur = document.activeElement;
    if (!box.contains(cur)) { ev.preventDefault(); first.focus(); } else if (ev.shiftKey && cur === first) { ev.preventDefault(); last.focus(); } else if (!ev.shiftKey && cur === last) { ev.preventDefault(); first.focus(); }
  });
  /* ---------- search panel ---------- */
  var sovT = 0;
  W.act.searchOpen = function () {
    var o = document.getElementById("searchOv"); if (!o) return;
    if (document.querySelector(".drawer.open")) W.act.drawer("close");
    o.hidden = false; void o.offsetWidth; o.classList.add("open");
    var b = document.querySelector(".search-link"); if (b) b.setAttribute("aria-expanded", "true");
    document.body.style.overflow = "hidden"; pauseAll(true);
    var i = document.getElementById("sovq"); if (i) { i.value = ""; document.getElementById("sovRes").innerHTML = W.searchPanelResults(""); setTimeout(function () { i.focus(); }, 30); }
  };
  W.act.searchClose = function (quiet) {
    var o = document.getElementById("searchOv"); if (!o || !o.classList.contains("open")) return;
    o.classList.remove("open"); clearTimeout(sovT); sovT = setTimeout(function () { if (!o.classList.contains("open")) o.hidden = true; }, reduced() ? 0 : 320);
    var b = document.querySelector(".search-link"); if (b) b.setAttribute("aria-expanded", "false");
    document.body.style.overflow = ""; pauseAll(false);
    if (b && quiet !== true) b.focus();
  };
  document.addEventListener("input", function (ev) { if (ev.target && ev.target.id === "sovq") { var r = document.getElementById("sovRes"); if (r) r.innerHTML = W.searchPanelResults(ev.target.value); } });
  document.addEventListener("click", function (ev) { if (!ev.target.closest) return; var o = ev.target.closest(".sov"); if (o && (ev.target === o || ev.target.closest(".sov a"))) W.act.searchClose(true); });
  window.addEventListener("hashchange", function () { if (document.querySelector(".sov.open")) W.act.searchClose(true); document.body.style.overflow = ""; });
  /* Menu sections in the mobile drawer open and close with a height and fade animation instead of snapping. */
  document.addEventListener("click", function (ev) {
    var s = ev.target.closest && ev.target.closest(".drawer summary"); if (!s) return;
    var d = s.parentElement, list = d.querySelector("ul"); if (!list || reduced() || !list.animate) return;
    ev.preventDefault();
    if (d._anim) { d._anim.cancel(); d._anim = null; }
    var opts = { duration: 300, easing: "cubic-bezier(.2,.7,.2,1)" };
    list.style.overflow = "hidden"; list.style.boxSizing = "border-box";
    if (!d.open) {
      d.classList.remove("is-closing"); d.open = true;
      var h = list.offsetHeight, pb = parseFloat(getComputedStyle(list).paddingBottom) || 0;
      d._anim = list.animate([{ height: "0px", paddingBottom: "0px", opacity: 0 }, { height: h + "px", paddingBottom: pb + "px", opacity: 1 }], opts);
      d._anim.onfinish = function () { d._anim = null; list.style.overflow = ""; list.style.boxSizing = ""; };
    } else {
      var h2 = list.offsetHeight, pb2 = parseFloat(getComputedStyle(list).paddingBottom) || 0;
      d.classList.add("is-closing");
      d._anim = list.animate([{ height: h2 + "px", paddingBottom: pb2 + "px", opacity: 1 }, { height: "0px", paddingBottom: "0px", opacity: 0 }], opts);
      d._anim.onfinish = function () { d._anim = null; d.open = false; d.classList.remove("is-closing"); list.style.overflow = ""; list.style.boxSizing = ""; };
    }
  });
  /* ---------- mobile drawer ---------- */
  W.act.drawer = function (how) {
    var d = document.getElementById("drawer"); if (!d) return;
    var show = how !== "close"; d.classList.toggle("open", show);
    var b = document.querySelector(".menu-btn"); if (b) b.setAttribute("aria-expanded", show ? "true" : "false");
    document.body.style.overflow = show ? "hidden" : ""; pauseAll(show);
    if (show) { var f = d.querySelector("button"); if (f) f.focus(); } else if (b) b.focus();
  };
  document.addEventListener("click", function (ev) { if (ev.target.closest && ev.target.closest(".drawer a")) { var d = document.getElementById("drawer"); if (d) d.classList.remove("open"); document.body.style.overflow = ""; } });
  window.addEventListener("scroll", function () { root.classList.toggle("is-scrolled", window.scrollY > 8); }, { passive: true });
})();
