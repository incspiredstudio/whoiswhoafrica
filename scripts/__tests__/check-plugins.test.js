// Self tests for scripts/check-plugins.js. Run: node --test scripts/__tests__
const test = require("node:test");
const assert = require("node:assert");
const { check, lockedPlugins } = require("../check-plugins.js");

const allow = (plugins) => ({ plugins });
const asLocked = (name, version) => lockedPlugins({ packages: [{ name, version, type: "wordpress-plugin" }], "packages-dev": [] });

test("a plugin on the allowlist passes", () => {
  const locked = asLocked("woocommerce/action-scheduler", "4.2.0");
  const problems = check({ locked, disk: ["action-scheduler"], allowlist: allow([{ package: "woocommerce/action-scheduler", version: "4.2.0", reason: "jobs" }]) });
  assert.deepStrictEqual(problems, []);
});

test("an SEO plugin added to composer.json fails", () => {
  const locked = asLocked("wpackagist-plugin/wordpress-seo", "24.0");
  const problems = check({ locked, disk: [], allowlist: allow([]) });
  assert.ok(problems.some((p) => /SEO plugin/.test(p)), problems.join("\n"));
  assert.ok(problems.some((p) => /not on the allowlist/.test(p)));
});

test("every forbidden kind is caught even when someone puts it on the allowlist", () => {
  const cases = {
    "wpackagist-plugin/all-in-one-seo-pack": /SEO/,
    "wpackagist-plugin/seo-by-rank-math": /SEO/,
    "wpackagist-plugin/ultimate-member": /membership/,
    "wpackagist-plugin/paid-memberships-pro": /membership/,
    "wpackagist-plugin/woocommerce": /payment or checkout/,
    "wpackagist-plugin/easy-digital-downloads": /payment or checkout/,
    "wpackagist-plugin/ai-engine": /AI drafting/,
    "wpackagist-plugin/elementor": /page builder/,
    "wpackagist-plugin/beaver-builder-lite-version": /page builder/,
  };
  for (const [name, re] of Object.entries(cases)) {
    const locked = asLocked(name, "1.0.0");
    const problems = check({ locked, disk: [], allowlist: allow([{ package: name, version: "1.0.0", reason: "x" }]) });
    assert.ok(problems.some((p) => re.test(p)), `${name} should be forbidden: ${problems.join(" | ")}`);
  }
});

test("allowed kinds are not caught by mistake", () => {
  for (const name of ["woocommerce/action-scheduler", "wpackagist-plugin/two-factor", "wpackagist-plugin/wpdiscuz", "wpackagist-plugin/user-role-editor", "wpackagist-plugin/fluent-smtp"]) {
    const locked = asLocked(name, "1.0.0");
    const problems = check({ locked, disk: [], allowlist: allow([{ package: name, version: "1.0.0", reason: "ok" }]) });
    assert.deepStrictEqual(problems, [], `${name}: ${problems.join(" | ")}`);
  }
});

test("a version mismatch between the lockfile and the allowlist fails", () => {
  const locked = asLocked("wpackagist-plugin/two-factor", "0.10.0");
  const problems = check({ locked, disk: [], allowlist: allow([{ package: "wpackagist-plugin/two-factor", version: "0.9.0", reason: "2fa" }]) });
  assert.ok(problems.some((p) => /lockfile has 0\.10\.0 but the allowlist says 0\.9\.0/.test(p)), problems.join("\n"));
});

test("a plugin folder copied in by hand fails", () => {
  const problems = check({ locked: [], disk: ["some-plugin"], allowlist: allow([]) });
  assert.ok(problems.some((p) => /some-plugin is not installed through the lockfile/.test(p)));
});

test("an allowlist entry needs a reason", () => {
  const locked = asLocked("wpackagist-plugin/two-factor", "1.0.0");
  const problems = check({ locked, disk: [], allowlist: allow([{ package: "wpackagist-plugin/two-factor", version: "1.0.0" }]) });
  assert.ok(problems.some((p) => /has no reason/.test(p)));
});
