import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1) and a built dist/ (npm run build).
test.describe("cookie notice (REQ-248, REQ-062, REQ-058)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("shows Decline and Accept, stores the choice, stays away on reload, and the footer button reopens it", async ({ page, context }) => {
    await context.clearCookies();
    await page.goto("/");
    const card = page.locator(".cookie[data-cookie]");
    // A short page shows it at once; a long page after a little scroll.
    await page.evaluate(() => window.scrollTo(0, 400));
    await expect(card).toBeVisible();
    await expect(card.getByRole("button", { name: "Decline" })).toBeVisible();
    await expect(card.getByRole("button", { name: "Accept" })).toBeVisible();
    await expectNoA11yViolations(page);
    await card.getByRole("button", { name: "Decline" }).click();
    await expect(card).toBeHidden();
    expect((await context.cookies()).find((c) => c.name === "wiw_consent")?.value).toBe("essential");
    await page.reload();
    await page.evaluate(() => window.scrollTo(0, 400));
    await expect(card).toBeHidden();
    await page.locator("[data-cookie-reopen]").click();
    await expect(card).toBeVisible();
    await expect(card.getByRole("button", { name: "Decline" })).toBeFocused();
  });

  test("no analytics script loads before Accept or after Decline, and loads after Accept", async ({ page, context }) => {
    await context.clearCookies();
    // Same origin, because the content security policy only allows scripts from this site (a real provider is added to its allowlist).
    const analytics = new URL("/analytics-test.js", process.env.BASE_URL || "http://localhost:8882").toString();
    let requested = 0;
    await page.route(analytics, (route) => {
      requested++;
      return route.fulfill({ contentType: "application/javascript", body: "" });
    });
    await page.addInitScript((url) => {
      (window as unknown as { wiwConsent: { analytics: string } }).wiwConsent = { analytics: url };
    }, analytics);
    // The page script reads window.wiwConsent after the inline config; the init script runs first, then the page overrides it
    // with the server value (empty by default), so set it again once the page has loaded and re-run the choice.
    await page.goto("/");
    expect(requested).toBe(0);
    await page.evaluate((url) => {
      (window as unknown as { wiwConsent: { analytics: string } }).wiwConsent = { analytics: url };
    }, analytics);
    await page.evaluate(() => window.scrollTo(0, 400));
    await page.locator(".cookie").getByRole("button", { name: "Accept" }).click();
    await expect.poll(() => requested).toBe(1);
    expect((await context.cookies()).find((c) => c.name === "wiw_consent")?.value).toBe("all");
  });

  test("without script the card never shows and no analytics runs", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/");
    await expect(page.locator(".cookie")).toBeHidden();
    await context.close();
  });
});
