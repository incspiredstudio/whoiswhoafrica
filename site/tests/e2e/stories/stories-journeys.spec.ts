import { execFileSync } from "node:child_process";
import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import { assertNoHorizontalScroll } from "../helpers/page";

// M2-STO-12: the stories journeys in a browser (REQ-027, REQ-029, REQ-182, REQ-263, REQ-155): browse, filter, read, a sponsored
// story, an expired placement and a draft. Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1) and the PHP
// command line with WIW_SECRETS_FILE set (the same file the site uses); the stories and one reviewed profile are fictional and are
// created once by tests/e2e/helpers/seed-stories.php, which refuses to run outside a local environment.
// The visibility matrix rows for story states are in tests/Integration/Stories/StoryVisibilityTest.php.

type Seed = { stories: string[]; profile: string };

function seed(mode: "" | "remove" = ""): Seed {
  const out = execFileSync("php", ["tests/e2e/helpers/seed-stories.php", ...(mode ? [mode] : [])], { encoding: "utf8", env: process.env });
  return JSON.parse(out.trim().split("\n").pop() as string) as Seed;
}

// The journeys run once, in the 1280 px project, because they share one database: the stories are created before and removed after,
// so every other spec still sees the site as it was. Page width and axe are covered by the stories list and story specs.
test.describe("stories journeys", () => {
  test.skip(!process.env.WIW_THEME_READY || !process.env.WIW_SECRETS_FILE, "needs WIW_THEME_READY and WIW_SECRETS_FILE");
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  let fixture: Seed;
  test.beforeAll(({}, info) => {
    if (info.project.name === "chromium-1280") fixture = seed();
  });
  test.afterAll(({}, info) => {
    if (info.project.name === "chromium-1280") seed("remove");
  });

  test("browse: the Stories page lists the published stories and never the draft (REQ-029, REQ-208)", async ({ page }) => {
    const res = await page.goto("/stories/");
    expect(res?.status()).toBe(200);
    for (const slug of ["e2e-story-newest", "e2e-story-middle", "e2e-story-oldest"]) {
      await expect(page.locator(`a[href$='/stories/${slug}/']`).first()).toBeVisible();
    }
    await expect(page.locator("a[href$='/stories/e2e-story-draft/']")).toHaveCount(0);
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("filter: the filter bar reports the count and a type address lists only that type (REQ-029)", async ({ page }) => {
    await page.goto("/stories/");
    await expect(page.locator("form[aria-label='Filter stories'] [role=status]")).toContainText(/\d+ stor/);
    await page.goto("/stories/?type=news");
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("a[href$='/stories/e2e-story-newest/']").first()).toBeVisible();
    await page.goto("/stories/?type=opinion");
    await expect(page.locator("a[href$='/stories/e2e-story-newest/']")).toHaveCount(0);
  });

  test("read: opening a card shows the story with one h1 and its body (REQ-027)", async ({ page }) => {
    await page.goto("/stories/");
    await page.locator("a[href$='/stories/e2e-story-newest/']").first().click();
    await expect(page).toHaveURL(/\/stories\/e2e-story-newest\/$/);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("h1")).toHaveText("E2E Story Newest");
    await expect(page.locator(".article-body")).toBeVisible();
    await expect(page.locator(".badge--sponsored")).toHaveCount(0);
  });

  test("a draft story answers 404 and shows nothing of its content (REQ-208)", async ({ page }) => {
    const res = await page.goto("/stories/e2e-story-draft/");
    expect(res?.status()).toBe(404);
    await expect(page.getByText("E2E Story Draft")).toHaveCount(0);
  });

  test("sponsored: the label and the in-story notice show while the placement is active (REQ-182, REQ-263)", async ({ page }) => {
    const res = await page.goto("/stories/e2e-story-sponsored/");
    expect(res?.status()).toBe(200);
    await expect(page.locator(".badge--sponsored")).toHaveText("Sponsored by Fictional Sponsor Ltd");
    await expect(page.locator(".notice--sponsored")).toContainText("did not write it");
    await page.goto("/stories/");
    await expect(page.locator(".stories-slot .badge--sponsored")).toHaveCount(1);
  });

  test("test_REQ_263_placement_expiry_in_browser: an ended placement leaves the story published with no label (REQ-263)", async ({ page }) => {
    const res = await page.goto("/stories/e2e-story-expired/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toHaveText("E2E Story Sponsored Expired");
    await expect(page.locator(".badge--sponsored")).toHaveCount(0);
    await expect(page.locator(".notice--sponsored")).toHaveCount(0);
    await expect(page.getByText("Sponsored by")).toHaveCount(0);
    await page.goto("/stories/");
    await expect(page.locator("a[href$='/stories/e2e-story-expired/']").first()).toBeVisible();
    await expect(page.locator(".stories-slot a[href$='/stories/e2e-story-expired/']")).toHaveCount(0);
  });

  test("order: a placement does not move the other stories (REQ-263)", async ({ page }) => {
    await page.goto("/stories/");
    const hrefs = await page.locator("main a[href*='/stories/e2e-story-']:not(.stories-slot a)").evaluateAll((as) =>
      Array.from(new Set(as.map((a) => (a as HTMLAnchorElement).getAttribute("href") ?? ""))),
    );
    const slugs = hrefs.map((h) => h.replace(/.*\/stories\/(e2e-story-[a-z]+)\/.*/, "$1"));
    // Newest first by date, with no regard to the placement: the sponsored story fills only the slot.
    const expected = ["e2e-story-newest", "e2e-story-middle", "e2e-story-oldest", "e2e-story-expired"];
    expect(slugs.filter((s) => expected.includes(s))).toEqual(expected);
    expect(slugs).not.toContain("e2e-story-sponsored");
  });

  test("test_REQ_155_no_profile_surface_shows_sponsored_or_featured", async ({ page }) => {
    for (const path of [`/profile/${fixture.profile}/`, "/directory/", "/industries/"]) {
      const res = await page.goto(path);
      expect(res?.status(), path).toBe(200);
      await expect(page.locator(".badge--sponsored, .notice--sponsored"), path).toHaveCount(0);
      const text = await page.locator("main").innerText();
      expect(text, path).not.toMatch(/\bsponsored\b/i);
      expect(text, path).not.toMatch(/\bfeatured\b/i);
    }
  });
});
