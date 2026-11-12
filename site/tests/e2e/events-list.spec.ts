import { test, expect } from "@playwright/test";

// Needs the core plugin active and at least one published event (a seeded site or the events import). With none the page still
// answers 200 and says so; the ordering and filter rules are covered in tests/Integration/EventsListTest.php (REQ-264).
test.describe("events list", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("/events answers 200 with one h1 and the filter bar", async ({ page }) => {
    const res = await page.goto("/events/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("form.fbar[aria-label='Filter events']")).toHaveCount(1);
  });

  test("upcoming events come before past ones and the city filter lives in the address", async ({ page }) => {
    await page.goto("/events/");
    const rows = page.locator(".ag-row");
    test.skip((await rows.count()) === 0, "no events to show");
    const tags = await page.locator(".ag-row .ag-tag").allInnerTexts();
    const firstPast = tags.findIndex((t) => /past/i.test(t));
    if (firstPast >= 0) expect(tags.slice(firstPast).every((t) => /past/i.test(t))).toBe(true);
    const select = page.locator("form.fbar select[name='city']");
    const cities = await select.locator("option").evaluateAll((os) => os.map((o) => (o as HTMLOptionElement).value).filter(Boolean));
    test.skip(cities.length === 0, "no cities to filter by");
    await Promise.all([page.waitForURL(/city=/), select.selectOption(cities[0])]);
    expect(new URL(page.url()).searchParams.get("city")).toBe(cities[0]);
    await expect(page.locator(".fbar-clear")).toHaveCount(1);
    await expect(page.locator(".fbar-n")).toHaveAttribute("role", "status");
  });

  test("the Stories page has no Events filter and no upcoming-events strip", async ({ page }) => {
    const res = await page.goto("/stories/");
    test.skip(!res || res.status() >= 400, "stories page does not exist yet");
    await expect(page.locator("form.fbar select[name='city']")).toHaveCount(0);
    await expect(page.locator(".ev-hero, .ag-row")).toHaveCount(0);
  });
});
