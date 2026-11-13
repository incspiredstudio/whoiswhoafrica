import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The profile enquiry page (REQ-018, REQ-081, REQ-122). The form, off state and markup rules are in
// tests/Integration/ProfileEnquiryPageTest.php; the browser checks need a published profile (set WIW_PROFILE_SLUG).
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("profile enquiry", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("an enquiry address for an unknown profile answers 404 (REQ-018)", async ({ page }) => {
    const res = await page.goto("/enquire/this-profile-does-not-exist/");
    expect(res?.status()).toBe(404);
  });

  test("the enquiry page shows the form without owner contact details (REQ-018)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    const res = await page.goto(`/enquire/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
    await expect(page.locator("form[method=post] input[name=email]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the profile rail links to the enquiry page (REQ-018)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    await page.goto(`/profile/${slug}/`);
    await expect(page.locator(`.profile-rail a[href$='/enquire/${slug}/']`)).toHaveCount(1);
  });
});
