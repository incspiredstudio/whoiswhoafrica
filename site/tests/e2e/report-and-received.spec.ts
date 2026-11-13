import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Report dialog, the report page, the received page with a reference and the expired link page (M1-COR-16; REQ-090,
// REQ-233, REQ-157). Needs WIW_THEME_READY=1 and a reviewed profile slug in WIW_PROFILE_SLUG.
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("report dialog and received pages", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the report page for an unknown slug is a 404", async ({ page }) => {
    const res = await page.goto("/report/no-such-profile-here/");
    expect(res?.status()).toBe(404);
    await expect(page).toHaveTitle(/^Page not found/);
  });

  test("the report link opens a dialog that traps focus, closes on Escape and returns focus", async ({ page }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    await page.goto(`/profile/${slug}/`);
    const opener = page.locator("[data-report-open]");
    await expect(opener).toHaveAttribute("href", new RegExp(`/report/${slug}/$`));
    await opener.focus();
    await page.keyboard.press("Enter");
    const dialog = page.locator("dialog#report-dialog");
    await expect(dialog).toBeVisible();
    await expect(dialog.getByRole("heading", { name: "Report this profile" })).toBeVisible();
    for (const name of ["reason", "details", "email", "wiw_form_token"]) {
      await expect(dialog.locator(`[name=${name}]`)).toHaveCount(1);
    }
    // Tab never leaves the dialog.
    for (let i = 0; i < 12; i++) {
      await page.keyboard.press("Tab");
      expect(await page.evaluate(() => !!document.activeElement?.closest("dialog#report-dialog"))).toBe(true);
    }
    await expectNoA11yViolations(page);
    await page.keyboard.press("Escape");
    await expect(dialog).toBeHidden();
    await expect(opener).toBeFocused();
  });

  test("without JavaScript the report link leads to the report page with the form", async ({ browser }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto(`/profile/${slug}/`);
    await page.locator("[data-report-open]").click();
    await expect(page).toHaveURL(new RegExp(`/report/${slug}/$`));
    await expect(page.locator("h1")).toHaveText("Report this profile");
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.getByRole("button", { name: "Send report" })).toBeVisible();
    await context.close();
  });

  test("a refused report shows an alert and no success state", async ({ page }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    await page.goto(`/report/${slug}/`);
    await page.locator("form.form button[type=submit]").click();
    await expect(page.locator(".errbox[role=alert]")).toBeVisible();
    await expect(page.locator(".done")).toHaveCount(0);
  });

  test("the received page shows the reference and the promise, and is not indexed", async ({ page }) => {
    const res = await page.goto("/done/?kind=report&ref=REP-42");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveText("Report received");
    await expect(page.locator(".done")).toContainText("REP-42");
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("an expired or used link shows the expired-or-used page", async ({ page }) => {
    await page.goto("/link/AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA/");
    await expect(page.locator("main")).toContainText(/expired|already been used/i);
    await expect(page.locator("form[method=post]")).toHaveCount(0);
  });

  test("the profile page with the report panel passes axe at this width", async ({ page }) => {
    test.skip(!slug, "set WIW_PROFILE_SLUG");
    await page.goto(`/profile/${slug}/`);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
});
