import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The search page and the header search (M2-SRC-05; REQ-008, REQ-254, REQ-182). Needs WIW_THEME_READY=1. For the results check set
// WIW_SEARCH_TERM to a word that matches a reviewed profile (the search index must be built).
const term = process.env.WIW_SEARCH_TERM;

test.describe("search page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("an empty search shows the prompt and the public-only note, and is not indexed", async ({ page }) => {
    const res = await page.goto("/search/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("main")).toContainText("Type a name, organisation or keyword");
    await expect(page.locator("main")).toContainText(/public, reviewed profiles/);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a search with no matches shows the no-results text and a link to the directory", async ({ page }) => {
    await page.goto("/search/?q=zzqxjkvv");
    await expect(page.locator("main")).toContainText("Nothing matches that yet");
    await expect(page.locator("main a[href$='/directory/']")).toBeVisible();
    await expect(page.locator(".pcard, .scard")).toHaveCount(0);
    await expectNoA11yViolations(page);
  });

  test("a matching search lists the Profiles group with the count and no events", async ({ page }) => {
    test.skip(!term, "set WIW_SEARCH_TERM");
    await page.goto(`/search/?q=${encodeURIComponent(term as string)}`);
    await expect(page.locator(".search-count")).toContainText(/\d+ results?/);
    await expect(page.getByRole("heading", { name: /^Profiles/ })).toBeVisible();
    await expect(page.locator(".pcard").first()).toBeVisible();
    await expect(page.locator("main a[href*='/events/']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the header panel form and the search page give the same results for the same query", async ({ page }) => {
    test.skip(!term, "set WIW_SEARCH_TERM");
    await page.goto("/");
    const panelForm = page.locator("form.sov-form");
    await expect(panelForm).toHaveAttribute("action", /\/search\/$/);
    await expect(panelForm).toHaveAttribute("method", /get/i);
    await page.goto(`/search/?q=${encodeURIComponent(term as string)}`);
    const viaUrl = await page.locator(".pcard").count();
    await page.goto("/search/");
    await page.locator("#search-page-q").fill(term as string);
    await page.getByRole("button", { name: "Search", exact: true }).click();
    await expect(page).toHaveURL(/\/search\/\?q=/);
    await expect(page.locator(".pcard")).toHaveCount(viaUrl);
  });

  test("the page works with scripts off", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    const res = await page.goto("/search/?q=abc");
    expect(res?.status()).toBe(200);
    await expect(page.locator("form[role=search][method=get]").last()).toBeVisible();
    await context.close();
  });
});
