import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("home header has no flash (REQ-046)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the header is transparent over the hero from the first paint", async ({ page }) => {
    await page.goto("/");
    // A hovered header is solid by design, and a headless browser can start with the pointer at 0,0, over the header. Move it
    // away, then wait for the header to settle (it fades out over a short transition).
    await page.mouse.move(600, 600);
    await expect
      .poll(() => page.locator(".site-head").evaluate((e) => getComputedStyle(e).backgroundColor), { timeout: 5000 })
      .toMatch(/rgba\(0, 0, 0, 0\)|transparent|\/ 0\)/);
  });

  test("the header turns solid after scrolling past the hero, and inner pages are solid", async ({ page }) => {
    await page.goto("/");
    await page.evaluate(() => window.scrollTo(0, 900));
    await expect(page.locator("html")).not.toHaveClass(/at-top/);
    await page.goto("/about/");
    await expect(page.locator("html")).not.toHaveClass(/is-home/);
  });
});
