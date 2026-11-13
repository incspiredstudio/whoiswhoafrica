import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The single story page (REQ-027, REQ-182, REQ-265, REQ-208). Rules for Sponsored, On the record, Related event and the staff
// preview are in tests/Integration/SingleStoryTemplateTest.php. The browser checks need a published story (set WIW_STORY_SLUG).
const slug = process.env.WIW_STORY_SLUG;

test.describe("single story", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("an unknown story answers 404 (REQ-208)", async ({ page }) => {
    const res = await page.goto("/stories/this-story-does-not-exist/");
    expect(res?.status()).toBe(404);
  });

  test("a story has one h1, a body, no horizontal scroll and passes axe (REQ-027)", async ({ page }) => {
    test.skip(!slug, "needs a published story (set WIW_STORY_SLUG)");
    const res = await page.goto(`/stories/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator(".article-body")).toBeVisible();
    await expect(page.locator(".crumbs")).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a story ends with more stories that link to other stories (REQ-029)", async ({ page }) => {
    test.skip(!slug, "needs a published story (set WIW_STORY_SLUG)");
    await page.goto(`/stories/${slug}/`);
    await expect(page.getByRole("heading", { name: "More stories" })).toBeVisible();
    await expect(page.locator(".story-more .scard")).toHaveCount(3);
  });

  test("the cover keeps a 16:9 crop when a story has one (REQ-027)", async ({ page }) => {
    test.skip(!slug, "needs a published story (set WIW_STORY_SLUG)");
    await page.goto(`/stories/${slug}/`);
    const media = page.locator(".article-cover .scard-media");
    if ((await media.count()) === 0) test.skip(true, "this story has no cover image");
    const box = await media.boundingBox();
    expect(box).not.toBeNull();
    expect(Math.abs((box!.width / box!.height) - 16 / 9)).toBeLessThan(0.05);
  });
});
