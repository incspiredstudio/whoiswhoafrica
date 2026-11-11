/* Focus trap for the drawer and dialogs: Tab and Shift+Tab stay inside, Escape closes, focus returns to the opener. */
const FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

function focusables(root) {
  return Array.from(root.querySelectorAll(FOCUSABLE)).filter((el) => el.offsetParent !== null || el === document.activeElement);
}

/**
 * Trap focus inside root. Returns release(), which removes the trap and returns focus to the opener.
 * @param {HTMLElement} root
 * @param {{onEscape?: () => void}} [options]
 */
export function trapFocus(root, options = {}) {
  const opener = document.activeElement;
  const onKey = (event) => {
    if (event.key === "Escape" && options.onEscape) {
      event.preventDefault();
      options.onEscape();
      return;
    }
    if (event.key !== "Tab") return;
    const items = focusables(root);
    if (!items.length) {
      event.preventDefault();
      return;
    }
    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  };
  root.addEventListener("keydown", onKey);
  const items = focusables(root);
  if (items.length) items[0].focus();
  return function release() {
    root.removeEventListener("keydown", onKey);
    if (opener && typeof opener.focus === "function") opener.focus();
  };
}
