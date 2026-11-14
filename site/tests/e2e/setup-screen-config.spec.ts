import { test, expect } from "@playwright/test";

// The Setup screen's no-code configuration (REQ-056, REQ-075, REQ-081, REQ-047): the Email check points to FluentSMTP, the Turnstile
// keys can be saved and the secret is never shown again, and the two-factor line is advice. Needs E2E_USER and E2E_PASS for an
// Administrator. The Turnstile keys are removed again at the end.
const { E2E_USER, E2E_PASS } = process.env;

test.describe("Setup screen configuration (REQ-056)", () => {
  test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");

  test("email, spam protection and two-factor advice need no file edits", async ({ page }) => {
    test.setTimeout(120_000);
    await page.goto("/wp-login.php");
    await page.fill("#user_login", E2E_USER as string);
    await page.fill("#user_pass", E2E_PASS as string);
    await page.click("#wp-submit");
    await expect(page).toHaveURL(/wp-admin/);

    await page.goto("/wordpress/wp-admin/admin.php?page=wiw-setup", { waitUntil: "domcontentloaded" });
    const email = page.locator("#wiw-item-email");
    await expect(email).toContainText("FluentSMTP");
    await expect(email).not.toContainText("wp-config");
    await expect(page.locator("#wiw-item-two_factor")).toContainText("optional");

    const spam = page.locator("#wiw-item-turnstile");
    await spam.locator("#wiw-turnstile-site-key").fill("0x4AAAAAAA-e2e-site");
    await spam.locator("#wiw-turnstile-secret").fill("0x4AAAAAAA-e2e-Secret-Value");
    await spam.getByRole("button", { name: "Save Turnstile keys" }).click();
    await expect(page.locator(".notice-success")).toContainText("Turnstile settings are saved");
    await expect(page.locator("body")).not.toContainText("e2e-Secret-Value");
    await expect(page.locator("#wiw-turnstile-secret")).toHaveValue("");
    await expect(page.locator("#wiw-turnstile-secret-note")).toContainText("Saved. It is not shown again.");

    await page.locator("input[name='turnstile_remove']").check();
    await page.getByRole("button", { name: "Save Turnstile keys" }).click();
    await expect(page.locator("#wiw-turnstile-secret-note")).toContainText("Not saved yet.");
  });
});
