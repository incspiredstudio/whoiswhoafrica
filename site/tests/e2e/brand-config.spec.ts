import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
// The brand strings themselves are checked by tests/Unit/BrandConfigTest.php; header and footer wording is asserted
// in the header and footer specs once those templates read wiw_brand().
test.describe("brand configuration (REQ-052)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the home page renders with the child theme active", async ({ page }) => {
    const res = await page.goto("/");
    expect(res?.ok()).toBeTruthy();
    expect((await page.title()).length).toBeGreaterThan(0);
  });
});
