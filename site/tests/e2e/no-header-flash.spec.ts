import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("header state before first paint (REQ-046)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the home page has is-home and at-top set by the inline script", async ({ page }) => {
    await page.goto("/");
    const cls = await page.evaluate(() => document.documentElement.className);
    expect(cls).toContain("is-home");
    expect(cls).toContain("at-top");
  });

  test("the inline script runs under the CSP without a violation", async ({ page }) => {
    const violations: string[] = [];
    page.on("console", (m) => {
      if (/content security policy/i.test(m.text())) violations.push(m.text());
    });
    await page.goto("/");
    expect(violations).toEqual([]);
    // Browsers hide the nonce value from the DOM; the classes above being set proves the script carried the nonce.
    expect(await page.locator("script[data-home]").count()).toBe(1);
  });
});
