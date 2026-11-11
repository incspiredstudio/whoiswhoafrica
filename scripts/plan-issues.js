#!/usr/bin/env node
// Build plan -> GitHub. Reads docs/plan/M*.json and creates labels, milestones and issues, then tracks progress.
//
//   node scripts/plan-issues.js validate            check the plan files (no network)
//   node scripts/plan-issues.js create --dry-run    show what would be created
//   node scripts/plan-issues.js create              create labels, milestones and issues (needs gh and gh auth login)
//   node scripts/plan-issues.js project <owner> [n] add every issue to a GitHub Project (creates one if n is omitted; needs the project scope)
//   node scripts/plan-issues.js status              progress per milestone, from GitHub
//
// The map docs/plan/issue-map.json (key -> issue number) makes `create` safe to re-run: issues already created are skipped.
// Issues are closed by the pull request that says "Closes #N" (CLAUDE.md). Tick acceptance boxes in the issue as work lands.

const fs = require("fs"), path = require("path"), cp = require("child_process");
const root = path.join(__dirname, ".."), planDir = path.join(root, "docs", "plan");
const MAP = path.join(planDir, "issue-map.json");
const AREAS = ["plugin", "theme", "infra", "qa", "human"], SIZES = ["S", "M", "L"], PRI = ["M", "S"];
const MILESTONES = { M0: "M0 Foundations", M1: "M1 The record", M2: "M2 Reading and finding", M3: "M3 Trust and revenue", M4: "M4 Recognition", M5: "M5 Launch" };
const COLORS = { area: "1d76db", feature: "5319e7", priority: "b60205", size: "c2e0c6", type: "0e8a16", wave: "fbca04", human: "d93f0b" };

function load() {
  const issues = [];
  for (const f of fs.readdirSync(planDir).filter((x) => /^M\d.*\.json$/.test(x)).sort()) {
    const j = JSON.parse(fs.readFileSync(path.join(planDir, f), "utf8"));
    for (const i of j.issues || []) { i._file = f; issues.push(i); }
  }
  return issues;
}
function liveReqs() {
  const t = fs.readFileSync(path.join(root, "docs", "requirements.md"), "utf8"), live = new Set(), dead = new Set();
  for (const l of t.split(/\r?\n/)) { const m = /^\| (REQ-\d+) \| (.*)$/.exec(l); if (!m) continue; (/WITHDRAWN/.test(m[2].slice(0, 80)) ? dead : live).add(m[1]); }
  return { live, dead };
}
function validate(issues) {
  const errs = [], keys = new Map(), { live, dead } = liveReqs();
  issues.forEach((i) => { if (keys.has(i.key)) errs.push(`${i.key}: duplicate key`); keys.set(i.key, i); });
  for (const i of issues) {
    const e = (m) => errs.push(`${i.key}: ${m}`);
    if (!/^M[0-5]-[A-Za-z0-9]{2,4}-\d{2}$/.test(i.key)) e("key format");
    if (!i.title || i.title.length > 90) e("title missing or over 90 characters");
    if (!MILESTONES[i.milestone] || !i.key.startsWith(i.milestone + "-")) e("milestone does not match key");
    if (!AREAS.includes(i.area)) e("area " + i.area); if (!SIZES.includes(i.size)) e("size " + i.size); if (!PRI.includes(i.priority)) e("priority " + i.priority);
    if (!i.summary) e("summary missing"); if (!Array.isArray(i.acceptance) || !i.acceptance.length) e("acceptance empty");
    if (i.area !== "human" && (!Array.isArray(i.reqs) || !i.reqs.length)) e("no REQ cited");
    for (const r of i.reqs || []) { if (dead.has(r)) e(r + " is withdrawn"); else if (!live.has(r)) e(r + " not found in requirements.md"); }
    for (const t of i.tests || []) if (/^test_/.test(t) && !/^test_\w+$/.test(t)) e("test name format: " + t);
    for (const d of i.depends || []) if (!keys.has(d)) e("depends on unknown " + d);
    for (const d of i.docs || []) if (!fs.existsSync(path.join(root, d))) e("doc not found: " + d);
  }
  // cycles
  const state = {};
  const visit = (k, stack) => { if (state[k] === 2) return; if (state[k] === 1) { errs.push("cycle: " + stack.concat(k).join(" > ")); return; } state[k] = 1; for (const d of (keys.get(k) || {}).depends || []) if (keys.has(d)) visit(d, stack.concat(k)); state[k] = 2; };
  for (const k of keys.keys()) visit(k, []);
  return errs;
}
function waves(issues) {
  const by = new Map(issues.map((i) => [i.key, i])), w = {};
  const f = (k) => (w[k] !== undefined ? w[k] : (w[k] = 1 + Math.max(-1, ...((by.get(k).depends || []).map(f)))));
  issues.forEach((i) => f(i.key)); return w;
}
const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "").slice(0, 40);
function repoBlob() { try { const u = cp.execSync("git remote get-url origin", { cwd: root }).toString().trim().replace(/\.git$/, ""); return u + "/blob/" + cp.execSync("git rev-parse --abbrev-ref HEAD", { cwd: root }).toString().trim() + "/"; } catch (e) { return ""; } }
function body(i, map, wave) {
  const blob = repoBlob(), L = [];
  L.push(i.summary, "");
  L.push(`**Key** ${i.key} | **Area** ${i.area} | **Size** ${i.size} | **Priority** ${i.priority} | **Wave** ${wave}`, "");
  if (i.reqs && i.reqs.length) L.push("**Requirements** " + i.reqs.join(", "), "");
  if (i.scope && i.scope.length) L.push("## Scope", ...i.scope.map((s) => "- " + s), "");
  L.push("## Acceptance", ...i.acceptance.map((a) => "- [ ] " + a.replace(/^-\s*\[ \]\s*/, "")), "");
  if (i.tests && i.tests.length) L.push("## Tests (write first)", ...i.tests.map((t) => "- `" + t + "`"), "");
  if (i.depends && i.depends.length) L.push("## Blocked by", ...i.depends.map((d) => "- " + (map[d] ? "#" + map[d] : d)), "");
  if (i.docs && i.docs.length) L.push("## Read first", ...i.docs.map((d) => "- [" + d + "](" + blob + d + ")"), "");
  if (i.files && i.files.length) L.push("## May write", ...i.files.map((f) => "- `" + f + "`"), "");
  if (i.notes) L.push("## Notes", i.notes, "");
  L.push("## Working rules", "- Branch `" + i.area + "/issue-<number>-" + slug(i.title) + "`; one pull request; the body says `Closes #<number>`, lists the REQ ids and shows test evidence.", "- Tests first. No scope creep: anything new becomes an issue labelled `type:discovered`.", "- Only a separate reviewer step marks this done. Tick the acceptance boxes as they are met.");
  return L.join("\n");
}
const sleep = (ms) => Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
function gh(args, opts) { return cp.execFileSync("gh", args, Object.assign({ cwd: root, encoding: "utf8", stdio: ["ignore", "pipe", "pipe"] }, opts || {})).trim(); }
function readMap() { return fs.existsSync(MAP) ? JSON.parse(fs.readFileSync(MAP, "utf8")) : {}; }
function writeMap(m) { fs.writeFileSync(MAP, JSON.stringify(m, null, 2) + "\n"); }
function labelsOf(i, wave) { return ["area:" + i.area, "feature:" + i.feature, "priority:" + i.priority, "size:" + i.size, "type:feature", "wave:" + wave].concat(i.area === "human" ? ["human"] : []); }

const cmd = process.argv[2], rest = process.argv.slice(3), dry = rest.includes("--dry-run");
const issues = load();
if (cmd === "validate" || cmd === "create") {
  const errs = validate(issues);
  const by = {}; issues.forEach((i) => { by[i.milestone] = (by[i.milestone] || 0) + 1; });
  console.log(issues.length + " issues", JSON.stringify(by));
  if (errs.length) { console.log(errs.length + " problems:\n" + errs.map((x) => " - " + x).join("\n")); process.exit(1); }
  console.log("plan is valid");
}
if (cmd === "create") {
  const w = waves(issues), map = readMap();
  const labels = new Set(); issues.forEach((i) => labelsOf(i, w[i.key]).forEach((l) => labels.add(l)));
  labels.add("type:discovered");
  if (dry) { console.log("\nlabels:", [...labels].join(", ")); console.log("milestones:", Object.values(MILESTONES).join("; ")); issues.forEach((i) => console.log((map[i.key] ? "skip   " : "create ") + i.key + " [" + labelsOf(i, w[i.key]).join(",") + "] " + i.title)); process.exit(0); }
  for (const l of labels) { const g = l.split(":")[0]; try { gh(["label", "create", l, "--color", COLORS[g] || "ededed", "--force"]); } catch (e) { console.error("label", l, String(e.stderr || e).split("\n")[0]); } }
  const have = JSON.parse(gh(["api", "repos/{owner}/{repo}/milestones?state=all&per_page=100"]) || "[]").map((m) => m.title);
  for (const t of Object.values(MILESTONES)) if (!have.includes(t)) gh(["api", "repos/{owner}/{repo}/milestones", "-f", "title=" + t]);
  const order = issues.slice().sort((a, b) => w[a.key] - w[b.key] || a.key.localeCompare(b.key));
  for (const i of order) {
    if (map[i.key]) continue;
    const tmp = path.join(require("os").tmpdir(), "wiw-issue.md"); fs.writeFileSync(tmp, body(i, map, w[i.key]));
    const args = ["issue", "create", "--title", `[${i.key}] ${i.title}`, "--body-file", tmp, "--milestone", MILESTONES[i.milestone]];
    labelsOf(i, w[i.key]).forEach((l) => args.push("--label", l));
    const url = gh(args); map[i.key] = Number(url.split("/").pop()); writeMap(map); console.log(i.key + " -> #" + map[i.key]); sleep(1500);
  }
  // second pass: dependency numbers are now known for every issue
  for (const i of order) { if (!(i.depends || []).length) continue; const tmp = path.join(require("os").tmpdir(), "wiw-issue.md"); fs.writeFileSync(tmp, body(i, map, w[i.key])); gh(["issue", "edit", String(map[i.key]), "--body-file", tmp]); sleep(1000); }
  console.log("done. Map saved to docs/plan/issue-map.json");
}
if (cmd === "project") {
  const owner = rest[0]; let n = rest[1]; const map = readMap();
  if (!owner) { console.error("usage: project <owner> [project-number]"); process.exit(1); }
  if (!n) { const out = JSON.parse(gh(["project", "create", "--owner", owner, "--title", "WHOISWHO build", "--format", "json"])); n = String(out.number); console.log("created project " + n); }
  const repoUrl = cp.execSync("git remote get-url origin", { cwd: root }).toString().trim().replace(/\.git$/, "");
  for (const num of Object.values(map)) { try { gh(["project", "item-add", n, "--owner", owner, "--url", repoUrl + "/issues/" + num]); } catch (e) { console.error("#" + num, String(e.stderr || e).split("\n")[0]); } }
  console.log("added " + Object.keys(map).length + " issues to project " + n);
}
if (cmd === "status") {
  const map = readMap(), rows = JSON.parse(gh(["issue", "list", "--state", "all", "--limit", "500", "--json", "number,state,title,milestone"]));
  const by = {}; for (const r of rows) { const m = (r.milestone && r.milestone.title) || "none"; (by[m] = by[m] || { open: 0, closed: 0 })[r.state === "CLOSED" ? "closed" : "open"]++; }
  console.log("Milestone".padEnd(28) + "Done  Open  %");
  for (const m of Object.values(MILESTONES)) { const b = by[m] || { open: 0, closed: 0 }, t = b.open + b.closed; console.log(m.padEnd(28) + String(b.closed).padEnd(6) + String(b.open).padEnd(6) + (t ? Math.round(100 * b.closed / t) : 0) + "%"); }
}
if (!["validate", "create", "project", "status"].includes(cmd)) console.log("usage: node scripts/plan-issues.js validate | create [--dry-run] | project <owner> [n] | status");
