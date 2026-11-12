/* Front page script (M2-HOM-08). Everything here is added on top of a page that already reads in full. With reduced motion, data saver
   or a 2G connection it does almost nothing: the videos stay posters and no scroll effect runs. */
import { motionAllowed } from "./motion.js";

const root = document.documentElement;
const clamp = (v, a, b) => Math.max(a, Math.min(b, v));

function startVideos(hero) {
  const videos = hero.querySelectorAll("video[data-src]");
  const play = () => {
    videos.forEach((v) => {
      if (!v.getAttribute("src")) v.setAttribute("src", v.dataset.src || "");
      v.muted = true;
      const p = v.play();
      if (p && p.catch) p.catch(() => {});
    });
  };
  // Wait until the page has painted so the videos never delay the first view.
  if (document.readyState === "complete") window.setTimeout(play, 0);
  else window.addEventListener("load", () => window.setTimeout(play, 0), { once: true });
  // Pause while the hero is off screen or the tab is hidden.
  if ("IntersectionObserver" in window) {
    new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) videos.forEach((v) => v.pause());
        else videos.forEach((v) => { if (v.getAttribute("src")) { const p = v.play(); if (p && p.catch) p.catch(() => {}); } });
      });
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
  window.requestAnimationFrame(() => window.requestAnimationFrame(() => root.classList.add("home-in")));
  startVideos(hero);
  scrollExit(hero);
}

init();
