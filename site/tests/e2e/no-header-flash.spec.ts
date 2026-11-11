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

  test("the inline script carries a nonce", async ({ page }) => {
    await page.goto("/");
    const nonce = await page.locator("script[data-home]").getAttribute("nonce");
    expect(nonce === null ? "" : nonce).not.toBe("");
  });
});
