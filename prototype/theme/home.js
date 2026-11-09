/* Home page choreography: smooth scroll, the 3D W, the colour scenes, cursor, preloader.
   Everything is added on top of a page that already works. With reduced motion or a data-saver setting none of it runs. */
(function () {
  var W = window.W; if (typeof document === "undefined" || !document.documentElement) return;
  var root = document.documentElement, on = false, raf = 0, lenis = null, st = null;
  var OFF = [243, 239, 230], ORANGE = [255, 166, 0], CHAR = [47, 47, 47];
  function reduced() { return !!(window.matchMedia && matchMedia("(prefers-reduced-motion: reduce)").matches); }
  function saveData() { var c = navigator.connection; return !!(c && (c.saveData || /(^|-)2g$/.test(c.effectiveType || ""))); }
  function fine() { return !!(window.matchMedia && matchMedia("(pointer: fine)").matches); }
  function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
  function ss(a, b, x) { var t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); }
  function mix(a, b, t) { return a + (b - a) * t; }
  function mixC(a, b, t) { return [mix(a[0], b[0], t), mix(a[1], b[1], t), mix(a[2], b[2], t)]; }

  function measure() {
    if (!st) return;
    var y = window.scrollY, r = function (n) { if (!n) return { top: 0, h: 0 }; var b = n.getBoundingClientRect(); return { top: b.top + y, h: b.height }; }, E = st.els;
    st.vh = window.innerHeight; st.vw = window.innerWidth;
    st.hero = r(E.hero); st.acts = r(E.actsSec);
    st.wideStories = st.vw >= 1024 && st.live;
    if (E.stories && E.track) {
      var dist = Math.max(0, E.track.scrollWidth - st.vw + 80);
      E.stories.style.height = st.wideStories ? (dist + st.vh) + "px" : "";
      st.storyDist = dist;
    }
    st.acts = r(E.actsSec); st.storiesR = r(E.stories);
  }
  function heroFrame(now, s) {
    var E = st.els, hl = clamp(s / Math.max(1, st.hero.h), 0, 1);
    st.mx += (st.px - st.mx) * .05; st.my += (st.py - st.my) * .05;
    if (st.wOn) W.heroW.frame(now, { px: st.mx, py: st.my, open: hl });
    /* the two lines of the title part as the hero leaves */
    if (E.lines.length > 1) { E.lines[0].style.transform = "translate3d(" + (-hl * 9).toFixed(2) + "vw,0,0)"; E.lines[1].style.transform = "translate3d(" + (hl * 9).toFixed(2) + "vw,0,0)"; }
    /* the videos stagger away and darken as the hero leaves (CSS reads --hx) */
    if (E.hero) E.hero.style.setProperty("--hx", hl.toFixed(3));
    if (E.foot) E.foot.style.opacity = (1 - clamp(hl * 2.4, 0, 1)).toFixed(3);
  }
  function frame(now) {
    if (!on || !st) return;
    raf = requestAnimationFrame(frame);
    if (lenis) lenis.raf(now);
    var y = window.scrollY; st.y += (y - st.y) * (lenis ? 1 : .16);
    var s = st.y, vh = st.vh, E = st.els;
    /* header */
    root.classList.toggle("at-top", s < 60);
    if (Math.abs(y - st.lastY) > 4) { root.classList.toggle("head-hidden", y > st.lastY && y > vh * .8 && !document.querySelector(".mega.open")); st.lastY = y; }
    if (s < st.hero.h + 40 && !document.hidden) heroFrame(now, s);
    /* The particle stage only starts once the visitor begins to scroll. */
    if (!st.stageAsked && s > st.hero.h * .2) {
      st.stageAsked = true;
      if (W.stage) W.stage.init(E.canvas, function () { if (!st) return; st.layout = W.stage.layout(); st.stageReady = true; root.classList.add("stage-on"); });
    }
    /* acts: three ordinary screens. a runs 0 to 1 while they pass; each mark forms as its act takes the screen. */
    var a = clamp((s - st.acts.top) / Math.max(1, st.acts.h - vh), 0, 1);
    var cur = a < .25 ? 0 : a < .75 ? 1 : 2, seg = 0, t = 0;
    if (cur !== st.act) st.act = cur;
    E.acts.forEach(function (n, i) { if (!n.classList.contains("is-on") && s > st.acts.top + (i - .55) * vh) n.classList.add("is-on"); });
    var toStar = ss(st.acts.top - vh * .9, st.acts.top - vh * .1, s), toComb = ss(.14, .4, a), toSpiral = ss(.6, .86, a);
    if (toSpiral > 0) { seg = 2; t = toSpiral; } else if (toComb > 0) { seg = 1; t = toComb; } else { seg = 0; t = toStar; }
    /* the particles only show inside the scenes */
    if (E.stage) { var top = Math.max(0, st.acts.top - s), bot = Math.max(0, vh - (st.acts.top + st.acts.h - s)); E.stage.style.clipPath = top >= vh || bot >= vh ? "inset(50% 0 50% 0)" : "inset(" + top.toFixed(0) + "px 0 " + bot.toFixed(0) + "px 0)"; }
    /* stage */
    var past = s > st.acts.top + st.acts.h - vh * .1, before = s < st.acts.top - vh * 1.1;
    if (!past && !before && st.stageReady && !document.hidden) {
      var L = st.layout;
      var pcol = mixC(OFF, CHAR, toComb * (1 - toSpiral)), pacc = mixC(ORANGE, OFF, toComb * (1 - toSpiral));
      st.mx += (st.px - st.mx) * .06; st.my += (st.py - st.my) * .06;
      W.stage.frame(now, {
        seg: seg, t: t, alpha: 1,
        globe: { s: L.globeS, x: L.markX, y: L.markY }, mark: { s: L.markS, x: L.markX, y: L.markY },
        color: pcol, accent: pacc,
        spin: [-.32 + st.mx * .2, .08 + st.my * .1], mouse: st.hasPointer ? [(st.px * .5) * L.W, (-st.py * .5) * L.H] : null
      });
    }
    /* stories track */
    if (st.wideStories && E.track) { var p = clamp((s - st.storiesR.top) / Math.max(1, st.storyDist), 0, 1); E.track.style.transform = "translate3d(" + (-p * st.storyDist).toFixed(1) + "px,0,0)"; }
    /* preview card and cursor */
    /* the page moved under a still pointer: re-check what is under it */
    if (st.fine && st.hasPointer && Math.abs(y - st.peekY) > 2) { st.peekY = y; var under = document.elementFromPoint(st.tx, st.ty); peekAt(under); cursorAt(under); }
    if (E.peek && st.fine) {
      st.cx += (st.tx - st.cx) * .14; st.cy += (st.ty - st.cy) * .14;
      E.peek.style.transform = "translate3d(" + (st.cx + 56).toFixed(1) + "px," + (st.cy - 170).toFixed(1) + "px,0) rotate(" + clamp((st.tx - st.cx) * .03, -6, 6).toFixed(2) + "deg)";
      if (E.cursor) { st.ox += (st.tx - st.ox) * .32; st.oy += (st.ty - st.oy) * .32; E.cursor.style.transform = "translate3d(" + st.ox.toFixed(1) + "px," + st.oy.toFixed(1) + "px,0)"; }
    }
  }
  /* The preview card shows for the name under the pointer, and is re-checked while the page scrolls under a still pointer. */
  function peekAt(target) {
    var E = st.els; if (!E.peek) return;
    var hit = target && target.closest ? target.closest("[data-peek-name]") : null;
    if (hit && hit !== st.peekOn) {
      st.peekOn = hit;
      var src = "theme/assets/mark-" + hit.getAttribute("data-peek-mark") + ".webp";
      var ph = hit.getAttribute("data-peek-photo");
      E.peek.className = "h-peek is-on h-peek--" + hit.getAttribute("data-peek-tone") + (ph ? " h-peek--photo" : "");
      E.peek.innerHTML = (ph ? '<img class="h-peek-photo" src="' + W.esc(ph) + '" alt="">' : '<img src="' + src + '" alt="">') + "<span>" + W.esc(hit.getAttribute("data-peek-name")) + "</span>";
    } else if (!hit && st.peekOn) { st.peekOn = null; E.peek.classList.remove("is-on"); }
  }
  function cursorAt(target) {
    var E = st.els; if (!E.cursor || !target || !target.closest) return;
    var c = target.closest("[data-cursor]"), l = c ? c.getAttribute("data-cursor") : "", link = target.closest("a,button,input");
    E.cursor.classList.toggle("has-label", !!c); E.cursor.classList.toggle("is-link", !!link && !c); E.cursor.firstChild.textContent = l;
  }
  function onMove(ev) {
    if (!st) return;
    st.tx = ev.clientX; st.ty = ev.clientY; st.hasPointer = ev.clientY < st.vh && fine();
    st.px = (ev.clientX / st.vw) * 2 - 1; st.py = (ev.clientY / st.vh) * 2 - 1;
    st.peekY = window.scrollY;
    peekAt(ev.target);
    if (st.els.cursor) { st.els.cursor.classList.add("is-ready"); cursorAt(ev.target); }
    var mg = ev.target.closest ? ev.target.closest(".h-magnetic") : null;
    if (st.mag && st.mag !== mg) { st.mag.style.transform = ""; st.mag = null; }
    if (mg) { var b = mg.getBoundingClientRect(); mg.style.transform = "translate(" + ((ev.clientX - b.left - b.width / 2) * .24).toFixed(1) + "px," + ((ev.clientY - b.top - b.height / 2) * .34).toFixed(1) + "px)"; st.mag = mg; }
  }
  function preloader(done) {
    var seen = false; try { seen = sessionStorage.getItem("wiw-intro") === "1"; sessionStorage.setItem("wiw-intro", "1"); } catch (e) { seen = false; }
    if (seen) { done(); return; }
    var p = document.createElement("div"); p.className = "h-intro"; p.setAttribute("aria-hidden", "true"); p.innerHTML = W.logoSvg("h-intro-logo");
    document.body.appendChild(p);
    setTimeout(done, 650);
    setTimeout(function () { p.classList.add("is-out"); }, 780);
    setTimeout(function () { p.remove(); }, 1500);
  }
  function mount(fresh) {
    root.classList.add("is-home");
    var q = function (s) { return document.querySelector(s); }, qa = function (s) { return [].slice.call(document.querySelectorAll(s)); }, live = !reduced() && !saveData();
    if (on) stopLoop();
    st = {
      live: live, fine: fine() && live, y: window.scrollY, lastY: window.scrollY, act: -1, stageReady: false, stageAsked: false, wOn: false, hasPointer: false, px: 0, py: 0, mx: 0, my: 0, tx: 0, ty: 0, cx: 0, cy: 0, ox: 0, oy: 0, peekOn: null, mag: null, layout: null, peekY: 0,
      els: { hero: q(".h-hero"), actsSec: q(".h-acts"), acts: qa(".h-act"), stage: q(".h-stage"), canvas: q(".h-canvas"), stories: q("#hStories"), track: q("#hTrack"), peek: q("#hPeek"), cursor: q("#hCursor"), lines: qa(".h-title > .h-line"), foot: q(".h-hero-foot") }
    };
    root.classList.toggle("home-live", live);
    root.classList.toggle("home-cursor", st.fine);
    if (!live) { root.classList.add("at-top"); W.home.reason = reduced() ? "reduced-motion" : "save-data"; window.addEventListener("scroll", staticHeader, { passive: true }); staticHeader(); return; }
    if (!lenis && st.fine && window.Lenis) { lenis = new window.Lenis({ lerp: .11, wheelMultiplier: 1, smoothWheel: true, prevent: function (n) { return !!(n.closest && n.closest(".dlg, #flows, #notes-panel, .drawer, .mega")); } }); }
    st.remeasure = function () { measure(); st.layout = (W.stage && st.stageReady) ? W.stage.layout() : null; if (W.stage && st.stageReady) W.stage.resize(); if (W.heroW && st.wOn) W.heroW.resize(); };
    st.remeasure();
    var startW = function () {
      if (!W.heroW || !q(".h-w-canvas")) return;
      W.heroW.init(q(".h-w-canvas"), function () { if (!st) return; st.wOn = true; root.classList.add("w-on"); });
    };
    var enter = function () { root.classList.add("home-in"); startW(); };
    if (fresh) { root.classList.remove("home-in"); void root.offsetWidth; preloader(enter); } else { root.classList.add("home-in"); startW(); }
    if ("IntersectionObserver" in window) {
      /* Clipped items are hidden by their own clip, which the observer counts as off screen, so their parent is watched instead. */
      var nodes = document.querySelectorAll(".h-rv, .h-rv-fade, .h-doors, .h-noms");
      st.io = new IntersectionObserver(function (es) { es.forEach(function (x) { if (x.isIntersecting) { x.target.classList.add("in"); [].forEach.call(x.target.querySelectorAll(".h-rv-clip"), function (c) { c.classList.add("in"); }); st.io.unobserve(x.target); } }); }, { rootMargin: "0px 0px -8% 0px", threshold: .05 });
      nodes.forEach(function (n) { st.io.observe(n); });
    } else document.querySelectorAll(".h-rv, .h-rv-fade, .h-rv-clip").forEach(function (n) { n.classList.add("in"); });
    on = true; raf = requestAnimationFrame(frame);
  }
  function staticHeader() { root.classList.toggle("at-top", window.scrollY < 60); }
  function stopLoop() { on = false; cancelAnimationFrame(raf); if (st && st.io) st.io.disconnect(); }
  function unmount() {
    stopLoop(); st = null;
    if (lenis) { lenis.destroy(); lenis = null; }
    window.removeEventListener("scroll", staticHeader);
    ["is-home", "home-live", "home-in", "home-cursor", "at-top", "head-hidden", "stage-on", "w-on"].forEach(function (c) { root.classList.remove(c); });
  }
  /* the hero's scroll cue: glide to the next section */
  W.act.hScroll = function () {
    var next = document.querySelector(".h-hero + section"); if (!next) return;
    var y = next.getBoundingClientRect().top + window.scrollY;
    if (lenis) lenis.scrollTo(y, { duration: 1.4 }); else window.scrollTo({ top: y, behavior: reduced() ? "auto" : "smooth" });
  };
  W.home = {
    sync: function (present, fresh) { if (present) mount(fresh); else unmount(); },
    state: function () { return st ? { live: st.live, act: st.act, stageReady: st.stageReady, wOn: st.wOn, on: on } : null; },
    pause: function (yes) { if (lenis) { if (yes) lenis.stop(); else lenis.start(); } }
  };
  document.addEventListener("pointermove", function (ev) { if (on) onMove(ev); }, { passive: true });
  var rt; window.addEventListener("resize", function () { clearTimeout(rt); rt = setTimeout(function () { if (on && st && st.remeasure) st.remeasure(); }, 120); }, { passive: true });
  window.addEventListener("load", function () { if (on && st && st.remeasure) st.remeasure(); });
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(function () { if (on && st && st.remeasure) st.remeasure(); });
})();
