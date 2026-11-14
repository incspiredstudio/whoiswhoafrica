/* Nominees page group filter (REQ-198), as the prototype: "All" and one button per group, filtering the groups without a reload.
   The buttons are hidden until this runs and the jump links show instead, so without scripts every group is listed and reachable.
   The number of nominees shown is announced in the page's status line. */
export function initNomineeTabs() {
  const bar = document.querySelector("[data-np-filter]");
  if (!bar) return;
  const scope = bar.parentElement;
  const groups = Array.from(scope.querySelectorAll(".np-group[data-group]"));
  const jump = scope.querySelector("[data-np-jump]");
  const status = scope.querySelector("[data-np-status]");
  bar.hidden = false;
  if (jump) jump.hidden = true;
  bar.addEventListener("click", (event) => {
    const button = event.target instanceof Element ? event.target.closest("[data-np-group]") : null;
    if (!button) return;
    const key = button.getAttribute("data-np-group");
    bar.querySelectorAll("[data-np-group]").forEach((b) => b.setAttribute("aria-pressed", b === button ? "true" : "false"));
    let shown = 0;
    groups.forEach((group) => {
      const show = key === "all" || group.getAttribute("data-group") === key;
      group.hidden = !show;
      if (show) shown += group.querySelectorAll(".nom-card").length;
    });
    if (status) status.textContent = shown === 1 ? "1 nominee shown" : shown + " nominees shown";
  });
}
