import { test, expect, type Page } from "@playwright/test";
import { execSync } from "node:child_process";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";

// The six-step nomination form and its result pages (REQ-030, REQ-196, REQ-233, REQ-122). Needs WIW_THEME_READY=1 and nominations
// switched on; it skips on a closed site. The posting tests send real forms, and the form allows 3 verification emails per address
// and 10 posts per address per hour, so they run only when WIW_NOMINATE_FLOW=1 and only in one browser project. The full submit also
// needs the draft marked verified (the emailed link cannot be opened here): set WIW_NOMINATE_VERIFY_CMD to a command that takes the
// draft token as its last argument and verifies it (a local test site only).
const unique = () => `nominator-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.org`;

async function open(page: Page): Promise<boolean> {
  await page.goto("/nominate/");
  return (await page.locator("[data-nominate]").count()) > 0;
}

async function stepOne(page: Page, email = unique()): Promise<void> {
  await page.locator("#f-nominator_name").fill("Test Nominator");
  await page.locator("#f-nominator_email").fill(email);
  await page.locator("#f-consent").check();
  // The form refuses a post made less than 3 seconds after the page was served.
  await page.waitForTimeout(3500);
  await page.getByRole("button", { name: "Send me a verification link" }).click();
}

async function stepsThreeToFive(page: Page, evidence: string, nominee: string): Promise<void> {
  await page.getByRole("heading", { level: 2, name: "Who are you nominating?" }).waitFor();
  await page.locator("#f-nominee_type").selectOption("person");
  await page.locator("#f-nominee_name").fill(nominee);
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByRole("heading", { level: 2, name: "Category and evidence" }).waitFor();
  await page.locator("#f-category_id").selectOption({ index: 1 });
  await page.locator("#f-evidence").fill(evidence);
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByRole("heading", { level: 2, name: "Conflict declaration" }).waitFor();
  await page.locator("#f-relationship").selectOption("none");
  await page.getByRole("button", { name: "Continue" }).click();
  await page.getByRole("heading", { level: 2, name: "Review and submit" }).waitFor();
}

test.describe("nomination form", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("six-steps: the documented order, accessible at every width", async ({ page }) => {
    test.skip(!(await open(page)), "nominations are closed on this site");
    const names = await page.locator(".nm-progress .nm-progress-t").allTextContents();
    expect(names.map((n) => n.trim())).toEqual([
      "Your details",
      "Verify email",
      "Nominee",
      "Category and evidence",
      "Conflict declaration",
      "Review and submit",
    ]);
    await expect(page.locator(".nm-progress [aria-current=step]")).toHaveCount(1);
    await expect(page.getByRole("heading", { level: 2, name: "Your details" })).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a step with missing answers stays put and says what is needed", async ({ page }) => {
    test.skip(!(await open(page)), "nominations are closed on this site");
    await page.getByRole("button", { name: "Send me a verification link" }).click();
    await expect(page.getByRole("alert").filter({ hasText: "Please check the highlighted fields." })).toBeVisible();
    await expect(page.locator("#f-nominator_name")).toHaveAttribute("aria-invalid", "true");
    await expect(page.getByRole("heading", { level: 2, name: "Your details" })).toBeVisible();
  });

  test("expired-link: an unknown or used link shows the expired page", async ({ page }) => {
    const res = await page.goto("/link/not-a-real-token-0000000000000000/");
    expect(res?.status()).toBeLessThan(500);
    await expect(page.getByRole("heading", { level: 1, name: "This link has expired or was already used" })).toBeVisible();
  });

  test("the done page shows the nomination reference", async ({ page }) => {
    await page.goto("/done/?kind=nomination&ref=NOM-A1B2C3");
    await expect(page.getByRole("heading", { level: 1, name: "Nomination submitted" })).toBeVisible();
    await expect(page.getByText("NOM-A1B2C3")).toBeVisible();
  });

  test.describe("posting", () => {
    test.skip(!process.env.WIW_NOMINATE_FLOW, "set WIW_NOMINATE_FLOW=1 to post real forms");
    test.setTimeout(240000);
    // Sending a real verification email can take a while on a test site.
    const slow = expect.configure({ timeout: 60000 });

    test("verify, evidence block and a reference", async ({ page }, info) => {
      test.skip(info.project.name !== "chromium-1280", "posting tests run in one project");
      test.skip(!(await open(page)), "nominations are closed on this site");
      const nominee = `Fictional Example Person ${Date.now()}`;
      await stepOne(page);
      await slow(page.getByRole("heading", { level: 2, name: "Verify your email" })).toBeVisible();
      // Not verified yet: the plugin refuses and the form returns to the verify step.
      await page.getByRole("button", { name: "I have verified my email" }).click();
      await stepsThreeToFive(page, "They led the fictional regional programme in 2024 and doubled its reach.", nominee);
      await page.getByRole("button", { name: "Submit nomination" }).click();
      await slow(page.getByRole("heading", { level: 2, name: "Verify your email" })).toBeVisible();
      await slow(page.getByRole("alert").filter({ hasText: "have not seen your email confirmed" })).toBeVisible();

      const verify = process.env.WIW_NOMINATE_VERIFY_CMD;
      test.skip(!verify, "set WIW_NOMINATE_VERIFY_CMD to verify the draft without the emailed link");
      const draft = await page.evaluate(() => sessionStorage.getItem("wiw_nomination_draft"));
      expect(draft).toBeTruthy();
      execSync(`${verify} ${draft}`);
      await page.getByRole("button", { name: "I have verified my email" }).click();
      await stepsThreeToFive(page, "Contact them on 08031234567 for details of the fictional programme and its results.", nominee);
      await page.getByRole("button", { name: "Submit nomination" }).click();
      await slow(page.getByRole("heading", { level: 2, name: "Category and evidence" })).toBeVisible();
      await slow(page.locator("#f-evidence-err")).toBeVisible();

      await page.locator("#f-evidence").fill("They led the fictional regional programme in 2024 and doubled its reach.");
      await page.getByRole("button", { name: "Continue" }).click();
      await page.getByRole("button", { name: "Continue" }).click();
      await page.getByRole("button", { name: "Submit nomination" }).click();
      await slow(page).toHaveURL(/\/done\/\?kind=nomination&ref=NOM-[A-F0-9]{6}/);
      await slow(page.getByRole("heading", { level: 1, name: "Nomination submitted" })).toBeVisible();

      // The same nominee in the same category again: a warning, and the nominator may still continue.
      await open(page);
      await stepOne(page);
      await slow(page.getByRole("heading", { level: 2, name: "Verify your email" })).toBeVisible();
      const again = await page.evaluate(() => sessionStorage.getItem("wiw_nomination_draft"));
      execSync(`${verify} ${again}`);
      await page.getByRole("button", { name: "I have verified my email" }).click();
      await stepsThreeToFive(page, "They led the fictional regional programme in 2024 and doubled its reach.", nominee);
      await page.getByRole("button", { name: "Submit nomination" }).click();
      await slow(page.getByText("This nominee may already be nominated.")).toBeVisible();
      await page.getByRole("button", { name: "Continue anyway" }).click();
      await slow(page).toHaveURL(/\/done\/\?kind=nomination&ref=NOM-[A-F0-9]{6}/);
    });

    test("too-many: repeated verification emails from one address show the limit state", async ({ page }, info) => {
      test.skip(info.project.name !== "chromium-1280", "posting tests run in one project");
      test.skip(!(await open(page)), "nominations are closed on this site");
      await stepOne(page);
      await slow(page.getByRole("heading", { level: 2, name: "Verify your email" })).toBeVisible();
      for (let i = 0; i < 3; i++) {
        await page.waitForTimeout(3500);
        await page.getByRole("button", { name: "Send a new link" }).click();
      }
      await slow(page.getByText("Too many nominations from this device.")).toBeVisible();
    });
  });
});
