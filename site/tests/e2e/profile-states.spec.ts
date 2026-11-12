import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Set WIW_PROFILE_SLUG to a reviewed profile and WIW_PROFILE_PENDING_SLUG to one pending review. The staff preview banner needs a
// signed-in staff user and a route that serves non-public profiles to staff; it is covered in
// tests/Integration/ProfileStatesPageTest.php (REQ-216, REQ-208, REQ-209).
const reviewed = process.env.WIW_PROFILE_SLUG;
const pending = process.env.WIW_PROFILE_PENDING_SLUG;

test.describe("profile states", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("a pending profile shows the label and correct and remove links, not Reviewed on (REQ-216)", async ({ page }) => {
    test.skip(!pending, "set WIW_PROFILE_PENDING_SLUG");
    const res = await page.goto(`/profile/${pending}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator(".notice--pending")).toHaveCount(1);
    await expect(page.locator(`.notice--pending a[href$='/correct/${pending}/']`)).toHaveCount(1);
    await expect(page.locator(`.notice--pending a[href$='/remove/${pending}/']`)).toHaveCount(1);
    await expect(page.locator(".profile-reviewed")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a reviewed profile shows the Reviewed on date and no pending label (REQ-216)", async ({ page }) => {
    test.skip(!reviewed, "set WIW_PROFILE_SLUG");
    await page.goto(`/profile/${reviewed}/`);
    await expect(page.locator(".profile-reviewed time[datetime]")).toHaveCount(1);
    await expect(page.locator(".notice--pending")).toHaveCount(0);
    await expect(page.locator(".notice--preview")).toHaveCount(0);
    await expectNoA11yViolations(page);
  });

  test("a visitor gets 404 for a profile that does not exist (REQ-208)", async ({ page }) => {
    const res = await page.goto("/profile/no-such-profile-here/");
    expect(res?.status()).toBe(404);
  });
});
