import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts, setReducedMotion } from "./helpers/page";

// Front page choreography (issue 744, REQ-043, REQ-041): the prototype's motion, kept additive.
// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("home motion (REQ-043, REQ-041)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("motion on: reveal hooks exist and the doors and closing title open when scrolled to", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await expect(page.locator("html")).toHaveClass(/home-live/);
    await expect(page.locator(".h-door.h-rv-clip")).toHaveCount(4);
    await expect(page.locator(".h-final-title.h-rv .h-line")).toHaveCount(3);
    await page.locator(".h-final-title").scrollIntoViewIfNeeded();
    await expect(page.locator(".h-final-title.h-rv")).toHaveClass(/\bin\b/);
    await page.locator(".h-doors").scrollIntoViewIfNeeded();
    await expect(page.locator(".h-door.h-rv-clip.in")).toHaveCount(4);
  });

  test("the header slides away reading down and returns on scrolling up", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await page.evaluate(() => window.scrollTo(0, 1600));
    await expect(page.locator("html")).toHaveClass(/head-hidden/);
    await page.evaluate(() => window.scrollTo(0, 1200));
    await expect(page.locator("html")).not.toHaveClass(/head-hidden/);
  });

  test("a hidden header comes back when it takes keyboard focus", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await page.evaluate(() => window.scrollTo(0, 1600));
    await expect(page.locator("html")).toHaveClass(/head-hidden/);
    await page.locator(".site-head a").first().focus();
    await expect.poll(async () => (await page.locator(".site-head").boundingBox())?.y ?? -999).toBeGreaterThanOrEqual(-1);
  });

  test("stories pin on wide screens and the track follows the scroll", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    const stories = page.locator(".h-stories");
    test.skip((await stories.count()) === 0, "no stories on this site");
    await expect(stories).toHaveClass(/is-pinned/);
    const height = await stories.evaluate((n) => n.getBoundingClientRect().height);
    expect(height).toBeGreaterThanOrEqual(800);
    await assertNoHorizontalScroll(page);
  });

  test("stories do not pin on phones", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await expect(page.locator(".h-stories")).not.toHaveClass(/is-pinned/);
    await assertNoHorizontalScroll(page);
  });

  test("cursor and magnetic buttons exist only with a mouse on wide screens", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await expect(page.locator(".h-cursor")).toHaveCount(1);
    await expect(page.locator(".h-cursor")).toHaveAttribute("aria-hidden", "true");
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    await expect(page.locator(".h-cursor")).toHaveCount(0);
  });

  test("the scroll cue still moves focus to the next section for keyboard users", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await page.locator(".h-cue").focus();
    await page.keyboard.press("Enter");
    await expect.poll(() => page.evaluate(() => document.activeElement?.id)).toBe("home-after-hero");
  });

  test("with reduced motion none of the choreography runs and every block is visible", async ({ page }) => {
    await setReducedMotion(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/home-live/);
    await expect(page.locator(".h-cursor, .h-intro, .h-stage")).toHaveCount(0);
    await expect(page.locator(".h-stories")).not.toHaveClass(/is-pinned/);
    for (const sel of [".h-final-title", ".h-door >> nth=0", ".h-act-word >> nth=0"]) {
      await expect(page.locator(sel)).toBeVisible();
      expect(await page.locator(sel).evaluate((n) => getComputedStyle(n).opacity)).toBe("1");
    }
    expect(await page.locator(".h-door >> nth=0").evaluate((n) => getComputedStyle(n).clipPath)).toBe("none");
  });

  test.describe("scripts off", () => {
    test.use(disableScripts);
    test("doors, closing title and stories read in full", async ({ page }) => {
      await page.goto("/");
      expect(await page.locator(".h-door >> nth=0").evaluate((n) => getComputedStyle(n).clipPath)).toBe("none");
      await expect(page.locator(".h-final-title")).toBeVisible();
      expect(await page.locator(".h-final-title .h-line > span >> nth=0").evaluate((n) => getComputedStyle(n).transform)).toBe("none");
      await expect(page.locator(".h-cursor, .h-intro, .h-stage")).toHaveCount(0);
    });
  });

  for (const width of [360, 768, 1024, 1280]) {
    test(`axe finds no violations at ${width} px (motion on)`, async ({ page }) => {
      await page.setViewportSize({ width, height: 800 });
      await page.goto("/");
      await page.waitForTimeout(1800); // the intro has finished
      await expectNoA11yViolations(page);
    });
  }
});
