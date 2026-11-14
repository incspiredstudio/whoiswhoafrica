import { test, expect } from "@playwright/test";
import { disableScripts } from "./helpers/page";

// REQ-198: the home page's nominee group chips, as in the prototype. They filter without a reload, say how many are shown, are
// buttons a keyboard can reach and press, and stay hidden without scripts (the grid then shows its first eight). Skips when the home
// page has no live nominees or only one group.
test.describe("home nominee group chips", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("a chip filters the grid without a reload and All brings back the first eight", async ({ page }, testInfo) => {
    test.skip(testInfo.project.name === "chromium-360", "the prototype hides the chips at phone width");
    await page.goto("/");
    const bar = page.locator(".h-nom-filter");
    test.skip((await bar.count()) === 0, "no nominee groups on this site");
    await expect(bar).toBeVisible();
    const items = page.locator(".h-noms .hn-item");
    const firstEight = await page.locator(".h-noms .hn-item:not([data-nom-extra])").count();
    const chip = bar.locator("[data-nom-filter]:not([data-nom-filter='all'])").first();
    const group = (await chip.getAttribute("data-nom-filter")) as string;
    const url = page.url();
    await chip.focus();
    await page.keyboard.press("Enter");
    await expect(chip).toHaveAttribute("aria-pressed", "true");
    expect(page.url()).toBe(url);
    const visible = page.locator(".h-noms .hn-item:not([hidden])");
    expect(await visible.count()).toBeGreaterThan(0);
    for (const g of await visible.evaluateAll((els) => els.map((e) => e.getAttribute("data-group")))) {
      expect(g).toBe(group);
    }
    await expect(page.locator("[data-nom-status]")).toHaveText(/nominees? shown/);
    await bar.locator("[data-nom-filter='all']").click();
    await expect(page.locator(".h-noms .hn-item:not([hidden])")).toHaveCount(firstEight);
    expect(await items.count()).toBeGreaterThanOrEqual(firstEight);
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("the chips stay hidden and the first eight show", async ({ page }) => {
      await page.goto("/");
      test.skip((await page.locator(".h-noms").count()) === 0, "no nominees on this site");
      await expect(page.locator(".h-nom-filter")).toBeHidden();
      expect(await page.locator(".h-noms .hn-item:not([hidden])").count()).toBeLessThanOrEqual(8);
    });
  });
});
