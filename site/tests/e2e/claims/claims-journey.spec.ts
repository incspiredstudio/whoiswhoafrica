import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import {
  adminUrl,
  loginAs,
  removeStaff,
  runHelper,
  seedStaff,
  staffReady,
  STAFF_SKIP_REASON,
} from "../helpers/staff-users";

// M3-CLM-13: the claim journey in a browser, and a check that the Claimed mark never reaches a public page (REQ-150, REQ-252, REQ-253,
// REQ-256). Fictional records come from tests/e2e/helpers/seed-claims.php and seed-staff.php, which refuse to run outside a local
// environment (the staff users also need WIW_E2E_TEST_MODE=1). The rules at the service level are in tests/Integration
// (ClaimIntake, ClaimDecision, ClaimButton). Email is not sent for real: the site's mail goes to a local address nobody listens on.
// Not covered here: My profiles and the review of a claimed profile's edits by a second user, because those screens are in pull
// requests that are not merged yet (#732, #722, #736); add them to this journey when they land.
// The journeys share one database, so they run once (the 1280 px project), in order, and the records are removed afterwards.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
const SLUG = "e2e-claim-target";
const CLAIMS = () => adminUrl("admin.php?page=wiw-claims");

/** What a visitor can fetch that shows or lists the profile. The claim form itself is left out: it is the place that says "already claimed". */
const PUBLIC_PATHS = [
  `/profile/${SLUG}/`,
  "/directory/",
  "/businesses/",
  `/search/?q=E2E+Claim+Target`,
  "/sitemap-profiles-1.xml",
  "/sitemap.xml",
  "/feed/",
];

async function sendClaim(page: Page): Promise<void> {
  await page.goto(`/claim/${SLUG}/`);
  await page.locator("#f-relationship").selectOption("representative");
  await page.locator("#f-name").fill("Fictional Claimant");
  await page.locator("#f-role").fill("Director");
  await page.locator("#f-work_email").fill("claimant@example.invalid");
  await page
    .locator("#f-evidence")
    .fill("A fictional note for the browser tests.");
  await page.locator("#f-consent").check();
  await page.waitForTimeout(3500); // the form refuses a post made a moment after the page was served
  await page.getByRole("button", { name: "Send claim" }).click();
}

test.describe("claims journey", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs once, in the 1280 px project",
  );
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);

  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("seed-claims.php", "remove");
    runHelper("seed-claims.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-claims.php", "remove");
    removeStaff();
  });

  test("the claim form passes the accessibility scan (REQ-251)", async ({
    page,
  }) => {
    await page.goto(`/claim/${SLUG}/`);
    await expectNoA11yViolations(page);
  });

  test("before any claim the profile offers Claim and no page says Claimed (REQ-150)", async ({
    page,
    request,
  }) => {
    await page.goto(`/profile/${SLUG}/`);
    await expect(
      page.locator(`a[href*='/claim/${SLUG}/']`).first(),
    ).toBeVisible();
    for (const path of PUBLIC_PATHS) {
      const html = await (await request.get(path)).text();
      expect(html, `${path} before the claim`).not.toMatch(/\bclaimed\b/i);
    }
  });

  test("a visitor sends a claim and gets a reference (REQ-251)", async ({
    page,
  }) => {
    await sendClaim(page);
    await expect(page).toHaveURL(/\/done\/\?.*kind=claim/);
    await expect(
      page.getByRole("heading", { name: "Claim received" }),
    ).toBeVisible();
    await expect(page.locator("main")).toContainText(/WIW-|CLM-/);
    expect(page.url()).not.toContain("@");
  });

  test("a Reviewer cannot open Claims, an Editor can and approves the claim (REQ-253, REQ-252)", async ({
    page,
    browser,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    const refused = await page.goto(CLAIMS());
    expect(refused?.status()).toBe(403);
    // A second user needs a second browser session.
    const editor = await (await browser.newContext({ baseURL })).newPage();
    await loginAs(editor, baseURL as string, "editor");
    const opened = await editor.goto(CLAIMS());
    expect(opened?.status()).toBe(200);
    await expect(editor.locator("body")).toContainText("Fictional Claimant");
    await Promise.all([
      editor.waitForURL(/wiw-claims/, { timeout: 60_000 }),
      editor.getByRole("button", { name: "Approve" }).first().click(),
    ]);
    await expect(editor.locator("body")).toContainText(/approved/i);
  });

  test("after approval the Claim button is gone and a second claim is turned away (REQ-252)", async ({
    page,
  }) => {
    await page.goto(`/profile/${SLUG}/`);
    await expect(page.locator(`a[href*='/claim/${SLUG}/']`)).toHaveCount(0);
    // The form is not offered for a profile that is already claimed: the page says so instead.
    const res = await page.goto(`/claim/${SLUG}/`);
    expect(res?.status()).toBe(200);
    await expect(page.locator("form [name=work_email]")).toHaveCount(0);
    await expect(page.locator("main")).toContainText(/already/i);
  });

  test("the Claimed mark is on no public page, card, share tag, sitemap or feed (REQ-150)", async ({
    page,
    request,
  }) => {
    for (const path of PUBLIC_PATHS) {
      const res = await request.get(path);
      expect(res.status(), path).toBeLessThan(500);
      expect(await res.text(), `${path} after the claim`).not.toMatch(
        /\bclaimed\b/i,
      );
    }
    await page.goto(`/profile/${SLUG}/`);
    const shareTags = await page
      .locator(
        "meta[property^='og:'], meta[name^='twitter:'], script[type='application/ld+json']",
      )
      .evaluateAll((nodes) => nodes.map((n) => n.outerHTML).join("\n"));
    expect(shareTags).not.toMatch(/\bclaimed\b/i);
    await expect(page.locator("main")).not.toContainText(/\bclaimed\b/i);
  });
});
