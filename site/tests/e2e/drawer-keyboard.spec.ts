import { test, expect } from "@playwright/test";

// Phone layout: the drawer opens from Menu, traps Tab, closes on Escape and returns focus to the Menu button (REQ-043).
test("drawer traps Tab and returns focus on close (REQ-043)", async ({ page }) => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  const viewport = page.viewportSize();
  test.skip(!viewport || viewport.width >= 1024, "drawer is for phones and tablets");
  await page.goto("/");
  const menu = page.locator("header.site-head .menu-btn");
  await menu.click();
  const drawer = page.locator("#drawer");
  await expect(drawer).toHaveClass(/open/);
  await expect(menu).toHaveAttribute("aria-expanded", "true");
  for (let i = 0; i < 25; i++) {
    await page.keyboard.press("Tab");
    expect(await page.evaluate(() => !!document.activeElement?.closest("#drawer")), "focus left the drawer").toBe(true);
  }
  await page.keyboard.press("Escape");
  await expect(drawer).not.toHaveClass(/open/);
  await expect(menu).toBeFocused();
});
