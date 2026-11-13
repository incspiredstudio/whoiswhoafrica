import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/. The block shows only when reviewed profiles exist.
test.describe("home On the record (REQ-218, REQ-257)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the block is either absent or complete, and never scrolls the page sideways", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    if ((await page.locator(".h-record").count()) > 0) {
      await expect(page.locator(".h-rail")).toBeVisible();
      await expect(page.locator(".h-index")).toBeHidden();
    }
    await assertNoHorizontalScroll(page);
  });

  test("on desktop the name index shows and hovering a name opens the preview card", async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto("/");
    test.skip((await page.locator(".h-index a").count()) === 0, "no reviewed profiles on this site");
    await page.locator(".h-index a").first().scrollIntoViewIfNeeded();
    await page.locator(".h-index a").first().hover();
    await expect(page.locator("#home-peek")).toHaveClass(/is-on/);
  });
});
