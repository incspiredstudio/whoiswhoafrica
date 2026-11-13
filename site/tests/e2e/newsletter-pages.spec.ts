import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The newsletter sign-up, done and confirm pages (REQ-061, REQ-249, REQ-058). Rules are in tests/Integration/NewsletterPagesTest.php.
test.describe("newsletter pages", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the sign-up page has an email field, a consent tick and no horizontal scroll", async ({ page }) => {
    const res = await page.goto("/newsletter/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("form[method=post] input[name=email]")).toHaveCount(1);
    await expect(page.locator("form[method=post] input[name=consent]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the done page for a sign-up says to check email and is not indexed", async ({ page }) => {
    await page.goto("/done/?kind=newsletter&from=newsletter");
    await expect(page.getByRole("heading", { name: "Check your email to finish signing up" })).toBeVisible();
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
  });

  test("an unknown confirm link shows the expired state with a way to sign up again", async ({ page }) => {
    const res = await page.goto("/confirm/aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa/");
    expect(res?.status()).toBe(410);
    await expect(page.getByRole("heading", { name: /expired or was already used/ })).toBeVisible();
    await expect(page.getByRole("link", { name: "Sign up again" })).toHaveAttribute("href", /\/newsletter\/$/);
    await expect(page.locator("meta[name=robots][content*=noindex]").first()).toBeAttached();
    await expectNoA11yViolations(page);
  });
});
