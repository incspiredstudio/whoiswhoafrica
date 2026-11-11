import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, disableScripts } from "./helpers/page";

// Needs a list page that uses the filter bar (Stories, Events, directory lists, Winners), built by the list-page issues.
// Until one exists this spec skips. Markup rules are covered by tests/Integration/FilterBarTest.php (REQ-029, REQ-264).
const LISTS = ["/stories/", "/events/", "/directory/"];

async function firstListWithBar(page: import("@playwright/test").Page): Promise<string | null> {
  for (const path of LISTS) {
    const res = await page.goto(path);
    if (res && res.status() < 400 && (await page.locator("form.fbar").count()) > 0) return path;
  }
  return null;
}

test("choosing a value applies it at once and writes it to the address (REQ-029)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const path = await firstListWithBar(page);
  test.skip(!path, "no list page with a filter bar yet");
  const select = page.locator("form.fbar select").first();
  const options = await select.locator("option").evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
  test.skip(options.length === 0, "filter has no values");
  await Promise.all([page.waitForURL(/\?/), select.selectOption(options[0])]);
  expect(new URL(page.url()).search).toContain(encodeURIComponent(options[0]));
  await expect(page.locator(".fbar-clear")).toHaveCount(1);
  await expect(page.locator("form.fbar .fbar-n")).toHaveAttribute("role", "status");
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});

test.describe("without scripts", () => {
  test.use(disableScripts);
  test("the form still works through its Apply button (REQ-029)", async ({ page }) => {
    test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
    const path = await firstListWithBar(page);
    test.skip(!path, "no list page with a filter bar yet");
    await expect(page.locator("form.fbar button[type=submit]")).toHaveCount(1);
    expect(await page.locator("form.fbar").getAttribute("method")).toBe("get");
  });
});
