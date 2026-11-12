// Self tests for scripts/prelaunch-audit.js. Run: node --test scripts/__tests__
const test = require("node:test");
const assert = require("node:assert");
const { scanText, missingFromRegister } = require("../prelaunch-audit.js");

test("REQ-046 a private key in history is found", () => {
  const found = scanText("+-----BEGIN RSA PRIVATE KEY-----\n+MIIE\n", "history");
  assert.strictEqual(found.length, 1);
  assert.match(found[0], /private key/);
  assert.doesNotMatch(found[0], /MIIE/, "the finding never repeats the secret");
});

test("REQ-046 a real salt value is found, the placeholder is not", () => {
  const real = "+define( 'AUTH_KEY', 'a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4e5f6' );";
  assert.strictEqual(scanText(real).length, 1);
  assert.deepStrictEqual(scanText("+define( 'AUTH_KEY', 'put your unique phrase here' );"), []);
});

test("REQ-046 a names-only secrets example is clean", () => {
  assert.deepStrictEqual(scanText("+DB_PASSWORD=\n+AUTH_KEY=\n+DB_PASSWORD=${DB_PASSWORD}\n"), []);
});

test("REQ-046 a pasted database password and a GitHub token are found", () => {
  assert.strictEqual(scanText("+DB_PASSWORD=hunter2hunter2\n").length, 1);
  assert.strictEqual(scanText("token ghp_" + "a".repeat(36)).length, 1);
});

test("REQ-065 an allowlisted plugin missing from the register is reported", () => {
  const allow = { plugins: [{ package: "woocommerce/action-scheduler" }, { package: "wpackagist-plugin/some-new-plugin" }] };
  const register = "| Background jobs | Action Scheduler | Chosen |";
  assert.deepStrictEqual(missingFromRegister(allow, register), ["some-new-plugin"]);
});

test("REQ-065 an empty allowlist has nothing missing", () => {
  assert.deepStrictEqual(missingFromRegister({ plugins: [] }, ""), []);
});
