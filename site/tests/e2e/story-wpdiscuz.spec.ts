import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";

// REQ-258, REQ-043: wpDiscuz on a story wears the theme. The theme's override sheet loads only on single stories while the plugin is
// active, the comment region passes axe (the plugin's own greys and greens fail colour contrast), and there is no tall blank band
// above the form or below the thread. Skips when wpDiscuz is not active on the site under test.
async function storyWithComments(page: import("@playwright/test").Page): Promise<string | null> {
  await page.goto("/stories/");
  const hrefs = await page.locator("main a[href*='/stories/']").evaluateAll((els) => els.map((e) => (e as HTMLAnchorElement).pathname));
  for (const h of hrefs.filter((x) => /^\/stories\/(?!type\/|page\/)[^/]+\/$/.test(x))) {
    await page.goto(h);
    if ((await page.locator("#wpdcom").count()) > 0) return h;
  }
  return null;
}

test.describe("wpDiscuz on brand", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("REQ-258 the override sheet loads on a story with wpDiscuz and not on other pages", async ({ page }) => {
    const path = await storyWithComments(page);
    test.skip(!path, "wpDiscuz is not active on this site");
    await expect(page.locator("link#wiw-wpdiscuz-css")).toHaveCount(1);
    await page.goto("/contact/");
    await expect(page.locator("link#wiw-wpdiscuz-css")).toHaveCount(0);
  });

  test("REQ-043 the comment region passes axe and takes the theme's type", async ({ page }) => {
    const path = await storyWithComments(page);
    test.skip(!path, "wpDiscuz is not active on this site");
    const body = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
    const author = page.locator("#wpdcom .wpd-comment-author").first();
    if ((await author.count()) > 0) {
      expect(await author.evaluate((el) => getComputedStyle(el).fontFamily)).toBe(body);
    }
    await expectNoA11yViolations(page);
  });

  test("REQ-258 no tall blank band around the comment region", async ({ page }) => {
    const path = await storyWithComments(page);
    test.skip(!path, "wpDiscuz is not active on this site");
    const gaps = await page.evaluate(() => {
      const region = document.querySelector("section.comments") as HTMLElement;
      const form = document.querySelector("#wpdcom") as HTMLElement;
      const top = form.getBoundingClientRect().top - region.getBoundingClientRect().top;
      const bottom = region.getBoundingClientRect().bottom - form.getBoundingClientRect().bottom;
      return { top, bottom };
    });
    expect(gaps.top).toBeLessThan(140);
    expect(gaps.bottom).toBeLessThan(140);
  });
});
