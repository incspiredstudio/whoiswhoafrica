import { expect, type Page } from "@playwright/test";

/** No horizontal page scroll at the current width (REQ-040). */
export async function assertNoHorizontalScroll(page: Page): Promise<void> {
  const over = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(over, "page scrolls horizontally").toBeLessThanOrEqual(0);
}

/** Tab through the page and confirm focus lands on interactive elements (REQ-043). */
export async function assertKeyboardReach(page: Page, steps = 10): Promise<void> {
  await page.keyboard.press("Tab");
  let reached = 0;
  for (let i = 0; i < steps; i++) {
    const tag = await page.evaluate(() => document.activeElement?.tagName ?? "");
    if (["A", "BUTTON", "INPUT", "SELECT", "TEXTAREA", "SUMMARY"].includes(tag)) reached++;
    await page.keyboard.press("Tab");
  }
  expect(reached, "keyboard focus never reached an interactive element").toBeGreaterThan(0);
}

export async function setReducedMotion(page: Page): Promise<void> {
  await page.emulateMedia({ reducedMotion: "reduce" });
}

/** Script-free rendering for no-JS checks. Call on the context before opening pages: see tests using test.use({ javaScriptEnabled: false }). */
export const disableScripts = { javaScriptEnabled: false } as const;

/**
 * Choose a filter value the way a visitor does at the current width, and wait for the address to carry it (REQ-029, REQ-044):
 * straight from the inline bar above 48rem; through the Filters button, the bottom sheet and "Show N results" on phones.
 */
export async function chooseFilter(page: Page, name: string, value: string, form = page.locator("form.fbar").first()): Promise<void> {
  const arrived = page.waitForURL((url) => url.searchParams.get(name) === value);
  const open = form.locator(".fbar-open");
  if (await open.isVisible()) {
    await open.click();
    await form.locator(`select[name='${name}']`).selectOption(value);
    await form.locator("[data-fbar-go]").click();
  } else {
    await form.locator(`select[name='${name}']`).selectOption(value);
  }
  await arrived;
}
