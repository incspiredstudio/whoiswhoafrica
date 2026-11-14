import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts } from "./helpers/page";

// The per-list search field (REQ-008, REQ-254). What it filters is the list query (the plugin); here: it sits in the dark band as in
// the prototype, what it writes to the address, that it is not the site search, and that the filters and the field keep each other.
const LISTS = ["/directory/", "/people/", "/entrepreneurs/", "/businesses/", "/institutions/"];

test.describe("directory list search field", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const path of LISTS) {
    test(`${path} has the search field in the dark band, labelled apart from site search, and chip tabs`, async ({ page }) => {
      await page.goto(path);
      const band = page.locator(".page-band--dark");
      const field = band.locator("form[role=search] input[type=search][name=q]");
      await expect(field).toHaveCount(1);
      await expect(band.getByLabel("Narrow this list")).toHaveCount(1);
      await expect(band.locator("nav.tabs[aria-label='Directory lists'] a")).toHaveCount(5);
      await expect(page.locator("form.fbar input[type=search]")).toHaveCount(0);
      await expect(page.locator("form.fbar .fbar-n")).toHaveAttribute("role", "status");
      await assertNoHorizontalScroll(page);
    });
  }

  test("typing and pressing Enter writes q to the address, keeps other filters, and offers Clear", async ({ page }) => {
    await page.goto("/directory/?sort=updated");
    const field = page.locator(".page-band input[name=q]");
    await field.fill("alpha");
    await Promise.all([page.waitForURL(/q=alpha/), field.press("Enter")]);
    const url = new URL(page.url());
    expect(url.pathname).toBe("/directory/");
    expect(url.searchParams.get("q")).toBe("alpha");
    expect(url.searchParams.get("sort")).toBe("updated");
    expect(url.searchParams.get("s")).toBeNull();
    await expect(page.locator(".page-band input[name=q]")).toHaveValue("alpha");
    await expect(page.locator(".fbar-clear")).toHaveCount(1);
    await expectNoA11yViolations(page);
  });

  test("changing a filter keeps the search text", async ({ page }) => {
    await page.goto("/directory/?q=alpha");
    const select = page.locator("form.fbar select[name=sort]");
    await Promise.all([page.waitForURL(/sort=updated/), select.selectOption("updated")]);
    expect(new URL(page.url()).searchParams.get("q")).toBe("alpha");
  });

  test("the chips are reachable by keyboard and show a focus ring", async ({ page }) => {
    await page.goto("/directory/");
    const chip = page.locator("nav.tabs a").nth(1);
    await page.keyboard.press("Tab");
    await chip.focus();
    await expect(chip).toBeFocused();
    const outline = await chip.evaluate((el) => getComputedStyle(el).outlineStyle);
    expect(outline).not.toBe("none");
  });

  test("a shared address restores the text in the field", async ({ page }) => {
    await page.goto("/people/?q=ngozi");
    await expect(page.locator(".page-band input[name=q]")).toHaveValue("ngozi");
  });

  test.describe("without scripts", () => {
    test.use(disableScripts);
    test("the band search submits by GET", async ({ page }) => {
      await page.goto("/directory/");
      const field = page.locator(".page-band input[name=q]");
      await field.fill("alpha");
      await Promise.all([page.waitForURL(/q=alpha/), field.press("Enter")]);
      expect(new URL(page.url()).searchParams.get("q")).toBe("alpha");
    });
  });
});
