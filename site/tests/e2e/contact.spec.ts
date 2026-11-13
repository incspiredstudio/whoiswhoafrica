import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Contact page and the done pages (REQ-039, REQ-081, REQ-058). Sending needs the plugin's POST routes; the markup and the
// done page are checked here, the submission rules in tests/Integration/FormPagesTest.php and the plugin's tests.
test.describe("contact page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("says a person reads every message and shows the four alternatives", async ({ page }) => {
    const res = await page.goto("/contact/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.getByText("A person reads every message")).toBeVisible();
    await expect(page.locator("form[method=post] input[name=email]")).toHaveCount(1);
    await expect(page.locator(".link-rows a")).toHaveCount(4);
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the done page after a message says Message sent with the reference", async ({ page }) => {
    await page.goto("/done/?kind=enquiry&from=contact&ref=ENQ-0005");
    await expect(page.getByRole("heading", { name: "Message sent" })).toBeVisible();
    await expect(page.getByText("ENQ-0005")).toBeVisible();
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
  });
});
