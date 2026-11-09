/* PROVISIONAL THEME (D-004, REQ-052, REQ-190).
   W.brand is the only place for the brand name, tagline, edition and domain.
   The colour and spacing values below style the staff and tool screens only (styles.css).
   Public pages take their design values from theme/tokens.css. */
(function () {
  var W = (window.W = window.W || {});
  W.brand = {
    name: "WHOISWHO",
    tagline: "Influence. Achievement. Legacy.",
    edition: "WHOISWHO in Africa: The Maiden Edition",
    domain: "wiwi.africa"
  };
  var t = {
    "c-bg": "#fafaf6", "c-surface": "#ffffff", "c-ink": "#1b1b1f", "c-muted": "#555a66",
    "c-line": "#cfd0c8", "c-brand": "#1f3a5f", "c-brand-ink": "#ffffff", "c-brand-soft": "#e6edf6",
    "c-accent": "#7a4f00", "c-accent-soft": "#f6ecd6", "c-focus": "#005fcc", "c-ok": "#1b6b3a",
    "c-ok-soft": "#e1f1e6", "c-warn": "#7a4f00", "c-warn-soft": "#fbf0d4", "c-bad": "#a12b2b",
    "c-bad-soft": "#f8e3e3", "c-ph": "#e6e6df", "c-ph-ink": "#6b6f7a", "c-staff": "#2b2d33",
    "c-staff-ink": "#f2f2ee", "c-judge": "#33264d", "c-bar": "#111318", "c-bar-ink": "#e9e9e4",
    "t-verified-bg": "#e1f1e6", "t-verified-ink": "#1b6b3a",
    "t-featured-bg": "#f6ecd6", "t-featured-ink": "#6a4300",
    "t-sponsored-ink": "#6b3a8a",
    "font-head": "Georgia, 'Times New Roman', serif",
    "font-body": "system-ui, -apple-system, 'Segoe UI', Roboto, Arial, sans-serif",
    "font-mono": "ui-monospace, Menlo, Consolas, monospace",
    "r": "6px", "r-lg": "12px", "s1": "4px", "s2": "8px", "s3": "12px", "s4": "16px",
    "s5": "24px", "s6": "40px", "wrap": "72rem"
  };
  W.tokens = t;
  var css = ":root{" + Object.keys(t).map(function (k) { return "--" + k + ":" + t[k]; }).join(";") + "}";
  try {
    var el = document.createElement("style");
    el.id = "tokens";
    el.textContent = css;
    document.head.appendChild(el);
  } catch (e) { /* no DOM (smoke test) */ }
})();
