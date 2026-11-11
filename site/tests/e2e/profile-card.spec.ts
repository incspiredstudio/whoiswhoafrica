import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site with the child theme active and published profiles listed at /directory/ (built by the directory list issues).
// Until then this spec skips. The card markup itself is covered by tests/Integration/ProfileCardTest.php (REQ-216, REQ-155).
test("profile card shows no trust mark or cover and is keyboard focusable (REQ-155, REQ-216)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/directory/");
  test.skip(!res || res.status() >= 400, "directory list does not exist yet");
  const cards = page.locator(".pcard");
  test.skip((await cards.count()) === 0, "no profiles to show");
  const text = (await cards.first().innerText()).toLowerCase();
  for (const word of ["verified", "featured", "sponsored", "claimed"]) expect(text).not.toContain(word);
  await expect(page.locator(".pcard img[class*='cover'], .pcard .banner")).toHaveCount(0);
  await cards.first().locator("a.stretched").focus();
  await expect(cards.first().locator("a.stretched")).toBeFocused();
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});
