import { test } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, setReducedMotion } from "./helpers/page";

test.describe("stories list accessibility", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("/stories/ has no serious accessibility violations", async ({ page }) => {
    await page.goto("/stories/");
    await expectNoA11yViolations(page);
  });

  test("the filtered empty state has none either", async ({ page }) => {
    await page.goto("/stories/?type=opinion&industry=no-such-industry");
    await expectNoA11yViolations(page);
  });

  test("with reduced motion the page is complete and does not scroll sideways", async ({ page }) => {
    await setReducedMotion(page);
    await page.goto("/stories/");
    await assertNoHorizontalScroll(page);
  });
});
