/* Live region helper: announce confirmations (polite) and errors (assertive) to assistive technology. */
function region(kind) {
  const id = "wiw-live-" + kind;
  let el = document.getElementById(id);
  if (!el) {
    el = document.createElement("div");
    el.id = id;
    el.className = "live-region";
    el.setAttribute("role", kind === "error" ? "alert" : "status");
    el.setAttribute("aria-live", kind === "error" ? "assertive" : "polite");
    document.body.appendChild(el);
  }
  return el;
}

/**
 * @param {string} message
 * @param {"status"|"error"} [kind]
 */
export function announce(message, kind = "status") {
  const el = region(kind);
  el.textContent = "";
  // A change in content is what screen readers announce; clear first so repeats are read.
  window.setTimeout(() => {
    el.textContent = message;
  }, 30);
}
