// Self tests for scripts/traceability.js (REQ-056). Run: node --test scripts/__tests__
const test = require("node:test");
const assert = require("node:assert");
const { parseRequirements, parsePlan, scanTestFile, parsePrs, build, render } = require("../traceability.js");

const REQS = `| REQ-001 | The site has pages. | 12.2 | M | SITE | Theme |
| REQ-002 | A thing nobody tested. | 12.2 | M | SITE | Theme |
| REQ-011 | **WITHDRAWN** (no checkout). ~~Pay online.~~ | 12.3 | Withdrawn | SITE | Custom plugin |
| REQ-083 | **WITHDRAWN (D-053): Native WordPress.** ~~Admin.~~ | 5 | M | SITE | Native |`;

const PLAN = JSON.stringify({
  milestone: "M0",
  issues: [
    { key: "M0-AAA-01", title: "First", reqs: ["REQ-001", "REQ-011"] },
    { key: "M0-AAA-02", title: "Second", reqs: ["REQ-999"] },
  ],
});

function model(tests, prs = []) {
  return build({
    requirements: parseRequirements(REQS),
    issues: parsePlan([PLAN], { "M0-AAA-01": 7 }),
    tests,
    prs: parsePrs(prs),
  });
}

test("requirements are read and withdrawn ones recognised", () => {
  const reqs = parseRequirements(REQS);
  assert.strictEqual(reqs.size, 4);
  assert.strictEqual(reqs.get("REQ-001").withdrawn, false);
  assert.strictEqual(reqs.get("REQ-011").withdrawn, true);
  assert.strictEqual(reqs.get("REQ-083").withdrawn, true);
});

test("PHPUnit names and spec titles give their requirement", () => {
  const php = scanTestFile("site/tests/FooTest.php", "public function test_REQ_001_pages_exist(): void {}\npublic function test_REQ_12_short(): void {}");
  assert.deepStrictEqual(php.map((t) => t.req), ["REQ-001", "REQ-012"]);
  const spec = scanTestFile("site/tests/e2e/a.spec.ts", 'test.describe("layout (REQ-044, REQ-040)", () => {\n  test("covers REQ-043", async () => {});\n});');
  assert.deepStrictEqual(spec.map((t) => t.req).sort(), ["REQ-040", "REQ-043", "REQ-044"]);
});

test("the table shows each requirement with its issue keys, pull requests and test names", () => {
  const tests = scanTestFile("site/tests/FooTest.php", "function test_REQ_001_pages_exist() {}");
  const md = render(model(tests, [{ number: 12, title: "Pages", body: "Closes #7. REQ-001" }]));
  const line = md.split("\n").find((l) => l.startsWith("| REQ-001 "));
  assert.match(line, /M0-AAA-01 \(#7\)/);
  assert.match(line, /#12/);
  assert.match(line, /test_REQ_001_pages_exist/);
});

test("a test that cites a withdrawn or missing requirement is listed as a problem", () => {
  const tests = [
    ...scanTestFile("site/tests/A.php", "function test_REQ_011_pay() {}"),
    ...scanTestFile("site/tests/B.php", "function test_REQ_500_ghost() {}"),
  ];
  const { problems } = model(tests);
  assert.ok(problems.testsCitingBadReq.some((p) => /test_REQ_011_pay.*withdrawn REQ-011/.test(p)), problems.testsCitingBadReq.join("\n"));
  assert.ok(problems.testsCitingBadReq.some((p) => /REQ-500.*does not exist/.test(p)));
});

test("an issue that cites a withdrawn or missing requirement is listed as a problem", () => {
  const { problems } = model([]);
  assert.ok(problems.issuesCitingBadReq.some((p) => /M0-AAA-01 cites the withdrawn REQ-011/.test(p)));
  assert.ok(problems.issuesCitingBadReq.some((p) => /M0-AAA-02 cites REQ-999/.test(p)));
});

test("a live requirement with no test is flagged, a withdrawn one is not", () => {
  const { problems } = model(scanTestFile("site/tests/FooTest.php", "function test_REQ_001_x() {}"));
  assert.deepStrictEqual(problems.withoutTest, ["REQ-002"]);
});

test("the output is deterministic whatever the input order", () => {
  const a = scanTestFile("site/tests/A.php", "function test_REQ_001_b() {}\nfunction test_REQ_001_a() {}");
  const b = [...a].reverse();
  assert.strictEqual(render(model(a)), render(model(b)));
  assert.ok(!/20\d\d-\d\d-\d\d/.test(render(model(a))), "no timestamps in the output");
});

test("many tests for one requirement are summarised", () => {
  const code = [1, 2, 3, 4, 5, 6].map((n) => `function test_REQ_001_case${n}() {}`).join("\n");
  const md = render(model(scanTestFile("site/tests/A.php", code)));
  assert.match(md.split("\n").find((l) => l.startsWith("| REQ-001 ")), /and 2 more/);
});
