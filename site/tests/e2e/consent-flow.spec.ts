import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { resetRateLimits, runHelper } from "./helpers/staff-users";

// The complimentary-listing consent answers on the single-use link page (M3-ENQ-13; REQ-175, REQ-233). Needs a local site with
// WIW_E2E_TEST_MODE=1 (the seed helper makes a fictional complimentary profile and its two links) and WIW_THEME_READY=1.
type Links = { invite: string; accept: string; decline: string };

test.describe("consent page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  test.skip(!process.env.WIW_E2E_TEST_MODE, "needs WIW_E2E_TEST_MODE=1 for the seed helper");

  test.setTimeout(60000);

  test.beforeEach(() => {
    resetRateLimits(); // every page view counts toward the per-address allowance
  });

  test.afterAll(() => {
    runHelper("seed-invitation.php", "remove");
  });

  test("the agree link explains the complimentary listing and records consent once", async ({ page }) => {
    const { accept } = runHelper<Links>("seed-invitation.php");
    await page.goto(accept);
    await expect(page.getByRole("heading", { level: 1, name: "Agree to a complimentary listing" })).toBeVisible();
    await expect(page.getByText("Nothing is public until you agree and a reviewer has approved the profile")).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
    await page.getByRole("button", { name: "I agree" }).click();
    await expect(page.getByRole("status").filter({ hasText: "Consent recorded" })).toBeVisible();
    await expect(page.getByText("not public until a reviewer approves it")).toBeVisible();
    await page.goto(accept);
    await expect(page.getByRole("heading", { name: "This link has expired or was already used" })).toBeVisible();
  });

  test("the decline link records the choice and says the profile will not be published", async ({ page }) => {
    const { decline } = runHelper<Links>("seed-invitation.php");
    await page.goto(decline);
    await expect(page.getByRole("heading", { level: 1, name: "Decline a complimentary listing" })).toBeVisible();
    await page.getByRole("button", { name: "I decline" }).click();
    await expect(page.getByRole("status").filter({ hasText: "Choice recorded" })).toBeVisible();
    await expect(page.getByText("The profile will not be published")).toBeVisible();
  });
});
