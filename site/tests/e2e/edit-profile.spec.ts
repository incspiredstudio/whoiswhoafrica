import { deflateSync } from "node:zlib";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// Issue 806 (REQ-125, REQ-162, REQ-203, REQ-188): an editor opens Edit profile for an existing profile, writes the narrative
// sections as paragraphs in multi-line boxes with a live character counter, and adds, then removes, a photo picked in the media
// library with its open licence, credit and source. Fictional names only; the profile is made through Add profile first and the
// cleanup helper removes it and the uploaded picture. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and
// E2E_STAFF_PASS; skips without them.

// A valid 8 x 8 grey PNG, built here so no binary file is committed; enough for the media library to accept and resize.
function crc32(buf: Buffer): number {
  let crc = 0xffffffff;
  for (const byte of buf) {
    crc ^= byte;
    for (let k = 0; k < 8; k++) crc = crc & 1 ? (crc >>> 1) ^ 0xedb88320 : crc >>> 1;
  }
  return (crc ^ 0xffffffff) >>> 0;
}
function chunk(type: string, data: Buffer): Buffer {
  const len = Buffer.alloc(4);
  len.writeUInt32BE(data.length);
  const body = Buffer.concat([Buffer.from(type, "ascii"), data]);
  const crc = Buffer.alloc(4);
  crc.writeUInt32BE(crc32(body));
  return Buffer.concat([len, body, crc]);
}
function png(size = 8): Buffer {
  const header = Buffer.alloc(13);
  header.writeUInt32BE(size, 0);
  header.writeUInt32BE(size, 4);
  header.set([8, 0, 0, 0, 0], 8); // 8-bit greyscale.
  const rows = Buffer.alloc((size + 1) * size, 0x80);
  for (let y = 0; y < size; y++) rows[y * (size + 1)] = 0; // Filter byte per row.
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    chunk("IHDR", header),
    chunk("IDAT", deflateSync(rows)),
    chunk("IEND", Buffer.alloc(0)),
  ]);
}
const PNG = png();

test.describe("edit a profile's sections and photo", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(150_000);
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("cleanup-added-profiles.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("cleanup-added-profiles.php");
    removeStaff();
  });

  const name = `E2E Addp Editable ${Date.now().toString(36)}`;

  async function addProfile(page: Page): Promise<void> {
    await page.goto(adminUrl("admin.php?page=wiw-profile-add"));
    await page.selectOption("#wiw-add-type", "individual");
    await page.getByRole("button", { name: "Continue" }).click();
    await page.fill("#wiw-f-name", name);
    const country = page.locator("#wiw-f-country");
    await country.selectOption((await country.locator("option").nth(1).getAttribute("value")) as string);
    await page.fill("#wiw-f-sources", "https://example.org/fictional-source");
    await page.getByRole("button", { name: "Save profile" }).click();
    await expect(page.locator(".notice-success")).toContainText("review queue");
    await page.getByRole("link", { name: "Open the profile" }).click();
    await expect(page.getByRole("heading", { name: "Edit profile", level: 1 })).toBeVisible();
  }

  test("an editor writes the sections as paragraphs and sets, then removes, a photo", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await addProfile(page);
    const editUrl = page.url();

    // Words, not codes (issue 806): type and state, origin, field names in the sources table.
    const wrap = page.locator(".wrap").first();
    await expect(wrap).not.toContainText(/individual \/|published_reviewed|imported_not_public/);
    await expect(wrap.locator("td", { hasText: /^(manual|import)$/ })).toHaveCount(0);
    // The Directory menu stays open with Profiles current, and there is no second top-level Profiles menu (issue 806).
    const menu = page.locator("#adminmenu");
    await expect(menu.locator("li#toplevel_page_wiw-menu-directory.wp-has-current-submenu")).toHaveCount(1);
    await expect(menu.locator("li.current > a[href*='page=wiw-profiles']")).toHaveCount(1);
    await expect(menu.locator("#menu-posts-wiw_profile")).toHaveCount(0);

    // Plain labels, and the four sections are multi-line boxes with a counter.
    await expect(page.locator('label[for="wiw-f-current_title"]')).toHaveText("Current title");
    for (const key of ["bio", "achievement", "influence", "legacy"]) {
      await expect(page.locator(`textarea#wiw-f-${key}`)).toHaveAttribute("maxlength", "6000");
    }
    const achievement = "Founded a fictional choir.\n\nToured three fictional cities.";
    await page.fill("#wiw-f-achievement", achievement);
    await expect(page.locator("#wiw-count-f_achievement")).toHaveText(`${achievement.length} / 6000 characters`);
    await page.fill("#wiw-f-legacy", "A fictional music school.");
    await page.locator('input[name="reason"][required]').first().fill("E2E sections");
    await page.getByRole("button", { name: "Save changes" }).click();
    await expect(page.locator(".notice-success")).toContainText("Saved");
    await expect(page.locator("#wiw-f-achievement")).toHaveValue(achievement);

    // Photo: pick it in the media library (uploaded there), give the licence, credit and source.
    await page.getByRole("button", { name: "Choose from media library" }).click();
    const modal = page.locator(".media-modal:visible");
    await expect(modal).toBeVisible();
    await modal.locator('input[type="file"]').setInputFiles({ name: "e2e-edit-portrait.png", mimeType: "image/png", buffer: PNG });
    await expect(modal.locator(".attachment.selected.save-ready, .attachment.details.selected")).toHaveCount(1, { timeout: 30_000 });
    await modal.locator(".media-button-select").click();
    await expect(page.locator("#wiw-f-image_id")).not.toHaveValue("");
    await expect(page.locator("#wiw-image-name")).toContainText("e2e-edit-portrait");
    await page.selectOption("#wiw-f-image_licence", "CC-BY-4.0");
    await page.fill("#wiw-f-image_credit", "Fictional Photographer");
    await page.fill("#wiw-f-image_source", "https://example.org/fictional-photo");
    await page.fill("#wiw-f-image_reason", "E2E photo");
    await page.getByRole("button", { name: "Save photo" }).click();
    await expect(page.locator(".notice-success")).toContainText("Saved");
    await expect(page.locator("img.wiw-image-preview")).toBeVisible();
    await expect(page.locator("#wiw-f-image_licence")).toHaveValue("CC-BY-4.0");
    await expect(page.locator("#wiw-f-image_credit")).toHaveValue("Fictional Photographer");

    // A licence outside the allowlist cannot even be chosen; leaving the licence empty is refused.
    await expect(page.locator('#wiw-f-image_licence option[value="CC-BY-NC-4.0"]')).toHaveCount(0);
    await page.selectOption("#wiw-f-image_licence", "");
    await page.fill("#wiw-f-image_reason", "E2E empty licence");
    await page.getByRole("button", { name: "Save photo" }).click();
    await expect(page.locator(".notice-error")).toContainText("open licences");

    // Remove it.
    await page.goto(editUrl);
    await page.fill("#wiw-f-image_reason", "E2E remove");
    await page.getByRole("button", { name: "Remove photo" }).click();
    await expect(page.locator(".notice-success")).toContainText("Saved");
    await expect(page.getByRole("button", { name: "Remove photo" })).toHaveCount(0);
    await expect(page.locator("#wiw-image-name")).toHaveText("Nothing chosen");
  });
});
