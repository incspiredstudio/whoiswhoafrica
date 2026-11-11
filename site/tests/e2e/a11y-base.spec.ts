import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, assertKeyboardReach } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("accessibility base (REQ-043, REQ-040, REQ-055)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("axe finds nothing and there is no sideways scroll", async ({ page }) => {
    await page.goto("/");
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("controls are keyboard reachable and the focus ring is visible", async ({ page }) => {
    await page.goto("/");
    await assertKeyboardReach(page);
    await page.goto("/");
    await page.keyboard.press("Tab");
    const outline = await page.evaluate(() => getComputedStyle(document.activeElement as Element).outlineStyle);
    expect(outline).not.toBe("none");
  });

  test("the focus trap keeps Tab inside and returns focus on release", async ({ page }) => {
    await page.goto("/");
    await page.evaluate(() => {
      document.body.insertAdjacentHTML("beforeend", '<button id="op">open</button><div id="dlg"><button id="b1">1</button><button id="b2">2</button></div>');
      (document.getElementById("op") as HTMLElement).focus();
      (window as unknown as { rel: () => void }).rel = (window as unknown as { wiw: { trapFocus: (el: HTMLElement) => () => void } }).wiw.trapFocus(document.getElementById("dlg") as HTMLElement);
    });
    await expect(page.locator("#b1")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.locator("#b2")).toBeFocused();
    await page.keyboard.press("Tab");
    await expect(page.locator("#b1")).toBeFocused();
    await page.keyboard.press("Shift+Tab");
    await expect(page.locator("#b2")).toBeFocused();
    await page.evaluate(() => (window as unknown as { rel: () => void }).rel());
    await expect(page.locator("#op")).toBeFocused();
  });

  test("announce writes into a status region", async ({ page }) => {
    await page.goto("/");
    await page.evaluate(() => (window as unknown as { wiw: { announce: (m: string) => void } }).wiw.announce("Saved"));
    await expect(page.locator('#wiw-live-status[role="status"]')).toHaveText("Saved");
  });
});
