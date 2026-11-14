import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// Filters on phones (REQ-044, REQ-007, REQ-001): below 48rem one "Filters (n)" button opens a bottom sheet (a native modal
// dialog) holding the selects, with "Show N results" and "Clear all"; the filters that are on show as chips that each remove one
// filter. Desktop keeps the inline bar; without script the inline form is the whole thing. Markup rules are in
// tests/Integration/MobileFiltersTest.php. tests/e2e/helpers/seed-profile-display.php adds a fictional reviewed profile classified
// Nigeria › Financial Services › Banking, used for the counts and the sector page's industry chips when the helper can run.
type Seed = { by: string; cc0: string };

const ready = () => !!process.env.WIW_THEME_READY;
const canSeed = () => !!process.env.WIW_SECRETS_FILE && process.env.WIW_E2E_TEST_MODE === "1";
const isPhone = (page: Page) => (page.viewportSize()?.width ?? 1280) < 768;

async function values(page: Page, name: string): Promise<string[]> {
  return page
    .locator(`form.fbar select[name='${name}'] option`)
    .evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
}

test.describe("filters on phones and desktop", () => {
  test.skip(!ready(), "theme not active yet");
  test.skip(({ viewport }) => viewport?.width !== 360 && viewport?.width !== 1280, "checked at 360 and 1280 px");

  test.beforeAll(() => {
    if (canSeed()) runHelper<Seed>("seed-profile-display.php");
  });

  test("phone: the Filters button opens the sheet, Escape and Close shut it and focus returns (REQ-044)", async ({ page }) => {
    test.skip(!isPhone(page), "phones only");
    await page.goto("/directory/");
    const open = page.locator(".fbar-open");
    await expect(open).toBeVisible();
    await expect(open).toHaveText(/^\s*Filters\s*$/);
    await expect(page.locator("form.fbar select:visible")).toHaveCount(0);
    await expect(page.locator(".fbar-clear")).toBeHidden();
    await assertNoHorizontalScroll(page);

    await open.click();
    const sheet = page.locator("dialog.fbar-sheet");
    await expect(sheet).toBeVisible();
    await expect(open).toHaveAttribute("aria-expanded", "true");
    await expect(sheet.getByRole("heading", { name: "Filters" })).toBeVisible();
    for (const name of ["industry", "country", "city"]) await expect(sheet.locator(`select[name='${name}']`)).toBeVisible();
    await expect(sheet.locator("select[name='industry'] optgroup").first()).toBeAttached();
    await expect(sheet.getByRole("list", { name: "Type" }).locator("a[aria-current='page']")).toHaveText("All");
    await expect(sheet.getByRole("link", { name: "Clear all" })).toBeVisible();
    await expect(sheet.locator("[data-fbar-go]")).toHaveText(/^Show \d+ results?$/);
    // The background does not scroll while the sheet is open.
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).overflow)).toBe("hidden");
    // Fields are full width with large touch targets.
    const box = await sheet.locator("select[name='country']").boundingBox();
    expect(box?.height ?? 0).toBeGreaterThanOrEqual(44);
    expect(box?.width ?? 0).toBeGreaterThan(280);
    await expectNoA11yViolations(page);

    await page.keyboard.press("Escape");
    await expect(sheet).toBeHidden();
    await expect(open).toBeFocused();
    await expect(open).toHaveAttribute("aria-expanded", "false");
    expect(await page.evaluate(() => getComputedStyle(document.documentElement).overflow)).not.toBe("hidden");

    await open.click();
    await sheet.getByRole("button", { name: "Close filters" }).click();
    await expect(sheet).toBeHidden();
    await expect(open).toBeFocused();
  });

  test("phone: choices in the sheet refresh the count, apply together, and each chip removes one filter (REQ-044, REQ-007)", async ({
    page,
  }) => {
    test.skip(!isPhone(page), "phones only");
    await page.goto("/directory/");
    const industries = await values(page, "industry");
    const countries = await values(page, "country");
    test.skip(industries.length === 0 || countries.length === 0, "no industries or countries seeded");
    const industry = industries.includes("financial-services") ? "financial-services" : industries[0];
    const country = countries.includes("nigeria") ? "nigeria" : countries[0];

    await page.locator(".fbar-open").click();
    const sheet = page.locator("dialog.fbar-sheet");
    const counted = page.waitForResponse((r) => r.url().includes(`industry=${industry}`) && r.request().method() === "GET");
    await sheet.locator("select[name='industry']").selectOption(industry);
    await counted;
    // Choosing does not leave the page: the sheet waits for "Show N results".
    await expect(sheet).toBeVisible();
    expect(new URL(page.url()).searchParams.get("industry")).toBeNull();
    const both = page.waitForResponse((r) => r.url().includes(`industry=${industry}`) && r.url().includes(`country=${country}`));
    await sheet.locator("select[name='country']").selectOption(country);
    await both;
    await expect(sheet.locator("[data-fbar-go]")).toHaveText(/^Show \d+ results?$/);
    await page.waitForTimeout(300);
    const shown = Number(((await sheet.locator("[data-fbar-go]").innerText()).match(/\d+/) || ["-1"])[0]);

    await Promise.all([
      page.waitForURL((u) => u.searchParams.get("industry") === industry && u.searchParams.get("country") === country),
      sheet.locator("[data-fbar-go]").click(),
    ]);
    const total = Number(((await page.locator(".fbar-n").innerText()).match(/\d+/) || ["-2"])[0]);
    expect(total).toBe(shown);
    await expect(page.locator(".fbar-open")).toContainText("(2)");
    const chips = page.locator(".fbar-chips .fbar-chip");
    await expect(chips).toHaveCount(2);
    await expect(chips.first()).toBeVisible();
    await expect(chips.first()).toHaveAccessibleName(/^Remove filter Industry:/);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);

    await Promise.all([page.waitForURL((u) => u.searchParams.get("industry") === null), chips.first().click()]);
    expect(new URL(page.url()).searchParams.get("country")).toBe(country);
    await expect(page.locator(".fbar-chips .fbar-chip")).toHaveCount(1);
    await expect(page.locator(".fbar-open")).toContainText("(1)");
  });

  test("phone: closing the sheet without applying keeps the page's filters (REQ-044)", async ({ page }) => {
    test.skip(!isPhone(page), "phones only");
    await page.goto("/stories/");
    const options = await values(page, "industry");
    test.skip(options.length === 0, "no industries to choose");
    await page.locator(".fbar-open").click();
    await page.locator("dialog.fbar-sheet select[name='industry']").selectOption(options[0]);
    await page.keyboard.press("Escape");
    await expect(page.locator("form.fbar select[name='industry']")).toHaveValue("");
    expect(new URL(page.url()).search).toBe("");
  });

  test("desktop: the inline bar is unchanged, with no Filters button, chips or sheet (REQ-007)", async ({ page }) => {
    test.skip(isPhone(page), "desktop only");
    await page.goto("/directory/?industry=no-such-industry&sort=updated");
    await expect(page.locator(".fbar-open")).toBeHidden();
    await expect(page.locator(".fbar-chips")).toBeHidden();
    await expect(page.locator("dialog.fbar-sheet")).toBeHidden();
    for (const name of ["industry", "country", "city", "sort"]) await expect(page.locator(`form.fbar .fbar-l select[name='${name}']`)).toBeVisible();
    await expect(page.locator(".fbar-clear")).toBeVisible();
    await assertNoHorizontalScroll(page);
  });

  test("a sector page shows its industries as chips that link to each industry (REQ-009)", async ({ page }) => {
    const res = await page.goto("/industries/financial-services/");
    test.skip(!res || res.status() !== 200, "no Financial Services sector on this site");
    const chips = page.locator(".ind-chips a.ind-chip");
    test.skip((await chips.count()) === 0, "no industry in this sector has public profiles");
    const banking = page.locator(".ind-chips a.ind-chip[href$='/industries/banking/']");
    if (canSeed()) await expect(banking).toContainText("Banking");
    const href = (await chips.first().getAttribute("href")) as string;
    await chips.first().click();
    await expect(page).toHaveURL(href);
    await expect(page.locator("h1")).toHaveCount(1);
    await page.goBack();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("the inline form shows at every width, with no Filters button or chips (REQ-044)", async ({ page }) => {
      await page.goto("/directory/?industry=no-such-industry");
      await expect(page.locator(".fbar-open")).toBeHidden();
      await expect(page.locator(".fbar-chips")).toBeHidden();
      await expect(page.locator("dialog.fbar-sheet")).toBeHidden();
      await expect(page.locator("form.fbar .fbar-l select[name='industry']")).toBeVisible();
      await expect(page.locator("form.fbar button[type=submit]")).toHaveCount(1);
      expect(await page.locator("form.fbar").getAttribute("method")).toBe("get");
      await assertNoHorizontalScroll(page);
    });
  });
});
