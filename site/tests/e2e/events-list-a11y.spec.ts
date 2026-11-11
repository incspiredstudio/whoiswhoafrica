import { test } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Axe and layout at the four widths (the playwright projects), for the Events list (REQ-264, REQ-043, REQ-040).
test("events list has no axe violations and no sideways scroll", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  await page.goto("/events/");
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});
