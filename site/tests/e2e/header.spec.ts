import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
test.describe("header (REQ-259, REQ-229, REQ-043, REQ-040)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("shows the groups, search, Get listed and no account menu", async ({ page, isMobile }) => {
    await page.goto("/");
    const header = page.locator("header.site-head");
    await expect(header).toBeVisible();
    // The header button shows from 640 px; on phones Get listed is the last link in the drawer.
    expect(await page.locator("header.site-head a.head-cta, #drawer a.drawer-link[href*='get-listed']").count()).toBeGreaterThan(0);
    await expect(page.getByRole("link", { name: /sign in|sign up|log in|my account/i })).toHaveCount(0);
    await expect(header.getByRole("button", { name: /search the directory/i })).toBeVisible();
    const viewport = page.viewportSize();
    if (viewport && viewport.width >= 1024 && !isMobile) {
      for (const name of ["Directory", "Awards", "About"]) await expect(header.locator("nav.nav-top").getByRole("button", { name })).toBeVisible();
      for (const name of ["Stories", "Events"]) await expect(header.locator("nav.nav-top").getByRole("link", { name })).toBeVisible();
    } else {
      await expect(header.getByRole("button", { name: /menu/i })).toBeVisible();
    }
    await assertNoHorizontalScroll(page);
  });

  test("the mega menu opens on click and closes on Escape (wide screens)", async ({ page }) => {
    const viewport = page.viewportSize();
    test.skip(!viewport || viewport.width < 1024, "mega menu is for wide screens");
    await page.goto("/");
    const button = page.locator("nav.nav-top").getByRole("button", { name: "Directory" });
    await button.click();
    await expect(button).toHaveAttribute("aria-expanded", "true");
    await expect(page.locator("#mega-directory")).toHaveClass(/open/);
    await page.keyboard.press("Escape");
    await expect(button).toHaveAttribute("aria-expanded", "false");
  });

  test("has no axe violations", async ({ page }) => {
    await page.goto("/");
    await expectNoA11yViolations(page);
  });
});
