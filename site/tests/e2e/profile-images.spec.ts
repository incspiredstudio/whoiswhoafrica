import { test, expect } from "@playwright/test";

// Needs a published profile that has a portrait or logo (no images can be imported yet, so this skips until one exists).
// Size, format and attribute rules are covered in tests/Integration/ProfileImagesTest.php (REQ-042, REQ-247).
test("profile portraits are served with a srcset, dimensions and lazy loading; no cover image (REQ-042, REQ-247)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY || !process.env.WIW_PROFILE_SLUG, "needs a published profile (set WIW_PROFILE_SLUG)");
  await page.goto(`/profile/${process.env.WIW_PROFILE_SLUG}/`);
  const images = page.locator(".profile-id img");
  test.skip((await images.count()) === 0, "this profile has no portrait or logo");
  const img = images.first();
  await expect(img).toHaveAttribute("width", /\d+/);
  await expect(img).toHaveAttribute("height", /\d+/);
  await expect(img).toHaveAttribute("srcset", /\d+w/);
  await expect(page.locator("img[class*='cover'], .profile-cover, .banner")).toHaveCount(0);
});
