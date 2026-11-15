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

// Fixes from the 0.3.0 release test (REQ-193, REQ-009, REQ-240, REQ-056):
// - Directory has Industries, Countries & cities and Organisation types, each opening its term screen with the item current;
// - a new sector can be added with Parent "None", an industry under an industry is refused with a clear message;
// - bulk unpublish in the "Needs classification" view counts exactly the published profiles the view lists;
// - Unclassified is never in the header menu and /industries/unclassified/ is a 404;
// - an old sector or industry slug redirects /industries/<old>/ with a 301.
// Fictional names only. Everything runs in the 1280 px project; the menu and form checks also run at 360 px by resizing the page.

type Tree = {
  unclassified: number;
  sector: number;
  oil: number;
  power: number;
};

test.describe("0.3.0 release-test fixes", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
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
    runHelper("seed-profiles.php");
    tree = runHelper<Tree>("seed-classification.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    removeStaff();
  });

  async function directoryItems(page: Page): Promise<string[]> {
    return page
      .locator("#toplevel_page_wiw-menu-directory .wp-submenu a")
      .allInnerTexts();
  }

  for (const width of [360, 1280]) {
    test(`Directory lists the three term screens and highlights the open one at ${width} px`, async ({
      page,
      baseURL,
    }) => {
      await page.setViewportSize({ width, height: 900 });
      await loginAs(page, baseURL as string, "editor");
      for (const [taxonomy, label] of [
        ["wiw_industry", "Industries"],
        ["wiw_place", "Countries & cities"],
        ["wiw_org_type", "Organisation types"],
      ]) {
        const url = `edit-tags.php?taxonomy=${taxonomy}&post_type=wiw_profile`;
        await page.goto(adminUrl(url));
        expect(await directoryItems(page)).toContain(label);
        const menu = page.locator("#toplevel_page_wiw-menu-directory");
        await expect(menu).toHaveClass(/wp-has-current-submenu/);
        await expect(menu.locator("li.current a")).toHaveText(label);
        await expect(menu.locator("li.current a")).toHaveAttribute(
          "href",
          new RegExp(url.replace(/[?]/g, "\\?")),
        );
      }
      // The industry list under Posts is labelled as the same, shared list.
      await page.goto(adminUrl("edit.php"));
      await expect(page.locator("#menu-posts .wp-submenu")).toContainText(
        "Industries (shared with profiles)",
      );
    });
  }

  test("a sector is added with Parent None; an industry under an industry is refused clearly", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(
      adminUrl("edit-tags.php?taxonomy=wiw_industry&post_type=wiw_profile"),
    );
    const name = `E2E New Sector ${Date.now().toString(36)}`;
    await page.fill("#tag-name", name);
    await expect(page.locator("#parent")).toHaveValue("-1");
    await page.locator("#submit").click();
    await expect(page.locator("#the-list")).toContainText(name, {
      timeout: 30_000,
    });
    await expect(page.locator("#ajax-response .error")).toHaveCount(0);

    await page.fill("#tag-name", "E2E Third Level");
    await page.selectOption("#parent", String(tree.oil));
    await page.locator("#submit").click();
    await expect(page.locator("#ajax-response")).toContainText(
      "To add a new sector, set Parent to None.",
      { timeout: 30_000 },
    );
    await expect(page.locator("#ajax-response")).not.toContainText(
      "Leave Parent empty",
    );
  });

  test("bulk unpublish in the Needs classification view counts the view", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(
      adminUrl("admin.php?page=wiw-profiles&classification=needs"),
    );
    const states = await page
      .locator("table.wiw-list-table tbody tr td.column-state")
      .allInnerTexts();
    const published = states.filter((s) => s.startsWith("published_")).length;
    const all = await page.goto(adminUrl("admin.php?page=wiw-profiles"));
    expect(all?.status()).toBe(200);
    await page.goto(
      adminUrl("admin.php?page=wiw-profiles&classification=needs"),
    );
    await expect(page.locator(".wiw-bulk-count")).toHaveText(
      published === 1
        ? "1 published profile matches the filter."
        : `${published} published profiles match the filter.`,
    );
    if (published > 0) {
      await expect(
        page.locator('.wiw-bulk-unpublish input[name="f_classification"]'),
      ).toHaveValue("needs");
      await expect(
        page.locator('.wiw-bulk-unpublish input[name="confirmed"]'),
      ).toHaveValue(String(published));
    }
  });

  test("Unclassified is not public navigation and an old industry slug redirects its page", async ({
    page,
    baseURL,
    request,
  }) => {
    for (const width of [360, 1280]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto("/");
      await expect(
        page.locator('a[href*="/industries/unclassified/"]'),
      ).toHaveCount(0);
    }
    const gone = await request.get("/industries/unclassified/", {
      maxRedirects: 0,
    });
    expect(gone.status()).toBe(404);

    await loginAs(page, baseURL as string, "admin");
    for (const id of [tree.sector, tree.oil]) {
      await page.goto(
        adminUrl(
          `term.php?taxonomy=wiw_industry&tag_ID=${id}&post_type=wiw_profile`,
        ),
      );
      const oldSlug = await page.inputValue("#slug");
      const newSlug = `${oldSlug.replace(/-[a-z0-9]{6,}$/, "")}-${Date.now().toString(36)}`;
      await page.fill("#slug", newSlug);
      await page.getByRole("button", { name: "Update" }).click();
      await expect(
        page.locator("#message, .notice-success").first(),
      ).toBeVisible();
      const res = await request.get(`/industries/${oldSlug}/`, {
        maxRedirects: 0,
      });
      expect(res.status()).toBe(301);
      expect(res.headers()["location"]).toContain(`/industries/${newSlug}/`);
    }
  });
});
