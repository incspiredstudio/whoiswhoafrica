import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll } from "./helpers/page";

// The home nominees grid (M2-HOM-11, M4-AWD-30; REQ-198, REQ-033, REQ-259). Needs WIW_THEME_READY=1. The block is absent, not empty,
// before nominees are public; with nominees it shows at most eight posters, the group filters on larger screens only, no awards band
// and no awards button. Each test runs in the state the site is in and skips the other.
test.describe("home nominees block", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("absent before nominees are public", async ({ page }) => {
    await page.goto("/");
    test.skip((await page.locator("#home-nominees").count()) > 0, "this site has public nominees");
    await expect(page.locator(".h-nominees")).toHaveCount(0);
    await expect(page.getByRole("heading", { name: "The nominees" })).toHaveCount(0);
  });

  test("present with at most eight nominee cards, and no awards band or button", async ({ page }) => {
    await page.goto("/");
    test.skip((await page.locator("#home-nominees").count()) === 0, "this site has no public nominees");
    const cards = page.locator("#home-nominees .nom-card");
    expect(await cards.count()).toBeGreaterThan(0);
    expect(await cards.count()).toBeLessThanOrEqual(8);
    await expect(page.getByRole("link", { name: "All nominees" })).toHaveAttribute("href", /\/awards\/nominees\//);
    await expect(page.locator(".home .h-awards, .home [class*='awards-band']")).toHaveCount(0);
    await expect(page.locator(".home a.btn[href$='/awards/'], .home a.btn[href*='/awards/winners'], .home a.btn[href*='/awards/nominees']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
  });

  test("group filters work on larger screens and are hidden on phones", async ({ page }, info) => {
    await page.goto("/");
    const bar = page.locator("#home-nominees .h-nominees-filter");
    test.skip((await bar.count()) === 0, "needs nominees from at least two groups");
    const width = page.viewportSize()?.width ?? 1280;
    if (width < 768) {
      await expect(bar).toBeHidden();
      return;
    }
    await expect(bar).toBeVisible();
    const buttons = bar.locator("button[data-group]");
    const total = await page.locator("#home-nominees .nom-card").count();
    await buttons.nth(1).click();
    await expect(buttons.nth(1)).toHaveAttribute("aria-pressed", "true");
    const visible = await page.locator("#home-nominees .nom-card:visible").count();
    expect(visible).toBeLessThan(total);
    await buttons.first().click();
    expect(await page.locator("#home-nominees .nom-card:visible").count()).toBe(total);
    info.annotations.push({ type: "width", description: String(width) });
  });
});
