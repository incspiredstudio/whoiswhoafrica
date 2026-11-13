import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The correction and removal forms (M1-COR-15; REQ-157, REQ-156, REQ-122). Needs a site whose active theme is whoiswho-child
// (WIW_THEME_READY=1) and a reviewed profile slug in WIW_PROFILE_SLUG. Unknown slugs are always checked.
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("correction and removal forms", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const kind of ["correct", "remove"]) {
    test(`/${kind}/ with an unknown slug is a 404 titled Page not found`, async ({ page }) => {
      const res = await page.goto(`/${kind}/no-such-profile-here/`);
      expect(res?.status()).toBe(404);
      await expect(page).toHaveTitle(/^Page not found/);
      await expect(page.locator(`form[action*='/${kind}/']`)).toHaveCount(0);
    });
  }

  test("the correction form has its fields, a challenge slot, a token, noindex and works without JavaScript", async ({ browser }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const res = await page.goto(`/correct/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    for (const name of ["relationship", "need", "field", "new_value", "evidence", "name", "email", "wiw_form_token", "wiw_website"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    await expect(page.locator("form a[href*='/privacy/']")).toHaveCount(1);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.getByRole("button", { name: "Send request" })).toBeVisible();
    await context.close();
  });

  test("the removal form has its fields and a removal button", async ({ page }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    const res = await page.goto(`/remove/${slug}/`);
    expect(res?.status()).toBe(200);
    for (const name of ["relationship", "reason", "name", "email", "wiw_form_token"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.getByRole("button", { name: "Request removal" })).toBeVisible();
  });

  for (const kind of ["correct", "remove"]) {
    test(`a refused ${kind} submission shows an alert, keeps the form and creates no success state`, async ({ page }) => {
      test.skip(!slug, "set WIW_PROFILE_SLUG");
      await page.goto(`/${kind}/${slug}/`);
      await page.locator("form.form button[type=submit]").click();
      await expect(page).toHaveURL(new RegExp(`/${kind}/${slug}/`));
      await expect(page.locator(".errbox[role=alert]")).toBeVisible();
      await expect(page.locator("form.form")).toHaveCount(1);
      await expect(page.locator(".done")).toHaveCount(0);
      // Each required field that failed is flagged and described.
      await expect(page.locator("form.form [aria-invalid=true]").first()).toBeVisible();
    });
  }

  for (const kind of ["correct", "remove"]) {
    for (const width of [360, 1280]) {
      test(`the ${kind} form passes axe with no horizontal scroll at ${width}px`, async ({ page }) => {
        test.skip(!slug, "set WIW_PROFILE_SLUG");
        await page.setViewportSize({ width, height: 900 });
        await page.goto(`/${kind}/${slug}/`);
        await assertNoHorizontalScroll(page);
        await expectNoA11yViolations(page);
      });
    }
  }
});
