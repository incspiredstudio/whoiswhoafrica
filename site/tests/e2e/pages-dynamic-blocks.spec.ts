import { test, expect } from "@playwright/test";

// Dynamic blocks (REQ-260, REQ-052). Needs WIW_THEME_READY=1 and a page that holds the blocks; WIW_DYNAMIC_BLOCKS_PATH names it
// (for example the About page once it carries the Figures block).
test.describe("dynamic blocks", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("figures show four calculated counts", async ({ page }) => {
    const path = process.env.WIW_DYNAMIC_BLOCKS_PATH;
    test.skip(!path, "no page with the dynamic blocks");
    await page.goto(path as string);
    const figures = page.locator("dl.wiw-figures");
    await expect(figures.locator("dt")).toHaveText(["Profiles", "Industries", "Countries", "Stories"]);
    for (const dd of await figures.locator("dd").allTextContents()) {
      expect(dd).toMatch(/^[\d,.\s]+$/);
    }
  });

  test("the stage button links to a stage page", async ({ page }) => {
    const path = process.env.WIW_DYNAMIC_BLOCKS_PATH;
    test.skip(!path, "no page with the dynamic blocks");
    await page.goto(path as string);
    const link = page.locator(".wiw-stage-button a");
    await expect(link).toHaveAttribute("href", /\/(nominate|awards\/(nominees|winners|rules))\/$/);
  });
});
