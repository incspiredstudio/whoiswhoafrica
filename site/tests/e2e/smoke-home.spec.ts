import { test } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

for (const path of ["/", "/wp-login.php"]) {
  test(`REQ-043 ${path} loads, does not scroll sideways and passes axe`, async ({ page }) => {
    // The home page is checked once the whoiswho-child theme is active (set WIW_THEME_READY=1); the default theme on a fresh site has its own axe failures.
    test.skip(path === "/" && !process.env.WIW_THEME_READY, "theme not active yet");
    const res = await page.goto(path);
    test.expect(res?.ok()).toBeTruthy();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
}
