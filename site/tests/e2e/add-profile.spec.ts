import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 798 (REQ-209, REQ-208, REQ-145, REQ-267): an editor adds a profile by hand from Directory > Add profile. The new profile is
// in the review queue and not public; adding the same name again shows the possible duplicate first; and the Nominations
// "Create profile" shortcut comes back to the Nominations screen with the new profile offered. Fictional names only. Needs
// WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS; skips without them.
test.describe("add a profile by hand", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("cleanup-added-profiles.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("cleanup-added-profiles.php");
    removeStaff();
  });

  const stamp = Date.now().toString(36);

  async function fill(page: Page, name: string | null): Promise<void> {
    await expect(page.getByRole("heading", { name: "Add profile", level: 1 })).toBeVisible();
    await page.selectOption("#wiw-add-type", "individual");
    await page.getByRole("button", { name: "Continue" }).click();
    if (name !== null) await page.fill("#wiw-f-name", name);
    const country = page.locator("#wiw-f-country");
    const first = await country.locator("option").nth(1).getAttribute("value");
    await country.selectOption(first as string);
    await page.fill("#wiw-f-f_current_title", "Composer");
    await page.fill("#wiw-f-f_current_organisation", "Fictional Orchestra");
    await page.fill("#wiw-f-f_bio", "A fictional composer.\n\nSecond paragraph.");
    await page.fill("#wiw-f-f_legacy", "A fictional school of music.");
    await page.fill("#wiw-f-sources", "https://example.org/fictional-source");
    await page.getByRole("button", { name: "Save profile" }).click();
  }

  test("an editor adds a profile; it is not public and a second copy is flagged", async ({ page, baseURL }) => {
    const name = `E2E Addp Composer ${stamp}`;
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-profile-add"));
    await fill(page, name);
    await expect(page.locator(".notice-success")).toContainText("review queue");

    // It is listed for staff, not public.
    await page.goto(adminUrl(`admin.php?page=wiw-profiles&name=${encodeURIComponent(name)}`));
    const row = page.locator("tr", { hasText: name });
    await expect(row).toContainText("imported_not_public");

    // The same name again: the possible duplicate is shown before anything is saved.
    await page.goto(adminUrl("admin.php?page=wiw-profile-add"));
    await fill(page, name);
    await expect(page.locator(".wiw-add-duplicates")).toContainText(name);
    await expect(page.locator("#wiw-f-name")).toHaveValue(name);
    await expect(page.getByLabel(/Create anyway/)).toBeVisible();

    // The public site does not list it: no result links to it (the search box itself echoes the query).
    await page.goto(`/search/?q=${encodeURIComponent(name)}`);
    await expect(page.getByRole("link", { name })).toHaveCount(0);
  });

  test("the Nominations shortcut creates the nominee and comes back", async ({ page, baseURL }) => {
    const name = `E2E Addp Nominee ${stamp}`;
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl(`admin.php?page=wiw-nominations&profile_q=${encodeURIComponent(name)}`));
    await page.locator("a.wiw-create-profile").click();
    await fill(page, null);
    await expect(page).toHaveURL(/page=wiw-nominations/);
    await expect(page.locator("body")).toContainText("Choose it below to add it to the nominations");
    await expect(page.locator('select[name="profile_id"]')).toContainText(name);
  });
});
