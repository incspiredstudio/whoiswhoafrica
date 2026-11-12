import { test, expect } from "@playwright/test";
import { disableScripts, setReducedMotion } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1) and a built dist/ (npm run build).
test.describe("motion switches (REQ-043, REQ-041, REQ-044)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("with motion allowed the reveal class is set", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("html")).toHaveClass(/js-motion/);
    // Desktop browsers have a fine pointer: the smooth-scroll library loads and marks the page.
    if (await page.evaluate(() => window.matchMedia("(pointer: fine)").matches)) await expect(page.locator("html")).toHaveClass(/lenis/);
  });

  test("reduced motion: no reveals, no smooth scroll, page complete", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/");
    await expect(page.locator("h1")).toBeVisible();
    await expect(page.locator("html")).not.toHaveClass(/js-motion/);
    await expect(page.locator("html")).not.toHaveClass(/lenis/);
    expect(await page.evaluate(() => document.querySelectorAll("script[src*='lenis']").length)).toBe(0);
    const hidden = await page.evaluate(() => [...document.querySelectorAll("[data-reveal]")].filter((n) => getComputedStyle(n).opacity === "0").length);
    expect(hidden).toBe(0);
  });

  test("data saver: motion choreography is skipped", async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, "connection", { value: { saveData: true, effectiveType: "4g" }, configurable: true });
    });
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/js-motion/);
  });

  test("2G connection: motion choreography is skipped", async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, "connection", { value: { saveData: false, effectiveType: "2g" }, configurable: true });
    });
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/js-motion/);
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("every page stays readable and no reveal class is set", async ({ page }) => {
      await page.goto("/");
      await expect(page.locator("h1")).toBeVisible();
      await expect(page.locator("html")).not.toHaveClass(/js-motion/);
    });
  });
});
