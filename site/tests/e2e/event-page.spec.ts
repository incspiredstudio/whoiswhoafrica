import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a published event (a seeded site or the events import); skips with none. Template rules are covered in
// tests/Integration/EventPageTest.php (REQ-262, REQ-264, REQ-265, D-005).
test("an event page shows the poster, details and body, no registration form, axe clean (REQ-264)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/events/");
  test.skip(!res || res.status() >= 400, "events list does not exist yet");
  const link = page.locator(".ag-link").first();
  test.skip((await link.count()) === 0, "no events to show");
  await link.click();
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator(".ev-poster")).toHaveCount(1);
  await expect(page.locator(".ev-card dt", { hasText: "When" })).toHaveCount(1);
  await expect(page.locator("form")).toHaveCount(await page.locator("header form, #drawer form, #searchOv form").count());
  const reg = page.locator(".ev-reg");
  if ((await reg.count()) > 0) await expect(reg).toHaveAttribute("target", "_blank");
  else await expect(page.locator(".ev-card")).toContainText("This event has ended");
  await assertNoHorizontalScroll(page);
  await expectNoA11yViolations(page);
});

test("an unknown event answers 404 (REQ-264)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const res = await page.goto("/events/this-event-does-not-exist/");
  test.skip(!res, "no response");
  expect(res.status()).toBe(404);
});
