import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Nominate page (REQ-259, REQ-250, REQ-194, REQ-030). Needs WIW_THEME_READY=1. The page shows the state the Nominations switch
// decides: each test runs in the state it describes and skips in the other (the default install is closed).
test.describe("nominate page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("closed: says so, offers links, and has no form and no sign-up", async ({ page }) => {
    const res = await page.goto("/nominate/");
    expect(res?.status()).toBe(200);
    test.skip((await page.locator("#nominate-form").count()) > 0, "nominations are open on this site");
    await expect(page.getByRole("heading", { level: 1, name: "Nominations are closed" })).toBeVisible();
    await expect(page.locator("main form")).toHaveCount(0);
    await expect(page.locator("main input[type=email]")).toHaveCount(0);
    await expect(page.locator(".nm-closed").getByRole("link", { name: "Read the rules" })).toBeVisible();
    await expect(page.locator(".nm-closed").getByRole("link", { name: "Awards overview" })).toBeVisible();
    // The guidance is still shown and collects nothing.
    await expect(page.getByRole("heading", { level: 2, name: "From nomination to announcement" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 2, name: "What to have ready" })).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("open: shows the form mount and the guidance", async ({ page }) => {
    const res = await page.goto("/nominate/");
    expect(res?.status()).toBe(200);
    test.skip((await page.locator("#nominate-form").count()) === 0, "nominations are closed on this site");
    await expect(page.getByRole("heading", { level: 1, name: "Nominate someone" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 2, name: "Who can be nominated" })).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the guidance reads its categories from the categories screen", async ({ page }) => {
    await page.goto("/nominate/");
    const cats = page.locator(".nm-cats li");
    test.skip((await cats.count()) === 0, "no categories on this site");
    await expect(cats.first().locator("strong")).not.toBeEmpty();
  });
});
