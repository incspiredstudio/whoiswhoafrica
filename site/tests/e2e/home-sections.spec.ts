import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll, disableScripts, setReducedMotion } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("home acts, doors and closing call (REQ-001, REQ-002)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the doors link to the four directory lists", async ({ page }) => {
    await page.goto("/");
    const hrefs = await page.locator(".h-door").evaluateAll((a) => a.map((x) => new URL((x as HTMLAnchorElement).href).pathname));
    expect(hrefs).toEqual(["/people/", "/entrepreneurs/", "/businesses/", "/institutions/"]);
  });

  test("no horizontal scroll at 360 px", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await assertNoHorizontalScroll(page);
  });

  test("with reduced motion the acts, doors and call are all readable", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/");
    for (const sel of [".h-act-word >> nth=0", ".h-act-word >> nth=2", ".h-door strong >> nth=0", ".h-final-title"]) {
      await expect(page.locator(sel)).toBeVisible();
      expect(await page.locator(sel).evaluate((n) => getComputedStyle(n).opacity)).toBe("1");
    }
  });

  test.describe("scripts off", () => {
    test.use(disableScripts);
    test("the page reads in full as static content", async ({ page }) => {
      await page.goto("/");
      await expect(page.locator(".h-act")).toHaveCount(3);
      await expect(page.locator(".h-act-foot p").first()).toBeVisible();
      await expect(page.locator(".h-final-title")).toBeVisible();
    });
  });
});
