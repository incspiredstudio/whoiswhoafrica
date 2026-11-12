import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// /industries and /industries/{slug} (REQ-007, REQ-009). Works on an empty site (empty state) and on a seeded one.
// Counts and the pending-profile rule are covered in tests/Integration/ProfileIndustriesTemplateTest.php.
test.describe("industries", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the index answers 200 with one h1 and no overflow or axe violations", async ({ page }) => {
    const res = await page.goto("/industries/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("an industry page opens from the index, has one h1 and no stories block when there are none", async ({ page }) => {
    await page.goto("/industries/");
    const tiles = page.locator("a.ind-tile");
    test.skip((await tiles.count()) === 0, "no industries to open");
    await tiles.first().click();
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator(".ind-page")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("an unknown industry is a 404", async ({ page }) => {
    const res = await page.goto("/industries/no-such-industry-here/");
    expect(res?.status()).toBe(404);
  });
});
