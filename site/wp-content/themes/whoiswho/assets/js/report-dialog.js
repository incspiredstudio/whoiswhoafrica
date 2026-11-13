/* Report dialog: the Report link goes to the report page; with scripts it opens the same form in a native modal dialog.
   The shared focus trap keeps Tab inside, Escape closes it and focus returns to the link. A click on the backdrop also closes it. */
import { trapFocus } from "./focus-trap.js";

export function initReportDialog() {
  document.querySelectorAll("[data-report-open]").forEach((link) => {
    const dialog = document.getElementById(link.getAttribute("data-report-open") || "");
    if (!(dialog instanceof HTMLDialogElement) || typeof dialog.showModal !== "function") return;
    let release = null;
    link.addEventListener("click", (event) => {
      event.preventDefault();
      dialog.showModal();
      release = trapFocus(dialog, { onEscape: () => dialog.close() });
    });
    dialog.querySelectorAll("[data-report-close]").forEach((button) => button.addEventListener("click", () => dialog.close()));
    dialog.addEventListener("click", (event) => {
      if (event.target === dialog) dialog.close();
    });
    // Also fires when the browser closes the dialog itself (Escape handled natively).
    dialog.addEventListener("close", () => {
      if (release) release();
      release = null;
    });
  });
}
