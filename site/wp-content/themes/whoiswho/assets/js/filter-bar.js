/* Filter bar: choosing a value applies it at once. Without script the form still works through its Apply button (REQ-029).
   Empty fields are left out of the address so shared links stay short, and the address is the whole state. */
function addressOf(form) {
  const params = new URLSearchParams();
  new FormData(form).forEach((value, name) => {
    if (typeof value === "string" && value !== "") params.append(name, value);
  });
  const query = params.toString();
  return form.getAttribute("action") + (query ? "?" + query : "");
}

function go(form) {
  try {
    window.sessionStorage.setItem("wiw-fbar-announce", "1");
  } catch {
    /* storage can be blocked; the count is still in the page */
  }
  window.location.assign(addressOf(form));
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

/* Phones (REQ-044, REQ-007): below 48rem the selects move into a bottom sheet, a native modal dialog, so focus stays inside,
   Escape closes it and the page behind is inert. The sheet sits inside the form, so the moved selects still belong to it. Choices there wait for "Show N results"; the number is refreshed by asking
   the same list address for its count. Above 48rem the selects go back into the inline bar. */
const PHONE = "(width < 48rem)";

function initSheet(box, form) {
  const sheet = box.querySelector("[data-fbar-sheet]");
  const open = box.querySelector("[data-fbar-open]");
  const fields = box.querySelector("[data-fbar-fields]");
  const apply = box.querySelector("[data-fbar-go]");
  const home = form.querySelector(".fbar-l");
  if (!(sheet instanceof HTMLDialogElement) || typeof sheet.showModal !== "function" || !open || !fields || !home) return null;
  const media = window.matchMedia(PHONE);
  const selects = () => Array.from(box.querySelectorAll(".fbar-sel"));
  let saved = [];
  let timer = 0;
  let pending = null;

  const place = () => {
    const phone = media.matches;
    selects().forEach((label) => {
      if (phone && label.parentElement !== fields) fields.appendChild(label);
      else if (!phone && label.parentElement === fields) home.appendChild(label);
    });
    if (!phone && sheet.open) sheet.close();
  };

  const label = (n) => {
    if (!apply) return;
    const text = (n === 1 ? apply.dataset.one : apply.dataset.many) || "";
    if (text) apply.textContent = text.replace("%d", String(n));
  };

  const refresh = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(() => {
      if (pending) pending.abort();
      pending = new AbortController();
      fetch(addressOf(form), { signal: pending.signal, credentials: "same-origin", headers: { Accept: "text/html" } })
        .then((res) => res.text())
        .then((html) => {
          const doc = new DOMParser().parseFromString(html, "text/html");
          const button = doc.querySelector("[data-fbar-go]");
          const match = button && button.textContent ? button.textContent.match(/\d[\d,.]*/) : null;
          if (match) label(parseInt(match[0].replace(/[,.]/g, ""), 10));
        })
        .catch(() => {
          /* Aborted or offline: the button still applies the choices. */
        });
    }, 250);
  };

  open.addEventListener("click", () => {
    place();
    saved = selects().map((l) => {
      const s = l.querySelector("select");
      return s ? s.value : "";
    });
    sheet.showModal();
    open.setAttribute("aria-expanded", "true");
    document.documentElement.classList.add("fbar-locked");
    const first = sheet.querySelector("select, a[href], button");
    if (first instanceof HTMLElement) first.focus();
  });
  if (apply) apply.addEventListener("click", () => go(form));
  sheet.querySelectorAll("[data-fbar-close]").forEach((b) => b.addEventListener("click", () => sheet.close()));
  sheet.addEventListener("click", (event) => {
    if (event.target === sheet) sheet.close();
  });
  sheet.addEventListener("close", () => {
    /* Closed without applying: put the choices back as the page shows them. */
    selects().forEach((l, i) => {
      const s = l.querySelector("select");
      if (s && saved[i] !== undefined) s.value = saved[i];
    });
    open.setAttribute("aria-expanded", "false");
    document.documentElement.classList.remove("fbar-locked");
    open.focus();
  });
  media.addEventListener("change", place);
  box.classList.add("is-sheet");
  place();
  return { sheet, refresh };
}

export function initFilterBars() {
  announceCount();
  document.querySelectorAll("form.fbar[data-filter]").forEach((form) => {
    const box = form.closest("[data-fbar-box]");
    const phone = box ? initSheet(box, form) : null;
    const onChange = (event) => {
      if (!(event.target instanceof HTMLSelectElement)) return;
      if (phone && phone.sheet.open) phone.refresh();
      else go(form);
    };
    form.addEventListener("change", onChange);
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
