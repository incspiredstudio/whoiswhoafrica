import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The claim page (REQ-251, REQ-252, REQ-156). Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1).
// A reviewed, unclaimed profile slug goes in WIW_CLAIM_SLUG for the form checks; an unknown slug is always checked.
test.describe("claim page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("an unknown slug is a 404", async ({ page }) => {
    const res = await page.goto("/claim/no-such-profile-here/");
    expect(res?.status()).toBe(404);
    await expect(page.locator("form[action*='/claim/']")).toHaveCount(0);
  });

  test("the form has the fields, the privacy link and works without JavaScript", async ({ browser }) => {
    const slug = process.env.WIW_CLAIM_SLUG;
    test.skip(!slug, "no claimable sample profile");
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const res = await page.goto(`/claim/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    for (const name of ["relationship", "name", "role", "work_email", "phone", "evidence", "consent"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    await expect(page.locator("form a[href*='/privacy/']")).toHaveCount(1);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.getByRole("button", { name: "Send claim" })).toBeVisible();
    await context.close();
  });

  test("the form page has no horizontal scroll and no axe violations", async ({ page }) => {
    const slug = process.env.WIW_CLAIM_SLUG;
    test.skip(!slug, "no claimable sample profile");
    await page.goto(`/claim/${slug}/`);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
});
