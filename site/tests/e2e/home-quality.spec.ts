import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Home quality (M2-HOM-13): the home page is a complete, accessible static page in every mode. Needs a site whose active theme is
// whoiswho-child (WIW_THEME_READY=1) and a built dist/. The four widths are the four Playwright projects.
test.describe("home quality (REQ-002, REQ-040, REQ-043, REQ-044)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("passes axe and does not scroll sideways at this width (REQ-043, REQ-040)", async ({ page }) => {
    await page.goto("/");
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("with reduced motion the page passes axe and scripts add no reveal state (REQ-044)", async ({ browser }) => {
    const context = await browser.newContext({ reducedMotion: "reduce" });
    const page = await context.newPage();
    await page.goto("/");
    await expect(page.locator("html")).not.toHaveClass(/js-motion/);
    await expectNoA11yViolations(page);
    await context.close();
  });

  test("with scripts off the page still has a heading and a way in (REQ-002)", async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    const page = await context.newPage();
    await page.goto("/");
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("main a[href]").first()).toBeVisible();
    // axe needs scripts, so the accessibility check for this mode is the one above plus the visible heading and link here.
    await context.close();
  });

  test("the header does not flash on a refresh either (REQ-046)", async ({ page }) => {
    await page.goto("/");
    await page.reload();
    await page.mouse.move(600, 600);
    await expect
      .poll(() => page.locator(".site-head").evaluate((e) => getComputedStyle(e).backgroundColor), { timeout: 5000 })
      .toMatch(/rgba\(0, 0, 0, 0\)|transparent|\/ 0\)/);
  });

  test("the page has no awards band or awards button while Nominations is off (REQ-002)", async ({ page }) => {
    await page.goto("/");
    // The header menu may name Awards; the page body must not push the awards or nominations.
    await expect(page.locator("main .awards-band, main [data-awards-marquee], main a[href*='/nominate']")).toHaveCount(0);
  });

  test("a missing optional block leaves no empty shell (REQ-002)", async ({ page }) => {
    await page.goto("/");
    for (const section of await page.locator("main section").all()) {
      const text = (await section.innerText()).trim();
      const hasMedia = (await section.locator("img, video, svg, picture").count()) > 0;
      expect(text.length > 0 || hasMedia, "a section with no text and no media").toBeTruthy();
    }
  });
});
