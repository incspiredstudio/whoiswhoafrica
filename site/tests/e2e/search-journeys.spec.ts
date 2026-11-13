import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M2-SRC-07: the search journeys in a browser (REQ-208, REQ-254, REQ-183, REQ-209): a name search, a story-word search that never returns a
// draft story, and unpublishing a profile then searching again with no delay. Records are fictional and come
// from tests/e2e/helpers/seed-review.php, seed-stories.php and seed-staff.php (they refuse to run outside a local environment with
// WIW_E2E_TEST_MODE=1). The journeys share one database, so they run once, in order (the 1280 px project).
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
// The visibility matrix for the search surface is tests/Visibility/SearchVisibilityTest.php; the empty, no-results and header
// parity cases are in search-page.spec.ts.

test.describe("search journeys", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("seed-review.php");
    runHelper("seed-stories.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-review.php", "remove");
    runHelper("seed-stories.php", "remove");
    removeStaff();
  });

  test("a name search lists the reviewed profile and is accessible (REQ-254)", async ({ page }) => {
    await page.goto("/search/?q=E2E+Bulk+One");
    await expect(page.locator(".search-count")).toContainText(/\d+ results?/);
    await expect(page.locator(".pcard", { hasText: "E2E Bulk One" })).toHaveCount(1);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a story-word search lists the published story and never the draft (REQ-208, REQ-254)", async ({ page }) => {
    await page.goto("/search/?q=E2E+Story");
    await expect(page.locator("main a[href$='/stories/e2e-story-newest/']").first()).toBeVisible();
    await expect(page.locator("main a[href$='/stories/e2e-story-draft/']")).toHaveCount(0);
    await expect(page.locator("main")).not.toContainText("E2E Story Draft");
  });

  test("unpublishing a profile removes it from the very next search (REQ-209)", async ({ page, baseURL }) => {
    await page.goto("/search/?q=E2E+Bulk+Two");
    await expect(page.locator(".pcard", { hasText: "E2E Bulk Two" })).toHaveCount(1);

    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-profiles&name=E2E+Bulk+Two"));
    await page.getByRole("link", { name: /unpublish/i }).first().click();
    await expect(page).toHaveURL(/wiw_bulk=unpublished/);

    // No wait and no job run: the next search already leaves it out.
    const context = await page.context().browser()!.newContext();
    const visitor = await context.newPage();
    await visitor.goto(`${baseURL}/search/?q=E2E+Bulk+Two`);
    await expect(visitor.locator(".pcard", { hasText: "E2E Bulk Two" })).toHaveCount(0);
    await context.close();
  });
});
