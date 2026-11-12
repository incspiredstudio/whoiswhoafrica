import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Key facts with sources and the right rail (REQ-144, REQ-133, REQ-142, REQ-156, REQ-006). Needs a published profile
// (set WIW_PROFILE_SLUG). Server-side rules are in tests/Integration/ProfileFactsRailTest.php.
const slug = process.env.WIW_PROFILE_SLUG;

test.describe("profile key facts and actions", () => {
  test.skip(!process.env.WIW_THEME_READY || !slug, "needs a published profile (set WIW_PROFILE_SLUG)");

  test("every key fact shows a source link and a retrieval date", async ({ page }) => {
    await page.goto(`/profile/${slug}/`);
    const rows = page.locator("table.facts tbody tr");
    const n = await rows.count();
    test.skip(n === 0, "profile has no sourced facts");
    for (let i = 0; i < n; i++) {
      await expect(rows.nth(i).locator("a[href^='http']")).toHaveCount(1);
      await expect(rows.nth(i).locator("time[datetime]")).toHaveCount(1);
    }
  });

  test("the rail offers the actions, no email or phone, and no share preview", async ({ page }) => {
    await page.goto(`/profile/${slug}/`);
    const rail = page.locator("aside.profile-rail");
    await expect(rail.getByRole("link", { name: "Suggest a correction" })).toHaveAttribute("href", new RegExp(`/correct/${slug}/`));
    await expect(rail.getByRole("link", { name: "Request removal" })).toHaveAttribute("href", new RegExp(`/remove/${slug}/`));
    await expect(rail.getByRole("link", { name: "Report this profile" })).toHaveAttribute("href", new RegExp(`/report/${slug}/`));
    await expect(rail.locator("a[href^='mailto:'], a[href^='tel:']")).toHaveCount(0);
    await expect(page.getByText("When shared")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
});
