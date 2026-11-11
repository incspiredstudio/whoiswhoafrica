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
