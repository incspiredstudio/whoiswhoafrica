/* Front page script (M2-HOM-08). Everything here is added on top of a page that already reads in full. With reduced motion, data saver
   or a 2G connection it does almost nothing: the videos stay posters and no scroll effect runs. */
import { motionAllowed } from "./motion.js";
import "./home-nominees.js";

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

/* On the record: a preview card follows the pointer over the name index (desktop with a mouse only). */
function initPeek() {
  const peek = document.getElementById("home-peek");
  const list = document.getElementById("home-index");
  if (!peek || !list || !window.matchMedia("(width >= 64rem) and (pointer: fine)").matches) return;
  let on = null;
  const place = (e) => { peek.style.transform = `translate3d(${e.clientX + 56}px, ${e.clientY - 170}px, 0)`; };
  const show = (a) => {
    on = a;
    const photo = a.getAttribute("data-peek-photo");
    peek.className = "h-peek is-on h-peek--" + (a.getAttribute("data-peek-tone") || "orange") + (photo ? " h-peek--photo" : "");
    peek.textContent = "";
    if (photo) { const img = document.createElement("img"); img.src = photo; img.alt = ""; peek.appendChild(img); }
    const name = document.createElement("span");
    name.textContent = a.getAttribute("data-peek-name") || "";
    peek.appendChild(name);
  };
  list.addEventListener("pointermove", (e) => {
    const a = e.target instanceof Element ? e.target.closest("[data-peek-name]") : null;
    if (a) { if (a !== on) show(a); place(e); }
  });
  list.addEventListener("pointerleave", () => { on = null; peek.classList.remove("is-on"); });
}

initPeek();
