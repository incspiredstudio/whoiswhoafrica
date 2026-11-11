import { test, expect } from "@playwright/test";

// Runs against a site whose active theme is whoiswho or whoiswho-child (set WIW_THEME_READY=1).
test.describe("theme structure (REQ-273, REQ-044)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("home loads without emoji or embed scripts", async ({ page }) => {
    const res = await page.goto("/");
    expect(res?.ok()).toBeTruthy();
    const html = await page.content();
    expect(html).not.toContain("wp-emoji");
    expect(html).not.toContain("wp-embed");
  });
});
