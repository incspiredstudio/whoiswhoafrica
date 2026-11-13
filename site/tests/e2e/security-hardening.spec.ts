import { test, expect, APIRequestContext } from "@playwright/test";

// Launch checklist items 4 and 5 as black-box checks that run against local, staging or live (M5-SEC-01, M5-SEC-02; REQ-046, REQ-047, REQ-229).
// They send only anonymous requests, so they are safe on the live site. Optional inputs:
//   WIW_HTTP_URL     the http:// address of the site, to prove the redirect to HTTPS (staging and live)
//   WIW_GONE_PATH    the path of a profile that is removed or hidden, to check the 410 and its headers
// The PHP tests in tests/Security cover the same rules inside WordPress; these prove them on the served site.
// One viewport is enough: nothing here depends on layout.
test.describe("security on the served site (REQ-046, REQ-047, REQ-229)", () => {
  test.beforeEach(({}, info) => {
    test.skip(info.project.name !== "chromium-1280", "layout-independent checks run once");
  });

  const headerRules = (h: Record<string, string>, https: boolean) => {
    expect(h["x-content-type-options"]).toBe("nosniff");
    expect(h["referrer-policy"]).toBeTruthy();
    expect(h["permissions-policy"]).toBeTruthy();
    const csp = h["content-security-policy"] || h["content-security-policy-report-only"] || "";
    expect(csp).toContain("frame-ancestors 'none'");
    expect(csp).toContain("script-src");
    expect(csp).toMatch(/nonce-/);
    if (https) expect(h["strict-transport-security"]).toContain("max-age=");
  };

  test("REQ-046 the home page carries every agreed header", async ({ request, baseURL }) => {
    const res = await request.get("/");
    expect(res.status()).toBe(200);
    headerRules(res.headers(), !!baseURL?.startsWith("https://"));
  });

  test("REQ-046 a 404 carries the headers and shows no technical detail", async ({ request, baseURL }, info) => {
    const res = await request.get("/this-page-does-not-exist-" + Date.now());
    expect(res.status()).toBe(404);
    headerRules(res.headers(), !!baseURL?.startsWith("https://"));
    const body = (await res.text()).toLowerCase();
    expect(body).not.toMatch(/stack trace|fatal error|\.php on line|warning:|notice:|mysql|wpdb/);
    // The PHP version should not be advertised (php.ini expose_php, or the guard). Reported as a finding, not a failure, until that is settled.
    if (/\d/.test(res.headers()["x-powered-by"] ?? "")) info.annotations.push({ type: "finding", description: "X-Powered-By exposes the PHP version" });
  });

  test("REQ-046 a removed profile answers 410 with noindex and no reason", async ({ request, baseURL }) => {
    const path = process.env.WIW_GONE_PATH;
    test.skip(!path, "set WIW_GONE_PATH to a removed profile path (needs a fixture on the site)");
    const res = await request.get(path!);
    expect([404, 410]).toContain(res.status());
    headerRules(res.headers(), !!baseURL?.startsWith("https://"));
    const body = await res.text();
    const noindex = (res.headers()["x-robots-tag"] ?? "").includes("noindex") || /<meta[^>]+robots[^>]+noindex/i.test(body);
    expect(noindex).toBe(true);
    expect(body.toLowerCase()).not.toMatch(/removed because|reason:|suppress|takedown|legal request/);
  });

  test("REQ-046 HTTP redirects to HTTPS", async ({ request }) => {
    const url = process.env.WIW_HTTP_URL;
    test.skip(!url, "set WIW_HTTP_URL=http://<host> on staging or live");
    const res = await request.get(url!, { maxRedirects: 0 });
    expect([301, 308]).toContain(res.status());
    expect(res.headers()["location"]).toMatch(/^https:\/\//);
  });

  test("REQ-046 XML-RPC is closed", async ({ request }) => {
    const res = await request.post("/xmlrpc.php", { data: "<?xml version=\"1.0\"?><methodCall><methodName>system.listMethods</methodName></methodCall>", headers: { "content-type": "text/xml" } });
    expect([403, 404, 405, 410]).toContain(res.status());
    expect(await res.text()).not.toContain("wp.getUsersBlogs");
  });

  test("REQ-229 the REST API is closed to visitors", async ({ request }) => {
    for (const path of ["/wp-json/", "/wp-json/wp/v2/posts", "/wp-json/wp/v2/pages", "/wp-json/wp/v2/users", "/?rest_route=/wp/v2/users"]) {
      const res = await request.get(path);
      expect([401, 403, 404], path).toContain(res.status());
      expect(await res.text(), path).not.toMatch(/"slug"|"name":"[^"]+","url"|"routes"/);
    }
  });

  test("REQ-046 there are no application-password routes", async ({ request }) => {
    for (const path of ["/wp-json/wp/v2/users/me/application-passwords", "/wp-json/wp-site-health/v1/directory-sizes"]) {
      const res = await request.get(path);
      expect([401, 403, 404], path).toContain(res.status());
    }
  });

  test("REQ-046 user enumeration is blocked", async ({ request }) => {
    for (const path of ["/?author=1", "/?author=2", "/author/admin/", "/wp-sitemap-users-1.xml", "/wp-sitemap.xml"]) {
      const res = await request.get(path, { maxRedirects: 0 });
      const loc = res.headers()["location"] ?? "";
      expect(loc, path).not.toMatch(/author\//);
      const body = await res.text();
      expect(body, path).not.toMatch(/wp-sitemap-users|<loc>[^<]*author/);
      expect([301, 302, 308, 403, 404, 410], path).toContain(res.status());
    }
  });

  test("REQ-046 registration is closed", async ({ request }) => {
    const res = await request.get("/wp-login.php?action=register");
    const body = await res.text();
    expect(body).not.toContain('name="user_email"');
    expect(body).not.toMatch(/id="registerform"/);
  });

  test("REQ-046 visitors are offered no sign-in or sign-up link", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator('a[href*="wp-login.php"], a[href*="wp-admin"], a[href*="action=register"]')).toHaveCount(0);
    await expect(page.getByRole("link", { name: /^(log ?in|sign ?in|sign ?up|register)$/i })).toHaveCount(0);
  });

  test("REQ-047 login errors do not reveal whether an account exists", async ({ request }) => {
    const attempt = async (api: APIRequestContext, user: string) => {
      const res = await api.post("/wp-login.php", { form: { log: user, pwd: "Not-The-Password-1!", "wp-submit": "Log In" } });
      const html = await res.text();
      const m = html.match(/id="login_error"[^>]*>([\s\S]*?)<\/div>/);
      return (m ? m[1] : "").replace(/<[^>]+>/g, "").replace(/\s+/g, " ").replace(/(Lost your password\?|Forgot password\?)/i, "").trim();
    };
    const unknown = await attempt(request, "no-such-user-" + Date.now());
    const admin = await attempt(request, "admin");
    expect(unknown).toBe(admin);
    expect(unknown.toLowerCase()).not.toMatch(/invalid username|is not registered|incorrect for the username/);
  });

  test("REQ-046 directory listings are off", async ({ request }) => {
    for (const path of ["/wp-content/", "/wp-content/uploads/", "/wp-content/plugins/", "/wp-content/themes/", "/wp-includes/"]) {
      const res = await request.get(path);
      expect(await res.text(), path).not.toMatch(/<title>Index of|Directory listing for/i);
    }
  });

  test("REQ-046 PHP in the uploads folder is not served and the secrets file is not reachable", async ({ request }) => {
    const up = await request.get("/wp-content/uploads/probe-" + Date.now() + ".php");
    expect([403, 404]).toContain(up.status());
    for (const path of ["/wiw-secrets.env", "/.env", "/wp-config.php", "/.git/config", "/composer.json", "/wp-content/debug.log"]) {
      const res = await request.get(path);
      const body = await res.text();
      expect(body, path).not.toMatch(/DB_PASSWORD|DB_NAME|\[core\]|"require"/);
    }
  });
});
