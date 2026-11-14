/* Compact image credit (REQ-203, REQ-043): the "i" button over a profile photo opens and closes the credit with aria-expanded.
   The button stays hidden without scripts; the credit is then still on the page in the "Image credits" line at the foot.
   Escape closes it and returns focus to the button; a click outside closes it. */
export function initCreditToggles(root = document) {
  root.querySelectorAll("[data-credit-toggle]").forEach((button) => {
    const panel = document.getElementById(button.getAttribute("aria-controls") || "");
    if (!panel) return;
    const set = (open) => {
      button.setAttribute("aria-expanded", open ? "true" : "false");
      panel.hidden = !open;
    };
    button.hidden = false;
    button.addEventListener("click", () => set(button.getAttribute("aria-expanded") !== "true"));
    const wrap = button.parentElement || panel;
    wrap.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && button.getAttribute("aria-expanded") === "true") {
        set(false);
        button.focus();
      }
    });
    document.addEventListener("click", (event) => {
      if (button.getAttribute("aria-expanded") === "true" && !wrap.contains(event.target)) set(false);
    });
  });
}
