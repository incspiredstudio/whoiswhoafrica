import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs the core plugin active with at least one published profile (the CI install activates the plugin; profiles come from the
// import or from a local seed). Set WIW_PROFILE_SLUG to a published profile slug to run it. Server-side rules are in
// tests/Integration/ProfilePageTest.php (REQ-003, REQ-004, REQ-005, REQ-247, REQ-082).
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("profile page", () => {
  test.skip(!process.env.WIW_THEME_READY || !slug, "needs a published profile (set WIW_PROFILE_SLUG)");

  test("renders the header, one h1, sections and no cover, email or phone (REQ-003, REQ-247)", async ({ page }) => {
    const res = await page.goto(`/profile/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator(".profile-head")).toBeVisible();
    await expect(page.locator(".profile-cover, .banner, [class*='cover']")).toHaveCount(0);
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
    for (const link of await page.locator(".profile-main a[href^='http']").all()) {
      expect(await link.getAttribute("rel")).toContain("noopener");
    }
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("an unknown profile answers 404 (REQ-208)", async ({ page }) => {
    const res = await page.goto("/profile/this-profile-does-not-exist/");
    expect(res?.status()).toBe(404);
  });
});
