import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Supported by block on the Awards overview (REQ-186, REQ-199). Needs WIW_THEME_READY=1. The block is absent when the page has no
// sponsor; with sponsors it shows the name or logo, the link and the independence note, and nothing commercial. Each test runs in the
// state the site is in and skips the other.
test.describe("supported by", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("absent when there are no sponsors", async ({ page }) => {
    const res = await page.goto("/awards/");
    test.skip(res?.status() === 404, "the Awards page is not created on this site");
    test.skip((await page.locator(".aw-sponsors").count()) > 0, "this site has sponsors");
    await expect(page.getByRole("heading", { name: "Supported by" })).toHaveCount(0);
    await expect(page.getByText("do not choose or influence")).toHaveCount(0);
  });

  test("shows name, link and the note, and nothing commercial", async ({ page }) => {
    const res = await page.goto("/awards/");
    test.skip(res?.status() === 404, "the Awards page is not created on this site");
    test.skip((await page.locator(".aw-sponsors").count()) === 0, "this site has no sponsors");
    const block = page.locator(".aw-sponsors");
    await expect(block.getByRole("heading", { level: 2, name: "Supported by" })).toBeVisible();
    await expect(block.locator("li.sponsor")).not.toHaveCount(0);
    await expect(block.getByText("do not choose or influence nominees or winners")).toBeVisible();
    for (const a of await block.locator("a").all()) {
      await expect(a).toHaveAttribute("rel", /sponsored/);
    }
    expect(((await block.textContent()) ?? "").toLowerCase()).not.toMatch(/amount|price|kobo|deal|invoice/);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });
});
