import { test, expect, type Page, type BrowserContext } from "@playwright/test";

// Cookie and analytics consent before launch (M5-OPS-06; REQ-062, REQ-248, REQ-078). A fresh browser must hold no cookie and run
// no third-party or analytics script before Accept; Decline keeps it that way; Accept adds only the one first-party choice cookie
// and then allows first-party analytics. Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1).
const PAGES = ["/", "/directory/", "/stories/", "/events/", "/contact/", "/get-listed/", "/data-rights/", "/cookies/"];
const CHOICE_COOKIE = "wiw_consent";

/** Every cookie the browser holds, plus every Set-Cookie header the site sent on the way. */
async function watchCookies(page: Page): Promise<string[]> {
  const sent: string[] = [];
  page.on("response", async (res) => {
    const headers = await res.headersArray().catch(() => []);
    for (const h of headers) if (h.name.toLowerCase() === "set-cookie") sent.push(h.value.split("=")[0]);
  });
  return sent;
}

function watchRequests(page: Page, origin: string): { foreign: string[]; scripts: string[] } {
  const seen = { foreign: [] as string[], scripts: [] as string[] };
  page.on("request", (req) => {
    const url = req.url();
    if (url.startsWith("data:") || url.startsWith("blob:")) return;
    if (new URL(url).origin !== origin) seen.foreign.push(url);
    if (req.resourceType() === "script") seen.scripts.push(url);
  });
  return seen;
}

async function names(context: BrowserContext): Promise<string[]> {
  return (await context.cookies()).map((c) => c.name).sort();
}

test.describe("consent and cookies (REQ-062, REQ-248, REQ-078)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  test.setTimeout(120_000); // eight cold page loads on a slow local server

  test("a fresh visit to the public pages sets no cookie and loads nothing from another origin", async ({ page, context, baseURL }) => {
    await context.clearCookies();
    const sent = await watchCookies(page);
    const seen = watchRequests(page, new URL(baseURL as string).origin);
    for (const path of PAGES) {
      const res = await page.goto(path);
      expect(res?.status(), path).toBe(200);
    }
    expect(await names(context), "cookies held before any choice").toEqual([]);
    expect(sent, "Set-Cookie headers sent before any choice").toEqual([]);
    expect(seen.foreign, "requests to another origin before any choice").toEqual([]);
  });

  test("the cookie card appears only after a little scroll on a long page, with Decline and Accept", async ({ page, context }) => {
    await context.clearCookies();
    await page.goto("/");
    const card = page.locator(".cookie[data-cookie]");
    await expect(card).toBeHidden();
    await page.evaluate(() => window.scrollTo(0, 600));
    await expect(card).toBeVisible();
    await expect(card.getByRole("button", { name: "Decline" })).toBeVisible();
    await expect(card.getByRole("button", { name: "Accept" })).toBeVisible();
    expect(await names(context), "showing the card sets no cookie").toEqual([]);
  });

  test("no analytics script runs before Accept or after Decline, and the only cookie is the choice", async ({ page, context, baseURL }) => {
    await context.clearCookies();
    const analytics = new URL("/analytics-test.js", baseURL as string).toString();
    let requested = 0;
    await page.route(analytics, (route) => {
      requested++;
      return route.fulfill({ contentType: "application/javascript", body: "" });
    });
    await page.goto("/");
    await page.evaluate((url) => {
      (window as unknown as { wiwConsent: { analytics: string } }).wiwConsent = { analytics: url };
    }, analytics);
    await page.evaluate(() => window.scrollTo(0, 600));
    await page.locator(".cookie").getByRole("button", { name: "Decline" }).click();
    await page.reload();
    await page.goto("/directory/");
    expect(requested, "analytics requested after Decline").toBe(0);
    const held = await context.cookies();
    expect(held.map((c) => c.name)).toEqual([CHOICE_COOKIE]);
    expect(held[0].value).toBe("essential");
  });

  test("Accept adds only the first-party choice cookie and then allows analytics", async ({ page, context, baseURL }) => {
    await context.clearCookies();
    const analytics = new URL("/analytics-test.js", baseURL as string).toString();
    let requested = 0;
    await page.route(analytics, (route) => {
      requested++;
      return route.fulfill({ contentType: "application/javascript", body: "" });
    });
    await page.goto("/");
    expect(requested, "analytics requested before Accept").toBe(0);
    await page.evaluate((url) => {
      (window as unknown as { wiwConsent: { analytics: string } }).wiwConsent = { analytics: url };
    }, analytics);
    await page.evaluate(() => window.scrollTo(0, 600));
    await page.locator(".cookie").getByRole("button", { name: "Accept" }).click();
    await expect.poll(() => requested).toBe(1);
    const held = await context.cookies();
    expect(held.map((c) => c.name)).toEqual([CHOICE_COOKIE]);
    expect(held[0].value).toBe("all");
    expect(held[0].httpOnly, "the choice cookie is readable by the page script that applies it").toBe(false);
    expect(held[0].sameSite).not.toBe("None");
  });

  test("with JavaScript off no script runs, no cookie is set and the card stays hidden", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/");
    await expect(page.locator(".cookie")).toBeHidden();
    expect(await names(context)).toEqual([]);
    await context.close();
  });
});
