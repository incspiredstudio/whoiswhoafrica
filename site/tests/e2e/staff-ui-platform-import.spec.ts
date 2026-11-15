import { mkdirSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Staff UI kit, phase 2, group C (REQ-275): the Platform and Import screens. Import history is a shared staff list table with
// bulk Undo selected and Open and Undo row actions; the Audit, Email and Purge logs are read-only list tables (no checkboxes);
// Required plugins has bulk Install and Activate; Setup's key form uses the form kit. Runs at 360 and 1280 px. Made-up batch ids
// only; seed-staff-ui-platform.php makes two applied, empty batches and one email log row per width and removes them.
// Set WIW_SHOTS_DIR to save screenshots for review. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and
// E2E_STAFF_PASS; skips without them.

type Seed = { batches: string[] };

/** WCAG 2.x A/AA axe check of the screen's own content (#wpbody-content); the core admin menu around it is left out (G63). */
async function expectScreenAccessible(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include("#wpbody-content")
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
    .analyze();
  expect(results.violations, results.violations.map((v) => `${v.id}: ${v.help} ${v.nodes.map((n) => n.html).join(" ")}`).join("\n")).toEqual([]);
}

test.describe("staff UI kit: Platform and Import screens", () => {
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
    seed = runHelper<Seed>("seed-staff-ui-platform.php", `w${width}`);
  });
  test.afterAll(({}, info) => {
    runHelper("seed-staff-ui-platform.php", `w${info.project.name.replace(/\D/g, "")}`, "remove");
    removeStaff();
  });

  async function shot(page: Page, name: string): Promise<void> {
    const dir = process.env.WIW_SHOTS_DIR;
    if (!dir) return;
    mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: join(dir, `${name}-${width}.png`), fullPage: true });
  }

  test("Import history: select two batches, bulk Undo selected after the browser asks, and see the notice", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-import"));
    const form = page.locator("form.wiw-list-form");
    for (const batch of seed.batches) await page.getByLabel(`Select ${batch}`, { exact: true }).check();
    await expect(form).toHaveClass(/has-selection/);
    await expect(form.locator(".wiw-selected-count")).toHaveText("2 selected");
    await shot(page, "import");

    const asked: string[] = [];
    page.once("dialog", async (dialog) => {
      asked.push(dialog.message());
      await dialog.accept();
    });
    await page.locator("#wiw-bulk-import-batches-top").selectOption("undo");
    await form.locator(".tablenav.top").getByRole("button", { name: "Apply" }).click();
    await expect(page.locator(".wiw-list-notice")).toContainText("2 undone");
    expect(asked[0]).toContain("Undo selected");
    for (const batch of seed.batches) {
      const row = page.locator("tr", { hasText: batch });
      await expect(row.locator(".wiw-pill")).toHaveText("undone");
      await expect(page.getByLabel(`Select ${batch}`, { exact: true })).toBeVisible();
      await expect(row.locator(".row-actions").getByRole("link", { name: "Undo" })).toHaveCount(0);
    }
  });

  test("row actions show on hover and on keyboard focus (Import history and Email log)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-import"));
    const row = page.locator("tr", { hasText: seed.batches[0] });
    const actions = row.locator(".row-actions");
    await expect(actions.getByRole("link", { name: "Open" })).toHaveAttribute("href", /page=wiw-import&batch=/);
    await expect(actions.getByRole("link", { name: "Undo" })).toHaveAttribute("href", /wiw_confirm=undo/);
    if (width > 782) {
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await row.hover();
      await expect(actions).toHaveCSS("opacity", "1");
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await actions.getByRole("link", { name: "Open" }).focus();
      await expect(actions).toHaveCSS("opacity", "1");
    } else {
      await expect(actions).toHaveCSS("opacity", "1");
    }

    // The row Undo opens the confirmation step for that one batch.
    await actions.getByRole("link", { name: "Undo" }).click();
    const step = page.locator(".wiw-list-step");
    await expect(step.getByRole("heading", { name: "Undo selected" })).toBeVisible();
    await expect(step).toContainText(seed.batches[0]);
    await expectScreenAccessible(page);
    await step.getByRole("link", { name: "Cancel" }).click();
    await expect(page.locator("tr", { hasText: seed.batches[0] }).locator(".wiw-pill")).toHaveText("applied");

    await page.goto(adminUrl("admin.php?page=wiw-email-log&event=e2e.staff_ui"));
    const mail = page.locator("tr.wiw-failed", { hasText: "e2e.staff_ui" });
    await expect(mail.locator(".row-actions").getByRole("link", { name: "Record bounce" })).toHaveAttribute("href", /action=wiw_email_record/);
    await expect(page.locator('input[name="ids[]"]')).toHaveCount(0);
    await expect(page.locator(".wiw-bulk-select")).toHaveCount(0);
    await shot(page, "email-log");
  });

  test("Setup's Turnstile form uses the form kit", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-setup"));
    const spam = page.locator("#wiw-item-turnstile");
    test.skip((await spam.locator("form.wiw-turnstile-form").count()) === 0, "the keys are set in the secrets file on this site");
    const field = spam.locator(".wiw-field", { has: page.locator("#wiw-turnstile-site-key") });
    await expect(field.locator("label")).toHaveText("Site key");
    expect((await page.locator("#wiw-turnstile-site-key").boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    expect((await page.locator("#wiw-turnstile-secret").boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    await expect(spam.locator(".wiw-actions").getByRole("button", { name: "Save Turnstile keys" })).toBeVisible();
    await expect(page.locator("#wiw-turnstile-secret-note")).toBeVisible();
    await expectScreenAccessible(page);
    await shot(page, "setup");
  });

  const screens: Array<[string, string]> = [
    ["Import", "admin.php?page=wiw-import"],
    ["Audit log", "admin.php?page=wiw-audit-log"],
    ["Email log", "admin.php?page=wiw-email-log"],
    ["Purge log", "admin.php?page=wiw-purge-log"],
    ["Sources", "admin.php?page=wiw-sources"],
    ["Required plugins", "admin.php?page=wiw-required-plugins"],
  ];
  for (const [name, path] of screens) {
    test(`${name}: kit markup and axe passes`, async ({ page, baseURL }) => {
      await loginAs(page, baseURL as string, "admin");
      await page.goto(adminUrl(path));
      await expect(page.locator(".wrap.wiw-staff h1")).toHaveText(name);
      await expect(page.locator("table.wiw-list-table").first()).toBeVisible();
      if (["Audit log", "Email log", "Purge log", "Sources"].includes(name)) {
        await expect(page.locator('input[name="ids[]"]')).toHaveCount(0);
      }
      await expectScreenAccessible(page);
      if (name === "Audit log" || name === "Required plugins") await shot(page, name.toLowerCase().replace(/ /g, "-"));
    });
  }
});
