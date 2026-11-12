/* Cookie notice (REQ-248, REQ-062, REQ-058). The choice is one first-party cookie, "wiw_consent": "all" or "essential".
   Before Accept only essential cookies run; the analytics script (if one is configured) is added to the page only after Accept.
   The card shows after a little scroll, or at once on a page too short to scroll; the footer button reopens it. */
import { announce } from "./live-region.js";

const NAME = "wiw_consent";
const DAYS = 180;

function readChoice() {
  const match = document.cookie.split("; ").find((row) => row.startsWith(NAME + "="));
  const value = match ? decodeURIComponent(match.slice(NAME.length + 1)) : "";
  return value === "all" || value === "essential" ? value : "";
}

function writeChoice(value) {
  const secure = window.location.protocol === "https:" ? "; Secure" : "";
  document.cookie = `${NAME}=${encodeURIComponent(value)}; Max-Age=${DAYS * 86400}; Path=/; SameSite=Lax${secure}`;
}

let analyticsLoaded = false;

/** Add the analytics script, once, and only when the visitor accepted. */
function applyChoice(choice) {
  const url = window.wiwConsent && window.wiwConsent.analytics;
  if (choice !== "all" || !url || analyticsLoaded) return;
  analyticsLoaded = true;
  const script = document.createElement("script");
  script.src = url;
  script.async = true;
  script.setAttribute("data-consent", "analytics");
  document.head.appendChild(script);
}

export function initCookieNotice() {
  const card = document.querySelector(".cookie[data-cookie]");
  if (!card) return;
  const choice = readChoice();
  applyChoice(choice);

  let opener = null;
  const show = (focus) => {
    card.hidden = false;
    // Reveal on the next frame so the entry transition runs; focus once it is visible.
    window.requestAnimationFrame(() => {
      card.classList.add("is-in");
      const first = card.querySelector("button");
      if (focus && first instanceof HTMLElement) first.focus();
    });
  };
  const hide = () => {
    card.classList.remove("is-in");
    card.hidden = true;
  };

  const scrolled = () => {
    const short = document.documentElement.scrollHeight - window.innerHeight < 360;
    if (window.scrollY > 320 || short) {
      window.removeEventListener("scroll", scrolled);
      if (!readChoice()) show(false);
    }
  };

  if (!choice) {
    window.addEventListener("scroll", scrolled, { passive: true });
    scrolled();
  }

  card.querySelectorAll("[data-consent]").forEach((button) => {
    button.addEventListener("click", () => {
      const value = button.getAttribute("data-consent") === "all" ? "all" : "essential";
      writeChoice(value);
      applyChoice(value);
      hide();
      announce(value === "all" ? "Analytics cookies accepted." : "Only essential cookies will be used.");
      if (opener && typeof opener.focus === "function") opener.focus();
      opener = null;
    });
  });

  // The footer button and the Cookie policy page button reopen the card.
  document.querySelectorAll("[data-cookie-reopen], [data-cookie-prefs]").forEach((button) => {
    button.addEventListener("click", () => {
      opener = button;
      show(true);
    });
  });
}
