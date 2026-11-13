import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The Get listed page and its done page (REQ-177, REQ-120, REQ-058). Sending needs the plugin's POST routes.
test.describe("get listed page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("shows the enquiry form with a consent tick and no price, package or checkout wording", async ({ page }) => {
    const res = await page.goto("/get-listed/");
    expect(res?.status()).toBe(200);
    for (const name of ["name", "organisation", "interest", "email", "phone", "message", "consent"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    const text = (await page.locator("main").innerText()).toLowerCase();
    for (const word of ["price", "pricing", "package", "checkout", "cart", "basket"]) expect(text).not.toContain(word);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("the done page shows the reference and that terms are agreed offline", async ({ page }) => {
    await page.goto("/done/?kind=enquiry&from=get_listed&ref=ENQ-0004");
    await expect(page.getByRole("heading", { name: "Enquiry received" })).toBeVisible();
    await expect(page.getByText("ENQ-0004")).toBeVisible();
    await expect(page.getByText(/agreed offline/)).toBeVisible();
  });
});
