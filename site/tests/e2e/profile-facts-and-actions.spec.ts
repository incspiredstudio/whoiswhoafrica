import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a published profile (set WIW_PROFILE_SLUG). Facts, the claim rule and the contact slot are covered server-side in
// tests/Integration/ProfileFactsAndRailTest.php (REQ-144, REQ-133, REQ-142, REQ-156, REQ-006). With WIW_PROFILE_PENDING_SLUG set
// to a profile that is pending review, the Claim button is also checked to be absent.
const slug = process.env.WIW_PROFILE_SLUG;
const pending = process.env.WIW_PROFILE_PENDING_SLUG;

test.describe("profile facts and actions", () => {
  test.skip(!process.env.WIW_THEME_READY || !slug, "needs a published profile (set WIW_PROFILE_SLUG)");

  test("each key fact shows a source link and a retrieval date (REQ-144)", async ({ page }) => {
    await page.goto(`/profile/${slug}/`);
    const rows = page.locator("table.facts tbody tr");
    test.skip((await rows.count()) === 0, "the profile has no sourced facts");
    for (const row of await rows.all()) {
      await expect(row.locator(".src a[href^='http']")).toHaveCount(1);
      await expect(row.locator(".src time[datetime]")).toHaveCount(1);
    }
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the rail offers correct, remove and report, and the When shared preview (REQ-142, REQ-006)", async ({ page }) => {
    await page.goto(`/profile/${slug}/`);
    const rail = page.locator(".profile-rail");
    await expect(rail.locator("a[data-action='correct']")).toHaveAttribute("href", new RegExp(`/correct/${slug}/$`));
    await expect(rail.locator("a[data-action='remove']")).toHaveAttribute("href", new RegExp(`/remove/${slug}/$`));
    await expect(rail.locator("a[data-action='report']")).toHaveAttribute("href", new RegExp(`/report/${slug}/$`));
    await expect(page.locator(".profile-rail .rail-share")).toHaveCount(1);
  });

  test("a profile pending review has no Claim button (REQ-156)", async ({ page }) => {
    test.skip(!pending, "set WIW_PROFILE_PENDING_SLUG to a pending profile");
    await page.goto(`/profile/${pending}/`);
    await expect(page.locator("a[data-action='correct']")).toHaveCount(1);
    await expect(page.locator("a[data-action='claim']")).toHaveCount(0);
  });

  test("no related-stories or award block appears when there is no data (REQ-142)", async ({ page }) => {
    await page.goto(`/profile/${slug}/`);
    const stories = page.locator("#related-stories");
    if ((await stories.count()) > 0) {
      expect(await stories.locator("li").count()).toBeGreaterThan(0);
    }
    const award = page.locator("#award");
    if ((await award.count()) > 0) {
      expect((await award.innerText()).trim().length).toBeGreaterThan(0);
    }
  });
});
