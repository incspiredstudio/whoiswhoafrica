import { test, expect } from "@playwright/test";
import { staffLogin } from "./helpers/staff-login";

// Settings > Required plugins (REQ-056, REQ-240): an Administrator sees each plugin, its status and the right button, and the
// reminder notice links to the screen while a required plugin is missing. Nothing is installed here: CI has no network.
test.describe("Required plugins screen", () => {
  test.skip(!process.env.WIW_E2E_TEST_MODE || !process.env.E2E_USER, "needs a seeded staff user (WIW_E2E_TEST_MODE, E2E_USER)");

  test("REQ-056 the screen lists FluentSMTP, wpDiscuz and Two Factor with their status", async ({ page, baseURL }) => {
    await staffLogin(page, baseURL ?? "");
    await page.goto("/wordpress/wp-admin/admin.php?page=wiw-required-plugins");
    await expect(page.getByRole("heading", { name: "Required plugins", level: 1 })).toBeVisible();
    for (const slug of ["fluent-smtp", "wpdiscuz", "two-factor"]) {
      const row = page.locator(`#wiw-plugin-${slug}`);
      await expect(row).toBeVisible();
      const status = await row.getAttribute("data-status");
      expect(["not_installed", "installed", "active", "included"]).toContain(status);
      if (status === "not_installed") {
        await expect(row.getByRole("button", { name: /^Install / })).toBeVisible();
      } else if (status === "installed") {
        await expect(row.getByRole("button", { name: /^Activate / })).toBeVisible();
      }
    }
  });

  test("REQ-056 the reminder links to the screen while a required plugin is missing", async ({ page, baseURL }) => {
    await staffLogin(page, baseURL ?? "");
    await page.goto("/wordpress/wp-admin/admin.php?page=wiw-required-plugins");
    const missing = await page.locator('#wiw-plugin-fluent-smtp:not([data-status="active"]), #wiw-plugin-wpdiscuz:not([data-status="active"])').count();
    await page.goto("/wordpress/wp-admin/index.php");
    const notice = page.locator("#wiw-required-plugins-notice");
    if (missing === 0) {
      await expect(notice).toHaveCount(0);
      return;
    }
    await expect(notice).toBeVisible();
    await expect(notice.getByRole("link", { name: "Set up the plugins" })).toHaveAttribute("href", /page=wiw-required-plugins/);
  });
});
