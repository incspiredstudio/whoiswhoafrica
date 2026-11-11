import { test, expect } from "@playwright/test";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("enqueue and CSP (REQ-041, REQ-046)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("no jQuery on the public site and no CSP violations", async ({ page }) => {
    const violations: string[] = [];
    page.on("console", (m) => {
      if (/content security policy/i.test(m.text())) violations.push(m.text());
    });
    await page.goto("/");
    expect(await page.evaluate(() => typeof (window as unknown as { jQuery?: unknown }).jQuery)).toBe("undefined");
    expect(violations).toEqual([]);
  });

  test("every script is deferred or inline with a nonce", async ({ page }) => {
    await page.goto("/");
    const bad = await page.locator("script[src]").evaluateAll((els) => els.filter((e) => !(e as HTMLScriptElement).defer && !(e as HTMLScriptElement).async && (e as HTMLScriptElement).type !== "module").map((e) => (e as HTMLScriptElement).src));
    expect(bad).toEqual([]);
  });
});
