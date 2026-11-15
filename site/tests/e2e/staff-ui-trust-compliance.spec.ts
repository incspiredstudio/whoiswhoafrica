import { mkdirSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Staff UI phase 2, Trust and Compliance (REQ-275): Claims, Enquiries, Requests and reports, Corrections and the Suppression list
// are shared staff list tables with bulk select, bulk actions and row quick actions, and their detail pages and forms use the staff
// form kit. Runs at 360 and 1280 px. Fictional rows only; seed-staff-ui-tc.php makes them per width and removes them. Set
// WIW_SHOTS_DIR to save screenshots for review. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and
// E2E_STAFF_PASS; skips without them.

type Seed = { claims: string[]; enquiries: string[]; reports: string[]; corrections: string[]; suppressed: string };

/** WCAG 2.x A/AA axe check of the screen's own content (#wpbody-content); core's admin menu is left out (G63). */
async function expectScreenAccessible(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include("#wpbody-content")
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
    .analyze();
  expect(results.violations, results.violations.map((v) => `${v.id}: ${v.help} ${v.nodes.map((n) => n.html).join(" ")}`).join("\n")).toEqual([]);
}

test.describe("staff UI, Trust and Compliance", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => ![360, 1280].includes(viewport?.width ?? 0), "runs in the 360 and 1280 px projects");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);

  let seed: Seed;
  let width = 0;

  test.beforeAll(() => {
    seedStaff();
  });
  test.beforeEach(({ viewport }) => {
    width = viewport?.width ?? 0;
    seed = runHelper<Seed>("seed-staff-ui-tc.php", `w${width}`);
  });
  test.afterAll(({}, info) => {
    runHelper("seed-staff-ui-tc.php", `w${info.project.name.replace(/\D/g, "")}`, "remove");
    removeStaff();
  });

  async function shot(page: Page, name: string): Promise<void> {
    const dir = process.env.WIW_SHOTS_DIR;
    if (!dir) return;
    mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: join(dir, `${name}-${width}.png`), fullPage: true });
  }

  test("Corrections: select two rows, bulk Mark resolved, and see the notice", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-corrections&status=new"));
    const form = page.locator("form.wiw-list-form");
    for (const ref of seed.corrections) await page.getByLabel(`Select ${ref}`, { exact: true }).check();
    await expect(form).toHaveClass(/has-selection/);
    await expect(form.locator(".wiw-selected-count")).toHaveText("2 selected");
    await shot(page, "corrections");
    await expectScreenAccessible(page);

    await page.locator("#wiw-bulk-corrections-top").selectOption("resolve");
    await form.locator(".tablenav.top").getByRole("button", { name: "Apply" }).click();
    await expect(page.locator(".wiw-list-notice")).toContainText("2 marked resolved");
    await expect(page).toHaveURL(/status=new/);
    for (const ref of seed.corrections) await expect(page.getByLabel(`Select ${ref}`, { exact: true })).toHaveCount(0);
  });

  test("Claims: row actions show on hover and on keyboard focus; Reject asks for the shared reason", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-claims"));
    const row = page.locator("tr", { hasText: seed.claims[0] });
    const actions = row.locator(".row-actions");
    await expect(actions.getByRole("link", { name: "View" })).toHaveAttribute("href", /page=wiw-claims&claim=\d+/);
    await expect(actions.getByRole("link", { name: "Approve" })).toHaveAttribute("href", /wiw_confirm=approve/);
    if (width > 782) {
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await row.hover();
      await expect(actions).toHaveCSS("opacity", "1");
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await actions.getByRole("link", { name: "View" }).focus();
      await expect(actions).toHaveCSS("opacity", "1");
    } else {
      await expect(actions).toHaveCSS("opacity", "1");
    }
    await expectScreenAccessible(page);
    await shot(page, "claims");

    await actions.getByRole("link", { name: "Reject" }).click();
    const step = page.locator(".wiw-list-step");
    await expect(step.getByRole("heading", { name: "Reject" })).toBeVisible();
    await expect(step).toContainText(seed.claims[0]);
    await expect(step.getByLabel("Reason")).toHaveAttribute("required", "required");
    await expectScreenAccessible(page);
  });

  test("Claim page uses the kit: cards, 40px reason field, decision buttons; axe passes", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-claims"));
    await page.locator("tr", { hasText: seed.claims[1] }).getByRole("link", { name: seed.claims[1], exact: true }).click();
    await expect(page.getByRole("heading", { name: seed.claims[1], level: 1 })).toBeVisible();
    expect(await page.locator(".wiw-staff .wiw-card").count()).toBeGreaterThanOrEqual(3);
    expect((await page.locator("#wiw-f-claim-reason").boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    await expect(page.getByRole("button", { name: "Approve and create account" })).toBeVisible();
    await expect(page.locator("section[aria-labelledby=wiw-claim-reject] .wiw-actions").getByRole("button", { name: "Reject" })).toBeVisible();
    await expectScreenAccessible(page);
    await shot(page, "claim-detail");
  });

  test("Enquiries, Requests and reports: list and detail pass axe", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    const screens: Array<[string, string]> = [
      ["wiw-enquiries", seed.enquiries[0]],
      ["wiw-requests", seed.reports[0]],
    ];
    for (const [slug, ref] of screens) {
      await page.goto(adminUrl(`admin.php?page=${slug}`));
      const row = page.locator("tr", { hasText: ref });
      await expect(row.getByLabel(`Select ${ref}`, { exact: true })).toHaveCount(1);
      await expect(row.locator(".row-actions").getByRole("link", { name: "Open" })).toHaveAttribute("href", /item=\d+/);
      await expectScreenAccessible(page);
      await row.getByRole("link", { name: ref, exact: true }).click();
      await expect(page).toHaveURL(/item=\d+/);
      await expect(page.locator(".wiw-staff .wiw-card").first()).toBeVisible({ timeout: 15_000 });
      await expectScreenAccessible(page);
    }
    await shot(page, "request-detail");
  });

  test("Corrections detail and the Suppression list pass axe; the add form uses the kit", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-corrections"));
    await page.locator("tr", { hasText: seed.corrections[0] }).getByRole("link", { name: seed.corrections[0], exact: true }).click();
    await expect(page.locator("section[aria-labelledby=wiw-correction-decline]").getByLabel("Reason (kept private)")).toBeVisible();
    await expectScreenAccessible(page);

    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-suppression"));
    await expect(page.locator("#wiw-suppression-add")).toBeVisible();
    expect((await page.locator("#wiw-f-name").boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    const row = page.locator("tr", { hasText: seed.suppressed });
    await expect(row.locator(".row-actions").getByRole("link", { name: "Remove" })).toHaveAttribute("href", /wiw_confirm=remove/);
    await expectScreenAccessible(page);
    await shot(page, "suppression");
  });
});
