import { test, expect, type Page } from "@playwright/test";
import { runHelper } from "./helpers/staff-users";

// The admin menus a non-coder sees (REQ-244, REQ-084, #810): one way to add a profile (Directory > Add profile; WordPress's own
// Profiles > Add profile is gone and its address leads there), detail screens (Edit profile, Review item) are not menu items but
// open from their lists with their title, and the review screens use words, not codes. Needs E2E_USER and E2E_PASS for an
// Administrator; a pending profile comes from helpers/seed-profiles.php when WIW_SECRETS_FILE is set.
const { E2E_USER, E2E_PASS } = process.env;
const admin = (path: string): string => "/wordpress/wp-admin/" + path;

async function login(page: Page): Promise<void> {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", E2E_USER as string);
  await page.fill("#user_pass", E2E_PASS as string);
  await page.click("#wp-submit");
  await expect(page).toHaveURL(/wp-admin/);
}

test.describe("plain admin menus (REQ-244)", () => {
  test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");

  test("one Add profile, no detail screens in the menu, words instead of codes", async ({ page }) => {
    test.setTimeout(120_000);
    if (process.env.WIW_SECRETS_FILE) {
      try {
        runHelper("seed-profiles.php");
      } catch {
        // Not a local site: the review part checks whatever is in the queue.
      }
    }
    await login(page);
    await page.goto(admin("index.php"), { waitUntil: "domcontentloaded" });

    const menu = page.locator("#adminmenu");
    await expect(menu.locator("a[href*='page=wiw-profile-add']")).toHaveCount(1);
    await expect(menu.locator("a[href*='post-new.php?post_type=wiw_profile']")).toHaveCount(0);
    // One Profiles item, under Directory: WordPress's own top-level Profiles menu is gone (issue 806).
    await expect(menu.locator("#menu-posts-wiw_profile")).toHaveCount(0);
    await expect(menu.locator("a[href*='edit.php?post_type=wiw_profile']")).toHaveCount(0);
    await expect(menu.locator("a[href*='page=wiw-profile-edit']")).toHaveCount(0);
    await expect(menu.locator("a[href*='page=wiw-review-item']")).toHaveCount(0);
    await expect(menu).not.toContainText("Edit profile");
    await expect(menu).not.toContainText("Review item");

    await page.goto(admin("post-new.php?post_type=wiw_profile"), { waitUntil: "domcontentloaded" });
    await expect(page).toHaveURL(/page=wiw-profile-add/);

    await page.goto(admin("admin.php?page=wiw-review"), { waitUntil: "domcontentloaded" });
    const table = page.locator(".wrap table", { has: page.locator("th", { hasText: "Kind" }) }).first();
    await expect(table).not.toContainText("imported_not_public");
    await expect(table).not.toContainText("published_pending");
    const first = table.locator("tbody a[href*='page=wiw-review-item']").first();
    test.skip((await first.count()) === 0, "nothing in the review queue");
    await first.click();
    await expect(page).toHaveURL(/page=wiw-review-item/);
    await expect(page).toHaveTitle(/Review item/);
    await expect(menu.locator("li#toplevel_page_wiw-menu-review.wp-has-current-submenu")).toHaveCount(1);
    const wrap = page.locator(".wrap").first();
    await expect(wrap).not.toContainText(/individual \/|organisation \/|imported_not_public|published_pending/);
    await expect(wrap.locator("select[name=reason_code] option[value=out_of_scope]")).toHaveText(/Out of scope/);
  });
});
