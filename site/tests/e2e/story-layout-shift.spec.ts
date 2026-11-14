import { test, expect } from "@playwright/test";

// REQ-041, REQ-042: a story's head must not jump when its fonts arrive. The meta line (500 weight) and the lede (300 weight) were
// fetched late and swapped in after first paint, pushing the article down (CLS 0.27 on the throttled phone profile). Their fonts are
// now preloaded on single stories; this checks the preload and measures the shift on a slow, cold load.
async function firstStory(page: import("@playwright/test").Page): Promise<string | null> {
  await page.goto("/stories/");
  const hrefs = await page.locator("main a[href*='/stories/']").evaluateAll((els) => els.map((e) => (e as HTMLAnchorElement).pathname));
  return hrefs.find((h) => /^\/stories\/(?!type\/|page\/)[^/]+\/$/.test(h)) ?? null;
}

test.describe("story page layout shift", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("REQ-041 a story preloads the font weights its head uses", async ({ page }) => {
    const path = await firstStory(page);
    test.skip(!path, "no story on this site yet");
    await page.goto(path as string);
    const fonts = await page.locator('link[rel="preload"][as="font"]').evaluateAll((els) => els.map((e) => (e as HTMLLinkElement).href));
    for (const weight of ["montreal-300.woff2", "montreal-400.woff2", "montreal-500.woff2"]) {
      expect(fonts.some((h) => h.endsWith(weight)), weight).toBe(true);
    }
  });

  test("REQ-042 a slow cold load of a story shifts less than 0.1", async ({ page, context, browserName }) => {
    test.skip(browserName !== "chromium", "needs the DevTools protocol");
    const path = await firstStory(page);
    test.skip(!path, "no story on this site yet");
    const cdp = await context.newCDPSession(page);
    await cdp.send("Network.enable");
    await cdp.send("Network.setCacheDisabled", { cacheDisabled: true });
    await cdp.send("Network.emulateNetworkConditions", { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
    await cdp.send("Emulation.setCPUThrottlingRate", { rate: 4 });
    await page.addInitScript(() => {
      (window as unknown as { __cls: number }).__cls = 0;
      new PerformanceObserver((list) => {
        for (const e of list.getEntries() as unknown as { hadRecentInput: boolean; value: number }[]) {
          if (!e.hadRecentInput) (window as unknown as { __cls: number }).__cls += e.value;
        }
      }).observe({ type: "layout-shift", buffered: true });
    });
    await page.goto(path as string, { waitUntil: "load", timeout: 60000 });
    await page.waitForTimeout(1500);
    const cls = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
    await cdp.send("Emulation.setCPUThrottlingRate", { rate: 1 });
    expect(cls).toBeLessThan(0.1);
  });
});
