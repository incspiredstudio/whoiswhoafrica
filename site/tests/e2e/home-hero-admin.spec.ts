import { test, expect } from "@playwright/test";
import path from "node:path";
import { adminUrl, loginAs, removeStaff, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 799 (REQ-001, REQ-041, REQ-257): an editor replaces a home hero clip from the Home page settings screen through the
// WordPress media modal (a real upload of a bundled, already optimised clip), the home page then shows it, and Reset to bundled
// puts the bundled clip back. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS; skips without them.
const CLIP = path.resolve(__dirname, "../../wp-content/themes/whoiswho-child/assets/home/hero-3.mp4");
const POSTER = path.resolve(__dirname, "../../wp-content/themes/whoiswho-child/assets/home/hero-3-540.jpg");

test.describe("home hero videos from the admin", () => {
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

  async function pick(page: import("@playwright/test").Page, slot: number, field: string, file: string): Promise<void> {
    const form = page.locator(`form.wiw-hero-slot[data-slot="${slot}"]`);
    await form.locator(`button.wiw-hero-pick[data-target="${field}"]`).click();
    const modal = page.locator(".media-modal:visible");
    await modal.getByRole("tab", { name: /upload files/i }).click();
    await modal.locator('input[type="file"]').first().setInputFiles(file);
    const choose = modal.locator(".media-toolbar-primary .button-primary");
    await expect(choose).toBeEnabled({ timeout: 60_000 });
    await choose.click();
    await expect(page.locator(".media-modal:visible")).toHaveCount(0);
  }

  test("an editor chooses, saves and resets a clip", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("admin.php?page=wiw-home-settings"));
    await expect(page.getByRole("heading", { name: "Home page hero videos" })).toBeVisible();
    await expect(page.locator("form.wiw-hero-slot")).toHaveCount(4);
    await expect(page.getByText("optimise-hero-video.sh").first()).toBeVisible();
    await expect(page.getByText(/Total weight of the four MP4 clips/)).toBeVisible();

    await pick(page, 3, "video_id", CLIP);
    await pick(page, 3, "poster_id", POSTER);
    const form = page.locator('form.wiw-hero-slot[data-slot="3"]');
    await expect(form.locator('input[name="video_id"]')).not.toHaveValue("0");
    await expect(form.locator('input[name="poster_id"]')).not.toHaveValue("0");
    await form.getByRole("button", { name: "Save clip" }).click();
    await expect(page.locator(".notice")).toContainText("Saved slot 3");
    await expect(page.locator('form.wiw-hero-slot[data-slot="3"] .wiw-hero-state')).toContainText("Your clip");

    // The home page now plays the chosen file for that slot and the bundled one for the others.
    await page.goto("/");
    const sources = await page.locator(".h-vid video").evaluateAll((vs) =>
      vs.map((v) => ((v as HTMLVideoElement).querySelector('source[type="video/mp4"]') as HTMLSourceElement).src),
    );
    expect(sources).toHaveLength(4);
    expect(sources[2]).toMatch(/\/uploads\/.*hero-3.*\.mp4$/);
    expect(sources[0]).toMatch(/\/assets\/home\/hero-1\.mp4$/);
    const first = await page.locator(".h-vid .h-vid-poster").first().getAttribute("fetchpriority");
    expect(first).toBe("high");

    await page.goto(adminUrl("admin.php?page=wiw-home-settings"));
    await page.locator('form.wiw-hero-slot[data-slot="3"]').getByRole("button", { name: "Reset to bundled" }).click();
    await expect(page.locator(".notice")).toContainText("bundled clip");
    await page.goto("/");
    const after = await page.locator(".h-vid video").nth(2).evaluate((v) => ((v as HTMLVideoElement).querySelector('source[type="video/mp4"]') as HTMLSourceElement).src);
    expect(after).toMatch(/\/assets\/home\/hero-3\.mp4$/);
  });
});
