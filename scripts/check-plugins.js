#!/usr/bin/env node
// Plugin allowlist check. Reads site/composer.lock (and the plugin folders on disk) and fails when:
//   - an installed plugin is of a forbidden kind (SEO, public login or membership, payment or checkout, AI drafting, page builder);
//   - an installed plugin is not on the allowlist, or its version differs from the allowlist's.
// A plugin on the allowlist that is also a forbidden kind still fails. The allowlist is site/config/plugin-allowlist.json; a change
// to it needs a pull request that cites docs/plugins/third-party.md and reruns the visibility matrix (tests/Visibility).
//
//   node scripts/check-plugins.js            check the repo
//   node --test scripts/__tests__/check-plugins.test.js   run the self tests

const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");

// Forbidden kinds, from docs/plugins/third-party.md ("Not allowed"). Matched against the plugin slug and package name.
const FORBIDDEN = [
  { kind: "an SEO plugin", re: /(^|[-_/])(seo|rank-?math|yoast|wordpress-seo|aioseo|seopress|slim-seo)([-_/]|$)/i },
  { kind: "a public-login, membership or account plugin", re: /(membership|memberpress|ultimate-member|wp-members|login-with|theme-my-login|user-registration|profile-builder|buddypress|bbpress)/i },
  { kind: "a payment or checkout plugin", re: /(woocommerce(?!\/action-scheduler)|easy-digital-downloads|edd-|stripe|paypal|payment|checkout|give-?wp|gravityforms-?pay)/i },
  { kind: "an AI drafting plugin", re: /(^|[-_/])(ai|gpt|chatgpt|openai|ai-engine|aiomatic|jetpack-ai)([-_/]|$)/i },
  { kind: "a page builder that outputs public templates", re: /(elementor|beaver-?builder|divi|wpbakery|js_composer|siteorigin|brizy|oxygen|bricks|visual-composer|thrive)/i },
];

const MESSAGE_HELP =
  "To approve a plugin: add it to site/config/plugin-allowlist.json in a pull request that cites docs/plugins/third-party.md " +
  "(job, personal data touched, visibility), pin the exact version, and rerun the visibility matrix (composer test -- --testsuite Visibility).";

/** Plugins installed by Composer: { name, slug, version }. */
function lockedPlugins(lock) {
  const all = [...(lock.packages || []), ...(lock["packages-dev"] || [])];
  return all
    .filter((p) => /^wordpress-(plugin|muplugin)$/.test(p.type || "") || /^wpackagist-plugin\//.test(p.name))
    .map((p) => ({ name: p.name, slug: p.name.split("/").pop(), version: String(p.version).replace(/^v/, "") }));
}

/** Plugin folders on disk that are not from our own code. */
function diskPlugins(pluginsDir, ours = ["whoiswho-core"]) {
  if (!fs.existsSync(pluginsDir)) return [];
  return fs
    .readdirSync(pluginsDir, { withFileTypes: true })
    .filter((d) => d.isDirectory() && !ours.includes(d.name))
    .map((d) => d.name);
}

/** Pure check: returns a list of problems. */
function check({ locked, disk, allowlist }) {
  const problems = [];
  const allowed = new Map((allowlist.plugins || []).map((p) => [p.package, p]));

  for (const plugin of locked) {
    for (const f of FORBIDDEN) {
      if (f.re.test(plugin.name) || f.re.test(plugin.slug)) {
        problems.push(`${plugin.name} is ${f.kind}, which is not allowed (docs/plugins/third-party.md, "Not allowed").`);
      }
    }
    const entry = allowed.get(plugin.name);
    if (!entry) {
      problems.push(`${plugin.name} ${plugin.version} is not on the allowlist.`);
    } else if (String(entry.version) !== plugin.version) {
      problems.push(`${plugin.name}: the lockfile has ${plugin.version} but the allowlist says ${entry.version}.`);
    }
  }

  // A plugin folder on disk that Composer did not install is also unapproved (for example copied in by hand).
  const lockedSlugs = new Set(locked.map((p) => p.slug));
  for (const folder of disk) {
    if (!lockedSlugs.has(folder)) problems.push(`wp-content/plugins/${folder} is not installed through the lockfile.`);
  }

  // The allowlist itself must not name forbidden kinds.
  for (const entry of allowlist.plugins || []) {
    for (const f of FORBIDDEN) {
      if (f.re.test(entry.package)) problems.push(`The allowlist names ${entry.package}, which is ${f.kind}.`);
    }
    if (!entry.reason) problems.push(`The allowlist entry ${entry.package} has no reason.`);
  }
  return problems;
}

function main() {
  const lock = JSON.parse(fs.readFileSync(path.join(root, "site", "composer.lock"), "utf8"));
  const allowlist = JSON.parse(fs.readFileSync(path.join(root, "site", "config", "plugin-allowlist.json"), "utf8"));
  const problems = check({ locked: lockedPlugins(lock), disk: diskPlugins(path.join(root, "site", "wp-content", "plugins")), allowlist });
  if (problems.length) {
    console.error("Plugin check failed:\n" + problems.map((p) => " - " + p).join("\n") + "\n\n" + MESSAGE_HELP);
    process.exit(1);
  }
  console.log("Plugin check passed: " + lockedPlugins(lock).length + " plugin(s), all on the allowlist.");
}

if (require.main === module) main();
module.exports = { check, lockedPlugins, diskPlugins, FORBIDDEN };
