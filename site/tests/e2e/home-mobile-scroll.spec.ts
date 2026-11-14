import { test, expect, type Page } from "@playwright/test";

// The front page's <body> also carries the class "home" (WordPress body_class). A rule written for the page wrapper must not
// reach <body>: overflow: clip there locked touch scrolling on phones, and its light text colour made the menu's dropdown
// buttons nearly invisible on the cream drawer (live site, 2026-11-14). REQ-001, REQ-044.
// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("front page on phones (REQ-001, REQ-044)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the body never clips scrolling on the front page", async ({ page }) => {
    await page.goto("/");
    const body = await page.evaluate(() => {
      const s = getComputedStyle(document.body);
      return [s.overflowX, s.overflowY];
    });
    for (const value of body) {
      expect(["visible", "auto"]).toContain(value);
    }
  });

  test("a finger swipe scrolls the front page", async ({ page, browserName }) => {
    test.skip(browserName !== "chromium", "touch is dispatched through the Chromium protocol");
    await page.setViewportSize({ width: 390, height: 664 });
    await page.goto("/");
    await page.waitForLoadState("load");
    const cdp = await page.context().newCDPSession(page);
    await cdp.send("Emulation.setTouchEmulationEnabled", { enabled: true, maxTouchPoints: 5 });
    await cdp.send("Input.dispatchTouchEvent", { type: "touchStart", touchPoints: [{ x: 190, y: 550 }] });
    for (let i = 1; i <= 12; i++) {
      await cdp.send("Input.dispatchTouchEvent", { type: "touchMove", touchPoints: [{ x: 190, y: 550 - (400 * i) / 12 }] });
    }
    await cdp.send("Input.dispatchTouchEvent", { type: "touchEnd", touchPoints: [] });
    await expect.poll(() => page.evaluate(() => window.scrollY), { timeout: 3000 }).toBeGreaterThan(100);
  });

  test("menu dropdown buttons have the same text colour on the front page as elsewhere", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 800 });
    const colour = async (p: Page, path: string) => {
      await p.goto(path);
      await p.locator('[aria-controls="drawer"]').first().click();
      const button = p.locator(".drawer button:not(.drawer-close)").first();
      await expect(button).toBeVisible();
      return button.evaluate((b) => getComputedStyle(b).color);
    };
    const elsewhere = await colour(page, "/about/");
    const home = await colour(page, "/");
    expect(home).toBe(elsewhere);
  });
});
