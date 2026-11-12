import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts } from "./helpers/page";

// The directory filter bar and shareable filter URLs (REQ-007, REQ-040, REQ-043). Works on an empty site and on a seeded one.
test.describe("directory filters", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("a filter lives in the address and a fresh browser shows the same result", async ({ page, browser }) => {
    await page.goto("/people/");
    const select = page.locator("form.fbar select[name=industry]");
    const values = await select.locator("option").evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
    test.skip(values.length === 0, "no industries seeded");
    await Promise.all([page.waitForURL(/industry=/), select.selectOption(values[0])]);
    const url = page.url();
    const count = await page.locator(".fbar-n").innerText();
    const fresh = await browser.newContext();
    const other = await fresh.newPage();
    await other.goto(url);
    await expect(other.locator(".fbar-n")).toHaveText(count);
    await expect(other.locator("select[name=industry]")).toHaveValue(values[0]);
    await fresh.close();
  });

  test("the Clear link is absent when no filter is on and present when one is", async ({ page }) => {
    await page.goto("/directory/");
    await expect(page.locator(".fbar-clear")).toHaveCount(0);
    await page.goto("/directory/?industry=no-such-industry");
    await expect(page.locator(".fbar-clear")).toHaveCount(1);
    await expect(page.locator(".fbar .fbar-n")).toHaveAttribute("role", "status");
  });

  test("the grid and list switch writes view=list to the address and keeps filters", async ({ page }) => {
    await page.goto("/directory/?sort=updated");
    await expect(page.locator(".view-toggle a[aria-current='true']")).toContainText("Grid");
    await page.locator(".view-toggle a", { hasText: "List" }).click();
    await expect(page).toHaveURL(/view=list/);
    await expect(page).toHaveURL(/sort=updated/);
    await expect(page.locator(".view-toggle a[aria-current='true']")).toContainText("List");
    await expect(page.locator("form.fbar input[name=view]")).toHaveValue("list");
    await assertNoHorizontalScroll(page);
  });

  test("no horizontal scroll at 360 px and no serious violations", async ({ page }) => {
    await page.setViewportSize({ width: 360, height: 800 });
    await page.goto("/directory/?view=list");
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("the form is a GET form with an Apply button", async ({ page }) => {
      await page.goto("/directory/");
      await expect(page.locator("form.fbar button[type=submit]")).toHaveCount(1);
      expect(await page.locator("form.fbar").getAttribute("method")).toBe("get");
    });
  });
});
