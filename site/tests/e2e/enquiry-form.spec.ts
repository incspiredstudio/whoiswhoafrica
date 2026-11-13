import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The profile enquiry page (REQ-018, REQ-081, REQ-122). The form, off state and markup rules are in
// tests/Integration/ProfileEnquiryPageTest.php; the browser checks need a published profile (set WIW_PROFILE_SLUG).
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("profile enquiry", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("an enquiry address for an unknown profile answers 404 (REQ-018)", async ({ page }) => {
    const res = await page.goto("/enquire/this-profile-does-not-exist/");
    expect(res?.status()).toBe(404);
  });

  test("the enquiry page shows the form without owner contact details (REQ-018)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    const res = await page.goto(`/enquire/${slug}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
    await expect(page.locator("form[method=post] input[name=email]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the confirmation page says the enquiry was sent and shows the reference (REQ-018)", async ({ page }) => {
    await page.goto("/done/?kind=enquiry&from=profile_enquiry&ref=ENQ-123");
    await expect(page.getByRole("heading", { level: 1, name: "Enquiry sent" })).toBeVisible();
    await expect(page.getByText("ENQ-123")).toBeVisible();
    await expect(page.getByText(/terms are agreed offline/i)).toHaveCount(0);
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
  });

  test("the form shows the privacy notice, the time-based token and the hidden bot field (REQ-081, REQ-122)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    await page.goto(`/enquire/${slug}/`);
    await expect(page.locator("form input[name=wiw_form_token]")).toHaveCount(1);
    expect(await page.locator("form input[name=wiw_form_token]").inputValue()).not.toBe("");
    await expect(page.locator(".bot-check input[name=wiw_website]")).toHaveCount(1);
    await expect(page.locator("form").getByRole("link", { name: /privacy/i }).first()).toBeVisible();
  });

  test("sending the form reaches the confirmation with a reference and no owner contact (REQ-018, REQ-122)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    test.skip(!process.env.WIW_ENQUIRY_FLOW, "set WIW_ENQUIRY_FLOW=1 to post a real form");
    await page.goto(`/enquire/${slug}/`);
    await page.locator("#f-name").fill("Test Sender");
    await page.locator("#f-email").fill(`sender-${Date.now()}@example.org`);
    await page.locator("#f-message").fill("A short fictional message for the team.");
    await page.waitForTimeout(3500);
    await page.getByRole("button", { name: "Send enquiry" }).click();
    await expect(page).toHaveURL(/\/done\/\?kind=enquiry&ref=ENQ-\d+/);
    await expect(page.getByRole("heading", { level: 1, name: "Enquiry sent" })).toBeVisible();
    await expect(page.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
  });

  test("the profile rail links to the enquiry page (REQ-018)", async ({ page }) => {
    test.skip(!slug, "needs a published profile (set WIW_PROFILE_SLUG)");
    await page.goto(`/profile/${slug}/`);
    await expect(page.locator(`.profile-rail a[href$='/enquire/${slug}/']`)).toHaveCount(1);
  });
});
