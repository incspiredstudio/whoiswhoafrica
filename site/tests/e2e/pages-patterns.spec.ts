import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a published page that contains the theme patterns (slug patterns-preview, created by hand or a seed); skips without it.
// Registration and structure are covered in tests/Integration/BlockPatternsTest.php (REQ-260).
test("pattern sections render without sideways scroll and without axe violations (REQ-260, REQ-040)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/patterns-preview/");
  test.skip(!res || res.status() >= 400, "no patterns preview page");
  await expect(page.locator(".ab-pillar")).toHaveCount(3);
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});
