/* Motion base (REQ-043, REQ-041, REQ-044): scroll reveals and smooth wheel scrolling, added on top of a finished page.
   Nothing here is needed to read or use a page. Every switch below leaves the page complete:
   - no script: the page never gets the js-motion class, so [data-reveal] content is simply visible;
   - prefers-reduced-motion, data saver or a 2G connection: no reveals, no smooth scroll;
   - no IntersectionObserver: everything is shown at once;
   - touch or no fine pointer: scrolling stays native (also keyboard scrolling, always). */

const root = document.documentElement;

export function prefersReducedMotion() {
  return !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);
}

/** Data saver on, or a 2G-class connection. */
export function saveData() {
  const c = navigator.connection;
  return !!(c && (c.saveData || /(^|-)2g$/.test(String(c.effectiveType || ""))));
}

export function motionAllowed() {
  return !prefersReducedMotion() && !saveData();
}

function finePointer() {
  return !!(window.matchMedia && window.matchMedia("(pointer: fine)").matches);
}

let observer = null;

function showAll() {
  root.classList.remove("js-motion");
  document.querySelectorAll("[data-reveal]").forEach((n) => n.classList.add("in"));
}

/** Reveal-on-scroll. Safe to call again after content changes. */
export function initReveals() {
  if (observer) observer.disconnect();
  if (!motionAllowed() || !("IntersectionObserver" in window)) {
    showAll();
    return;
  }
  root.classList.add("js-motion");
  observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("in");
          observer.unobserve(entry.target);
        }
      });
    },
    { rootMargin: "0px 0px -6% 0px", threshold: 0.04 }
  );
  document.querySelectorAll("[data-reveal]").forEach((n) => observer.observe(n));
  // Safety net: nothing stays hidden if the observer never fires.
  setTimeout(() => {
    document.querySelectorAll("[data-reveal]:not(.in)").forEach((n) => {
      if (n.getBoundingClientRect().top < window.innerHeight) n.classList.add("in");
    });
  }, 1200);
}

// Areas that must scroll natively: dialogs, the drawer, the menus, open filters, form fields, anything marked.
const NATIVE_SCROLL = ".dlg, .drawer, .mega, .sov, details.filters, textarea, select, [data-lenis-prevent]";

let smooth = null;
let frame = 0;

function tick(time) {
  if (!smooth) return;
  smooth.raf(time);
  frame = requestAnimationFrame(tick);
}

function stopSmooth() {
  if (!smooth) return;
  cancelAnimationFrame(frame);
  smooth.destroy();
  smooth = null;
}

function startSmooth() {
  if (smooth || !window.Lenis) return;
  smooth = new window.Lenis({
    lerp: 0.1,
    wheelMultiplier: 1,
    smoothWheel: true,
    prevent: (node) => !!(node.closest && node.closest(NATIVE_SCROLL)),
  });
  frame = requestAnimationFrame(tick);
}

/** Glide to a scroll position: through the smooth-scroll library when it runs, otherwise native (instant with reduced motion). */
export function scrollToY(y, duration = 1.4) {
  if (smooth) smooth.scrollTo(y, { duration });
  else window.scrollTo({ top: y, behavior: prefersReducedMotion() ? "auto" : "smooth" });
}

/** Smooth wheel scrolling: only with a fine pointer and motion allowed. The library is loaded only then. */
export function initSmoothScroll() {
  const url = window.wiwMotion && window.wiwMotion.lenis;
  if (!motionAllowed() || !finePointer() || !url) return;
  if (window.Lenis) {
    startSmooth();
    return;
  }
  const s = document.createElement("script");
  s.src = url;
  s.async = true;
  s.onload = startSmooth;
  document.head.appendChild(s);
}

/** Switches change while the page is open (the visitor turns reduced motion on): follow them. */
function follow() {
  if (!window.matchMedia) return;
  const mq = window.matchMedia("(prefers-reduced-motion: reduce)");
  const change = () => {
    if (mq.matches) {
      stopSmooth();
      showAll();
    }
  };
  if (mq.addEventListener) mq.addEventListener("change", change);
}

export function initMotion() {
  initReveals();
  initSmoothScroll();
  follow();
}
