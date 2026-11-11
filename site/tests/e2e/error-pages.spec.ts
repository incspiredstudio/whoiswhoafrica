import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1). 410 and 500 are covered by tests/Integration/SystemPagesTest.php:
// they need a plugin-marked removed profile and a fatal error, which the browser suite cannot create yet.
test.describe("error pages (REQ-121, REQ-209)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("404 has navigation, search and directory links and no technical detail", async ({ page }) => {
    const res = await page.goto("/?p=99999999");
    expect(res?.status()).toBe(404);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("header")).toHaveCount(1);
    await expect(page.locator("footer")).toHaveCount(1);
    await expect(page.locator('main a[href$="/directory/"]')).toHaveCount(1);
    await expect(page.locator("main form[role=search], main form.search-form")).toHaveCount(1);
    const text = (await page.locator("body").innerText()).toLowerCase();
    expect(text).not.toMatch(/stack|\.php|fatal|warning/);
    await expect(page.locator('meta[name="robots"][content*="noindex"]')).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
});
