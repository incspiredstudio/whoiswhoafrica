import { test, expect, type Page } from "@playwright/test";
import {
  adminUrl,
  loginAs,
  removeStaff,
  runHelper,
  seedStaff,
  staffReady,
  STAFF_SKIP_REASON,
} from "./helpers/staff-users";

// Classification admin (REQ-009, REQ-125, REQ-209, REQ-091): Add profile and Edit profile use the Industry picker (grouped by
// sector, with a filter box and chips for the other industries), the Organisation type dropdown and the Country and City selects;
// the Profiles list has a "Needs classification" view with its count; the term screens refuse a city without a country, keep
// Unclassified undeletable, and an old industry slug redirects to the new one. Fictional names only; seed-classification.php makes
// a small tree (kept for the next run; profiles are removed by cleanup-added-profiles.php). Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS.

type Tree = {
  unclassified: number;
  sector: number;
  oil: number;
  power: number;
  company: number;
  nigeria: number;
  city: number;
};

test.describe("classification admin", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  // Seeding staff from two browser projects at once races, so everything runs in the 1280 px project; the picker test also checks
  // the form at 360 px by resizing the page.
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs in the 1280 px project, at 360 and 1280 px",
  );
  test.describe.configure({ mode: "serial" });
  test.setTimeout(150_000);
  let tree: Tree;
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("cleanup-added-profiles.php");
    tree = runHelper<Tree>("seed-classification.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("cleanup-added-profiles.php");
    removeStaff();
  });

  async function openOrganisationForm(page: Page): Promise<void> {
    await page.goto(adminUrl("admin.php?page=wiw-profile-add"));
    await page.selectOption("#wiw-add-type", "organisation");
    await page.getByRole("button", { name: "Continue" }).click();
    await expect(page.locator("#wiw-f-primary_industry")).toBeVisible();
    // The script shows the filter box once it has run, so the chips and city narrowing are wired up.
    await expect(page.getByLabel("Filter the industries")).toBeVisible();
  }

  for (const width of [360, 1280]) {
    test(`the pickers filter, add chips and narrow the cities at ${width} px`, async ({
      page,
      baseURL,
    }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAs(page, baseURL as string, "editor");
      await openOrganisationForm(page);
      // Industries are grouped by sector; the filter box narrows the list.
      await expect(
        page.locator(
          '#wiw-f-primary_industry optgroup[label="E2E Sector"] option',
        ),
      ).toHaveCount(2);
      await page.getByLabel("Filter the industries").fill("e2e pow");
      await expect(
        page.locator(`#wiw-f-primary_industry option[value="${tree.oil}"]`),
      ).toBeHidden();
      await page.selectOption("#wiw-f-primary_industry", String(tree.power));
      // Choosing another industry makes a chip that can be unticked.
      await page.selectOption("#wiw-f-industries_add", String(tree.oil));
      const chip = page.locator(
        '[data-wiw-chips="industries"] input[type="checkbox"]',
      );
      await expect(chip).toHaveCount(1);
      await expect(chip).toBeChecked();
      await expect(page.locator("#wiw-f-industries_add")).toHaveValue("0");
      // Cities follow the country.
      await page.selectOption("#wiw-f-country", String(tree.nigeria));
      await expect(
        page.locator(
          `#wiw-f-city optgroup[data-wiw-country="${tree.nigeria}"]`,
        ),
      ).not.toHaveAttribute("hidden", /.*/);
      await page.selectOption("#wiw-f-city", String(tree.city));
      await expect(page.locator("#wiw-f-org_type")).toBeVisible();
      // The old text inputs are gone.
      for (const name of [
        "f_industry",
        "f_sector",
        "f_org_type",
        "f_hq_city",
      ]) {
        await expect(page.locator(`[name="${name}"]`)).toHaveCount(0);
      }
      // The controls fit the screen: none is wider than the page.
      const overflow = await page.evaluate(() =>
        [
          "#wiw-f-primary_industry",
          "#wiw-f-industries_add",
          "#wiw-f-country",
          "#wiw-f-city",
          "#wiw-f-org_type",
        ].filter((sel) => {
          const box = document.querySelector(sel)?.getBoundingClientRect();
          return !box || box.right > document.documentElement.clientWidth + 1;
        }),
      );
      expect(overflow).toEqual([]);
    });
  }

  test("an editor adds, edits and finds a profile that needs classification", async ({
    page,
    baseURL,
  }) => {
    const name = `E2E Addp Classified ${Date.now().toString(36)}`;
    await loginAs(page, baseURL as string, "editor");
    await openOrganisationForm(page);
    await page.fill("#wiw-f-name", name);
    await page.selectOption("#wiw-f-org_type", String(tree.company));
    await page.selectOption("#wiw-f-country", String(tree.nigeria));
    await page.selectOption("#wiw-f-city", String(tree.city));
    await page.selectOption("#wiw-f-primary_industry", String(tree.oil));
    await page.selectOption("#wiw-f-industries_add", String(tree.power));
    await page.fill("#wiw-f-sources", "https://example.org/fictional-source");
    await page.getByRole("button", { name: "Save profile" }).click();
    await expect(page.locator(".notice-success")).toContainText("review queue");
    await page.getByRole("link", { name: "Open the profile" }).click();

    // Edit profile shows the classification with only the profile's own extra as a chip.
    await expect(
      page.getByRole("heading", { name: "Classification" }),
    ).toBeVisible();
    await expect(page.locator("#wiw-f-cls_primary_industry")).toHaveValue(
      String(tree.oil),
    );
    await expect(page.locator("#wiw-f-cls_org_type")).toHaveValue(
      String(tree.company),
    );
    await expect(page.locator("#wiw-f-cls_city")).toHaveValue(
      String(tree.city),
    );
    const chips = page.locator(
      '[data-wiw-chips="cls_industries"] input[type="checkbox"]',
    );
    await expect(chips).toHaveCount(1);
    await expect(chips.first()).toHaveAttribute("value", String(tree.power));
    const wrap = page.locator(".wrap").first();
    await expect(wrap.getByRole("heading", { name: "Lists" })).toHaveCount(0);
    await expect(wrap).not.toContainText("Arrangements");
    await expect(wrap).not.toContainText("Source sync");
    await expect(wrap).not.toContainText(
      "Changes from the source since review",
    );
    await expect(
      wrap.getByRole("heading", { name: "Where each fact comes from" }),
    ).toBeVisible();

    // Untick the extra and mark it Unclassified.
    await chips.first().uncheck();
    await page.selectOption(
      "#wiw-f-cls_primary_industry",
      String(tree.unclassified),
    );
    await page
      .locator('input[name="reason"][required]')
      .first()
      .fill("E2E classification");
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(page.locator(".notice-success")).toContainText("Saved");
    await expect(page.locator("#wiw-f-cls_primary_industry")).toHaveValue(
      String(tree.unclassified),
    );
    await expect(
      page.locator('[data-wiw-chips="cls_industries"] input[type="checkbox"]'),
    ).toHaveCount(0);

    // The Profiles list's Needs classification view lists it, with a count.
    await page.goto(adminUrl("admin.php?page=wiw-profiles"));
    const view = page.getByRole("link", {
      name: /Needs classification \(\d+\)/,
    });
    await expect(view).toBeVisible();
    await view.click();
    await expect(
      page.getByRole("link", { name: /Needs classification/ }),
    ).toHaveAttribute("aria-current", "page");
    await expect(page.locator("tr", { hasText: name })).toHaveCount(1);
  });

  test("the term screens refuse a city with no country, keep Unclassified and redirect an old slug", async ({
    page,
    baseURL,
    request,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(
      adminUrl("edit-tags.php?taxonomy=wiw_place&post_type=wiw_profile"),
    );
    await expect(page.locator(".wiw-term-help")).toContainText(
      "two-letter code",
    );
    await expect(page.locator("thead th#wiw_code")).toBeVisible();
    await page.fill("#tag-name", "E2E Orphan City");
    await page.selectOption("#wiw_place_kind", "city");
    await page.locator("#submit").click();
    await expect(page.locator("#ajax-response")).toContainText("A city needs its country as Parent.", { timeout: 30_000 });

    await page.goto(
      adminUrl("edit-tags.php?taxonomy=wiw_industry&post_type=wiw_profile"),
    );
    await expect(page.locator("thead th#wiw_sector")).toBeVisible();
    // The real tree has many terms, so search for Unclassified to bring its row onto the page.
    await page.goto(adminUrl("edit-tags.php?taxonomy=wiw_industry&post_type=wiw_profile&s=Unclassified"));
    const unclassified = page.locator(`#tag-${tree.unclassified}`);
    await expect(unclassified).toBeVisible();
    await expect(unclassified.locator(".row-actions .delete")).toHaveCount(0);

    // Change an industry's slug; the old directory address answers with a 301 to the new one.
    await page.goto(
      adminUrl(
        `term.php?taxonomy=wiw_industry&tag_ID=${tree.power}&post_type=wiw_profile`,
      ),
    );
    const oldSlug = await page.inputValue("#slug");
    const newSlug = `e2e-power-${Date.now().toString(36)}`;
    await page.fill("#slug", newSlug);
    await page.getByRole("button", { name: "Update" }).click();
    await expect(
      page.locator("#message, .notice-success").first(),
    ).toBeVisible();
    const res = await request.get(`/directory/?industry=${oldSlug}`, {
      maxRedirects: 0,
    });
    expect(res.status()).toBe(301);
    expect(res.headers()["location"]).toContain(`industry=${newSlug}`);
  });
});
