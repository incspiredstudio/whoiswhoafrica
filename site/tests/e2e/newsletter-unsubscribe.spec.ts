import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Unsubscribe page and the one-click unsubscribe link page (M2-NWS-07; REQ-249, REQ-061, REQ-058). Needs WIW_THEME_READY=1.
// For the link flow set WIW_UNSUB_URL to a fresh unsubscribe link (the plugin's Marketing::unsubscribe_url); it is used once.
test.describe("newsletter unsubscribe", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("the Unsubscribe page explains the steps, needs no account, is not indexed and passes axe", async ({ page }) => {
    const res = await page.goto("/unsubscribe/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveText("Unsubscribe");
    await expect(page.locator("main ol li")).toHaveCount(3);
    await expect(page.locator("main a[href$='/contact/']")).toHaveCount(1);
    await expect(page.locator("main form")).toHaveCount(0);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the unsubscribe link asks first, then confirms, and cannot be used twice", async ({ page }) => {
    const url = process.env.WIW_UNSUB_URL;
    test.skip(!url, "set WIW_UNSUB_URL to a fresh unsubscribe link");
    await page.goto(url as string);
    await expect(page.locator("h1")).toHaveText("Unsubscribe from the newsletter");
    // Opening the page again changes nothing: a GET never consumes the link.
    await page.reload();
    await expect(page.getByRole("button", { name: "Unsubscribe" })).toBeVisible();
    await expectNoA11yViolations(page);
    await page.getByRole("button", { name: "Unsubscribe" }).click();
    await expect(page.locator("h1[role=status]")).toHaveText("You are unsubscribed");
    await expectNoA11yViolations(page);
    // The link is now used.
    await page.goto(url as string);
    await expect(page.locator("h1")).toHaveText("This link has expired or was already used");
  });
});
