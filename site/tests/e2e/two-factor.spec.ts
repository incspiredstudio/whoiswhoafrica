import { test, expect } from "@playwright/test";

// Two-factor enforcement in a browser (REQ-047). A user without a second factor can sign in with the password but cannot reach any
// wp-admin screen: every one sends them to their profile, where the Two Factor plugin offers set-up. Needs E2E_USER and E2E_PASS
// for a staff user who has NOT set up two-factor, and WIW_E2E_TEST_MODE unset (test mode exempts the seeded user on purpose).
const { E2E_USER, E2E_PASS, WIW_E2E_TEST_MODE } = process.env;

test.describe("two-factor enforcement (REQ-047)", () => {
  test("a visitor is sent to sign in, not to the admin", async ({ page }) => {
    await page.goto("/wordpress/wp-admin/index.php");
    await expect(page).toHaveURL(/wp-login\.php/);
  });

  test("a staff user without a second factor cannot reach any admin screen", async ({ page }) => {
    test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");
    test.skip(WIW_E2E_TEST_MODE === "1", "test mode exempts the seeded user from two-factor");
    await page.goto("/wp-login.php");
    await page.fill("#user_login", E2E_USER as string);
    await page.fill("#user_pass", E2E_PASS as string);
    await page.click("#wp-submit");
    await page.waitForLoadState("domcontentloaded");

    for (const screen of ["/wordpress/wp-admin/index.php", "/wordpress/wp-admin/edit.php", "/wordpress/wp-admin/users.php", "/wordpress/wp-admin/options-general.php"]) {
      await page.goto(screen);
      await expect(page, screen).toHaveURL(/profile\.php/);
      await expect(page.locator(".notice-warning")).toContainText("two-factor");
    }
    // The set-up section, with the recovery codes option, is on the screen they land on.
    await expect(page.locator("#two-factor-options")).toBeVisible();
  });
});
