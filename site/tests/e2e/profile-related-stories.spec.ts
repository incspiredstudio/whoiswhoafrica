import { test, expect } from "@playwright/test";

// Related stories on a profile page (REQ-028). Needs WIW_THEME_READY=1. WIW_STORY_PROFILE_SLUG names a profile with a linked
// story; WIW_CLAIM_SLUG (any public profile without one) is used for the absent case when WIW_NO_STORY_PROFILE_SLUG is set.
test.describe("profile related stories", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("a profile page lists its linked stories and shows no Sponsored or Featured marker", async ({ page }) => {
    const slug = process.env.WIW_STORY_PROFILE_SLUG;
    test.skip(!slug, "no profile with a linked story");
    await page.goto(`/profile/${slug}/`);
    const section = page.locator("section#stories");
    await expect(section.locator(".scard").first()).toBeVisible();
    await expect(page.locator(".badge--sponsored")).toHaveCount(0);
    await expect(page.getByText(/\bsponsored\b|\bfeatured\b/i)).toHaveCount(0);
  });

  test("the section is absent when no story links the profile", async ({ page }) => {
    const slug = process.env.WIW_NO_STORY_PROFILE_SLUG;
    test.skip(!slug, "no profile without stories");
    await page.goto(`/profile/${slug}/`);
    await expect(page.locator("section#stories")).toHaveCount(0);
  });
});
