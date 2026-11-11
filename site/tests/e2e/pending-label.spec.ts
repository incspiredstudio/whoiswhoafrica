import { test, expect } from "@playwright/test";

// Needs published profiles in both states. Skips until the directory exists. Server-side rules are in tests/Integration/ProfileCardTest.php.
test("pending label shows only on pending cards (REQ-216)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/directory/");
  test.skip(!res || res.status() >= 400, "directory list does not exist yet");
  const cards = page.locator(".pcard");
  const n = await cards.count();
  test.skip(n === 0, "no profiles to show");
  for (let i = 0; i < n; i++) {
    const card = cards.nth(i);
    const pending = (await card.getAttribute("class"))?.includes("pcard--pending") ?? false;
    await expect(card.locator(".badge--pending")).toHaveCount(pending ? 1 : 0);
  }
});
