/* Filter bar: choosing a value applies it at once. Without script the form still works through its Apply button (REQ-029).
   Empty fields are left out of the address so shared links stay short, and the address is the whole state. */
function go(form) {
  const params = new URLSearchParams();
  new FormData(form).forEach((value, name) => {
    if (typeof value === "string" && value !== "") params.append(name, value);
  });
  const query = params.toString();
  try {
    window.sessionStorage.setItem("wiw-fbar-announce", "1");
  } catch {
    /* storage can be blocked; the count is still in the page */
  }
  window.location.assign(form.getAttribute("action") + (query ? "?" + query : ""));
}

/* After a filter change the page reloads: say the new count once, since a status region present on load is not read out. */
function announceCount() {
  let flagged;
  try {
    flagged = window.sessionStorage.getItem("wiw-fbar-announce") === "1";
    window.sessionStorage.removeItem("wiw-fbar-announce");
  } catch {
    return;
  }
  const count = document.querySelector("form.fbar .fbar-n");
  if (flagged && count && count.textContent && window.wiw && window.wiw.announce) window.wiw.announce(count.textContent.trim());
}

export function initFilterBars() {
  announceCount();
  document.querySelectorAll("form.fbar[data-filter]").forEach((form) => {
    form.addEventListener("change", (event) => {
      if (event.target instanceof HTMLSelectElement) go(form);
    });
    /* The clear cross of a search field fires "search" with an empty value: apply that too. */
    form.addEventListener("search", (event) => {
      if (event.target instanceof HTMLInputElement && event.target.value === "") go(form);
    });
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      go(form);
    });
  });
}
