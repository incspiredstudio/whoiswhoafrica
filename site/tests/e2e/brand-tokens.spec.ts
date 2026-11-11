import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("brand tokens (REQ-052, REQ-190)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the child's brand colours are in force", async ({ page }) => {
    await page.goto("/");
    const resolved = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue("--orange").trim().toLowerCase());
    expect(resolved).toBe("#ffa600");
  });

  test("fonts are served from the theme as woff2 and preloaded", async ({ page, request }) => {
    await page.goto("/");
    const preloads = await page.locator('link[rel="preload"][as="font"]').evaluateAll((els) => els.map((e) => (e as HTMLLinkElement).href));
    expect(preloads.some((h) => h.endsWith("repline.woff2"))).toBe(true);
    for (const href of preloads) {
      const res = await request.get(href);
      expect(res.ok(), href).toBeTruthy();
      expect(res.headers()["content-type"]).toMatch(/font|woff|octet/);
    }
  });
});
