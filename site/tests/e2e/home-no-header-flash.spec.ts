import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("home header has no flash (REQ-046)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the header is transparent over the hero from the first paint", async ({ page }) => {
    await page.goto("/");
    const bg = await page.locator(".site-head").evaluate((e) => getComputedStyle(e).backgroundColor);
    expect(bg).toMatch(/rgba\(0, 0, 0, 0\)|transparent/);
  });

  test("the header turns solid after scrolling past the hero, and inner pages are solid", async ({ page }) => {
    await page.goto("/");
    await page.evaluate(() => window.scrollTo(0, 900));
    await expect(page.locator("html")).not.toHaveClass(/at-top/);
    await page.goto("/about/");
    await expect(page.locator("html")).not.toHaveClass(/is-home/);
  });
});
