import { test, expect, type Page } from "@playwright/test";

// The demo screen's words follow what is chosen (REQ-056, REQ-208, #810): the import confirmation lists only the ticked parts
// (Stories alone shows no Profiles paragraph), and after a part is removed its "Imported N" line and Remove button go away
// without a reload. Needs E2E_USER and E2E_PASS for an Administrator. The import-and-remove journey changes the site, so it
// runs once, in the 1280 px project.
const { E2E_USER, E2E_PASS } = process.env;

async function openSetup(page: Page): Promise<void> {
  await page.goto("/wp-login.php");
  await page.fill("#user_login", E2E_USER as string);
  await page.fill("#user_pass", E2E_PASS as string);
  await page.click("#wp-submit");
  await expect(page).toHaveURL(/wp-admin/);
  await page.goto("/wordpress/wp-admin/admin.php?page=wiw-setup", { waitUntil: "domcontentloaded" });
}

async function chooseOnly(page: Page, parts: string[]): Promise<void> {
  for (const box of await page.locator("input[data-wiw-part]").all()) {
    const part = (await box.getAttribute("data-wiw-part")) as string;
    if (parts.includes(part)) await box.check();
    else await box.uncheck();
  }
}

test.describe("demo parts on the Setup screen (REQ-056)", () => {
  test.skip(!E2E_USER || !E2E_PASS, "E2E_USER and E2E_PASS are required");

  test("the confirmation lists only the chosen parts", async ({ page }) => {
    await openSetup(page);
    await chooseOnly(page, ["stories"]);
    await page.locator("[data-wiw=open-import]").click();
    const confirm = page.locator("[data-wiw=confirm-import]");
    await expect(confirm).toBeVisible();
    await expect(confirm.locator("li[data-wiw-for=stories]")).toBeVisible();
    await expect(confirm.locator("li[data-wiw-for=profiles]")).toBeHidden();
    expect(await confirm.innerText(), "no Profiles paragraph is shown").not.toContain("Profiles:");
    await expect(confirm.locator("li[data-wiw-for=events]")).toBeHidden();
    await confirm.locator("[data-wiw=cancel]").click();

    await chooseOnly(page, ["profiles", "events"]);
    await page.locator("[data-wiw=open-import]").click();
    await expect(confirm.locator("li[data-wiw-for=profiles]")).toBeVisible();
    await expect(confirm.locator("li[data-wiw-for=events]")).toBeVisible();
    await expect(confirm.locator("li[data-wiw-for=stories]")).toBeHidden();
    await confirm.locator("[data-wiw=cancel]").click();
  });

  test("a removed part's line goes away at once", async ({ page }, info) => {
    test.skip(info.project.name !== "chromium-1280", "changes the site: runs once, in the 1280 px project");
    test.setTimeout(240_000);
    await openSetup(page);
    const part = page.locator(".wiw-part[data-part=events]");
    if ((await part.locator("[data-wiw=remove-part]").count()) === 0) {
      await chooseOnly(page, ["events"]);
      await page.locator("[data-wiw=open-import]").click();
      await page.locator("[data-wiw=do-import]").click();
      await expect(page.locator("[data-wiw=reload]")).toBeVisible({ timeout: 180_000 });
      await page.reload({ waitUntil: "domcontentloaded" });
    }
    await expect(part.locator("[data-wiw=part-status]")).toContainText("Imported");
    await part.locator("[data-wiw=remove-part]").click();
    await page.locator("[data-wiw=do-remove-part]").click();
    await expect(page.locator("[data-wiw=reload]")).toBeVisible({ timeout: 180_000 });
    await expect(part.locator("[data-wiw=part-status]")).toHaveCount(0);
    await expect(part.locator("[data-wiw=remove-part]")).toHaveCount(0);
  });
});
