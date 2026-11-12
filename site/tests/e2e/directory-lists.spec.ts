import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The five lists (REQ-001, REQ-007, REQ-218). Works on an empty site (empty state) and on a seeded one (cards, at most 16 a page).
// The pending-profile rule and the 16-a-page rule are covered in tests/Integration/DirectoryListTemplateTest.php.
const lists: Array<[string, string]> = [
  ["/directory/", "All"],
  ["/people/", "People"],
  ["/entrepreneurs/", "Entrepreneurs"],
  ["/businesses/", "Businesses"],
  ["/institutions/", "Institutions"],
];

test.describe("directory lists", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const [path, label] of lists) {
    test(`${path} answers 200 with one h1, the tabs marking ${label}, and at most 16 cards`, async ({ page }) => {
      const res = await page.goto(path);
      expect(res?.status()).toBe(200);
      await expect(page.locator("h1")).toHaveCount(1);
      await expect(page.locator("nav[aria-label='Directory lists'] a")).toHaveCount(5);
      await expect(page.locator("nav[aria-label='Directory lists'] a[aria-current='page']")).toHaveText(label);
      await expect(page.locator("form.fbar[aria-label='Filter profiles']")).toHaveCount(1);
      expect(await page.locator(".pcard").count()).toBeLessThanOrEqual(16);
      await assertNoHorizontalScroll(page);
    });
  }

  test("a filter that matches nothing shows Clear filters, which returns to the list", async ({ page }) => {
    const res = await page.goto("/directory/?industry=no-such-industry");
    expect(res?.status()).toBe(200);
    await expect(page.locator(".pcard")).toHaveCount(0);
    await expect(page.getByRole("heading", { name: "No profiles match" })).toBeVisible();
    await page.getByRole("link", { name: "Clear filters" }).click();
    await expect(page).toHaveURL(/\/directory\/$/);
  });

  test("the lists have no serious accessibility violations", async ({ page }) => {
    await page.goto("/people/");
    await expectNoA11yViolations(page);
  });
});
