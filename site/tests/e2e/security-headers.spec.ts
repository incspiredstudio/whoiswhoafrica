import { test, expect } from "@playwright/test";

// Headers are checked on the home page and the sign-in page. HSTS is only sent over HTTPS, so it is checked when the base URL is https.
test.describe("security headers (REQ-046)", () => {
  for (const path of ["/", "/wp-login.php"]) {
    test(`${path} carries the security headers`, async ({ request, baseURL }) => {
      const res = await request.get(path);
      const h = res.headers();
      expect(h["x-content-type-options"]).toBe("nosniff");
      expect(h["referrer-policy"]).toBeTruthy();
      expect(h["permissions-policy"]).toBeTruthy();
      expect(h["content-security-policy"] || h["content-security-policy-report-only"]).toBeTruthy();
      if (baseURL?.startsWith("https://")) expect(h["strict-transport-security"]).toContain("max-age=");
    });
  }

  test("the CSP blocks an inline script without the nonce", async ({ page }) => {
    await page.goto("/");
    const violations: string[] = [];
    page.on("console", (m) => {
      if (m.text().includes("Content Security Policy")) violations.push(m.text());
    });
    const ran = await page.evaluate(() => {
      (window as unknown as { __wiw: boolean }).__wiw = false;
      const s = document.createElement("script");
      s.textContent = "window.__wiw = true;";
      document.head.appendChild(s);
      return (window as unknown as { __wiw: boolean }).__wiw;
    });
    expect(ran).toBe(false);
  });

  test("an inline script with the page nonce runs", async ({ page }) => {
    await page.goto("/");
    const nonce = await page.evaluate(() => document.querySelector("script[nonce]")?.getAttribute("nonce") ?? "");
    test.skip(!nonce, "no script tag with a nonce on this page yet");
    const ran = await page.evaluate((n) => {
      (window as unknown as { __wiw: boolean }).__wiw = false;
      const s = document.createElement("script");
      s.setAttribute("nonce", n);
      s.textContent = "window.__wiw = true;";
      document.head.appendChild(s);
      return (window as unknown as { __wiw: boolean }).__wiw;
    }, nonce);
    expect(ran).toBe(true);
  });

  test("wp-login still works with the policy", async ({ page }) => {
    const res = await page.goto("/wp-login.php");
    expect(res?.ok()).toBeTruthy();
    await expect(page.locator("#user_login")).toBeVisible();
  });
});
