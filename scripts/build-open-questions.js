// Generates docs/open-questions.md from the Open rows of docs/questions.md (the single source).
// Only questions that still need an answer are published on the docs site. Run: node scripts/build-open-questions.js
const fs = require("fs"), path = require("path");
const root = path.join(__dirname, "..");
const src = fs.readFileSync(path.join(root, "docs", "questions.md"), "utf8");
const rows = [];
for (const line of src.split(/\r?\n/)) {
  const m = /^\| (Q\d+) \| ([^|]+) \| (.*)$/.exec(line);
  if (!m || m[2].trim() !== "Open") continue;
  const c = line.split(" | ").map((x) => x.trim().replace(/^\|\s*/, "").replace(/\s*\|$/, ""));
  rows.push({ id: c[0], q: c[2], why: c[3], rec: c[4], type: c[5] || "", scope: c[6] || "" });
}
const clean = (s) => String(s || "").replace(/\*\*/g, "").replace(/</g, "&lt;");
const num = (r) => parseInt(r.id.slice(1), 10);
const build = rows.filter((r) => num(r) >= 81 && num(r) <= 91), product = rows.filter((r) => !(num(r) >= 81 && num(r) <= 91));
const card = (r) => `### ${r.id}. ${clean(r.q)}\n\n**Why it matters.** ${clean(r.why)}\n\n**Suggested answer.** ${clean(r.rec)}\n`;
let md = "# Open questions\n\nThese are the questions that still need an answer. Each one has a suggested answer, so silence means we go with the suggestion. Answered questions are not listed here.\n\n";
md += `${rows.length} open: ${product.length} about the product and ${build.length} about the WordPress build.\n\n`;
md += "## Product\n\n" + product.map(card).join("\n") + "\n## WordPress build\n\nThese need an answer before the build plan is final.\n\n" + build.map(card).join("\n");
md += "\n---\n\nGenerated from the open items in `docs/questions.md` by `scripts/build-open-questions.js`. Do not edit this page by hand.\n";
fs.writeFileSync(path.join(root, "docs", "open-questions.md"), md);
console.log("written", rows.length, "open questions");
