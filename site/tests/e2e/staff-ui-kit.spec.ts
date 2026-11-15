import { mkdirSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Staff UI kit, phase 1 (REQ-275): the Review queue and the Profiles list are shared staff list tables with bulk select, bulk
// actions and row quick actions; Edit profile uses the staff form kit (card sections, sticky save bar, unsaved-changes warning).
// Runs at 360 and 1280 px. Fictional names only; seed-staff-ui.php makes two pending organisations per width and removes them.
// Set WIW_SHOTS_DIR to save screenshots for review. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and
// E2E_STAFF_PASS; skips without them.

type Seed = { ids: number[]; names: string[] };

/**
 * WCAG 2.x A/AA axe check of the screen's own content (#wpbody-content). The WordPress admin menu and toolbar around it are core
 * markup this plugin does not draw (core's "Collapse Menu" button fails label-content-name-mismatch), so they are left out.
 */
async function expectScreenAccessible(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include("#wpbody-content")
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
    .analyze();
  expect(results.violations, results.violations.map((v) => `${v.id}: ${v.help} ${v.nodes.map((n) => n.html).join(" ")}`).join("\n")).toEqual([]);
}

test.describe("staff UI kit", () => {
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
    seed = runHelper<Seed>("seed-staff-ui.php", `w${width}`);
  });
  test.afterAll(({}, info) => {
    runHelper("seed-staff-ui.php", `w${info.project.name.replace(/\D/g, "")}`, "remove");
    removeStaff();
  });

  async function shot(page: Page, name: string): Promise<void> {
    const dir = process.env.WIW_SHOTS_DIR;
    if (!dir) return;
    mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: join(dir, `${name}-${width}.png`), fullPage: name !== "edit-profile-top" });
  }

  test("select two Review rows, bulk approve with the checklist, and see the notice", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_b");
    await page.goto(adminUrl("admin.php?page=wiw-review"));
    const form = page.locator("form.wiw-list-form");
    for (const name of seed.names) await page.getByLabel(`Select ${name}`, { exact: true }).check();
    await expect(form).toHaveClass(/has-selection/);
    await expect(form.locator(".wiw-selected-count")).toHaveText("2 selected");
    await expect(form.locator(".tablenav.top")).toHaveCSS("position", "sticky");
    await shot(page, "review-queue");

    await page.locator("#wiw-bulk-review-items-top").selectOption("approve");
    await form.locator(".tablenav.top").getByRole("button", { name: "Apply" }).click();
    const step = page.locator(".wiw-list-step");
    await expect(step.getByRole("heading", { name: "Approve" })).toBeVisible();
    for (const name of seed.names) await expect(step).toContainText(name);
    for (const box of await step.locator('input[type="checkbox"]').all()) await box.check();
    await shot(page, "review-approve-step");
    await step.getByRole("button", { name: "Approve" }).click();
    await expect(page.locator(".wiw-list-notice")).toContainText("2 approved");
    for (const name of seed.names) await expect(page.getByLabel(`Select ${name}`, { exact: true })).toHaveCount(0);
  });

  test("row actions show on hover and on keyboard focus", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_b");
    await page.goto(adminUrl("admin.php?page=wiw-review"));
    const row = page.locator("tr", { hasText: seed.names[0] });
    const actions = row.locator(".row-actions");
    await expect(actions.getByRole("link", { name: "Review" })).toHaveAttribute("href", /page=wiw-review-item/);
    await expect(actions.getByRole("link", { name: "Approve" })).toHaveAttribute("href", /wiw_confirm=approve/);
    if (width > 782) {
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await row.hover();
      await expect(actions).toHaveCSS("opacity", "1");
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await actions.getByRole("link", { name: "Review" }).focus();
      await expect(actions).toHaveCSS("opacity", "1");
    } else {
      // No hover on a phone: the actions are always shown.
      await expect(actions).toHaveCSS("opacity", "1");
    }
    await expectScreenAccessible(page);
  });

  test("Profiles: rows selected show the bulk bar with Unpublish selected; axe passes", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-profiles&source=e2e-kit"));
    const form = page.locator("form.wiw-list-form");
    await expect(page.locator("#wiw-bulk-profiles-top option[value=unpublish]")).toHaveText("Unpublish selected");
    const row = page.locator("tr", { hasText: seed.names[0] });
    await expect(row.locator(".row-actions").getByRole("link", { name: "Edit" })).toHaveAttribute("href", /page=wiw-profile-edit/);
    await expect(row.locator(".row-actions").getByRole("link", { name: "View" })).toHaveCount(1);
    await expectScreenAccessible(page);
    for (const name of seed.names) await page.getByLabel(`Select ${name}`, { exact: true }).check();
    await expect(form.locator(".wiw-selected-count")).toHaveText("2 selected");
    await shot(page, "profiles");

    // The browser asks before a destructive bulk action; saying no changes nothing.
    page.once("dialog", (dialog) => dialog.dismiss());
    await page.locator("#wiw-bulk-profiles-top").selectOption("unpublish");
    await form.locator(".tablenav.top").getByRole("button", { name: "Apply" }).click();
    await expect(page).toHaveURL(/page=wiw-profiles&source=e2e-kit/);
    await expect(page.locator(".wiw-list-notice")).toHaveCount(0);
  });

  test("Edit profile uses the kit: cards, 40px fields, sticky save bar, unsaved warning; axe passes", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    const editUrl = adminUrl(`admin.php?page=wiw-profile-edit&profile_id=${seed.ids[0]}`);
    await page.goto(editUrl);
    await expect(page.getByRole("heading", { name: "Edit profile", level: 1 })).toBeVisible();
    await expect(page.locator(".wiw-staff .wiw-card").first()).toBeVisible();
    expect(await page.locator(".wiw-staff .wiw-card").count()).toBeGreaterThan(3);
    const reason = page.locator("#wiw-f-reason");
    expect((await reason.boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    await expect(page.locator(".wiw-actions--sticky")).toHaveCSS("position", "sticky");
    await expect(page.locator(".wiw-actions--sticky").getByRole("button", { name: "Save changes" })).toBeVisible();
    await expect(page.locator('input[role="switch"]').first()).toBeAttached();
    await expectScreenAccessible(page);
    await shot(page, "edit-profile");

    // An unsaved change warns before leaving.
    const seen: string[] = [];
    page.on("dialog", async (dialog) => {
      seen.push(dialog.type());
      await dialog.accept();
    });
    await page.locator("#wiw-f-reason").click();
    await page.keyboard.type("E2E kit");
    await page.goto(adminUrl("admin.php?page=wiw-profiles"));
    expect(seen).toContain("beforeunload");

    // After a successful save there is no warning.
    seen.length = 0;
    await page.goto(editUrl);
    await page.locator("#wiw-f-reason").click();
    await page.keyboard.type("E2E kit save");
    await page.locator(".wiw-actions--sticky").getByRole("button", { name: "Save changes" }).click();
    await expect(page.locator(".notice-error, .notice-success").first()).toBeVisible();
    await page.goto(adminUrl("admin.php?page=wiw-profiles"));
    expect(seen).not.toContain("beforeunload");
  });
});
