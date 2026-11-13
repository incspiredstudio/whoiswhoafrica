import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import { assertNoHorizontalScroll } from "../helpers/page";
import {
  adminUrl,
  loginAs,
  removeStaff,
  runHelper,
  seedStaff,
  staffReady,
  STAFF_SKIP_REASON,
} from "../helpers/staff-users";

// M2-EVT-11: the Events feature in a browser (REQ-262, REQ-264, REQ-265, REQ-244). Fictional records come from
// tests/e2e/helpers/seed-events.php and seed-staff.php, which refuse to run outside a local environment (the staff users also need
// WIW_E2E_TEST_MODE=1). The rules at the service level are in tests/Integration (EventPageTest, EventComponentsTest, EventsAdminTest,
// EventSeoTest, RelatedEventTest). The journeys share one database, so they run once (the 1280 px project), in order, and the
// records are removed afterwards. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see
// site/README.md); skips without them.
const seed = (cmd = "seed") => runHelper("seed-events.php", cmd);

test.describe("events journeys", () => {
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
    seed("remove");
    seed();
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seed("show");
    seed("revoke");
    seed("remove");
    removeStaff();
  });

  test("an Editor creates and publishes an event, and it appears on the list and on its own page (REQ-262)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("post-new.php?post_type=wiw_event"));
    await page.locator("#title").fill("E2E Event Created By Editor");
    await page.locator("#wiw_start").fill("2027-03-04T10:00");
    await page.locator("#wiw_city").fill("Accra");
    await page.locator("#wiw_country").fill("Ghana");
    await page.locator("#wiw_organiser").fill("Fictional Organiser");
    await Promise.all([
      page.waitForURL(/post\.php\?post=\d+/, { timeout: 60_000 }),
      page.locator("#publish").click(),
    ]);
    await expect(
      page.locator("#message.updated, .notice-success").first(),
    ).toBeVisible();
    await page.goto("/events/");
    await expect(page.getByText("E2E Event Created By Editor")).toBeVisible();
    await page.getByText("E2E Event Created By Editor").first().click();
    await expect(page.locator("h1")).toHaveText("E2E Event Created By Editor");
    await expect(page.locator("main")).toContainText("Accra");
  });

  test("a Reviewer without the grant is refused the Events screen, and opens it once granted (REQ-244)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    const refused = await page.goto(adminUrl("edit.php?post_type=wiw_event"));
    expect(
      refused?.status() === 403 ||
        (await page.locator("body").innerText()).match(
          /not allowed|do not have permission/i,
        ) !== null,
    ).toBe(true);
    seed("grant");
    // A change of capabilities ends the user's sessions, so sign in again to see the new access.
    await loginAs(page, baseURL as string, "reviewer_a");
    const opened = await page.goto(adminUrl("edit.php?post_type=wiw_event"));
    expect(opened?.status()).toBe(200);
    await expect(page.locator("#wpbody-content")).toContainText(/events/i);
    seed("revoke");
  });

  test("the list puts upcoming events first, rolls past ones over, tags Online, and never shows a draft (REQ-264)", async ({
    page,
  }) => {
    await page.goto("/events/");
    const body = page.locator("main");
    await expect(body).toContainText("E2E Event Upcoming");
    await expect(body).toContainText("E2E Event Online");
    await expect(body).toContainText("E2E Event Past");
    await expect(body).not.toContainText("E2E Event Draft");
    const text = await body.innerText();
    expect(text.indexOf("E2E Event Upcoming")).toBeLessThan(
      text.indexOf("E2E Event Past"),
    );
    expect(text.indexOf("E2E Event Online")).toBeLessThan(
      text.indexOf("E2E Event Past"),
    );
    await expect(
      page.locator(".ag-row", { hasText: "E2E Event Online" }),
    ).toContainText(/online/i);
    await expect(
      page.locator(".ag-row", { hasText: "E2E Event Past" }),
    ).toContainText(/past|ended/i);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
    expect((await page.request.get("/events/e2e-evt-draft/")).status()).toBe(
      404,
    );
  });

  test("a story shows its related event only when one is set (REQ-265)", async ({
    page,
  }) => {
    await page.goto("/e2e-evt-story-linked/");
    await expect(page.locator("main")).toContainText("E2E Event Upcoming");
    await page.goto("/e2e-evt-story-plain/");
    await expect(page.locator("main")).not.toContainText("E2E Event");
  });

  test("an event page has structured data that matches it, is in the sitemap, and has no payment or sign-up form (REQ-262, REQ-264)", async ({
    page,
    request,
  }) => {
    await page.goto("/events/e2e-evt-upcoming/");
    const ld = JSON.parse(
      (await page
        .locator("script[type='application/ld+json']")
        .first()
        .textContent()) as string,
    );
    expect(ld["@type"]).toBe("Event");
    expect(ld.name).toBe(await page.locator("h1").innerText());
    await expect(page.locator("main form")).toHaveCount(0);
    await expect(page.locator("main")).not.toContainText(
      /pay now|buy a ticket|checkout/i,
    );
    const link = page.getByRole("link", { name: /register|details/i }).first();
    await expect(link).toHaveAttribute(
      "href",
      /^https:\/\/organiser\.example\//,
    );
    const sitemap = await (await request.get("/sitemap-events.xml")).text();
    expect(sitemap).toContain("/events/e2e-evt-upcoming/");
    expect(sitemap).not.toContain("/events/e2e-evt-draft/");
  });

  test("events are not in site search (REQ-262)", async ({ page }) => {
    await page.goto("/search/?q=E2E+Event+Upcoming");
    await expect(page.locator("main a[href*='/events/']")).toHaveCount(0);
  });

  test("the home Events block is there with upcoming events and gone without them (REQ-264)", async ({
    page,
  }) => {
    await page.goto("/");
    await expect(page.locator(".h-events")).toHaveCount(1);
    await expect(page.locator(".h-events")).toContainText("E2E Event Upcoming");
    seed("hide");
    await page.goto("/");
    await expect(page.locator(".h-events")).toHaveCount(0);
    seed("show");
  });
});
