import { test, expect } from "@playwright/test";

// Needs a site whose active theme loads the base tokens (set WIW_THEME_READY=1).
test.describe("design tokens (REQ-190, REQ-273)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("semantic tokens exist as computed custom properties", async ({ page }) => {
    await page.goto("/");
    const missing = await page.evaluate(() => {
      const names = ["--c-bg", "--c-text", "--c-cta", "--c-link", "--c-line", "--c-focus", "--t-base", "--t-xl", "--s-1", "--s-9", "--w-page", "--w-text", "--head-h", "--r-2", "--sh-2", "--m-base", "--ease-out", "--font-body", "--l-pending"];
      const style = getComputedStyle(document.documentElement);
      return names.filter((n) => style.getPropertyValue(n).trim() === "");
    });
    expect(missing).toEqual([]);
  });
});
