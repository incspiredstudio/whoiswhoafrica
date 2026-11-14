import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The profile page with the contract 1.1 narrative sections, the image credit and the "When shared" panel (REQ-003, REQ-162,
// REQ-006). Needs a public profile imported from a 1.1 bundle with sections and an open-licence image: set WIW_V11_PROFILE_SLUG
// (for example an organisation from the fixture in tests/fixtures/import/v11 or the sample-50 bundle).
const slug = process.env.WIW_V11_PROFILE_SLUG;

for (const width of [360, 1280]) {
  test.describe(`profile sections at ${width}px`, () => {
    test.skip(!process.env.WIW_THEME_READY || !slug, "needs a public profile with sections (set WIW_V11_PROFILE_SLUG)");
    test.use({ viewport: { width, height: 900 } });

    test("Bio, Achievement, Influence and Legacy render as headed sections with paragraphs (REQ-003)", async ({ page }) => {
      const res = await page.goto(`/profile/${slug}/`);
      expect(res?.status()).toBe(200);
      const headings = page.locator(".profile-main .profile-section > h2");
      const titles = (await headings.allInnerTexts()).map((t) => t.trim());
      const order = ["Bio", "Achievement", "Influence", "Legacy"].filter((t) => titles.includes(t));
      expect(order.length).toBeGreaterThan(0);
      expect(titles.filter((t) => order.includes(t))).toEqual(order);
      const bio = page.locator("#bio .profile-prose p");
      if (await bio.count()) {
        const box = await page.locator("#bio .profile-prose").boundingBox();
        // A readable measure: never wider than about 70 characters.
        expect(box?.width ?? 0).toBeLessThanOrEqual(760);
      }
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
    });

    test("one related-stories block at most and the When shared panel in the rail (REQ-028, REQ-006)", async ({ page }) => {
      await page.goto(`/profile/${slug}/`);
      expect(await page.locator("#stories, #related-stories").count()).toBeLessThanOrEqual(1);
      await expect(page.locator(".profile-rail .rail-share h2")).toHaveText("When shared");
      await expect(page.locator(".profile-rail .rail-share strong")).not.toHaveText("");
    });

    test("an image carries a visible credit line with its licence (REQ-162)", async ({ page }) => {
      await page.goto(`/profile/${slug}/`);
      const credit = page.locator(".profile-credit");
      test.skip((await credit.count()) === 0, "this profile has no image");
      await expect(credit).toBeVisible();
      await expect(credit).toContainText(/Photo:|Logo:/);
      await page.screenshot({ path: `test-results/profile-sections-${width}.png`, fullPage: true });
    });
  });
}
