import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1). Viewports 360, 768, 1280 and 1600 come from the playwright projects.
test.describe("base layout (REQ-044, REQ-040, REQ-043)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("document structure: lang, one h1, landmarks, skip link to main", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("html")).toHaveAttribute("lang", /.+/);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("header")).toHaveCount(1);
    await expect(page.locator("main#main")).toHaveCount(1);
    await expect(page.locator("footer")).toHaveCount(1);
    await expect(page.locator('a.skip[href="#main"]')).toHaveCount(1);
  });

  test("the skip link moves focus to main", async ({ page }) => {
    await page.goto("/");
    await page.keyboard.press("Tab");
    await expect(page.locator("a.skip")).toBeFocused();
    await page.keyboard.press("Enter");
    await expect(page.locator("main#main")).toBeFocused();
  });

  test("passes axe and has no horizontal scroll", async ({ page }) => {
    await page.goto("/");
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the page is readable without JavaScript", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/");
    await expect(page.locator("h1")).toHaveCount(1);
    await context.close();
  });
});
