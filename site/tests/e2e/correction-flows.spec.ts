import { test, expect, type Page } from "@playwright/test";
import { adminUrl, cleanupSupport, loginAs, removeStaff, resetRateLimits, runHelper, runJobs, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M1-COR-17: correction, removal and report flows from the public form to the staff decision (REQ-157, REQ-156, REQ-158,
// REQ-090, REQ-233, REQ-232). Profiles and requesters are fictional: the profiles come from tests/e2e/helpers/seed-review.php and
// the requester addresses end in @example.invalid, which cleanup-support.php removes at the end (these helpers refuse outside a
// local environment with WIW_E2E_TEST_MODE=1). The journeys share one database, so they run once, in order (the 1280 px project).
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
// The rules at the service level are in tests/Integration/Compliance (RemovalDecisions, RequestsScreen, CorrectionsScreen).

const CORRECT = "e2e-bulk-one";
const REMOVE = "e2e-bulk-two";
const REPORT = "e2e-bulk-three";

/** The reference of the received request, from the received page address (the page itself does not print generated references yet). */
async function referenceOnPage(page: Page): Promise<string> {
  const ref = new URL(page.url()).searchParams.get("ref") ?? "";
  expect(ref, "the received page address carries the reference").toMatch(/^WIW-[A-Z0-9]{4,}$/);
  return ref;
}

async function sendForm(page: Page, button: RegExp): Promise<void> {
  await page.waitForTimeout(3500); // the form's own minimum fill time
  await page.getByRole("button", { name: button }).click();
}

test.describe("correction, removal and report flows", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  let correctionRef = "";
  let removalRef = "";
  let reportRef = "";
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("seed-review.php");
    resetRateLimits();
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    cleanupSupport();
    runHelper("seed-review.php", "remove");
    removeStaff();
    resetRateLimits();
  });
  test.beforeEach(() => resetRateLimits());

  test("a visitor sends a correction without an account and gets a reference (REQ-157, REQ-233)", async ({ page }) => {
    await page.goto(`/correct/${CORRECT}/`);
    await page.locator("[name=relationship]").selectOption("person");
    await page.locator("[name=need]").selectOption("correct_fact");
    await page.locator("[name=field]").fill("Head office city");
    await page.locator("[name=new_value]").fill("Abuja");
    await page.locator("[name=evidence]").fill("https://example.invalid/e2e-evidence");
    await page.locator("[name=name]").fill("E2E Requester One");
    await page.locator("[name=email]").fill("e2e.correction@example.invalid");
    await sendForm(page, /send request/i);
    await expect(page).toHaveURL(/\/done\//);
    correctionRef = await referenceOnPage(page);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
  });

  test("the correction appears on the Corrections screen; Apply opens the edit with the reference as the reason and resolves it (REQ-158, REQ-232)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-corrections"));
    const row = page.locator("table.widefat tbody tr", { hasText: correctionRef });
    await expect(row).toHaveCount(1);
    await expect(row).toContainText("E2E Bulk One");
    await expect(row).toContainText("new");
    await row.getByRole("link", { name: correctionRef }).click();
    // Everything the requester sent is on the detail, for staff only.
    for (const text of ["E2E Requester One", "e2e.correction@example.invalid", "Abuja"]) await expect(page.locator(".wrap")).toContainText(text);
    await page.getByRole("link", { name: /apply/i }).click();
    await expect(page.locator("input[name=reason]").first()).toHaveValue(correctionRef);
    await expect(page.locator("select[name=resolves]")).not.toHaveValue("0");
    await page.locator("input[name=f_org_type]").fill("Company"); // the fictional profile was seeded without its required field
    await page.locator("input[name=f_hq_city]").fill("Abuja");
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(page.locator(".notice-success")).toContainText("Saved");
    await page.goto(adminUrl("admin.php?page=wiw-corrections"));
    await expect(page.locator("table.widefat tbody tr", { hasText: correctionRef })).toContainText("resolved");
  });

  test("a decline needs a reason, which is never shown on the public site (REQ-157)", async ({ page, baseURL, request }) => {
    await page.goto(`/correct/${CORRECT}/`);
    await page.locator("[name=relationship]").selectOption("someone_else");
    await page.locator("[name=need]").selectOption("correct_fact");
    await page.locator("[name=new_value]").fill("E2E a claim without a source");
    await page.locator("[name=name]").fill("E2E Requester Two");
    await page.locator("[name=email]").fill("e2e.declined@example.invalid");
    await sendForm(page, /send request/i);
    await expect(page).toHaveURL(/\/done\//);
    const ref = await referenceOnPage(page);
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-corrections"));
    await page.locator("table.widefat tbody tr", { hasText: ref }).getByRole("link", { name: ref }).click();
    // The reason field is required by the form itself, and the service refuses a blank one.
    await expect(page.locator("form input[name=reason][required]").last()).toBeVisible();
    await page.locator("form input[name=reason]").last().fill("E2E private reason: the source is a forum post");
    await page.getByRole("button", { name: "Decline" }).click();
    await page.goto(adminUrl("admin.php?page=wiw-corrections"));
    await expect(page.locator("table.widefat tbody tr", { hasText: ref })).toContainText("declined");
    const html = await (await request.get(`/profile/${CORRECT}/`)).text();
    expect(html.includes("private reason"), "the reason is not on the profile page").toBe(false);
    expect(html.includes(ref), "the reference is not on the profile page").toBe(false);
  });

  test("a removal request from the subject is upheld: the profile answers 410 and the name is suppressed (REQ-156, REQ-090)", async ({ page, baseURL, request }) => {
    await page.goto(`/remove/${REMOVE}/`);
    await page.locator("[name=relationship]").selectOption("person");
    await page.locator("[name=reason]").fill("E2E please remove this fictional profile");
    await page.locator("[name=name]").fill("E2E Requester Three");
    await page.locator("[name=email]").fill("e2e.removal@example.invalid");
    await sendForm(page, /request removal/i);
    await expect(page).toHaveURL(/\/done\//);
    removalRef = await referenceOnPage(page);
    expect((await request.get(`/profile/${REMOVE}/`)).status(), "still public until a person decides").toBe(200);

    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-requests"));
    await page.locator("table.widefat tbody tr", { hasText: removalRef }).getByRole("link", { name: removalRef }).click();
    await page.getByRole("button", { name: /valid: hide and suppress/i }).click();
    runJobs();
    expect((await request.get(`/profile/${REMOVE}/`)).status()).toBe(410);
    await page.goto(adminUrl("admin.php?page=wiw-suppression"));
    await expect(page.locator(".wrap")).toContainText("E2E Bulk Two");
  });

  test("a report is received and the profile can be hidden at once by staff (REQ-090, REQ-233)", async ({ page, baseURL, request }) => {
    await page.goto(`/report/${REPORT}/`);
    await page.locator("[name=reason]").first().selectOption({ index: 1 });
    await page.locator("[name=details]").fill("E2E this fictional profile is wrong");
    await page.locator("[name=email]").fill("e2e.report@example.invalid");
    await sendForm(page, /send report/i);
    await expect(page).toHaveURL(/\/done\//);
    reportRef = await referenceOnPage(page);

    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-requests"));
    await page.locator("table.widefat tbody tr", { hasText: reportRef }).getByRole("link", { name: reportRef }).click();
    await page.getByRole("button", { name: /hide the profile now/i }).click();
    runJobs();
    expect((await request.get(`/profile/${REPORT}/`)).status()).not.toBe(200);
  });
});
