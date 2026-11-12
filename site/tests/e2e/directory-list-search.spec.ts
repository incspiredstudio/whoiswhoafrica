import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts } from "./helpers/page";

// The per-list narrowing box (REQ-008, REQ-254). What it filters is the list query (the plugin); here: where it sits, what it writes
// to the address, that it is not the site search, and that the count is announced.
const LISTS = ["/directory/", "/people/", "/entrepreneurs/", "/businesses/", "/institutions/"];

test.describe("directory list narrowing box", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const path of LISTS) {
    test(`${path} has the narrowing field first in the filter bar, labelled apart from site search`, async ({ page }) => {
      await page.goto(path);
      const bar = page.locator("form.fbar[aria-label='Filter profiles']");
      const field = bar.locator("input[type=search][name=q]");
      await expect(field).toHaveCount(1);
      await expect(bar.getByLabel("Narrow this list")).toHaveCount(1);
      const firstControl = await bar.locator("input[type=search], select").first().getAttribute("name");
      expect(firstControl).toBe("q");
      await expect(bar.locator(".fbar-n")).toHaveAttribute("role", "status");
      await assertNoHorizontalScroll(page);
    });
  }

  test("typing and pressing Enter writes q to the address, keeps other filters, and offers Clear", async ({ page }) => {
    await page.goto("/directory/?sort=updated");
    const field = page.locator("form.fbar input[name=q]");
    await field.fill("alpha");
    await Promise.all([page.waitForURL(/q=alpha/), field.press("Enter")]);
    const url = new URL(page.url());
    expect(url.pathname).toBe("/directory/");
    expect(url.searchParams.get("q")).toBe("alpha");
    expect(url.searchParams.get("sort")).toBe("updated");
    expect(url.searchParams.get("s")).toBeNull();
    await expect(page.locator("form.fbar input[name=q]")).toHaveValue("alpha");
    await expect(page.locator(".fbar-clear")).toHaveCount(1);
    await expectNoA11yViolations(page);
  });

  test("a shared address restores the text in the field", async ({ page }) => {
    await page.goto("/people/?q=ngozi");
    await expect(page.locator("form.fbar input[name=q]")).toHaveValue("ngozi");
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("the visually hidden Apply button submits the narrowing text by GET", async ({ page }) => {
      await page.goto("/directory/");
      const field = page.locator("form.fbar input[name=q]");
      await field.fill("alpha");
      await Promise.all([page.waitForURL(/q=alpha/), field.press("Enter")]);
      expect(new URL(page.url()).searchParams.get("q")).toBe("alpha");
    });
  });
});
