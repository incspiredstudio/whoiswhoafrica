import { test, expect, type Page } from "@playwright/test";
import { runHelper } from "./helpers/staff-users";

// The "last email could not be sent" notice (issue 829, REQ-075): Administrators only, on the Dashboard and the Setup screen only,
// it says how long ago, and its Dismiss button clears it. Needs E2E_USER and E2E_PASS for an Administrator, and a local site
// (WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1) so the helper can set the flag.
const { E2E_USER, E2E_PASS } = process.env;
const admin = (path: string): string => "/wordpress/wp-admin/" + path;

async function login(page: Page): Promise<void> {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", E2E_USER as string);
  await page.fill("#user_pass", E2E_PASS as string);
  await page.click("#wp-submit");
  await expect(page).toHaveURL(/wp-admin/);
}

test.describe("mail error notice (REQ-075)", () => {
  test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");
  test.skip(!process.env.WIW_SECRETS_FILE || process.env.WIW_E2E_TEST_MODE !== "1", "needs a local site in test mode");

  test.afterEach(() => {
    runHelper("mail-error.php", "clear");
  });

  test("shows on the Dashboard with its age, not elsewhere, and Dismiss clears it", async ({ page }) => {
    test.setTimeout(120_000);
    runHelper("mail-error.php", "set");
    await login(page);

    await page.goto(admin("index.php"), { waitUntil: "domcontentloaded" });
    const notice = page.locator(".wiw-mail-notice");
    await expect(notice).toHaveCount(1);
    // Three hours ago as set, unless a send failed on this local site meanwhile (it has no mail server).
    await expect(notice).toContainText(/The last email could not be sent \(\d+ \w+ ago\)\./);

    await page.goto(admin("admin.php?page=wiw-setup"), { waitUntil: "domcontentloaded" });
    await expect(page.locator(".wiw-mail-notice")).toHaveCount(1);

    await page.goto(admin("edit.php"), { waitUntil: "domcontentloaded" });
    await expect(page.locator(".wiw-mail-notice")).toHaveCount(0);

    await page.goto(admin("index.php"), { waitUntil: "domcontentloaded" });
    await page.locator(".wiw-mail-notice button", { hasText: "Dismiss" }).click();
    await expect(page).toHaveURL(/wp-admin\/(index\.php)?/);
    await expect(page.locator(".wiw-mail-notice")).toHaveCount(0);
    expect(runHelper<{ flag: unknown }>("mail-error.php", "status").flag).toBe(false);
  });
});
