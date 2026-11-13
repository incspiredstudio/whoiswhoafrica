// REQ-041. Run with: node --test site/tests/e2e/perf/discover.test.mjs (needs --experimental-strip-types on older Node).
import test from "node:test";
import assert from "node:assert";
import { firstMatch, profilePattern, storyPattern } from "./discover.ts";

test("REQ-041 picks the first profile link and ignores navigation links", () => {
  const html = '<a href="/directory/">Directory</a><a href="/directory/jane-doe/">J</a><a href="https://x.test/profile/a/b/">P</a>';
  assert.strictEqual(firstMatch(html, profilePattern), "/directory/jane-doe/");
});

test("REQ-041 skips story type and pagination links", () => {
  const html = '<a href="/stories/type/interview/">t</a><a href="/stories/page/2/">2</a><a href="/stories/my-story/">s</a>';
  assert.strictEqual(firstMatch(html, storyPattern), "/stories/my-story/");
});

test("REQ-041 returns null when the list is empty", () => {
  assert.strictEqual(firstMatch("<p>none</p>", storyPattern), null);
});
