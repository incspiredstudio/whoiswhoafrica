import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and a published page with the slug about; skips without it.
test("About renders its hero and three pillars, with no draft note for visitors (REQ-260, REQ-040)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/?pagename=about");
  test.skip(!res || res.status() >= 400, "no about page");
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator(".ab-hero .ab-title")).toBeVisible();
  await expect(page.locator(".ab-pillar")).toHaveCount(3);
  await expect(page.locator(".ab-do-list li")).toHaveCount(3);
  await expect(page.locator(".notice--draft")).toHaveCount(0);
  await expect(page.locator("main")).not.toContainText("Delete this note");
  // The Editorial template adds no second band above the hero.
  await expect(page.locator(".page-band")).toHaveCount(0);
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});

test("About at phone width has no sideways scroll (REQ-040)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  await page.setViewportSize({ width: 375, height: 800 });
  const res = await page.goto("/?pagename=about");
  test.skip(!res || res.status() >= 400, "no about page");
  await assertNoHorizontalScroll(page);
});
