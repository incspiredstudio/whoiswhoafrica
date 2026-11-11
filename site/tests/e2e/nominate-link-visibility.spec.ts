import { test, expect } from "@playwright/test";

// While the Nominations flag is off (the default on a clean install) no Nominate link appears in the header, drawer or footer (REQ-259).
// The open state is covered server-side in tests/Integration/HeaderTest.php, which flips the flag; the browser spec checks the default.
test("no Nominate link in the header, drawer or footer while nominations are closed (REQ-259)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  await page.goto("/");
  await expect(page.locator("header.site-head a[href*='/nominate']")).toHaveCount(0);
  await expect(page.locator("#drawer a[href*='/nominate']")).toHaveCount(0);
  await expect(page.locator("footer.site-foot a[href*='/nominate']")).toHaveCount(0);
});
