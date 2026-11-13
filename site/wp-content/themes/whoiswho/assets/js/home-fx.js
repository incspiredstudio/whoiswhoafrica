/* Front page choreography on top of the finished page (issue 744, REQ-043, REQ-041). Everything here runs only after home.js has
   confirmed that motion is allowed (no reduced-motion, no data saver), and every effect only moves transform or opacity. Without
   script, or with any of those switches, the page is the plain static page and nothing is hidden.
   Ported from prototype/theme/home.js: intro wipe, header that hides while reading, pinned sideways stories, labelled cursor,
   magnetic buttons, line and clip reveals, rail autoplay and the scroll cue glide. */
const root = document.documentElement;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
/* The smooth-scroll instance lives in main.js (a separate bundle), which shares it as window.wiw.scrollToY. */
const scrollToY = (y, d) => {
  if (window.wiw && window.wiw.scrollToY) window.wiw.scrollToY(y, d);
  else window.scrollTo({ top: y, behavior: "smooth" });
};
const mq = (q) => !!(window.matchMedia && window.matchMedia(q).matches);

/* ---------- intro: the logo covers the page for a moment, once per visit, then wipes up ---------- */
export function intro(done) {
  let seen;
  try {
    seen = window.sessionStorage.getItem("wiw-intro") === "1";
    window.sessionStorage.setItem("wiw-intro", "1");
  } catch {
    seen = false;
  }
  if (seen) {
    done();
    return;
  }
  const panel = document.createElement("div");
  panel.className = "h-intro";
  panel.setAttribute("aria-hidden", "true");
  panel.innerHTML = '<span class="wiw-logo h-intro-logo"></span>';
  document.body.appendChild(panel);
  window.setTimeout(done, 650);
  window.setTimeout(() => panel.classList.add("is-out"), 780);
  window.setTimeout(() => panel.remove(), 1500);
}

/* ---------- header: slides away while reading down, comes back on any upward scroll ---------- */
function headerHide() {
  let last = window.scrollY;
  let queued = false;
  const update = () => {
    queued = false;
    const y = window.scrollY;
    if (Math.abs(y - last) <= 4) return;
    const busy = document.querySelector(".mega.open, #drawer.open, #searchOv.open");
    root.classList.toggle("head-hidden", y > last && y > window.innerHeight * 0.8 && !busy);
    last = y;
  };
  window.addEventListener(
    "scroll",
    () => {
      if (!queued) {
        queued = true;
        window.requestAnimationFrame(update);
      }
    },
    { passive: true }
  );
}

/* ---------- stories: on wide screens the section pins and the vertical scroll moves the track sideways ---------- */
function pinStories() {
  const sec = document.querySelector(".h-stories");
  const track = sec ? sec.querySelector(".h-track") : null;
  if (!sec || !track || !("IntersectionObserver" in window)) return;
  const wide = window.matchMedia("(width >= 64rem)");
  let on = false;
  let dist = 0;
  let top = 0;
  let near = false;
  let queued = false;
  const progress = () => clamp((window.scrollY - top) / Math.max(1, dist), 0, 1);
  const paint = () => {
    queued = false;
    if (on) track.style.transform = "translate3d(" + (-progress() * dist).toFixed(1) + "px,0,0)";
  };
  const measure = () => {
    on = wide.matches;
    sec.classList.toggle("is-pinned", on);
    if (!on) {
      sec.style.height = "";
      track.style.transform = "";
      return;
    }
    sec.style.height = "";
    dist = Math.max(0, track.scrollWidth - window.innerWidth + 80);
    sec.style.height = dist + window.innerHeight + "px";
    top = sec.getBoundingClientRect().top + window.scrollY;
    paint();
  };
  new IntersectionObserver((entries) => entries.forEach((e) => (near = e.isIntersecting)), { rootMargin: "20% 0px" }).observe(sec);
  window.addEventListener(
    "scroll",
    () => {
      if (on && near && !queued) {
        queued = true;
        window.requestAnimationFrame(paint);
      }
    },
    { passive: true }
  );
  let timer = 0;
  window.addEventListener("resize", () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(measure, 120);
  });
  window.addEventListener("load", measure);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(measure);
  // Keyboard: focusing a story that is off to the side scrolls the page to the place where it comes into view.
  track.addEventListener("focusin", (event) => {
    const card = event.target instanceof Element ? event.target.closest(".h-story") : null;
    if (!on || !card) return;
    const left = card.getBoundingClientRect().left + progress() * dist;
    scrollToY(top + clamp((left - 80) / Math.max(1, dist), 0, 1) * dist, 0.6);
  });
  measure();
}

/* ---------- cursor, preview card and magnetic buttons (mouse on wide screens only) ---------- */
function pointerFx() {
  if (!mq("(pointer: fine)") || !mq("(width >= 64rem)")) return;
  const cursor = document.createElement("div");
  cursor.className = "h-cursor";
  cursor.setAttribute("aria-hidden", "true");
  cursor.appendChild(document.createElement("span"));
  document.body.appendChild(cursor);
  root.classList.add("home-cursor");
  const label = cursor.firstChild;
  const peek = document.getElementById("home-peek");
  const list = document.getElementById("home-index");
  document.querySelectorAll(".home .h-btn").forEach((b) => b.classList.add("h-magnetic"));

  const s = { tx: 0, ty: 0, cx: 0, cy: 0, ox: 0, oy: 0, on: false, raf: 0, peekOn: null, mag: null, recheck: false };
  const sync = (target) => {
    const tag = target instanceof Element ? target.closest("[data-cursor]") : null;
    const link = target instanceof Element ? target.closest("a,button,input,summary") : null;
    cursor.classList.toggle("has-label", !!tag);
    cursor.classList.toggle("is-link", !!link && !tag);
    label.textContent = tag ? tag.getAttribute("data-cursor") || "" : "";
    if (!peek || !list) return;
    const hit = target instanceof Element && list.contains(target) ? target.closest("[data-peek-name]") : null;
    if (hit && hit !== s.peekOn) {
      s.peekOn = hit;
      const photo = hit.getAttribute("data-peek-photo");
      peek.className = "h-peek is-on h-peek--" + (hit.getAttribute("data-peek-tone") || "orange") + (photo ? " h-peek--photo" : "");
      peek.textContent = "";
      if (photo) {
        const img = document.createElement("img");
        img.src = photo;
        img.alt = "";
        peek.appendChild(img);
      }
      const name = document.createElement("span");
      name.textContent = hit.getAttribute("data-peek-name") || "";
      peek.appendChild(name);
    } else if (!hit && s.peekOn) {
      s.peekOn = null;
      peek.classList.remove("is-on");
    }
  };
  const tick = () => {
    s.raf = 0;
    if (s.recheck) {
      s.recheck = false;
      sync(document.elementFromPoint(s.tx, s.ty));
    }
    s.cx += (s.tx - s.cx) * 0.14;
    s.cy += (s.ty - s.cy) * 0.14;
    s.ox += (s.tx - s.ox) * 0.32;
    s.oy += (s.ty - s.oy) * 0.32;
    if (peek) peek.style.transform = "translate3d(" + (s.cx + 56).toFixed(1) + "px," + (s.cy - 170).toFixed(1) + "px,0) rotate(" + clamp((s.tx - s.cx) * 0.03, -6, 6).toFixed(2) + "deg)";
    cursor.style.transform = "translate3d(" + s.ox.toFixed(1) + "px," + s.oy.toFixed(1) + "px,0)";
    // Keep going until the cursor and the card have caught up with the pointer.
    if (Math.abs(s.tx - s.cx) > 0.2 || Math.abs(s.ty - s.cy) > 0.2 || Math.abs(s.tx - s.ox) > 0.2 || Math.abs(s.ty - s.oy) > 0.2) s.raf = window.requestAnimationFrame(tick);
  };
  const wake = () => {
    if (!s.raf) s.raf = window.requestAnimationFrame(tick);
  };
  document.addEventListener(
    "pointermove",
    (ev) => {
      if (ev.pointerType !== "mouse") return;
      if (!s.on) {
        s.on = true;
        s.cx = s.ox = ev.clientX;
        s.cy = s.oy = ev.clientY;
        cursor.classList.add("is-ready");
      }
      s.tx = ev.clientX;
      s.ty = ev.clientY;
      sync(ev.target);
      const mag = ev.target instanceof Element ? ev.target.closest(".h-magnetic") : null;
      if (s.mag && s.mag !== mag) {
        s.mag.style.transform = "";
        s.mag = null;
      }
      if (mag) {
        const b = mag.getBoundingClientRect();
        mag.style.transform = "translate(" + ((ev.clientX - b.left - b.width / 2) * 0.24).toFixed(1) + "px," + ((ev.clientY - b.top - b.height / 2) * 0.34).toFixed(1) + "px)";
        s.mag = mag;
      }
      wake();
    },
    { passive: true }
  );
  // The page moves under a still pointer: look again at what is under it.
  window.addEventListener(
    "scroll",
    () => {
      if (s.on) {
        s.recheck = true;
        wake();
      }
    },
    { passive: true }
  );
  document.addEventListener("pointerleave", () => cursor.classList.remove("is-ready"));
  document.addEventListener("pointerenter", () => s.on && cursor.classList.add("is-ready"));
  if (list && peek) list.addEventListener("pointerleave", () => sync(null));
}

/* ---------- reveals: mask-line headings and the doors' clip, once, as they enter ---------- */
function reveals() {
  const nodes = document.querySelectorAll(".h-rv, .h-doors, .h-noms");
  const show = (n) => {
    n.classList.add("in");
    n.querySelectorAll(".h-rv-clip").forEach((c) => c.classList.add("in"));
  };
  if (!("IntersectionObserver" in window)) {
    nodes.forEach(show);
    return;
  }
  const io = new IntersectionObserver(
    (entries) =>
      entries.forEach((e) => {
        if (e.isIntersecting) {
          show(e.target);
          io.unobserve(e.target);
        }
      }),
    { rootMargin: "0px 0px -8% 0px", threshold: 0.05 }
  );
  nodes.forEach((n) => io.observe(n));
  // Safety net: nothing stays hidden if the observer never fires.
  window.setTimeout(() => nodes.forEach((n) => n.getBoundingClientRect().top < window.innerHeight && show(n)), 1500);
}

/* ---------- rail (phones and tablets): moves by itself, stops when touched, pauses off screen ---------- */
function rail() {
  const r = document.querySelector(".h-rail");
  if (!r) return;
  let hold = 0;
  ["pointerdown", "touchstart", "wheel", "focusin"].forEach((t) => r.addEventListener(t, () => (hold = Date.now() + 7000), { passive: true }));
  window.setInterval(() => {
    if (document.hidden || Date.now() < hold || !r.offsetParent) return;
    const b = r.getBoundingClientRect();
    if (b.bottom < 0 || b.top > window.innerHeight) return;
    const item = r.querySelector(".h-rail-item");
    const w = item ? item.getBoundingClientRect().width + 12 : 240;
    if (r.scrollLeft + r.clientWidth >= r.scrollWidth - 4) r.scrollTo({ left: 0, behavior: "smooth" });
    else r.scrollBy({ left: w, behavior: "smooth" });
  }, 3200);
}

/* ---------- scroll cue: glide to the next section, and move focus there for keyboard users ---------- */
function cue() {
  const link = document.querySelector(".h-cue");
  const target = document.getElementById("home-after-hero");
  if (!link || !target) return;
  link.addEventListener("click", (event) => {
    event.preventDefault();
    scrollToY(target.getBoundingClientRect().top + window.scrollY, 1.4);
    target.focus({ preventScroll: true });
  });
}

/* ---------- particle stage: Three.js and the stage script are fetched once the visitor starts scrolling, and only on capable devices ---------- */
function stage() {
  const urls = window.wiwHome;
  const hero = document.querySelector(".h-hero");
  const acts = document.querySelector(".h-acts");
  const home = document.querySelector(".home");
  if (!urls || !urls.three || !urls.stage || !hero || !acts || !home) return;
  if ((navigator.deviceMemory && navigator.deviceMemory < 4) || (navigator.hardwareConcurrency && navigator.hardwareConcurrency < 4)) return;
  try {
    if (!document.createElement("canvas").getContext("webgl")) return;
  } catch {
    return;
  }
  const load = (src) =>
    new Promise((resolve, reject) => {
      const el = document.createElement("script");
      el.src = src;
      el.async = true;
      el.onload = resolve;
      el.onerror = reject;
      document.head.appendChild(el);
    });
  let asked = false;
  const onScroll = () => {
    if (asked || window.scrollY < hero.offsetHeight * 0.2) return;
    asked = true;
    window.removeEventListener("scroll", onScroll);
    const go = () =>
      load(urls.three)
        .then(() => load(urls.stage))
        .then(() => {
          const host = document.createElement("div");
          host.className = "h-stage";
          host.setAttribute("aria-hidden", "true");
          home.insertBefore(host, home.firstChild);
          if (!window.wiwStage || !window.wiwStage.start(host, acts)) host.remove();
        })
        .catch(() => {});
    if (window.requestIdleCallback) window.requestIdleCallback(go, { timeout: 900 });
    else window.setTimeout(go, 300);
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  onScroll();
}

export function initHomeFx() {
  headerHide();
  pinStories();
  pointerFx();
  reveals();
  rail();
  cue();
  stage();
}
