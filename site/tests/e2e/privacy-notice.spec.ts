import { test, expect } from "@playwright/test";

// Every public form that collects personal data shows a privacy notice link at the point of collection, and a consent form shows
// an unticked, required tick with the policy version (REQ-058, REQ-081). The forms arrive with their own features (claim, Get
// listed, enquiry, data rights, newsletter), so this spec checks whichever POST forms the pages below carry today and keeps
// guarding the new ones. The template rule is tests/Integration/PrivacyFormPartsTest.php.
const pages = ["/", "/events/", "/privacy/", "/cookies/", "/contact/", "/get-listed/", "/claim/", "/newsletter/"];

test.describe("privacy notice on forms", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const path of pages) {
    test(`${path}: each personal-data form has a privacy notice link`, async ({ page }) => {
      const res = await page.goto(path);
      test.skip(!res || res.status() >= 400, "page does not exist yet");
      const forms = page.locator("form[method='post']:not([data-personal-data='none'])");
      for (const form of await forms.all()) {
        await expect(form.locator(".privacy-notice a[href$='/privacy/']")).toHaveCount(1);
        const tick = form.locator("input[type='checkbox'][name*='consent']");
        if ((await tick.count()) > 0) {
          await expect(tick.first()).not.toBeChecked();
          await expect(tick.first()).toHaveAttribute("required", "");
          await expect(form.locator("input[type='hidden'][name='policy_version']")).toHaveCount(1);
        }
      }
    });
  }
});
