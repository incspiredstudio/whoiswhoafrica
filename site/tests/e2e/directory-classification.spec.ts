import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// The classification tree in the directory filter and on the industries index (REQ-009, REQ-007, REQ-043, REQ-040). The industry
// select groups industries under their sector (the sector itself selectable, "unclassified" never offered), and /industries/
// lists the sectors with their industries under them. tests/e2e/helpers/seed-profile-display.php adds a fictional reviewed
// profile classified as Nigeria › Financial Services › Banking. Read-only, so it runs in every project; the checks that matter
// here are at 360 and 1280 px. Needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type Seed = { by: string; cc0: string };

const ready = () => !!process.env.WIW_THEME_READY && !!process.env.WIW_SECRETS_FILE && process.env.WIW_E2E_TEST_MODE === "1";

test.describe("classification in the directory filter and the industries index", () => {
  test.skip(!ready(), "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)");
  test.skip(({ viewport }) => viewport?.width !== 360 && viewport?.width !== 1280, "checked at 360 and 1280 px");

  test.setTimeout(90_000);

  test.beforeAll(() => {
    runHelper<Seed>("seed-profile-display.php");
  });

  test("the industry filter groups industries under selectable sectors and filters by sector (REQ-009)", async ({ page }) => {
    await page.goto("/directory/");
    const select = page.locator("select[name='industry']");
    await expect(select).toHaveCount(1);
    await expect(select.locator("optgroup[label='Financial Services']")).toHaveCount(1);
    await expect(select.locator("optgroup[label='Financial Services'] option").first()).toHaveAttribute("value", "financial-services");
    await expect(select.locator("optgroup[label='Financial Services'] option[value='banking']")).toHaveCount(1);
    await expect(select.locator("option[value='unclassified']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);

    const res = await page.goto("/directory/?industry=financial-services");
    expect(res?.status()).toBe(200);
    await expect(page.locator("select[name='industry']")).toHaveValue("financial-services");
    await expect(page.locator(".pcard-name", { hasText: "E2E Display Adaeze Okonkwo-Beaumont" })).toHaveCount(1);
    await expect(page.locator(".pcard-kick", { hasText: "Banking" }).first()).toBeVisible();
  });

  test("the industries index lists sectors with their industries under them (REQ-009, REQ-007)", async ({ page }) => {
    await page.goto("/industries/");
    const sector = page.locator(".ind-sector", { has: page.locator("h2", { hasText: "Financial Services" }) });
    await expect(sector).toHaveCount(1);
    await expect(sector.locator("h2 a")).toHaveAttribute("href", /\/industries\/financial-services\/$/);
    await expect(sector.locator(".ind-list a[href$='/industries/banking/']")).toContainText("Banking");
    await expect(page.locator(".ind-sector h2", { hasText: /^Unclassified/ })).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
    const res = await page.goto("/industries/financial-services/");
    expect(res?.status()).toBe(200);
    await expect(page.locator(".pcard-name", { hasText: "E2E Display Adaeze Okonkwo-Beaumont" })).toHaveCount(1);
  });
});
