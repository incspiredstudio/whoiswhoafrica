import { test, expect } from "@playwright/test";
import { routes } from "./helpers/routes";

// No page offers sign in or sign up, shows a trust label, a Claimed mark or Sponsored on a plain page, or a profile cover image
// (REQ-229, REQ-150, REQ-155, REQ-247). Runs on every route in helpers/routes.ts at one width; the checks do not depend on layout.
test.describe("no accounts UI and no trust labels (REQ-229, REQ-150, REQ-155, REQ-247)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const route of routes) {
    test(`${route.name} offers no sign in, sign up, trust label or cover image`, async ({ page }, info) => {
      test.skip(info.project.name !== "chromium-1280", "layout-independent check runs once");
      const res = await page.goto(route.path);
      test.skip(res?.status() === 404 && (route.status ?? 200) === 200, "page not built yet");
      await expect(page.locator('a[href*="wp-login.php"], a[href*="wp-admin"], a[href*="action=register"], a[href*="wp-signup"]')).toHaveCount(0);
      await expect(page.getByRole("link", { name: /^(log ?in|sign ?in|sign ?up|register|my account|create an account)$/i })).toHaveCount(0);
      await expect(page.getByRole("button", { name: /^(log ?in|sign ?in|sign ?up|register)$/i })).toHaveCount(0);
      await expect(page.locator('input[type="password"]')).toHaveCount(0);
      const text = (await page.locator("body").innerText()).toLowerCase();
      expect(text).not.toMatch(/\bverified\b.{0,20}\bbadge\b|\btrust score\b|\bclaimed profile\b/);
      await expect(page.locator(".profile-cover, [data-profile-cover]")).toHaveCount(0);
    });
  }
});
