import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { routes, type Route } from "./helpers/routes";

// The "Every page" list in docs/theme/theme-acceptance.md, run on every route in helpers/routes.ts at every project width
// (360, 768, 1280, 1600, and the phone profile): axe, no sideways scroll, language, landmarks, one h1, the skip link, and a page
// that still reads and links with scripts off. Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
// REQ-043, REQ-040, REQ-273, REQ-259, REQ-229.

async function open(page: Page, route: Route): Promise<boolean> {
  const res = await page.goto(route.path);
  const want = route.status ?? 200;
  if (route.optional !== false && res?.status() === 404 && want === 200) {
    test.info().annotations.push({ type: "skipped", description: `${route.path} is not built yet` });
    return false;
  }
  expect(res?.status(), `${route.path} status`).toBe(want);
  return true;
}

test.describe("every page (REQ-043, REQ-040)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const route of routes) {
    test.describe(route.name, () => {
      test("axe finds no WCAG 2.1 AA violation", async ({ page }) => {
        test.skip(!(await open(page, route)), "page not built yet");
        await expectNoA11yViolations(page);
      });

      test("no horizontal scroll", async ({ page }) => {
        test.skip(!(await open(page, route)), "page not built yet");
        await assertNoHorizontalScroll(page);
      });

      test("language, landmarks and exactly one h1", async ({ page }) => {
        test.skip(!(await open(page, route)), "page not built yet");
        await expect(page.locator("html")).toHaveAttribute("lang", /^[a-z]{2}/i);
        await expect(page.locator("main")).toHaveCount(1);
        await expect(page.locator("header, [role=banner]").first()).toBeAttached();
        await expect(page.locator("footer, [role=contentinfo]").first()).toBeAttached();
        await expect(page.locator("h1")).toHaveCount(1);
      });

      test("the skip link is first in the tab order and reaches main", async ({ page, isMobile }) => {
        test.skip(isMobile === true, "a touch profile has no keyboard");
        test.skip(!(await open(page, route)), "page not built yet");
        await page.keyboard.press("Tab");
        const target = await page.evaluate(() => (document.activeElement as HTMLAnchorElement | null)?.getAttribute("href") ?? "");
        expect(target, "the first Tab stop is an in-page skip link").toMatch(/^#./);
        await page.keyboard.press("Enter");
        await expect(page.locator(target)).toBeAttached();
        await expect(page.locator("main")).toBeVisible();
      });
    });
  }
});

test.describe("every page with scripts off (REQ-043)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  test.use({ javaScriptEnabled: false });

  for (const route of routes) {
    test(`${route.name} is readable and every link has a destination`, async ({ page }) => {
      test.skip(!(await open(page, route)), "page not built yet");
      await expect(page.locator("h1")).toHaveCount(1);
      const text = (await page.locator("main").innerText()).trim();
      expect(text.length, "main has readable text without scripts").toBeGreaterThan(20);
      const bad = await page.locator("a:not([href]), a[href=''], a[href='#']").count();
      expect(bad, "links without a destination").toBe(0);
    });
  }
});
