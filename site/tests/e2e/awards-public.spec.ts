import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Public Awards pages (REQ-033, REQ-198, REQ-064, REQ-200, REQ-266). Needs WIW_THEME_READY=1. WIW_AWARDS_CATEGORY_ID names a
// category; WIW_AWARDS_NOMINEE_REF a live nominee; WIW_AWARDS_HIDDEN_REF a nomination that is not live.
test.describe("awards public pages", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("overview: stage, categories and judging", async ({ page }) => {
    const res = await page.goto("/awards/");
    test.skip(res?.status() === 404, "the Awards page is not created on this site");
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator(".aw-steps [aria-current=step]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("rules: no public voting and payment never affects outcomes", async ({ page }) => {
    const res = await page.goto("/awards/rules/");
    test.skip(res?.status() === 404, "the rules page is not created on this site");
    await expect(page.getByText("There is no public voting")).toBeVisible();
    await expect(page.getByText("Payment never affects")).toBeVisible();
  });

  test("category", async ({ page }) => {
    const id = process.env.WIW_AWARDS_CATEGORY_ID;
    test.skip(!id, "no category sample");
    const res = await page.goto(`/awards/category/${id}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("nominees", async ({ page }) => {
    const res = await page.goto("/awards/nominees/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("nominee-404 for a nomination that is not live", async ({ page }) => {
    const ref = process.env.WIW_AWARDS_HIDDEN_REF;
    test.skip(!ref, "no hidden nominee sample");
    const res = await page.goto(`/awards/nominee/${ref}/`);
    expect(res?.status()).toBe(404);
    await expect(page.getByText("The nominator")).toHaveCount(0);
  });

  test("a nominee page never names the nominator", async ({ page }) => {
    const ref = process.env.WIW_AWARDS_NOMINEE_REF;
    test.skip(!ref, "no nominee sample");
    const res = await page.goto(`/awards/nominee/${ref}/`);
    expect(res?.status()).toBe(200);
    await expect(page.getByText(/nominated by/i)).toHaveCount(0);
  });
});
