import { test, expect } from "@playwright/test";
import { adminUrl, cleanupSupport, loginAs, removeStaff, resetRateLimits, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M3-ENQ-14: the contact flow from the public form to the staff Enquiries screen (REQ-122, REQ-157, REQ-244). The requester is
// fictional (the address ends in @example.invalid, which cleanup-support.php removes at the end; it and the staff seeds refuse to
// run outside a local environment with WIW_E2E_TEST_MODE=1). The journeys share one database, so they run once, in order (the
// 1280 px project). Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips
// without them. The /get-listed/ page has no enquiry form on the site under test yet, and the profile enquiry flow needs the
// Enquiries switch on a claimed profile, so those two are not covered here (see the pull request).

test.describe("contact and enquiry flow", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  let reference = "";
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    resetRateLimits();
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    cleanupSupport();
    removeStaff();
    resetRateLimits();
  });

  test("a visitor with no account sends a message and sees the received page (REQ-157)", async ({ page }) => {
    await page.goto("/contact/");
    await expect(page.locator("form.form [name=wiw_website]")).toHaveCount(1); // the honeypot field is in the form
    await page.locator("[name=name]").fill("E2E Enquirer");
    await page.locator("[name=email]").fill("e2e.enquiry@example.invalid");
    await page.locator("[name=message]").fill("E2E: how do I get my organisation listed?");
    await page.waitForTimeout(3500); // the form's own minimum fill time
    await page.getByRole("button", { name: /send message/i }).click();
    await expect(page).toHaveURL(/\/done\//);
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    reference = new URL(page.url()).searchParams.get("ref") ?? "";
    expect(reference).not.toBe("");
  });

  test("a message that fills the honeypot is not kept (REQ-122)", async ({ page, request }) => {
    await page.goto("/contact/");
    const token = await page.locator("[name=wiw_form_token]").inputValue();
    await page.waitForTimeout(3500);
    const action = (await page.locator("form.form").getAttribute("action")) ?? "/contact/";
    const res = await request.post(new URL(action, page.url()).toString(), {
      form: { name: "Bot", email: "bot.enquiry@example.invalid", message: "E2E honeypot message", wiw_website: "http://spam.example.invalid/", wiw_form_token: token },
      maxRedirects: 0,
    });
    expect(res.status()).toBeLessThan(500);
  });

  test("the enquiry appears on the Enquiries screen; an Editor sets its status and adds a private note (REQ-244)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-enquiries"));
    const row = page.locator("table.widefat tbody tr", { hasText: "E2E Enquirer" });
    await expect(row).toHaveCount(1);
    await expect(page.locator("table.widefat tbody tr", { hasText: "bot.enquiry" })).toHaveCount(0);
    await row.getByRole("link").first().click();
    await expect(page.locator(".wrap")).toContainText("how do I get my organisation listed");
    await page.locator("select[name=status]").selectOption({ label: "in_progress" }).catch(async () => page.locator("select[name=status]").selectOption("in_progress"));
    await page.getByRole("button", { name: "Save", exact: true }).click();
    await page.locator("textarea[name=note]").fill("E2E internal note: reply with the Get listed guide");
    await page.getByRole("button", { name: "Add note" }).click();
    await expect(page.locator(".wrap")).toContainText("reply with the Get listed guide");
  });

  test("a Reviewer, who has no support inbox capability, cannot open the Enquiries screen (REQ-244)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    const res = await page.goto(adminUrl("admin.php?page=wiw-enquiries"));
    expect(res?.status()).toBe(403);
  });
});
