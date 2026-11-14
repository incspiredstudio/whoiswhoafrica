import { test, expect } from "@playwright/test";

// Two-factor in a browser (REQ-047): available and recommended for staff, not enforced (owner decision 2026-11-14, D-070). A staff
// user without a second factor signs in with the password and reaches the admin screens; the set-up section is on their profile.
// Needs E2E_USER and E2E_PASS for a staff user who has NOT set up two-factor.
const { E2E_USER, E2E_PASS } = process.env;

test.describe("two-factor is optional (REQ-047)", () => {
  test("a visitor is sent to sign in, not to the admin", async ({ page }) => {
    await page.goto("/wordpress/wp-admin/index.php");
    await expect(page).toHaveURL(/wp-login\.php/);
  });

  test("a staff user without a second factor reaches the admin and can switch it on", async ({ page }) => {
    test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");
    await page.goto("/wp-login.php");
    await page.fill("#user_login", E2E_USER as string);
    await page.fill("#user_pass", E2E_PASS as string);
    await page.click("#wp-submit");
    await page.waitForLoadState("domcontentloaded");

    for (const screen of ["/wordpress/wp-admin/index.php", "/wordpress/wp-admin/edit.php"]) {
      await page.goto(screen);
      await expect(page, screen).not.toHaveURL(/profile\.php/);
      await expect(page.locator("#wpbody")).toBeVisible();
    }
    // The set-up section, with the recovery codes option, is available on the profile.
    await page.goto("/wordpress/wp-admin/profile.php");
    await expect(page.locator("#two-factor-options")).toBeVisible();
  });
});
