import { test, expect, type Page } from "@playwright/test";
import { loginAs, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 798 item 2: signed-in staff see the WordPress admin bar (32 px, 46 px below 783 px; on phones it scrolls away). The site
// header, the mega menu, the drawer, the search overlay and anchor scrolling must sit below it, never under it. Needs
// WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS; skips without them. Runs at 360 and 1280 px.

type Box = { top: number; bottom: number };

const box = (page: Page, selector: string): Promise<Box> =>
  page.locator(selector).first().evaluate((el) => {
    const r = el.getBoundingClientRect();
    return { top: Math.round(r.top), bottom: Math.round(r.bottom) };
  });

/** The visible bottom edge of the admin bar (it scrolls away on phones). */
const barBottom = async (page: Page): Promise<number> => Math.max(0, (await box(page, "#wpadminbar")).bottom);

async function signIn(page: Page, baseURL: string): Promise<void> {
  for (let attempt = 0; ; attempt++) {
    try {
      await loginAs(page, baseURL, "editor");
      return;
    } catch (error) {
      if (attempt >= 12) throw error;
      await page.waitForTimeout(2500);
    }
  }
}

/** Open a page as the signed-in editor. If the other project's seed reset the password meanwhile, sign in again. */
async function visit(page: Page, path: string): Promise<void> {
  await page.goto(path);
  if ((await page.locator("#wpadminbar").count()) === 0) {
    await signIn(page, new URL(page.url()).origin);
    await page.goto(path);
  }
}

test.describe("admin bar does not cover the header (issue 798)", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 360 && viewport?.width !== 1280, "runs at 360 and 1280 px");
  test.setTimeout(90_000);
  // The seed is idempotent and the throw-away users live only in the scratch database; the 360 and 1280 projects may start together.
  // Only the 1280 px project seeds (a second seed would reset the passwords and end the other project's session); the 360 px
  // project signs in once the users exist.
  test.beforeAll(({}, info) => {
    if (info.project.name === "chromium-1280") seedStaff();
  });
  test.beforeEach(async ({ page, baseURL }) => {
    await signIn(page, baseURL as string);
  });

  for (const path of ["/", "/stories/"]) {
    test(`header sits under the bar at the top of ${path} and after scrolling`, async ({ page }) => {
      await visit(page, path);
      await expect(page.locator("#wpadminbar")).toBeVisible();
      const bar = await barBottom(page);
      expect(bar).toBeGreaterThanOrEqual(32);
      const head = await box(page, "header.site-head");
      expect(head.top, "header starts below the admin bar").toBeGreaterThanOrEqual(bar);
      // The logo and the first control are not clipped behind the bar.
      expect((await box(page, "header.site-head .logo")).top).toBeGreaterThanOrEqual(bar);
      await page.evaluate(() => window.scrollTo(0, 900));
      await page.waitForTimeout(400);
      const after = await barBottom(page);
      const scrolled = await box(page, "header.site-head");
      if (path === "/" && scrolled.bottom <= after) return; // the home header slid away while reading down: it is under the bar, which is the point
      expect(scrolled.top, "the pinned header stays below whatever part of the bar is still on screen").toBeGreaterThanOrEqual(after);
      expect(scrolled.top).toBeLessThanOrEqual(after + 1);
    });
  }

  test("the home header hides on scroll without leaving a strip, and returns below the bar", async ({ page }) => {
    await visit(page, "/");
    await page.evaluate(() => window.scrollTo(0, 1600));
    await page.waitForTimeout(300);
    await page.evaluate(() => window.scrollTo(0, 2200));
    await page.waitForTimeout(600);
    const bar = await barBottom(page);
    const head = await box(page, "header.site-head");
    expect(head.bottom, "hidden header is fully out of view").toBeLessThanOrEqual(bar);
    await page.evaluate(() => window.scrollTo(0, 2000));
    await page.waitForTimeout(700);
    const back = await box(page, "header.site-head");
    expect(back.top).toBeGreaterThanOrEqual(await barBottom(page));
  });

  test("anchor scrolling leaves room for the bar and the header", async ({ page }) => {
    await visit(page, "/stories/");
    const need = await page.evaluate(() => {
      const bar = document.getElementById("wpadminbar");
      const head = document.querySelector("header.site-head");
      return (bar ? bar.getBoundingClientRect().height : 0) + (head ? head.getBoundingClientRect().height : 0);
    });
    const pad = await page.evaluate(() => parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop));
    expect(pad).toBeGreaterThanOrEqual(need - 1);
  });

  test("the mega menu opens below the header, not under the bar", async ({ page }) => {
    test.skip((page.viewportSize()?.width ?? 0) < 1024, "mega menu is for wide screens");
    await visit(page, "/stories/");
    await page.locator("nav.nav-top").getByRole("button", { name: "Directory" }).click();
    await expect(page.locator("#mega-directory")).toHaveClass(/open/);
    const mega = await box(page, "#mega-directory");
    const head = await box(page, "header.site-head");
    expect(mega.top).toBeGreaterThanOrEqual(head.bottom - 1);
    expect(mega.top).toBeGreaterThanOrEqual(await barBottom(page));
  });

  test("the drawer and the search overlay open below the bar on phones", async ({ page }) => {
    test.skip((page.viewportSize()?.width ?? 0) >= 1024, "the drawer is for phones");
    await visit(page, "/stories/");
    const bar = await barBottom(page);
    await page.locator("header.site-head").getByRole("button", { name: /menu/i }).click();
    await expect(page.locator("#drawer")).toHaveClass(/open/);
    await page.waitForTimeout(500);
    expect((await box(page, "#drawer")).top, "drawer starts below the bar").toBeGreaterThanOrEqual(bar);
    expect((await box(page, "#drawer .drawer-head")).top).toBeGreaterThanOrEqual(bar);
    await page.keyboard.press("Escape");
    await expect(page.locator("#drawer")).not.toHaveClass(/open/);
    await page.locator("header.site-head .search-link").click();
    await expect(page.locator("#searchOv")).toHaveClass(/open/);
    await page.waitForTimeout(500);
    expect((await box(page, "#searchOv .sov-top")).top).toBeGreaterThanOrEqual(bar);
  });

  test("a visitor without the bar sees no offset", async ({ browser, baseURL }) => {
    const context = await browser.newContext({ baseURL: baseURL as string });
    const page = await context.newPage();
    await page.goto("/stories/");
    await expect(page.locator("#wpadminbar")).toHaveCount(0);
    expect((await box(page, "header.site-head")).top).toBe(0);
    await context.close();
  });
});
