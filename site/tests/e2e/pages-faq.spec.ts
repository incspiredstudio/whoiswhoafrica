import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs the FAQ page (/faq/) to hold FAQ blocks; with none the spec skips. Block output and structured data are covered in
// tests/Integration/FaqBlockTest.php (REQ-260).
test.describe("faq page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("groups hold questions as disclosures, only the first is open, and they work by keyboard", async ({ page }) => {
    const res = await page.goto("/faq/");
    test.skip(!res || res.status() >= 400 || (await page.locator("details.faq-q").count()) === 0, "no FAQ blocks on /faq/ yet");
    const questions = page.locator("details.faq-q");
    expect(await questions.count()).toBeGreaterThan(1);
    await expect(page.locator("details.faq-q[open]")).toHaveCount(1);
    await expect(questions.first()).toHaveAttribute("open", "");
    const second = questions.nth(1);
    await expect(second).not.toHaveAttribute("open", "");
    await second.locator("summary").focus();
    await page.keyboard.press("Enter");
    await expect(second).toHaveAttribute("open", "");
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("structured data is absent unless switched on", async ({ page }) => {
    const res = await page.goto("/faq/");
    test.skip(!res || res.status() >= 400, "no /faq/ page yet");
    await expect(page.locator("script[type='application/ld+json']:has-text('FAQPage')")).toHaveCount(0);
  });
});
