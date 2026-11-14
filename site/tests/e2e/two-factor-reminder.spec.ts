import { test, expect, type Page } from "@playwright/test";
import { runHelper, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 814 (REQ-047, REQ-235): two-factor stays optional, and a staff member without it is reminded. An editor without two-factor
// sees the warning notice on admin screens and the dashboard line, hides the notice for a day with "Remind me tomorrow" (the
// dashboard line stays), and an editor with two-factor on sees neither. Runs at 1280 and 360, each with its own fictional users.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS; skips without them.
test.describe("two-factor reminder", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => ![360, 1280].includes(viewport?.width ?? 0), "runs at 360 and 1280");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(90_000);

  let users: { on: string; off: string };
  test.beforeAll(({}, info) => {
    users = runHelper<{ on: string; off: string }>("two-factor-reminder.php", "seed", info.project.name);
  });
  test.afterAll(({}, info) => {
    runHelper("two-factor-reminder.php", "remove", info.project.name);
  });

  async function login(page: Page, login: string): Promise<string> {
    await page.goto("/wp-login.php");
    await page.fill("#user_login", login);
    await page.fill("#user_pass", process.env.E2E_STAFF_PASS as string);
    await page.click("#wp-submit");
    await expect(page).toHaveURL(/wp-admin/);
    const base = new URL(page.url()).pathname.match(/^(.*\/wp-admin\/)/);
    return base ? base[1] : "/wp-admin/";
  }

  test("an editor without two-factor is reminded and can hide it for a day", async ({ page }) => {
    const admin = await login(page, users.off);
    const notice = page.locator("#wiw-two-factor-reminder");
    await page.goto(admin + "index.php");
    await expect(notice).toBeVisible();
    await expect(notice).toHaveClass(/notice-warning/);
    await expect(notice).toContainText("Protect your account: turn on two-factor sign-in.");
    await expect(notice.getByRole("link", { name: "Turn it on now" })).toHaveAttribute("href", /profile\.php#two-factor-options$/);
    await expect(page.locator("#wiw_two_factor_reminder")).toContainText("Turn it on now");

    // Another admin screen shows it too.
    await page.goto(admin + "profile.php");
    await expect(notice).toBeVisible();
    await expect(page.locator("#two-factor-options")).toHaveCount(1);

    await page.goto(admin + "index.php");
    await notice.getByRole("link", { name: "Remind me tomorrow" }).click();
    await expect(page).toHaveURL(/wp-admin\/(index\.php)?$/);
    await expect(notice).toHaveCount(0);
    await expect(page.locator("#wiw_two_factor_reminder")).toBeVisible();
    await page.goto(admin + "edit.php");
    await expect(notice).toHaveCount(0);

    const state = runHelper<{ snoozed_for: number; emailed: boolean }>("two-factor-reminder.php", "state", users.off);
    expect(state.snoozed_for).toBeGreaterThan(86_400 - 600);
    expect(state.snoozed_for).toBeLessThanOrEqual(86_400);
    expect(state.emailed, "the first sign-in queued the one reminder email").toBe(true);
  });

  test("an editor with two-factor on sees nothing", async ({ page }) => {
    const admin = await login(page, users.on);
    await page.goto(admin + "index.php");
    await expect(page.locator("#wpbody-content")).toBeVisible();
    await expect(page.locator("#wiw-two-factor-reminder")).toHaveCount(0);
    await expect(page.locator("#wiw_two_factor_reminder")).toHaveCount(0);
    const state = runHelper<{ snoozed_for: number; emailed: boolean }>("two-factor-reminder.php", "state", users.on);
    expect(state.emailed).toBe(false);
  });
});
