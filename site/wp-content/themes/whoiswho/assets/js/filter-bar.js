/* Filter bar: choosing a value applies it at once. Without script the form still works through its Apply button (REQ-029).
   Empty fields are left out of the address so shared links stay short, and the address is the whole state. */
function go(form) {
  const params = new URLSearchParams();
  new FormData(form).forEach((value, name) => {
    if (typeof value === "string" && value !== "") params.append(name, value);
  });
  const query = params.toString();
  window.location.assign(form.getAttribute("action") + (query ? "?" + query : ""));
}

export function initFilterBars() {
  document.querySelectorAll("form.fbar[data-filter]").forEach((form) => {
    form.addEventListener("change", (event) => {
      if (event.target instanceof HTMLSelectElement) go(form);
    });
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      go(form);
    });
  });
}
