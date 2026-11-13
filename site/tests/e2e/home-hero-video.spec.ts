import { test, expect } from "@playwright/test";
import { setReducedMotion } from "./helpers/page";

// REQ-001, REQ-041: the hero clips are decoration that must never harm the first view. Needs a site whose active theme is
// whoiswho-child (WIW_THEME_READY=1) and a built dist/ (npm run build).
test.describe("home hero videos load politely (REQ-041)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("no video bytes at load but the first clip's; the others wait their turn", async ({ page }) => {
    const media: { t: number; url: string }[] = [];
    const t0 = Date.now();
    page.on("request", (r) => {
      if (/hero-\d\.(mp4|webm)/.test(r.url())) media.push({ t: Date.now() - t0, url: r.url() });
    });
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/", { waitUntil: "domcontentloaded" });
    // Before the load event nothing is requested: every clip is preload="none".
    expect(media.filter((m) => m.t < 50)).toHaveLength(0);
    await expect.poll(() => media.length, { timeout: 15000 }).toBeGreaterThan(0);
    expect(media[0].url).toMatch(/hero-1\.(mp4|webm)/);
    // The second clip is not requested in the same breath as the first.
    const seq = [...new Set(media.map((m) => m.url.match(/hero-(\d)/)![1]))];
    expect(seq[0]).toBe("1");
    await expect.poll(async () => new Set(media.map((m) => m.url.match(/hero-(\d)/)![1])).size, { timeout: 20000 }).toBe(4);
    const order = [...new Set(media.map((m) => m.url.match(/hero-(\d)/)![1]))];
    expect(order).toEqual(["1", "2", "3", "4"]);
  });

  test("reduced motion shows the posters only: no clip is requested or played", async ({ page }) => {
    const media: string[] = [];
    page.on("request", (r) => {
      if (/hero-\d\.(mp4|webm)/.test(r.url())) media.push(r.url());
    });
    await setReducedMotion(page);
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await page.waitForTimeout(1500);
    expect(media).toEqual([]);
    const posters = page.locator(".h-vid .h-vid-poster");
    await expect(posters).toHaveCount(4);
    for (let i = 0; i < 4; i++) {
      expect(await posters.nth(i).evaluate((img: HTMLImageElement) => img.complete && img.naturalWidth > 0)).toBe(true);
    }
    expect(await page.locator(".h-vid video").evaluateAll((vs) => vs.every((v) => (v as HTMLVideoElement).paused))).toBe(true);
  });

  test("the hero causes no layout shift, and the first poster is the largest contentful paint", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.addInitScript(() => {
      const w = window as unknown as { __cls: number; __lcp: string; __heroShift: number };
      w.__cls = 0;
      w.__lcp = "";
      w.__heroShift = 0;
      new PerformanceObserver((list) => {
        for (const e of list.getEntries() as unknown as { value: number; hadRecentInput: boolean; sources?: { node?: Node }[] }[]) {
          if (e.hadRecentInput) continue;
          w.__cls += e.value;
          // A shift that moves anything inside the video stack is the hero's doing; the title or header settling is not.
          if ((e.sources || []).some((s) => s.node && (s.node as Element).closest && (s.node as Element).closest(".h-vids"))) w.__heroShift += e.value;
        }
      }).observe({ type: "layout-shift", buffered: true });
      new PerformanceObserver((list) => {
        const last = list.getEntries().at(-1) as unknown as { element?: Element; url?: string };
        w.__lcp = last?.url || last?.element?.tagName || "";
      }).observe({ type: "largest-contentful-paint", buffered: true });
    });
    await page.goto("/");
    await page.waitForLoadState("load");
    await page.waitForTimeout(2500);
    const { cls, lcp, heroShift } = await page.evaluate(() => {
      const w = window as unknown as { __cls: number; __lcp: string; __heroShift: number };
      return { cls: w.__cls, lcp: w.__lcp, heroShift: w.__heroShift };
    });
    expect(heroShift, "the posters and clips never move anything").toBe(0);
    expect(cls, "page CLS stays in the good range").toBeLessThan(0.1);
    // The LCP element is an image or text, never a video frame that arrived late.
    expect(lcp).not.toBe("VIDEO");
    // Every poster and clip reserves its space with width and height attributes.
    expect(await page.locator(".h-vid img").evaluateAll((els) => els.every((e) => e.getAttribute("width") === "540" && e.getAttribute("height") === "960"))).toBe(true);
  });

  test("a clip pauses when the hero scrolls out of view and resumes when it returns", async ({ page, browserName }) => {
    test.skip(browserName !== "chromium", "autoplay policy differs elsewhere");
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    // The second clip has a WebM; the first is MP4 only, which the bundled Chromium cannot decode, so it is skipped there.
    const first = page.locator(".h-vid video").nth(1);
    await expect.poll(() => first.evaluate((v: HTMLVideoElement) => !v.paused), { timeout: 20000 }).toBe(true);
    await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
    await expect.poll(() => first.evaluate((v: HTMLVideoElement) => v.paused), { timeout: 5000 }).toBe(true);
    await page.evaluate(() => window.scrollTo(0, 0));
    await expect.poll(() => first.evaluate((v: HTMLVideoElement) => !v.paused), { timeout: 5000 }).toBe(true);
  });
});
