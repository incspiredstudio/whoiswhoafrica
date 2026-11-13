/* Front page script (M2-HOM-08). Everything here is added on top of a page that already reads in full. With reduced motion, data saver
   or a 2G connection it does almost nothing: the videos stay posters and no scroll effect runs. */
import { motionAllowed } from "./motion.js";
import { initHomeFx, intro } from "./home-fx.js";

const root = document.documentElement;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

function startVideos(hero) {
  const videos = Array.from(hero.querySelectorAll("video")).sort((x, y) => Number(x.dataset.turn || 0) - Number(y.dataset.turn || 0));
  if (!videos.length) return;
  const started = new Set();
  const playing = () => (document.hidden ? false : heroVisible);
  let heroVisible = true;
  const play = (v) => {
    v.muted = true;
    const p = v.play();
    if (p && p.catch) p.catch(() => {});
  };
  // One clip at a time: the next one is fetched and started only once the one before it is running (or has failed), so the first
  // clip gets the whole connection. Until a clip's turn its <video> has preload="none" and fetches nothing.
  const start = (i) => {
    const v = videos[i];
    if (!v) return;
    started.add(v);
    const next = () => { v.removeEventListener("playing", next); v.removeEventListener("error", next); window.setTimeout(() => start(i + 1), 150); };
    v.addEventListener("playing", next);
    v.addEventListener("error", next, true);
    v.preload = "auto";
    if (playing()) play(v);
    else v.load();
  };
  // Wait until the page has painted so the videos never delay the first view (the first poster is the largest contentful paint).
  const go = () => window.setTimeout(() => start(0), 0);
  if (document.readyState === "complete") go();
  else window.addEventListener("load", go, { once: true });
  // Pause while the hero is off screen or the tab is hidden; resume the clips that have had their turn.
  const sync = () => videos.forEach((v) => { if (!started.has(v)) return; if (playing()) play(v); else v.pause(); });
  document.addEventListener("visibilitychange", sync);
  if ("IntersectionObserver" in window) {
    new IntersectionObserver((entries) => {
      entries.forEach((e) => { heroVisible = e.isIntersecting; });
      sync();
    }).observe(hero);
  }
}

function scrollExit(hero) {
  let ticking = false;
  const update = () => {
    ticking = false;
    const h = Math.max(1, hero.offsetHeight);
    hero.style.setProperty("--hx", clamp(window.scrollY / h, 0, 1).toFixed(3));
  };
  window.addEventListener("scroll", () => {
    if (!ticking) { ticking = true; window.requestAnimationFrame(update); }
  }, { passive: true });
  update();
}

function init() {
  const hero = document.querySelector(".h-hero");
  if (!hero) return;
  if (!motionAllowed()) return;
  root.classList.add("home-live");
  intro(() => window.requestAnimationFrame(() => window.requestAnimationFrame(() => root.classList.add("home-in"))));
  startVideos(hero);
  scrollExit(hero);
  initHomeFx();
}

init();

/* Nominees block: the group filter. The chips are hidden until this runs, so without scripts everyone is shown. */
function initNomineeFilter() {
  document.querySelectorAll("[data-nom-scope]").forEach((scope) => {
    const bar = scope.querySelector(".h-nom-filter");
    if (!bar) return;
    bar.hidden = false;
    const items = scope.querySelectorAll(".hn-item");
    bar.addEventListener("click", (event) => {
      const chip = event.target instanceof Element ? event.target.closest("[data-nom-filter]") : null;
      if (!chip) return;
      const group = chip.getAttribute("data-nom-filter");
      bar.querySelectorAll("[data-nom-filter]").forEach((c) => c.setAttribute("aria-pressed", c === chip ? "true" : "false"));
      items.forEach((item) => { item.hidden = group !== "all" && item.getAttribute("data-group") !== group; });
    });
  });
}
initNomineeFilter();
