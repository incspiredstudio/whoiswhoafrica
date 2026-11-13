import { test, expect } from "@playwright/test";
import { setReducedMotion } from "./helpers/page";

// Page to page motion (issue 745, REQ-043, REQ-041). Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/.
test.describe("page transitions (REQ-043)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the stylesheet opts every page in to cross-document view transitions, for visitors who allow motion", async ({ page }) => {
    await page.goto("/contact/");
    const rules = await page.evaluate(() =>
      [...document.styleSheets].flatMap((sheet) => {
        try {
          return [...sheet.cssRules].map((r) => r.cssText);
        } catch {
          return [];
        }
      })
    );
    expect(rules.some((r) => r.startsWith("@media (prefers-reduced-motion: no-preference)") && r.includes("@view-transition"))).toBe(true);
    expect(await page.locator(".site-head").evaluate((n) => getComputedStyle(n).viewTransitionName)).toBe("site-head");
  });

  test("without view transition support a page fades in; with it, no script animation runs", async ({ page }) => {
    await page.goto("/contact/");
    const native = await page.evaluate(() => "onpageswap" in window);
    expect(await page.locator("html").evaluate((n) => n.classList.contains("page-enter"))).toBe(!native);
  });

  test("a fallback page-enter plays on browsers that lack the feature", async ({ page }) => {
    await page.addInitScript(() => {
      try {
        delete (Window.prototype as unknown as Record<string, unknown>).onpageswap;
      } catch {
        /* ignore */
      }
      delete (window as unknown as Record<string, unknown>).onpageswap;
    });
    await page.goto("/contact/");
    const gone = await page.evaluate(() => !("onpageswap" in window));
    test.skip(!gone, "this browser keeps onpageswap on a sealed prototype");
    await expect(page.locator("html")).toHaveClass(/page-enter/);
  });

  test("opening another page runs a real view transition in a browser that supports them", async ({ page }) => {
    await page.addInitScript(() => {
      window.addEventListener("pagereveal", (e) => ((window as unknown as Record<string, unknown>).__vt = !!(e as unknown as { viewTransition?: unknown }).viewTransition));
    });
    await page.goto("/contact/");
    test.skip(!(await page.evaluate(() => "onpagereveal" in window)), "no cross-document view transitions in this browser");
    await page.goto("/about/");
    await page.locator("footer a[href$='/contact/']").first().click();
    await expect(page).toHaveURL(/\/contact\/$/);
    await expect.poll(() => page.evaluate(() => (window as unknown as Record<string, unknown>).__vt)).toBe(true);
  });

  test("with reduced motion no page-enter class is set and the page is still complete", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/contact/");
    await expect(page.locator("html")).not.toHaveClass(/page-enter/);
    await expect(page.locator("h1")).toBeVisible();
  });

  test("the home page leaves the entrance to its own intro", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/page-enter/);
  });
});
