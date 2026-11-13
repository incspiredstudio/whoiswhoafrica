import { test, expect } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, runJobs, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M1-REV-12: the review flows in a browser (REQ-084, REQ-148, REQ-222, REQ-193). All records are fictional and are created by
// tests/e2e/helpers/seed-staff.php and seed-review.php, which refuse to run outside a local environment with WIW_E2E_TEST_MODE=1.
// The journeys share one database, so they run once (the 1280 px project), in order, and the records are removed afterwards.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
// The same rules at the service level are in tests/Integration (ReviewService, DuplicateMerge, BulkUnpublish).

type Seed = { org: string; orgId: number; dup: number[]; bulk: string[] };

test.describe("review flows", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000); // sign-in and admin pages are slow on a single-threaded local PHP server
  let fixture: Seed;
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    fixture = runHelper<Seed>("seed-review.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-review.php", "remove");
    removeStaff();
  });

  const itemUrl = () => adminUrl(`admin.php?page=wiw-review-item&profile_id=${fixture.orgId}`);

  test("a pending organisation is noindex, labelled and not in the sitemap before review (REQ-084, REQ-208)", async ({ request }) => {
    const res = await request.get(`/profile/${fixture.org}/`);
    expect(res.status()).toBe(200);
    const html = await res.text();
    expect(html).toContain('<meta name="robots" content="noindex">');
    expect(html).toContain("notice--pending");
    const sitemap = await (await request.get("/sitemap-profiles-1.xml")).text();
    expect(sitemap).not.toContain(`/profile/${fixture.org}/`);
  });

  test("the importer is blocked from approving, with the reason on screen (REQ-148)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    await page.goto(itemUrl());
    await expect(page.getByText("You imported this record, so another reviewer must approve it.")).toBeVisible();
    for (const box of await page.locator("input[type=checkbox][name^=c]").all()) await box.check();
    await page.getByRole("button", { name: "Approve", exact: true }).click();
    await expect(page.locator(".notice-error")).toContainText("cannot approve");
  });

  test("a second reviewer approves; page, header label and sitemap change together (REQ-084, REQ-208)", async ({ page, request, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_b");
    await page.goto(itemUrl());
    // The checklist is required: approving with nothing ticked is refused.
    await page.getByRole("button", { name: "Approve", exact: true }).click();
    await expect(page.locator(".notice-error")).toContainText("Tick all five points");
    for (const box of await page.locator("input[type=checkbox][name^=c]").all()) await box.check();
    await page.getByRole("button", { name: "Approve", exact: true }).click();
    await expect(page.locator(".notice-success")).toBeVisible();

    const res = await request.get(`/profile/${fixture.org}/`);
    expect(res.status()).toBe(200);
    const html = await res.text();
    expect(html).not.toContain('<meta name="robots" content="noindex">');
    expect(html).not.toContain("notice--pending");
    const sitemap = await (await request.get("/sitemap-profiles-1.xml")).text();
    expect(sitemap).toContain(`/profile/${fixture.org}/`);
  });

  test("a duplicate is merged by hand and the dropped page redirects to the kept one (REQ-222)", async ({ page, request, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_b");
    await page.goto(adminUrl("admin.php?page=wiw-duplicates"));
    await expect(page.getByText("E2E Dup Alpha / E2E Dup Alpha Ltd")).toBeVisible();
    await page.getByLabel("Reason").first().fill("Same organisation, two spellings");
    await page.getByRole("button", { name: "Merge" }).click();
    await expect(page).toHaveURL(/result=merged/);
    await page.goto(adminUrl("admin.php?page=wiw-duplicates"));
    await expect(page.getByText("No open duplicates.")).toBeVisible();
    runJobs(); // the public cache is purged by a queued job
    const kept = await request.get("/profile/e2e-dup-alpha/");
    // The page that was merged away no longer shows a profile: it sends visitors to the one that was kept (301).
    const dropped = await request.get("/profile/e2e-dup-alpha-ltd/", { maxRedirects: 0 });
    expect(kept.status()).toBe(200);
    expect(dropped.status()).toBe(301);
    expect(dropped.headers()["location"]).toContain("/profile/e2e-dup-alpha/");
  });

  test("bulk unpublish shows the count and names, answers 410 for each, then Restore this batch returns 200 (REQ-193)", async ({ page, request, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_b");
    await page.goto(adminUrl("admin.php?page=wiw-profiles&source=e2e-bulk"));
    await expect(page.locator(".wiw-bulk-count")).toContainText("3 published profiles match the filter");
    const sentence = page.locator(".wiw-bulk-confirmation");
    await expect(sentence).toContainText("You are about to unpublish 3 profiles");
    for (const name of ["E2E Bulk One", "E2E Bulk Two", "E2E Bulk Three"]) await expect(sentence).toContainText(name);
    // A reason is required.
    await page.locator(".wiw-bulk-unpublish input[name=reason]").fill("Fictional batch withdrawn for the browser test");
    await page.getByRole("button", { name: "Unpublish these profiles" }).click();
    await expect(page.locator(".wiw-bulk-unpublish .notice-info")).toContainText("Bulk unpublish started");
    runJobs();
    for (const slug of fixture.bulk) expect((await request.get(`/profile/${slug}/`)).status(), slug).toBe(410);

    await page.goto(adminUrl("admin.php?page=wiw-profiles&source=e2e-bulk"));
    await page.getByRole("link", { name: "Restore this batch" }).first().click();
    await expect(page.locator(".wiw-bulk-unpublish .notice-info")).toContainText("Restore started");
    runJobs();
    for (const slug of fixture.bulk) expect((await request.get(`/profile/${slug}/`)).status(), slug).toBe(200);
  });
});
