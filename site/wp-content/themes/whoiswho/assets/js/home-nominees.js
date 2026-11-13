/* Home nominees grid: the group filters (REQ-198). The buttons are hidden in the markup and shown here, so a visitor without script
   sees the whole grid and no dead buttons. A filter hides cards by their group; it changes nothing on the server. The count of what
   is shown is announced politely. */
const scope = document.querySelector(".h-nominees");
const bar = scope ? scope.querySelector(".h-nominees-filter") : null;
if (scope && bar) {
  bar.hidden = false;
  const cards = Array.from(scope.querySelectorAll(".nom-card"));
  const buttons = Array.from(bar.querySelectorAll("button[data-group]"));
  buttons.forEach((button) => {
    button.addEventListener("click", () => {
      const key = button.dataset.group;
      buttons.forEach((b) => b.setAttribute("aria-pressed", b === button ? "true" : "false"));
      let shown = 0;
      cards.forEach((card) => {
        const match = key === "all" || card.dataset.group === key;
        card.hidden = !match;
        if (match) shown += 1;
      });
      if (window.wiw && window.wiw.announce) window.wiw.announce(shown + (shown === 1 ? " nominee shown" : " nominees shown"));
    });
  });
}
