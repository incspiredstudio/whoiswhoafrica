import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, setReducedMotion } from "./helpers/page";

// Needs the Events list (built by the events template issues) with at least one event. Until then this spec skips.
// The markup rules are covered by tests/Integration/EventComponentsTest.php (REQ-264, REQ-265, D-005).
test("agenda rows read in full, past rows are muted, no author or comments (REQ-264)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/events/");
  test.skip(!res || res.status() >= 400, "events list does not exist yet");
  const rows = page.locator(".ag-row");
  test.skip((await rows.count()) === 0, "no events to show");
  await expect(rows.first().locator(".ag-title")).not.toBeEmpty();
  await expect(page.locator(".ag-row .ag-tag").first()).toBeVisible();
  for (const word of ["comment", "reading time", "min read"]) expect((await page.locator("main").innerText()).toLowerCase()).not.toContain(word);
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});

test("reduced motion: rows still read in full (REQ-264, REQ-043)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  await setReducedMotion(page);
  const res = await page.goto("/events/");
  test.skip(!res || res.status() >= 400, "events list does not exist yet");
  const link = page.locator(".ag-link").first();
  test.skip((await link.count()) === 0, "no events to show");
  const before = await link.boundingBox();
  await link.hover();
  const after = await link.boundingBox();
  expect(after?.x).toBe(before?.x);
});

test("an event page shows details and opens registration in a new tab (REQ-264)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/events/");
  test.skip(!res || res.status() >= 400, "events list does not exist yet");
  const first = page.locator(".ag-link").first();
  test.skip((await first.count()) === 0, "no events to show");
  await first.click();
  await expect(page.locator(".ev-card")).toHaveCount(1);
  const reg = page.locator(".ev-reg");
  if ((await reg.count()) > 0) {
    await expect(reg).toHaveAttribute("target", "_blank");
    await expect(reg).toHaveAttribute("rel", /noopener/);
  } else {
    await expect(page.locator(".ev-card")).toContainText("This event has ended");
  }
});
