import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll, disableScripts, setReducedMotion } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/ (npm run build).
test.describe("home hero and first screen (REQ-001, REQ-002, REQ-046)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("first screen on a 360 px phone says what the brand is: headline, lede and scroll cue, no buttons (issue 798)", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await expect(page.locator("h1")).toBeVisible();
    await expect(page.locator(".h-lede")).toBeInViewport();
    await expect(page.locator(".h-cue")).toBeInViewport();
    await expect(page.locator(".h-hero-ctas")).toHaveCount(0);
    await expect(page.locator(".h-hero a.btn, .h-hero .h-link")).toHaveCount(0);
    await expect(page.locator(".h-hero").getByText(/get listed|browse the directory|nominate/i)).toHaveCount(0);
    // The ways in stay further down the page.
    await expect(page.locator(".h-final a.btn")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
  });

  test("four muted looping videos; 2 by 2 grid on phones", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await expect(page.locator(".h-vid video")).toHaveCount(4);
    const boxes = await page.locator(".h-vid").evaluateAll((els) => els.map((e) => e.getBoundingClientRect()));
    expect(new Set(boxes.map((b) => Math.round(b.top))).size).toBe(2);
    expect(await page.locator(".h-vid video").first().evaluate((v: HTMLVideoElement) => v.muted && v.loop)).toBe(true);
  });

  test("hovering a video widens it without scaling it", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    const vids = page.locator(".h-vid");
    const before = (await vids.nth(1).boundingBox())!.width;
    await vids.nth(1).hover();
    await page.waitForTimeout(900);
    expect((await vids.nth(1).boundingBox())!.width).toBeGreaterThan(before);
    const transform = await vids.nth(1).locator("video").evaluate((v) => getComputedStyle(v).transform);
    expect(transform).not.toMatch(/matrix\(1\.\d*[1-9]/); // translate only, never a scale
  });

  test("reduced motion: no live class, no autoplay, page reads in full", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/home-live/);
    await expect(page.locator(".h-title")).toBeVisible();
    expect(await page.locator(".h-vid video").first().evaluate((v: HTMLVideoElement) => v.paused)).toBe(true);
  });

  test.describe("scripts off", () => {
    test.use(disableScripts);
    test("the hero is complete with posters only", async ({ page }) => {
      await page.goto("/");
      await expect(page.locator("h1")).toBeVisible();
      await expect(page.locator(".h-lede")).toBeVisible();
    });
  });
});
