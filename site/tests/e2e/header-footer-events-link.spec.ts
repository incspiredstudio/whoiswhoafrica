import { test, expect } from "@playwright/test";

// The Events link is a plain link (not a mega menu group) in the header, the phone drawer and the footer (REQ-264).
// The markup is also asserted in tests/Integration/HeaderTest.php.
test.describe("Events link (REQ-264)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("header, drawer and footer each link to /events/", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("nav.nav-top a.nav-link[href$='/events/']")).toHaveCount(1);
    await expect(page.locator("#drawer a.drawer-link[href$='/events/']")).toHaveCount(1);
    await expect(page.locator("footer.site-foot a[href$='/events/']")).toHaveCount(1);
    // A plain link, not a menu button.
    await expect(page.locator("nav.nav-top button", { hasText: "Events" })).toHaveCount(0);
  });

  test("the drawer link is reachable on a phone", async ({ page }) => {
    const viewport = page.viewportSize();
    test.skip(!viewport || viewport.width >= 1024, "drawer is for phones and tablets");
    await page.goto("/");
    await page.locator("header.site-head .menu-btn").click();
    await expect(page.locator("#drawer a.drawer-link[href$='/events/']")).toBeVisible();
  });
});
