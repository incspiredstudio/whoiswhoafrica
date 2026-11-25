/* Pager "Go to page" box (REQ-007): page addresses are paths (/people/page/12/), so a plain form cannot build them. The
   server writes the address pattern with {page}; this shows the box and goes to the page typed, kept between 1 and the last. */
export function initPagerJump(root = document) {
  root.querySelectorAll("form[data-pager-jump]").forEach((form) => {
    const pattern = form.getAttribute("data-pager-jump") || "";
    const pages = parseInt(form.getAttribute("data-pages") || "1", 10);
    const input = form.querySelector("input");
    if (!pattern.includes("{page}") || !input) return;
    form.hidden = false;
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const n = Math.min(pages, Math.max(1, parseInt(input.value, 10) || 1));
      window.location.assign(n === 1 ? pattern.replace(/page\/\{page\}\/?/, "") : pattern.replace("{page}", String(n)));
    });
  });
}
