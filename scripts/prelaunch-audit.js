#!/usr/bin/env node
// Pre-launch dependency audit (M5-SEC-05, REQ-046, REQ-065). One command, one PASS / FAIL / SKIP line per check, exit 1 on any FAIL:
//   node scripts/prelaunch-audit.js [--skip-network]
// Checks: composer audit --locked, npm audit --audit-level=high, a secret scan over the full git history, the plugin allowlist,
// and that every allowlisted plugin is named in docs/plugins/third-party.md (the register). It does not call a plugin
// vulnerability service: see docs/runbook.md, "Pre-launch audit" for the manual WordPress plugin check.
//   node --test scripts/__tests__/prelaunch-audit.test.js   run the self tests

const { spawnSync } = require("child_process");
const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");

// High-confidence secret shapes. Placeholders and names-only files (.env.example) never match these.
const SECRET_PATTERNS = [
  { name: "private key block", re: /-----BEGIN (?:RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY-----/ },
  { name: "AWS access key id", re: /\bAKIA[0-9A-Z]{16}\b/ },
  { name: "GitHub token", re: /\bgh[pousr]_[A-Za-z0-9]{36,}\b/ },
  { name: "Slack token", re: /\bxox[baprs]-[A-Za-z0-9-]{10,}\b/ },
  { name: "Stripe-style live key", re: /\b[sr]k_live_[A-Za-z0-9]{16,}\b/ },
  { name: "WordPress salt or key with a real value", re: /define\(\s*'(?:AUTH|SECURE_AUTH|LOGGED_IN|NONCE)_(?:KEY|SALT)'\s*,\s*'(?!put your unique phrase here)[^']{32,}'/ },
  { name: "database password assignment", re: /^\+[ \t]*(?:DB_PASSWORD|MYSQL_PASSWORD)[ \t]*=[ \t]*[^\s#'"<${][^\s#]{5,}$/m },
];

/** Find secrets in a text (a git patch). Returns one line per finding, without the secret itself. */
function scanText(text, label = "text") {
  const found = [];
  for (const { name, re } of SECRET_PATTERNS) {
    if (re.test(text)) found.push(`${name} in ${label}`);
  }
  return found;
}

/** Every allowlisted package slug must appear in the register page. Returns the missing slugs. */
function missingFromRegister(allowlist, registerText) {
  const text = registerText.toLowerCase();
  return (allowlist.plugins || [])
    .map((p) => String(p.package).split("/").pop())
    .filter((slug) => {
      const loose = slug.replace(/-/g, "[ -]?");
      return !new RegExp(loose, "i").test(text);
    });
}

function run(cmd, args, cwd) {
  const r = spawnSync(cmd, args, { cwd, encoding: "utf8", shell: process.platform === "win32", maxBuffer: 256 * 1024 * 1024 });
  return { code: r.status === null ? 127 : r.status, out: `${r.stdout || ""}${r.stderr || ""}` };
}

function main(argv) {
  const skipNetwork = argv.includes("--skip-network");
  const results = [];
  const add = (name, status, detail = "") => {
    results.push({ name, status, detail });
    console.log(`${status} ${name}${detail ? ": " + detail : ""}`);
  };
  const site = path.join(root, "site");

  if (skipNetwork) {
    add("composer audit", "SKIP", "--skip-network");
    add("npm audit", "SKIP", "--skip-network");
  } else {
    const c = run("composer", ["audit", "--locked"], site);
    if (c.code === 127 || /not recognized|command not found/i.test(c.out)) add("composer audit", "SKIP", "composer not on PATH");
    else add("composer audit", c.code === 0 ? "PASS" : "FAIL", c.code === 0 ? "" : c.out.trim().split("\n").slice(0, 5).join(" | "));
    const n = run("npm", ["audit", "--audit-level=high"], site);
    add("npm audit (high)", n.code === 0 ? "PASS" : "FAIL", n.code === 0 ? "" : n.out.trim().split("\n").slice(-6).join(" | "));
  }

  // Secret scan over the full history: gitleaks when installed (what CI uses), else the built-in patterns over every added line.
  const g = run("gitleaks", ["detect", "--source", root, "--no-banner", "--redact"], root);
  if (g.code !== 127 && !/not recognized|not found/i.test(g.out)) {
    add("secret scan (gitleaks, full history)", g.code === 0 ? "PASS" : "FAIL", g.code === 0 ? "" : g.out.trim().split("\n").slice(-4).join(" | "));
  } else {
    const log = run("git", ["log", "--all", "-p", "--no-color", "--diff-filter=AM", "-U0", "--", ".", ":(exclude)*.lock", ":(exclude)package-lock.json", ":(exclude)docs", ":(exclude)*/tests/*", ":(exclude)*/__tests__/*"], root);
    const findings = scanText(log.out, "git history");
    add("secret scan (built-in patterns, full history; install gitleaks for the full rule set)", findings.length ? "FAIL" : "PASS", findings.join("; "));
  }

  const p = run("node", [path.join("scripts", "check-plugins.js")], root);
  add("plugin allowlist and forbidden kinds", p.code === 0 ? "PASS" : "FAIL", p.code === 0 ? "" : p.out.trim().split("\n").slice(0, 4).join(" | "));

  const allowFile = path.join(site, "config", "plugin-allowlist.json");
  const registerFile = path.join(root, "docs", "plugins", "third-party.md");
  if (fs.existsSync(allowFile) && fs.existsSync(registerFile)) {
    const missing = missingFromRegister(JSON.parse(fs.readFileSync(allowFile, "utf8")), fs.readFileSync(registerFile, "utf8"));
    add("every allowlisted plugin is in the register (docs/plugins/third-party.md)", missing.length ? "FAIL" : "PASS", missing.join(", "));
  } else {
    add("plugin register comparison", "SKIP", "allowlist or register file missing");
  }
  add("approved by the Administrator", "SKIP", "a person confirms this: see docs/runbook.md, Pre-launch audit");

  const failed = results.filter((r) => r.status === "FAIL").length;
  const passed = results.filter((r) => r.status === "PASS").length;
  console.log(`\n${passed} passed, ${failed} failed, ${results.length - passed - failed} skipped`);
  return failed || !passed ? 1 : 0;
}

if (require.main === module) process.exit(main(process.argv.slice(2)));

module.exports = { scanText, missingFromRegister, SECRET_PATTERNS };
