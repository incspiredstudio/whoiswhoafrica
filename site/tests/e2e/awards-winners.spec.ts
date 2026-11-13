import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, setReducedMotion } from "./helpers/page";

// The winners page (REQ-246, REQ-033, REQ-034). Needs WIW_THEME_READY=1. The filter bar and the empty state work on any site;
// the confetti checks run only when the site has public winners.
test.describe("awards winners page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("filter bar with every filter, the count and no horizontal scroll", async ({ page }) => {
    const res = await page.goto("/awards/winners/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    const bar = page.getByRole("search", { name: "Filter winners" }).or(page.locator("form[aria-label='Filter winners']"));
    await expect(bar).toBeVisible();
    for (const name of ["q", "year", "group", "category", "type", "country", "sort"]) {
      await expect(bar.locator(`[name='${name}']`)).toHaveCount(1);
    }
    await expect(bar.locator("[role=status]")).toContainText(/winners?/);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("filters live in the address, are kept in the form and offer Clear", async ({ page }) => {
    await page.goto("/awards/winners/?q=zzzz-nobody&sort=name&type=organisation");
    const bar = page.locator("form[aria-label='Filter winners']");
    await expect(bar.locator("input[name=q]")).toHaveValue("zzzz-nobody");
    await expect(bar.locator("select[name=sort]")).toHaveValue("name");
    await expect(bar.locator("select[name=type]")).toHaveValue("organisation");
    await expect(page.getByText("No winners match")).toBeVisible();
    await page.getByRole("link", { name: "Clear" }).first().click();
    await expect(page).toHaveURL(/\/awards\/winners\/?$/);
  });

  test("an unknown filter value is dropped, not trusted", async ({ page }) => {
    const res = await page.goto("/awards/winners/?year=abc&group=%3Cscript%3E&sort=evil");
    expect(res?.status()).toBe(200);
    await expect(page.locator("select[name=sort]")).toHaveValue("newest");
    await expect(page.locator("script", { hasText: "alert" })).toHaveCount(0);
  });

  test("confetti plays once on load", async ({ page }) => {
    await page.goto("/awards/winners/");
    const state = await page.locator(".win-body").getAttribute("data-confetti");
    test.skip(state === "none", "no public winners on this site");
    await expect(page.locator(".win-body")).toHaveAttribute("data-confetti", /playing|done/);
    await expect(page.locator(".win-body")).toHaveAttribute("data-confetti", "done", { timeout: 10000 });
    expect(await page.locator("canvas.win-confetti").count()).toBe(0);
  });

  test("confetti does not play when reduced motion is requested", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/awards/winners/");
    const state = await page.locator(".win-body").getAttribute("data-confetti");
    test.skip(state === "none", "no public winners on this site");
    await expect(page.locator(".win-body")).toHaveAttribute("data-confetti", "skipped");
    expect(await page.locator("canvas.win-confetti").count()).toBe(0);
  });
});
