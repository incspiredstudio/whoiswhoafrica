import { expect, type Page } from "@playwright/test";

/**
 * Signs in a seeded test staff user, including the two-factor step. TEST ENVIRONMENT ONLY.
 * It refuses unless WIW_E2E_TEST_MODE=1 and the base URL is local, so it cannot run against production.
 * The matching server-side hook is built in M0-PLG-26 and exists only when WIW_E2E_TEST_MODE is set in the secrets file.
 * Credentials come from E2E_USER, E2E_PASS and (if asked) E2E_2FA_CODE; nothing is committed.
 */
export async function staffLogin(page: Page, baseURL: string): Promise<void> {
  const host = new URL(baseURL).hostname;
  if (process.env.WIW_E2E_TEST_MODE !== "1" || !["localhost", "127.0.0.1"].includes(host)) {
    throw new Error("staffLogin is refused: it needs WIW_E2E_TEST_MODE=1 and a local base URL");
  }
  const { E2E_USER, E2E_PASS, E2E_2FA_CODE } = process.env;
  if (!E2E_USER || !E2E_PASS) throw new Error("E2E_USER and E2E_PASS are required");
  await page.goto("/wp-login.php");
  await page.fill("#user_login", E2E_USER);
  await page.fill("#user_pass", E2E_PASS);
  await page.click("#wp-submit");
  const code = page.locator("input[name='authcode'], input[name='two-factor-code']").first();
  if (await code.isVisible({ timeout: 2000 }).catch(() => false)) {
    if (!E2E_2FA_CODE) throw new Error("Two-factor prompt shown but E2E_2FA_CODE is not set");
    await code.fill(E2E_2FA_CODE);
    await page.keyboard.press("Enter");
  }
  await expect(page).toHaveURL(/wp-admin/);
}
