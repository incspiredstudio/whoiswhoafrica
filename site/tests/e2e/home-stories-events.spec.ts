import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a built dist/. Blocks appear only when there is content.
test.describe("home stories track and events (REQ-029, REQ-263, REQ-264)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the stories track is a keyboard-focusable group and the page does not scroll sideways at 360 px", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 740 });
    await page.goto("/");
    if ((await page.locator(".h-stories").count()) > 0) {
      await expect(page.locator(".h-track-wrap")).toHaveAttribute("tabindex", "0");
      await expect(page.locator(".h-story")).not.toHaveCount(0);
      expect(await page.locator(".h-story").count()).toBeLessThanOrEqual(6);
    }
    await assertNoHorizontalScroll(page);
  });

  test("the events block lists at most three compact rows and links to all events", async ({ page }) => {
    await page.goto("/");
    if ((await page.locator(".h-events").count()) > 0) {
      expect(await page.locator(".h-events .ag-row").count()).toBeLessThanOrEqual(3);
      await expect(page.locator(".h-events a", { hasText: "All events" })).toHaveAttribute("href", /\/events\/$/);
    }
  });
});
