import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts, setReducedMotion } from "./helpers/page";

// Scroll reveals on the inner pages (issue 746, REQ-043, REQ-041). Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
const PAGE = "/awards/";

test.describe("inner page reveals (REQ-043)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("blocks below the first screen are tagged, blocks on it are not, and nothing moves layout", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(PAGE);
    const groups = page.locator(".aw-group");
    test.skip((await groups.count()) < 2, "this page has too few blocks to check");
    const states = await groups.evaluateAll((nodes) =>
      nodes.map((n) => ({ top: n.getBoundingClientRect().top, tagged: n.hasAttribute("data-reveal"), opacity: getComputedStyle(n).opacity }))
    );
    for (const s of states) {
      if (s.top >= 800 * 0.95) {
        expect(s.tagged, "a block below the fold is tagged").toBe(true);
        expect(s.opacity).toBe("0");
      } else {
        expect(s.tagged, "a block on the first screen is left alone").toBe(false);
      }
    }
    await assertNoHorizontalScroll(page);
  });

  test("a tagged block rises in when scrolled to and then drops its tag so hover and focus rules apply", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(PAGE);
    const last = page.locator(".aw-group").last();
    test.skip((await page.locator(".aw-group[data-reveal-auto]").count()) === 0, "nothing below the fold on this page");
    await last.scrollIntoViewIfNeeded();
    await expect(last).toHaveClass(/\bin\b|^(?!.*in)/);
    await expect.poll(() => last.evaluate((n) => n.hasAttribute("data-reveal"))).toBe(false);
    expect(await last.evaluate((n) => getComputedStyle(n).opacity)).toBe("1");
    expect(await last.evaluate((n) => getComputedStyle(n).transform)).toBe("none");
  });

  test("with reduced motion nothing is tagged and every block is visible", async ({ page }) => {
    await setReducedMotion(page);
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto(PAGE);
    await expect(page.locator("[data-reveal-auto]")).toHaveCount(0);
    await expect(page.locator("html")).not.toHaveClass(/js-motion/);
    const opacities = await page.locator(".aw-group").evaluateAll((n) => n.map((x) => getComputedStyle(x).opacity));
    expect(opacities.every((o) => o === "1")).toBe(true);
  });

  test.describe("scripts off", () => {
    test.use(disableScripts);
    test("every block is visible", async ({ page }) => {
      await page.goto(PAGE);
      const opacities = await page.locator(".aw-group").evaluateAll((n) => n.map((x) => getComputedStyle(x).opacity));
      expect(opacities.every((o) => o === "1")).toBe(true);
      await expect(page.locator("[data-reveal-auto]")).toHaveCount(0);
    });
  });

  for (const width of [360, 768, 1024, 1280]) {
    test(`axe finds no violations at ${width} px after scrolling through the page`, async ({ page }) => {
      await page.setViewportSize({ width, height: 800 });
      await page.goto(PAGE);
      await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 400) {
          window.scrollTo(0, y);
          await new Promise((r) => setTimeout(r, 60));
        }
      });
      await page.waitForTimeout(1300);
      await expectNoA11yViolations(page);
    });
  }
});
