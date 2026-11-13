/* The six-step nomination form (REQ-030, REQ-196, REQ-233, REQ-122, REQ-043). Steps 1 and 2 post the nominator's details to
   /nominate/start/ and get a draft token back as JSON; the nominator opens the emailed link, then steps 3 to 6 are posted to
   /nominate/ with that token. The plugin is the authority: it validates, scans the free text for personal details, warns about
   duplicates and rate-limits. This script only walks the steps, shows what the plugin answers and moves focus so errors are
   announced. Nothing personal is stored in the browser except the draft token, kept in sessionStorage for this tab. */

const STORE = "wiw_nomination_draft";
const STEP_OF_FIELD = {
  nominator_name: 1,
  nominator_email: 1,
  consent: 1,
  nominee_name: 3,
  nominee_org: 3,
  nominee_email: 3,
  category_id: 4,
  evidence: 4,
  relationship: 5,
  relationship_note: 5,
};

function root() {
  return document.querySelector("[data-nominate]");
}

function init(wiz) {
  const words = JSON.parse(wiz.dataset.words || "{}");
  const categories = JSON.parse(wiz.dataset.categories || "{}");
  const form = wiz.querySelector("[data-form]");
  const alertBox = wiz.querySelector("[data-alert]");
  const limit = wiz.querySelector("[data-limit]");
  const steps = Array.from(form.querySelectorAll("[data-step]"));
  const dots = Array.from(wiz.querySelectorAll("[data-progress]"));
  let current = 1;
  let draft = "";
  let busy = false;
  try {
    draft = sessionStorage.getItem(STORE) || "";
  } catch {
    draft = "";
  }

  const field = (name) => form.elements.namedItem(name);
  const value = (name) => {
    const el = field(name);
    return el && "value" in el ? el.value.trim() : "";
  };

  function remember() {
    try {
      if (draft) sessionStorage.setItem(STORE, draft);
      else sessionStorage.removeItem(STORE);
    } catch {
      /* storage can be unavailable: the form still works for this page view. */
    }
  }

  function clearErrors() {
    form.querySelectorAll(".err[data-js]").forEach((n) => n.remove());
    form.querySelectorAll("[aria-invalid]").forEach((n) => {
      n.removeAttribute("aria-invalid");
      const ids = (n.getAttribute("aria-describedby") || "").split(" ").filter((i) => i && !i.endsWith("-err"));
      if (ids.length) n.setAttribute("aria-describedby", ids.join(" "));
      else n.removeAttribute("aria-describedby");
    });
    alertBox.hidden = true;
    alertBox.textContent = "";
    alertBox.className = "nm-alert";
    form.querySelector("[data-duplicate]").hidden = true;
  }

  function fieldError(name, code) {
    const el = field(name);
    if (!el || !el.id) return;
    const msg = words[code] || words.invalid;
    const p = document.createElement("p");
    p.className = "err";
    p.id = el.id + "-err";
    p.dataset.js = "1";
    p.setAttribute("role", "alert");
    p.textContent = msg;
    el.setAttribute("aria-invalid", "true");
    el.setAttribute("aria-describedby", ((el.getAttribute("aria-describedby") || "") + " " + p.id).trim());
    (el.closest(".field") || el.parentNode).appendChild(p);
  }

  function say(text, kind) {
    alertBox.textContent = text;
    alertBox.className = "nm-alert " + (kind === "warn" ? "warnbox" : "errbox");
    alertBox.hidden = false;
    alertBox.focus();
  }

  // Moving to a step moves focus to its heading, except when the page has only just loaded: focus must not skip the skip link.
  function show(n, moveFocus = true) {
    current = n;
    steps.forEach((s) => {
      s.hidden = Number(s.dataset.step) !== n;
    });
    dots.forEach((d) => {
      const i = Number(d.dataset.progress);
      d.classList.toggle("is-now", i === n);
      d.classList.toggle("is-done", i < n);
      if (i === n) d.setAttribute("aria-current", "step");
      else d.removeAttribute("aria-current");
    });
    if (n === 6) review();
    const h = form.querySelector('[data-step="' + n + '"] h2');
    if (h && moveFocus) h.focus();
  }

  function review() {
    const dl = form.querySelector("[data-review]");
    dl.textContent = "";
    const rows = [
      ["Nominee", value("nominee_name") + " (" + (value("nominee_type") === "organisation" ? words.organisation : words.person) + ")"],
      ["Category", categories[value("category_id")] || ""],
      ["Evidence", value("evidence") + (value("evidence_link") ? " " + value("evidence_link") : "")],
      ["Link to the nominee", value("relationship") === "yes" ? value("relationship_note") : words.none],
    ];
    rows.forEach(([t, d]) => {
      const dt = document.createElement("dt");
      dt.textContent = t;
      const dd = document.createElement("dd");
      dd.textContent = d;
      dl.append(dt, dd);
    });
  }

  // Quick client check of what the plugin will insist on; the plugin stays the authority.
  function check(n) {
    const need = {
      1: ["nominator_name", "nominator_email", "consent"],
      3: ["nominee_name", "nominee_type"],
      4: ["category_id", "evidence"],
      5: ["relationship"],
    }[n] || [];
    if (n === 5 && value("relationship") === "yes") need.push("relationship_note");
    const bad = need.filter((name) => {
      const el = field(name);
      return el && (el.type === "checkbox" ? !el.checked : !el.value.trim());
    });
    bad.forEach((name) => fieldError(name, "required"));
    if (bad.length) {
      say(words.fix);
      const el = field(bad[0]);
      if (el) el.focus();
    }
    return bad.length === 0;
  }

  function body(token, extra) {
    const data = new URLSearchParams();
    ["wiw_website", "policy_version"].forEach((k) => data.set(k, value(k)));
    data.set("wiw_form_token", token);
    Object.keys(extra).forEach((k) => data.set(k, extra[k]));
    return data;
  }

  async function post(url, data) {
    const res = await fetch(url, {
      method: "POST",
      headers: { Accept: "application/json", "Content-Type": "application/x-www-form-urlencoded" },
      body: data.toString(),
      credentials: "same-origin",
    });
    return res.json();
  }

  function setBusy(on) {
    busy = on;
    form.querySelectorAll("button").forEach((b) => {
      b.disabled = on;
    });
  }

  function fail(r, fallbackStep) {
    const code = r.status;
    if (code === "try_later") {
      form.hidden = true;
      limit.hidden = false;
      limit.focus();
      return;
    }
    if (code === "closed") {
      form.hidden = true;
      say(words.closed);
      return;
    }
    if (code === "draft_expired") {
      draft = "";
      remember();
      say(words.draft);
      show(1);
      alertBox.focus();
      return;
    }
    if (code === "unverified") {
      show(2);
      say(words.unverified);
      return;
    }
    if (code === "form_expired") {
      say(words.expired);
      return;
    }
    if (code === "invalid") {
      const errors = r.errors || {};
      const names = Object.keys(errors);
      const scanned = r.message && r.message !== words.fix && names.some((n) => errors[n] === "remove_personal_text");
      let first = 99;
      names.forEach((n) => {
        fieldError(n, errors[n] === "remove_personal_text" ? "invalid" : errors[n]);
        first = Math.min(first, STEP_OF_FIELD[n] || fallbackStep);
      });
      if (first === 99) first = fallbackStep;
      show(first);
      if (scanned) {
        names.filter((n) => errors[n] === "remove_personal_text").forEach((n) => {
          const p = form.querySelector("#f-" + n + "-err");
          if (p) p.textContent = r.message;
        });
      }
      say(scanned ? r.message : words.fix);
      const bad = names.find((n) => (STEP_OF_FIELD[n] || fallbackStep) === first);
      const el = bad && field(bad);
      if (el) el.focus();
      return;
    }
    say(r.message || words.fix);
  }

  async function start() {
    clearErrors();
    if (!check(1)) return;
    setBusy(true);
    try {
      const r = await post(
        wiz.dataset.startUrl,
        body(wiz.dataset.tokenStart, {
          nominator_name: value("nominator_name"),
          nominator_email: value("nominator_email"),
          consent: field("consent").checked ? "1" : "",
        })
      );
      if (r.status === "received") {
        draft = r.draft || "";
        remember();
        show(2);
      } else {
        fail(r, 1);
      }
    } catch {
      say(words.network);
    }
    setBusy(false);
  }

  async function submit(again) {
    clearErrors();
    setBusy(true);
    const org = value("nominee_type") === "organisation" ? value("nominee_name") : "";
    const link = value("evidence_link");
    try {
      const r = await post(
        wiz.dataset.submitUrl,
        body(wiz.dataset.tokenSubmit, {
          draft,
          nominee_name: value("nominee_name"),
          nominee_org: org,
          nominee_email: value("nominee_email"),
          category_id: value("category_id"),
          evidence: value("evidence") + (link ? "\n" + link : ""),
          relationship: value("relationship"),
          relationship_note: value("relationship") === "yes" ? value("relationship_note") : "",
          continue_anyway: again ? "1" : "",
        })
      );
      if (r.status === "received" && r.reference) {
        draft = "";
        remember();
        window.location.assign(wiz.dataset.doneUrl + "?kind=nomination&ref=" + encodeURIComponent(r.reference));
        return;
      }
      if (r.status === "duplicate") {
        show(6);
        form.querySelector("[data-duplicate]").hidden = false;
        const btn = form.querySelector("[data-submit]");
        btn.textContent = words.again;
        btn.dataset.again = "1";
        form.querySelector("[data-duplicate]").focus();
      } else {
        fail(r, 6);
      }
    } catch {
      say(words.network);
    }
    setBusy(false);
  }

  form.addEventListener("click", (ev) => {
    const t = ev.target.closest("button");
    if (!t || busy) return;
    if (t.matches("[data-back]")) {
      clearErrors();
      show(Math.max(1, current - 1));
    } else if (t.matches("[data-resend]")) {
      start();
    } else if (t.matches("[data-submit]")) {
      submit(t.dataset.again === "1");
    } else if (t.matches("[data-next]")) {
      clearErrors();
      if (current === 1) start();
      else if (current === 2) show(3);
      else if (check(current)) show(current + 1);
    }
  });
  // Enter inside a field moves to the next step instead of posting the whole form at the wrong address.
  form.addEventListener("submit", (ev) => ev.preventDefault());
  form.addEventListener("keydown", (ev) => {
    if (ev.key === "Enter" && ev.target.tagName === "INPUT" && ev.target.type !== "checkbox") {
      ev.preventDefault();
      const next = form.querySelector('[data-step="' + current + '"] [data-next], [data-step="' + current + '"] [data-submit]');
      if (next) next.click();
    }
  });
  const relationship = field("relationship");
  const note = form.querySelector("[data-relationship-note]");
  relationship.addEventListener("change", () => {
    note.hidden = relationship.value !== "yes";
  });

  wiz.hidden = false;
  // A returning nominator (the emailed link opened in this tab and back) continues from the verification step.
  show(draft ? 2 : 1, Boolean(draft));
}

const mount = root();
if (mount) init(mount);
