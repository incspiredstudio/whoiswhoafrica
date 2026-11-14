import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// Profile breadcrumb, compact image credit and the key facts setting (REQ-009, REQ-003, REQ-203, REQ-144, REQ-043, REQ-040).
// tests/e2e/helpers/seed-profile-display.php seeds two fictional profiles (a CC BY portrait whose credit repeats the licence, and a
// CC0 portrait) and switches the two site-wide settings. Because the settings are site-wide, the whole file runs in one project
// (chromium-1280), one test at a time, and checks each state at 360 px and at 1280 px by resizing the page; it restores the
// defaults at the end. Needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type Seed = { by: string; cc0: string };

const ready = () => !!process.env.WIW_THEME_READY && !!process.env.WIW_SECRETS_FILE && process.env.WIW_E2E_TEST_MODE === "1";
const widths = [360, 1280];

async function cls(page: Page): Promise<number> {
  return page.evaluate(
    () =>
      new Promise<number>((resolve) => {
        let total = 0;
        try {
          new PerformanceObserver((list) => {
            for (const entry of list.getEntries() as unknown as Array<{ value: number; hadRecentInput: boolean }>) {
              if (!entry.hadRecentInput) total += entry.value;
            }
          }).observe({ type: "layout-shift", buffered: true });
        } catch {
          resolve(0);
          return;
        }
        setTimeout(() => resolve(total), 400);
      }),
  );
}

test.describe("profile display: breadcrumb, image credit and key facts", () => {
  test.describe.configure({ mode: "serial" });
  test.skip(!ready(), "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)");
  test.skip(({ viewport }) => viewport?.width !== 1280, "site-wide settings: runs once, in the 1280 px project");
  test.setTimeout(120_000);

  let seed: Seed;
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seed = runHelper<Seed>("seed-profile-display.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-profile-display.php", "reset");
  });

  test("the breadcrumb ends with the name, links into the directory and folds its middle on phones (REQ-009, REQ-043)", async ({ page }) => {
    runHelper("seed-profile-display.php", "set", "compact", "hidden");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      const nav = page.getByRole("navigation", { name: "Breadcrumb" });
      await expect(nav).toHaveCount(1);
      const current = nav.locator("[aria-current='page']");
      await expect(current).toHaveText("E2E Display Adaeze Okonkwo-Beaumont");
      const links = nav.locator("a");
      expect(await links.count()).toBeGreaterThan(0);
      for (const href of await links.evaluateAll((as) => as.map((a) => (a as HTMLAnchorElement).getAttribute("href") || ""))) {
        expect(href).toMatch(/\/directory\/(\?country=[a-z0-9-]+(&industry=[a-z0-9-]+)?)?$/);
      }
      const mids = nav.locator(".crumbs-mid");
      if ((await mids.count()) > 0) {
        // Country › Sector › Industry › Name: the middle crumbs fold to "…" on phones but stay readable by screen readers.
        const fold = nav.locator(".crumbs-fold");
        if (width < 640) {
          await expect(fold).toBeVisible();
          await expect(fold).toHaveAttribute("aria-hidden", "true");
          const box = await mids.first().boundingBox();
          expect(box && box.width <= 1).toBeTruthy();
          await expect(mids.first().locator("a")).toHaveText(/\S/);
        } else {
          await expect(fold).toBeHidden();
          await expect(mids.first()).toBeVisible();
        }
      }
      await assertNoHorizontalScroll(page);
    }
    // A link in the breadcrumb leads to a directory list that answers.
    const first = page.getByRole("navigation", { name: "Breadcrumb" }).locator("a").first();
    const res = await page.request.get((await first.getAttribute("href")) || "/directory/");
    expect(res.status()).toBe(200);
  });

  test("compact credit: an i button opens the credit with the keyboard, and the foot lists it (REQ-203, REQ-043)", async ({ page }) => {
    runHelper("seed-profile-display.php", "set", "compact", "hidden");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      const button = page.locator(".credit-toggle");
      const pop = page.locator("#profile-credit-pop");
      await expect(button).toBeVisible();
      await expect(button).toHaveAccessibleName("Photo credit");
      await expect(button).toHaveAttribute("aria-expanded", "false");
      await expect(pop).toBeHidden();
      expect(await cls(page)).toBeLessThan(0.05);
      const h1Before = await page.locator("h1").boundingBox();
      await button.focus();
      await page.keyboard.press("Enter");
      await expect(button).toHaveAttribute("aria-expanded", "true");
      await expect(pop).toBeVisible();
      await expect(pop).toContainText("Photo:");
      await expect(pop.locator("a[rel~='license']")).toHaveText("CC BY 4.0");
      expect((await pop.innerText()).match(/CC BY 4\.0/g)?.length, "the licence shows once (issue 829)").toBe(1);
      const h1After = await page.locator("h1").boundingBox();
      expect(h1After?.y, "opening the credit moves nothing").toBe(h1Before?.y);
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
      await page.keyboard.press("Escape");
      await expect(button).toHaveAttribute("aria-expanded", "false");
      await expect(pop).toBeHidden();
      await expect(button).toBeFocused();
      await page.keyboard.press("Space");
      await expect(button).toHaveAttribute("aria-expanded", "true");
      await page.locator("h1").click();
      await expect(pop).toBeHidden();
      const foot = page.locator(".profile-credits");
      await expect(foot).toContainText("Image credits");
      await expect(foot).toContainText("Fictional Studio Lagos");
      await expect(page.locator(".profile-credit.small")).toHaveCount(0);
    }
  });

  test("full credit shows today's line; hidden_when_not_required hides only a CC0 credit (REQ-203)", async ({ page }) => {
    runHelper("seed-profile-display.php", "set", "full", "hidden");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      await expect(page.locator(".profile-credit.small")).toContainText("Fictional Studio Lagos");
      await expect(page.locator(".credit-toggle")).toHaveCount(0);
      await expect(page.locator(".profile-credits")).toHaveCount(0);
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
    }
    runHelper("seed-profile-display.php", "set", "hidden_when_not_required", "hidden");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.cc0}/`);
      await expect(page.locator(".credit-toggle, .profile-credit, .profile-credits")).toHaveCount(0);
      await expect(page.getByText("Fictional Archive")).toHaveCount(0);
      await page.goto(`/profile/${seed.by}/`);
      await expect(page.locator(".credit-toggle")).toBeVisible();
      await expect(page.locator(".profile-credits")).toContainText("CC BY 4.0");
      await expectNoA11yViolations(page);
    }
  });

  test("key facts: hidden leaves no trace, collapsed is a closed disclosure at the foot, show is the table (REQ-144)", async ({ page }) => {
    runHelper("seed-profile-display.php", "set", "compact", "hidden");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      await expect(page.locator("#key-facts")).toHaveCount(0);
      await expect(page.getByText("Key facts and sources")).toHaveCount(0);
      await expect(page.locator("a[href='#key-facts']")).toHaveCount(0);
    }
    runHelper("seed-profile-display.php", "set", "compact", "collapsed");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      const fold = page.locator("details#key-facts");
      await expect(fold).toHaveCount(1);
      await expect(fold).not.toHaveAttribute("open", /.*/);
      await expect(fold.locator("table.facts")).toBeHidden();
      expect(await cls(page)).toBeLessThan(0.05);
      await fold.locator("summary").focus();
      await page.keyboard.press("Enter");
      await expect(fold.locator("table.facts")).toBeVisible();
      for (const row of await fold.locator("tbody tr").all()) {
        await expect(row.locator(".src a[href^='http']")).toHaveCount(1);
      }
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
    }
    runHelper("seed-profile-display.php", "set", "compact", "show");
    for (const width of widths) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(`/profile/${seed.by}/`);
      await expect(page.locator("section#key-facts h2")).toHaveText("Key facts and sources");
      await expect(page.locator("section#key-facts table.facts tbody tr").first()).toBeVisible();
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
    }
  });
});
