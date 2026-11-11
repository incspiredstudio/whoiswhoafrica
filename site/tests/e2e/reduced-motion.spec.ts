import { test, expect } from "@playwright/test";
import { setReducedMotion } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("reduced motion (REQ-043)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("with reduced motion the page is complete and nothing animates", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/");
    await expect(page.locator("h1")).toBeVisible();
    const behavior = await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior);
    expect(behavior).not.toBe("smooth");
    const dur = await page.evaluate(() => getComputedStyle(document.querySelector("a.skip") as Element).transitionDuration);
    expect(parseFloat(dur)).toBeLessThan(0.01);
  });
});
