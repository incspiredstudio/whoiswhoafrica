/* Header behaviour (REQ-043, REQ-040): mega menus (hover and click, Escape closes), the phone drawer and the search panel with a focus
   trap that returns focus on close, drawer sections that animate, and the scrolled state. Every link is a real link: without script
   the page is still usable through the footer and the page content. */
import { trapFocus } from "./focus-trap.js";

const root = document.documentElement;
const reduced = () => !!(window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches);

/* ---------- mega menus ---------- */
let closeTimer = 0;
let openedByHover = false;

function closeMega() {
  clearTimeout(closeTimer);
  document.querySelectorAll(".mega.open").forEach((m) => m.classList.remove("open"));
  document.querySelectorAll('.nav-link[aria-expanded="true"]').forEach((b) => b.setAttribute("aria-expanded", "false"));
}

function openMega(id, button) {
  clearTimeout(closeTimer);
  closeMega();
  const panel = document.getElementById(id);
  if (!panel) return;
  panel.classList.add("open");
  button.setAttribute("aria-expanded", "true");
}

function initMega() {
  document.querySelectorAll(".nav-link[aria-controls]").forEach((button) => {
    button.addEventListener("click", () => {
      const open = button.getAttribute("aria-expanded") === "true";
      if (open && openedByHover) {
        openedByHover = false; // the click that follows a hover keeps the menu open
        return;
      }
      if (open) closeMega();
      else openMega(button.getAttribute("aria-controls") || "", button);
    });
  });
  document.addEventListener("pointerover", (event) => {
    if (event.pointerType !== "mouse" || !(event.target instanceof Element)) return;
    const button = event.target.closest(".nav-link[aria-controls]");
    if (button && button.getAttribute("aria-expanded") !== "true") {
      openMega(button.getAttribute("aria-controls") || "", button);
      openedByHover = true;
      return;
    }
    if (event.target.closest(".mega, .nav-link[aria-controls]")) {
      clearTimeout(closeTimer);
      return;
    }
    if (document.querySelector(".mega.open")) {
      clearTimeout(closeTimer);
      closeTimer = window.setTimeout(closeMega, 180);
    }
  });
  document.addEventListener("click", (event) => {
    if (!(event.target instanceof Element)) return;
    if (event.target.closest(".mega a") || !event.target.closest(".mega, .nav-link[aria-controls]")) closeMega();
  });
  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") return;
    const open = document.querySelector('.nav-link[aria-expanded="true"]');
    if (open instanceof HTMLElement) {
      closeMega();
      open.focus();
    }
  });
}

/* ---------- drawer ---------- */
let releaseDrawer = null;

function setDrawer(show) {
  const drawer = document.getElementById("drawer");
  const button = document.querySelector(".menu-btn");
  if (!drawer) return;
  drawer.classList.toggle("open", show);
  if (button) button.setAttribute("aria-expanded", show ? "true" : "false");
  document.body.style.overflow = show ? "hidden" : "";
  if (show) {
    closeSearch(true);
    releaseDrawer = trapFocus(drawer, { onEscape: () => setDrawer(false) });
  } else if (releaseDrawer) {
    releaseDrawer();
    releaseDrawer = null;
  }
}

function initDrawer() {
  const button = document.querySelector(".menu-btn");
  const drawer = document.getElementById("drawer");
  if (!button || !drawer) return;
  button.addEventListener("click", () => setDrawer(true));
  const close = drawer.querySelector(".drawer-close");
  if (close) close.addEventListener("click", () => setDrawer(false));
  drawer.querySelectorAll("a").forEach((a) => a.addEventListener("click", () => setDrawer(false)));
  // Leaving the phone layout closes the drawer.
  window.matchMedia("(min-width: 64rem)").addEventListener("change", (e) => {
    if (e.matches && drawer.classList.contains("open")) setDrawer(false);
  });
  animateSections(drawer);
}

/* Sections open and close with a height and fade animation instead of snapping. */
function animateSections(drawer) {
  drawer.querySelectorAll("summary").forEach((summary) => {
    summary.addEventListener("click", (event) => {
      const details = summary.parentElement;
      const list = details ? details.querySelector("ul") : null;
      if (!details || !list || reduced() || !list.animate) return;
      event.preventDefault();
      if (details._anim) {
        details._anim.cancel();
        details._anim = null;
      }
      const opts = { duration: 300, easing: "cubic-bezier(.2,.7,.2,1)" };
      const pad = () => parseFloat(getComputedStyle(list).paddingBottom) || 0;
      list.style.overflow = "hidden";
      list.style.boxSizing = "border-box";
      const done = (closing) => () => {
        details._anim = null;
        if (closing) details.open = false;
        list.style.overflow = "";
        list.style.boxSizing = "";
      };
      if (!details.open) {
        details.open = true;
        const h = list.offsetHeight;
        const p = pad();
        details._anim = list.animate(
          [
            { height: "0px", paddingBottom: "0px", opacity: 0 },
            { height: h + "px", paddingBottom: p + "px", opacity: 1 },
          ],
          opts
        );
        details._anim.onfinish = done(false);
      } else {
        const h = list.offsetHeight;
        const p = pad();
        details._anim = list.animate(
          [
            { height: h + "px", paddingBottom: p + "px", opacity: 1 },
            { height: "0px", paddingBottom: "0px", opacity: 0 },
          ],
          opts
        );
        details._anim.onfinish = done(true);
      }
    });
  });
}

/* ---------- search panel ---------- */
let releaseSearch = null;
let hideTimer = 0;

function openSearch() {
  const panel = document.getElementById("searchOv");
  const button = document.querySelector(".search-link");
  if (!panel) return;
  setDrawer(false);
  clearTimeout(hideTimer);
  panel.hidden = false;
  void panel.offsetWidth;
  panel.classList.add("open");
  if (button) button.setAttribute("aria-expanded", "true");
  document.body.style.overflow = "hidden";
  releaseSearch = trapFocus(panel, { onEscape: () => closeSearch(false) });
  const input = document.getElementById("sovq");
  if (input) input.focus();
}

function closeSearch(quiet) {
  const panel = document.getElementById("searchOv");
  if (!panel || !panel.classList.contains("open")) return;
  const button = document.querySelector(".search-link");
  panel.classList.remove("open");
  hideTimer = window.setTimeout(
    () => {
      if (!panel.classList.contains("open")) panel.hidden = true;
    },
    reduced() ? 0 : 320
  );
  if (button) button.setAttribute("aria-expanded", "false");
  document.body.style.overflow = "";
  if (releaseSearch) {
    releaseSearch();
    releaseSearch = null;
  }
  if (quiet === true && button instanceof HTMLElement) button.blur();
}

function initSearch() {
  const button = document.querySelector(".search-link");
  const panel = document.getElementById("searchOv");
  if (!button || !panel) return;
  button.addEventListener("click", openSearch);
  const close = panel.querySelector(".sov-close");
  if (close) close.addEventListener("click", () => closeSearch(false));
}

/* ---------- scrolled state ---------- */
function initScrolled() {
  const update = () => root.classList.toggle("is-scrolled", window.scrollY > 8);
  update();
  window.addEventListener("scroll", update, { passive: true });
  // The header-state script held transitions back until now (hdr-init); release them after the page has loaded.
  const release = () => root.classList.remove("hdr-init");
  if (document.readyState === "complete") release();
  else window.addEventListener("load", release, { once: true });
  window.setTimeout(release, 6000);
  const at = () => root.classList.toggle("at-top", window.scrollY < 60);
  if (root.classList.contains("is-home")) {
    at();
    window.addEventListener("scroll", at, { passive: true });
  }
}

export function initHeader() {
  initMega();
  initDrawer();
  initSearch();
  initScrolled();
}
