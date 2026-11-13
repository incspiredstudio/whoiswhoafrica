import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Your data rights page (M3-PRV-11; REQ-059, REQ-058). Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1)
// and the seeded legal pages. The submission rules are in the plugin's tests; here the page, the refusal and the done state.
test.describe("your data rights page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("shows the request form and points to the profile forms", async ({ page }) => {
    const res = await page.goto("/data-rights/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    for (const name of ["kind", "name", "email", "details", "wiw_form_token", "wiw_website"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    await expect(page.locator("form a[href*='/privacy/']")).toHaveCount(1);
    await expect(page.locator(".form-aside a[href*='/directory/']")).toHaveCount(1);
    await expect(page.locator(".form-aside a[href*='/complaints/']")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a refused request keeps the page, shows an alert and no success state", async ({ page }) => {
    await page.goto("/data-rights/");
    await page.locator("form [name=kind]").selectOption("access");
    await page.locator("form [name=name]").fill("Ada Test");
    await page.locator("form [name=email]").fill("not-an-email");
    await page.locator("form [name=details]").fill("Please send me what you hold.");
    await page.getByRole("button", { name: "Send request" }).click();
    await expect(page.locator(".errbox[role=alert]")).toBeVisible();
    await expect(page.locator("form [name=name]")).toHaveValue("Ada Test");
    await expect(page.getByRole("heading", { name: "Request received" })).toHaveCount(0);
  });

  test("a valid request ends on the done page with a reference and the confirmation step", async ({ page }) => {
    await page.goto("/data-rights/");
    await page.locator("form [name=kind]").selectOption("access");
    await page.locator("form [name=name]").fill("Ada Test");
    await page.locator("form [name=email]").fill(`ada.${Date.now()}@example.test`);
    await page.locator("form [name=details]").fill("Please send me what you hold about me.");
    await page.waitForTimeout(3500); // the form's own minimum fill time
    await page.getByRole("button", { name: "Send request" }).click();
    await expect(page).toHaveURL(/\/done\/\?.*kind=data_rights/);
    await expect(page.getByRole("heading", { name: "Request received" })).toBeVisible();
    await expect(page.getByText(/confirmation email/)).toBeVisible();
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
  });
});
