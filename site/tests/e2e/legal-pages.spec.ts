import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1) and published pages with the slugs privacy, terms and cookies.
// Those pages are created by the editable-pages issues; until they exist this spec skips each path that answers 404.
for (const slug of ["privacy", "terms", "cookies"]) {
  test(`legal page ${slug} shows the draft note (REQ-100, REQ-248)`, async ({ page }) => {
    test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
    const res = await page.goto(`/?pagename=${slug}`);
    test.skip(res?.status() === 404, `page ${slug} does not exist yet`);
    await expect(page.locator(".notice--draft")).toHaveCount(1);
    if (slug === "cookies") await expect(page.locator("[data-cookie-prefs]")).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
}
