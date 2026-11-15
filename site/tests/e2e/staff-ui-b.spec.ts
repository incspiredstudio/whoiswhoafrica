import { mkdirSync } from "node:fs";
import { join } from "node:path";
import AxeBuilder from "@axe-core/playwright";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON, type StaffRole } from "./helpers/staff-users";

// Staff UI phase 2, group B (REQ-275): the Content, Commerce, Awards and Review staff screens use the shared list table and form
// kit. Nominations and Award categories have bulk select, bulk actions and row quick actions; Customers, Sponsored stories,
// Subscribers, the nomination history and Recent bulk actions are lists without checkboxes; the forms are kit cards. Runs at 360
// and 1280 px. Fictional names only; seed-staff-ui-b.php makes one category and two nominations per width and removes them.
// Set WIW_SHOTS_DIR to save screenshots for review. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and
// E2E_STAFF_PASS; skips without them.

type Seed = { category: number; nominations: number[]; profiles: number[]; names: string[] };

/** WCAG 2.x A/AA axe check of the screen's own content (#wpbody-content), as in staff-ui-kit.spec.ts (G63). */
async function expectScreenAccessible(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include("#wpbody-content")
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"])
    .analyze();
  expect(results.violations, results.violations.map((v) => `${v.id}: ${v.help} ${v.nodes.map((n) => n.html).join(" ")}`).join("\n")).toEqual([]);
}

test.describe("staff UI group B", () => {
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
    seed = runHelper<Seed>("seed-staff-ui-b.php", `w${width}`);
  });
  test.afterAll(({}, info) => {
    runHelper("seed-staff-ui-b.php", `w${info.project.name.replace(/\D/g, "")}`, "remove");
    removeStaff();
  });

  async function shot(page: Page, name: string): Promise<void> {
    const dir = process.env.WIW_SHOTS_DIR;
    if (!dir) return;
    mkdirSync(dir, { recursive: true });
    await page.screenshot({ path: join(dir, `${name}-${width}.png`), fullPage: true });
  }

  const nominationsUrl = () => adminUrl(`admin.php?page=wiw-nominations&category=${seed.category}`);

  test("Nominations: select two rows, bulk Set stage to Winner in the confirmation step, and see the notice", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(nominationsUrl());
    const form = page.locator("form.wiw-list-form");
    for (const name of seed.names) await page.getByLabel(`Select ${name}`, { exact: true }).check();
    await expect(form).toHaveClass(/has-selection/);
    await expect(form.locator(".wiw-selected-count")).toHaveText("2 selected");
    await shot(page, "nominations");

    await page.locator("#wiw-bulk-nominations-top").selectOption("stage");
    await form.locator(".tablenav.top").getByRole("button", { name: "Apply" }).click();
    const step = page.locator(".wiw-list-step");
    await expect(step.getByRole("heading", { name: "Set stage" })).toBeVisible();
    for (const name of seed.names) await expect(step).toContainText(name);
    await step.getByLabel("Stage").selectOption("winner");
    await step.getByRole("button", { name: "Set stage" }).click();
    await expect(page.locator(".wiw-list-notice")).toContainText("2 stages saved");
    for (const name of seed.names) {
      const row = page.locator("tr", { hasText: name });
      // Below 782 px WordPress folds the other columns away until the row is opened, so the chooser may be hidden.
      await expect(row.getByRole("combobox", { name: `Stage for ${name}`, includeHidden: true })).toHaveValue("winner");
    }
  });

  test("Nominations: row actions show on hover and on keyboard focus; the inline chooser still saves", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(nominationsUrl());
    const row = page.locator("tr", { hasText: seed.names[0] });
    const actions = row.locator(".row-actions");
    await expect(actions.getByRole("link", { name: "Details" })).toHaveAttribute("href", /page=wiw-nomination&/);
    await expect(actions.getByRole("link", { name: "Remove" })).toHaveAttribute("href", /wiw_confirm=remove/);
    if (width > 782) {
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await row.hover();
      await expect(actions).toHaveCSS("opacity", "1");
      await page.mouse.move(0, 0);
      await expect(actions).toHaveCSS("opacity", "0");
      await actions.getByRole("link", { name: "Details" }).focus();
      await expect(actions).toHaveCSS("opacity", "1");
    } else {
      await expect(actions).toHaveCSS("opacity", "1");
    }
    await expectScreenAccessible(page);

    // The inline stage chooser posts through its own form, outside the bulk form. On a phone, open the row first.
    if (width <= 782) await row.getByRole("button", { name: "Show more details" }).click();
    await row.getByRole("combobox", { name: `Stage for ${seed.names[0]}` }).selectOption("winner");
    await row.locator(".column-stage").getByRole("button", { name: "Save" }).click();
    await expect(page.locator(".notice-info")).toContainText("Stage saved");
  });

  test("Award categories: one table per group with bulk actions and row actions; axe passes", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-award-categories"));
    await expect(page.locator("form.wiw-list-form")).toHaveCount(5);
    const row = page.locator("tr", { hasText: `E2E UiB Category w${width}` });
    await expect(row.locator(".row-actions").getByRole("link", { name: "Rename" })).toHaveAttribute("href", /rename=/);
    await expect(row.locator(".row-actions").getByRole("link", { name: "Archive" })).toHaveAttribute("href", /op=archive/);
    await expect(page.locator("#wiw-bulk-award-categories-technology-innovation-top option[value=remove]")).toHaveText("Remove");
    await expectScreenAccessible(page);
    await shot(page, "award-categories");
  });

  test("Customers: the Add customer form uses the kit cards, 40px fields and a sticky save bar; axe passes", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-customers&customer=new"));
    await expect(page.getByRole("heading", { name: "Add customer", level: 1 })).toBeVisible();
    expect(await page.locator(".wiw-staff .wiw-card").count()).toBeGreaterThanOrEqual(2);
    expect((await page.locator("#wiw-c-name").boundingBox())?.height ?? 0).toBeGreaterThanOrEqual(40);
    await expect(page.locator(".wiw-actions--sticky")).toHaveCSS("position", "sticky");
    await expect(page.locator(".wiw-actions--sticky").getByRole("button", { name: "Save" })).toBeVisible();
    await expectScreenAccessible(page);
    await shot(page, "customer-form");

    await page.goto(adminUrl("admin.php?page=wiw-customers"));
    await expect(page.locator("table.wiw-list-table--customers")).toBeVisible();
    await expect(page.locator("table.wiw-list-table--customers input[type=checkbox]")).toHaveCount(0);
    await expectScreenAccessible(page);
  });

  const screens: Array<{ name: string; role: StaffRole; url: () => string }> = [
    { name: "Subscribers", role: "editor", url: () => adminUrl("admin.php?page=wiw-subscribers") },
    { name: "Sponsored stories", role: "admin", url: () => adminUrl("admin.php?page=wiw-sponsored-stories") },
    { name: "Home page settings", role: "editor", url: () => adminUrl("admin.php?page=wiw-home-settings") },
    { name: "Nomination detail", role: "editor", url: () => adminUrl(`admin.php?page=wiw-nomination&nomination_id=${seed.nominations[0]}`) },
    { name: "Nominations confirmation step", role: "editor", url: () => adminUrl(`admin.php?page=wiw-nominations&wiw_confirm=remove&wiw_ids=${seed.nominations[0]}`) },
    { name: "Duplicates", role: "reviewer_b", url: () => adminUrl("admin.php?page=wiw-duplicates") },
    { name: "Review item", role: "reviewer_b", url: () => adminUrl(`admin.php?page=wiw-review-item&profile_id=${seed.profiles[0]}`) },
    { name: "Profiles with the Bulk unpublish panel", role: "reviewer_b", url: () => adminUrl("admin.php?page=wiw-profiles&source=e2e-uib") },
  ];
  for (const screen of screens) {
    test(`${screen.name}: kit markup and axe pass`, async ({ page, baseURL }) => {
      await loginAs(page, baseURL as string, screen.role);
      const res = await page.goto(screen.url());
      expect(res?.status()).toBe(200);
      await expect(page.locator(".wrap.wiw-staff")).toBeVisible();
      await expectScreenAccessible(page);
      if (["Home page settings", "Review item"].includes(screen.name)) await shot(page, screen.name.toLowerCase().replace(/\s+/g, "-"));
    });
  }
});
