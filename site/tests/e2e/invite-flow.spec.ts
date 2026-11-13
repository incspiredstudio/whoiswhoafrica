import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { resetRateLimits, runHelper } from "./helpers/staff-users";

// The customer invitation page at /invite/{token} (M3-ENQ-13; REQ-013, REQ-233, REQ-141). Needs a local site with
// WIW_E2E_TEST_MODE=1 (the seed helper makes a fictional customer and a single-use link) and WIW_THEME_READY=1.
type Links = { invite: string; accept: string; decline: string };

test.describe("invitation page", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");
  test.skip(!process.env.WIW_E2E_TEST_MODE, "needs WIW_E2E_TEST_MODE=1 for the seed helper");

  test.setTimeout(60000);

  test.beforeEach(() => {
    resetRateLimits(); // every page view counts toward the per-address allowance
  });

  test.afterAll(() => {
    runHelper("seed-invitation.php", "remove");
  });

  test("shows the profile form, is not indexed and passes the page checks", async ({ page }) => {
    const { invite } = runHelper<Links>("seed-invitation.php");
    const res = await page.goto(invite);
    expect(res?.status()).toBe(200);
    await expect(page.getByRole("heading", { level: 1, name: "Your profile" })).toBeVisible();
    for (const name of ["name", "type", "summary", "website", "image"]) {
      await expect(page.locator(`form [name=${name}]`)).toHaveCount(1);
    }
    await expect(page.locator("meta[name=robots][content*=noindex]")).toHaveCount(1);
    await expect(page.locator("form.form")).not.toContainText(/price|package|payment/i);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("free text with a phone number shows a field message and keeps what was typed", async ({ page }) => {
    const { invite } = runHelper<Links>("seed-invitation.php");
    await page.goto(invite);
    await page.locator("form [name=name]").fill("E2E Invitation Co");
    await page.locator("form [name=summary]").fill("Call us on 0803 000 0000 for details.");
    await page.getByRole("button", { name: "Send for review" }).click();
    await expect(page.locator(".errbox[role=alert]")).toBeVisible();
    await expect(page.getByText("Please remove personal details such as phone numbers")).toBeVisible();
    await expect(page.locator("form [name=name]")).toHaveValue("E2E Invitation Co");
    await expect(page.getByRole("heading", { name: "Thank you" })).toHaveCount(0);
  });

  test("a file that is not a picture is refused with a message about the type", async ({ page }) => {
    const { invite } = runHelper<Links>("seed-invitation.php");
    await page.goto(invite);
    await page.locator("form [name=name]").fill("E2E Invitation Co");
    await page.locator("form [name=image]").setInputFiles({ name: "notes.png", mimeType: "image/png", buffer: Buffer.from("this is text, not a picture") });
    await page.getByRole("button", { name: "Send for review" }).click();
    await expect(page.getByText("The picture must be a JPEG, PNG or WebP file.")).toBeVisible();
  });

  test("a draft is saved against the link and comes back", async ({ page }) => {
    const { invite } = runHelper<Links>("seed-invitation.php");
    await page.goto(invite);
    await page.locator("form [name=name]").fill("E2E Draft Co");
    await page.getByRole("button", { name: "Save draft" }).click();
    await expect(page.getByText("Your draft is saved")).toBeVisible();
    await page.goto(invite);
    await expect(page.locator("form [name=name]")).toHaveValue("E2E Draft Co");
  });

  test("sending once confirms, and a second use of the link says it expired or was already used", async ({ page }) => {
    const { invite } = runHelper<Links>("seed-invitation.php");
    await page.goto(invite);
    await page.locator("form [name=name]").fill("E2E Invitation Co");
    await page.locator("form [name=type]").selectOption("organisation");
    await page.locator("form [name=summary]").fill("We make fictional things for browser tests.");
    await page.getByRole("button", { name: "Send for review" }).click();
    await expect(page.getByRole("heading", { name: "Thank you" })).toBeVisible();
    await expect(page.getByText("Nothing is published until a reviewer approves it")).toBeVisible();
    await page.goto(invite);
    await expect(page.getByRole("heading", { name: "Link expired or already used" })).toBeVisible();
    await expect(page.getByRole("link", { name: "Ask the team for a new link" })).toBeVisible();
    await expect(page.locator("form [name=summary]")).toHaveCount(0);
  });
});
