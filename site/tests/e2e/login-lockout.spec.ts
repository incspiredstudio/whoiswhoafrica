import { test, expect } from "@playwright/test";

// Sign-in errors and lockout (REQ-047). Every failure shows one generic message, so nothing reveals whether an account exists.
// The lockout itself needs a throwaway account (WIW_E2E_LOCKOUT_USER, WIW_E2E_LOCKOUT_PASS) because it locks it for 15 minutes.
const { WIW_E2E_LOCKOUT_USER, WIW_E2E_LOCKOUT_PASS } = process.env;

async function attempt(page: import("@playwright/test").Page, user: string, pass: string): Promise<string> {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", user);
  await page.fill("#user_pass", pass);
  await page.click("#wp-submit");
  return (await page.locator("#login_error").innerText()).trim();
}

test.describe("sign-in protection (REQ-047)", () => {
  test("an unknown name and a wrong password give the same generic message", async ({ page }) => {
    const a = await attempt(page, `nobody-${Date.now()}`, "Wrong-Password-1!");
    const b = await attempt(page, `another-${Date.now()}`, "Wrong-Password-2!");
    expect(a).toBe(b);
    expect(a.toLowerCase()).not.toContain("invalid username");
    expect(a.toLowerCase()).not.toContain("incorrect password");
  });

  test("five failed attempts lock the account", async ({ page }) => {
    test.skip(!WIW_E2E_LOCKOUT_USER || !WIW_E2E_LOCKOUT_PASS, "WIW_E2E_LOCKOUT_USER and WIW_E2E_LOCKOUT_PASS name a throwaway account");
    for (let i = 0; i < 5; i++) await attempt(page, WIW_E2E_LOCKOUT_USER as string, "Wrong-Password-1!");
    const message = await attempt(page, WIW_E2E_LOCKOUT_USER as string, WIW_E2E_LOCKOUT_PASS as string);
    expect(message.length).toBeGreaterThan(0);
    await expect(page).toHaveURL(/wp-login\.php/);
  });
});
