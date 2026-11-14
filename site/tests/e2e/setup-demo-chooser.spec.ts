import { test, expect } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 809 (REQ-208, REQ-056, REQ-240): the administrator chooses the parts of the demo content on the Setup screen. Ticking a
// part ticks what it needs with a note saying why, unticking takes away what needs it, the import posts only the ticked parts,
// and an imported part shows when it came and has its own Remove button. Imports only the Settings part, then removes it.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS; skips without them.
test.describe("setup demo chooser", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    removeStaff();
  });

  test("parts tick what they need, and one part imports and removes on its own", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("admin.php?page=wiw-setup"));
    const demo = page.locator(".wiw-demo");
    await expect(demo.getByRole("group", { name: "Choose the parts to import" })).toBeVisible();
    const box = (name: string) => demo.getByRole("checkbox", { name, exact: true });
    for (const name of ["Profiles", "Stories", "Comments", "Events", "Awards", "Home page curation", "Settings"]) {
      await expect(box(name)).toBeVisible();
    }

    // Start from nothing ticked.
    for (const input of await demo.locator("[data-wiw-part]").all()) await input.uncheck();
    await box("Awards").check();
    await expect(box("Profiles")).toBeChecked();
    await expect(demo.locator('[data-wiw="auto-note"]')).toContainText("Profiles was ticked too. Awards need Profiles");
    await box("Comments").check();
    await expect(box("Stories")).toBeChecked();
    await box("Profiles").uncheck();
    await expect(box("Awards")).not.toBeChecked();
    await expect(demo.locator('[data-wiw="auto-note"]')).toContainText("cannot be imported without Profiles");

    // Only Settings: the start request carries exactly that part.
    for (const input of await demo.locator("[data-wiw-part]").all()) await input.uncheck();
    await box("Settings").check();
    await demo.getByRole("button", { name: "Import demo content" }).click();
    await expect(demo.locator('[data-wiw="chosen"]')).toHaveText("Settings");
    const start = page.waitForRequest((r) => r.method() === "POST" && (r.postData() ?? "").includes("action=wiw_demo_start"));
    const typed = demo.locator('[data-wiw="typed"]');
    if (await typed.count()) await typed.fill("IMPORT DEMO");
    await demo.getByRole("button", { name: "Yes, import the demo content" }).click();
    const body = new URLSearchParams((await start).postData() ?? "");
    expect(body.get("parts")).toBe("settings");
    expect(body.get("_wpnonce")).toBeTruthy();
    await expect(demo.locator('[data-wiw="label"]')).toContainText("Done", { timeout: 60_000 });

    await page.reload();
    const settings = page.locator('.wiw-part[data-part="settings"]');
    await expect(settings.locator('[data-wiw="part-status"]')).toContainText(/Imported 1 on /);
    await settings.getByRole("button", { name: "Remove Settings" }).click();
    await expect(page.locator('[data-wiw="part-title"]')).toHaveText("Remove the demo Settings?");
    await page.getByRole("button", { name: "Yes, remove it" }).click();
    await expect(demo.locator('[data-wiw="label"]')).toContainText("has been removed", { timeout: 60_000 });
    await page.reload();
    await expect(page.locator('.wiw-part[data-part="settings"] [data-wiw="part-status"]')).toHaveCount(0);
  });
});
