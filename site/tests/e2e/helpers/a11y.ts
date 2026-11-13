import AxeBuilder from "@axe-core/playwright";
import { expect, type Page } from "@playwright/test";

/**
 * Waits for the page's finite animations and transitions to end (entrance fades, reveals). Axe reads colours at the moment it runs,
 * so an element caught half-way through a fade shows a blended colour and a false contrast failure. Endless animations are left alone.
 */
async function settleAnimations(page: Page): Promise<void> {
  await page.evaluate(async () => {
    for (let round = 0; round < 10; round++) {
      const running = document
        .getAnimations()
        .filter((a) => a.playState === "running" && Number.isFinite(a.effect?.getComputedTiming().endTime ?? Infinity));
      if (running.length === 0) return;
      await Promise.all(running.map((a) => a.finished.catch(() => undefined)));
    }
  });
}

/** Fails the test on any WCAG 2.x A/AA axe violation (REQ-043). */
export async function expectNoA11yViolations(page: Page): Promise<void> {
  await settleAnimations(page);
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"]).analyze();
  expect(results.violations, results.violations.map((v) => `${v.id}: ${v.help}`).join("\n")).toEqual([]);
}
