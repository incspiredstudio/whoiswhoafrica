import { test, expect } from "@playwright/test";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Stories page (REQ-029, REQ-249, REQ-263). Works on an empty site (empty state) and on a seeded one (mosaic, grid, cards).
// Story visibility, ordering, the sponsored slot dates and the sign-up position are covered in tests/Integration/StoriesListTemplateTest.php.
test.describe("stories list", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("/stories/ answers 200 with one h1, type tabs, the filter bar, and no Events filter or strip", async ({ page }) => {
    const res = await page.goto("/stories/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("main h1")).toHaveCount(1);
    await expect(page.locator("nav[aria-label='Story types'] a")).toHaveCount(7);
    await expect(page.locator("nav[aria-label='Story types'] a[aria-current='page']")).toHaveText("All");
    await expect(page.locator("form.fbar[aria-label='Filter stories']")).toHaveCount(1);
    await expect(page.locator("form.fbar select[name=city]")).toHaveCount(0);
    await expect(page.locator(".ev-hero, .ag-row")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
  });

  test("a type with no stories says No stories match and Clear filters returns to the list", async ({ page }) => {
    await page.goto("/stories/?type=opinion&industry=no-such-industry");
    await expect(page.getByRole("heading", { name: "No stories match" })).toBeVisible();
    await page.getByRole("link", { name: "Clear filters" }).click();
    await expect(page).toHaveURL(/\/stories\/$/);
  });

  test("choosing a type tab writes it to the address", async ({ page }) => {
    await page.goto("/stories/");
    await page.getByRole("link", { name: "Interview", exact: true }).click();
    await expect(page).toHaveURL(/type=interview/);
    await expect(page.locator("nav[aria-label='Story types'] a[aria-current='page']")).toHaveText("Interview");
  });

  test("cards use a 16:9 cover, and the first cover loads at once", async ({ page }) => {
    await page.goto("/stories/");
    const covers = page.locator(".scard-media");
    const n = await covers.count();
    test.skip(n === 0, "no stories on this site");
    const box = await covers.first().boundingBox();
    expect(box).not.toBeNull();
    expect(Math.abs(box!.width / box!.height - 16 / 9)).toBeLessThan(0.02);
    const img = page.locator(".scard--lead img").first();
    if (await img.count()) await expect(img).toHaveAttribute("loading", "eager");
  });
});
