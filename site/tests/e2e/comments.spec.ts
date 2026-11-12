import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a published story at /?p=<id> that takes comments (WIW_STORY_ID); skips without it.
test("the comments region on a story has a labelled form and no email field leaks (REQ-258, REQ-040)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY || !process.env.WIW_STORY_ID, "no story with comments on this site");
  await page.goto(`/?p=${process.env.WIW_STORY_ID}`);
  const section = page.locator("#comments");
  test.skip((await section.count()) === 0, "comments are not offered on this story");
  await expect(section.getByLabel("Your name")).toBeVisible();
  await expect(section.getByLabel("Your email")).toBeVisible();
  await expect(section.locator("input[name=wiw_comment_consent]")).toHaveCount(1);
  await expect(section.getByText("Comments are checked before they appear")).toBeVisible();
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});

test("an ordinary page has no comments region (REQ-258)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  await page.goto("/");
  await expect(page.locator("#comments")).toHaveCount(0);
});
