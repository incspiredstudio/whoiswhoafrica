import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll, setReducedMotion } from "./helpers/page";

// The home awards marquee (M2-HOM-11; REQ-198, REQ-001). Needs WIW_THEME_READY=1 and at least one award category (set
// WIW_HAS_CATEGORIES=1 once the site has some; the marquee is absent, not empty, when there are none).
test.describe("home awards marquee", () => {
  test.skip(!process.env.WIW_THEME_READY || !process.env.WIW_HAS_CATEGORIES, "needs the theme and award categories");

  test("it shows the category names as text only: no heading, list numbers, link or button", async ({ page }) => {
    await page.goto("/");
    const strip = page.locator(".h-marquee");
    await expect(strip).toHaveCount(1);
    expect(await strip.locator(".h-marquee-list:not([aria-hidden]) li").count()).toBeGreaterThan(0);
    await expect(strip.locator("h1, h2, h3, h4, a, button, ol")).toHaveCount(0);
    await expect(page.locator(".home .h-awards, .home [class*='awards-band']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
  });

  test("with reduced motion the marquee does not move and every category is readable", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/");
    const track = page.locator(".h-marquee-track");
    expect(await track.evaluate((n) => getComputedStyle(n).animationName)).toBe("none");
    await expect(page.locator(".h-marquee-copy")).toBeHidden();
    await expect(page.locator(".h-marquee-list li").first()).toBeVisible();
  });

  test("with motion allowed it scrolls, and the copy that makes the loop is hidden from assistive technology", async ({ page }) => {
    await page.emulateMedia({ reducedMotion: "no-preference" });
    await page.goto("/");
    const track = page.locator(".h-marquee-track");
    expect(await track.evaluate((n) => getComputedStyle(n).animationName)).toBe("h-marquee");
    await expect(page.locator(".h-marquee-copy")).toHaveAttribute("aria-hidden", "true");
  });

  test("it has no horizontal scroll at 360 px", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await assertNoHorizontalScroll(page);
  });
});
