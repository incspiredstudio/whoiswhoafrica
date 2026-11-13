import { test, expect } from "@playwright/test";

// The Claim this profile button (REQ-156, REQ-252, REQ-226). Needs WIW_THEME_READY=1. WIW_CLAIM_SLUG names a reviewed, unclaimed
// profile; WIW_CLAIMED_SLUG a claimed one; WIW_PENDING_SLUG one still pending review.
test.describe("claim button", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("a reviewed, unclaimed profile offers the button", async ({ page }) => {
    const slug = process.env.WIW_CLAIM_SLUG;
    test.skip(!slug, "no claimable sample profile");
    await page.goto(`/profile/${slug}/`);
    await expect(page.getByRole("link", { name: "Claim this profile" })).toHaveAttribute("href", new RegExp(`/claim/${slug}/$`));
  });

  test("a claimed profile has no button and no Claimed wording", async ({ page }) => {
    const slug = process.env.WIW_CLAIMED_SLUG;
    test.skip(!slug, "no claimed sample profile");
    await page.goto(`/profile/${slug}/`);
    await expect(page.getByRole("link", { name: "Claim this profile" })).toHaveCount(0);
    await expect(page.getByText(/\bclaimed\b/i)).toHaveCount(0);
  });

  test("a pending-review profile does not offer the claim form", async ({ page }) => {
    const slug = process.env.WIW_PENDING_SLUG;
    test.skip(!slug, "no pending sample profile");
    await page.goto(`/profile/${slug}/`);
    await expect(page.getByRole("link", { name: "Claim this profile" })).toHaveCount(0);
  });
});
