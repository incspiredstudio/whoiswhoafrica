import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import { assertNoHorizontalScroll } from "../helpers/page";

// M5-A11-01: axe (WCAG 2.1 A and AA) on the key templates at the four project widths (360, 768, 1280, 1600), plus 200 percent zoom,
// page language and announced messages. Needs a site whose active theme is whoiswho-child (WIW_THEME_READY=1). Pages that need
// content are driven by slugs of a seeded site and skipped, with a note, when the slug is not set:
//   WIW_PROFILE_SLUG (reviewed profile), WIW_CLAIM_SLUG (reviewed, unclaimed profile), WIW_STORY_SLUG (published story),
//   WIW_EVENT_SLUG (published event), WIW_PAST_EVENT_SLUG (an event that has ended).
// The routes that need no content always run. REQ-043, REQ-040.

type Page_ = { name: string; path: string | null; status?: number; needs?: string; optional?: boolean };

const profile = process.env.WIW_PROFILE_SLUG;
const claim = process.env.WIW_CLAIM_SLUG;
const story = process.env.WIW_STORY_SLUG;
const event = process.env.WIW_EVENT_SLUG;
const pastEvent = process.env.WIW_PAST_EVENT_SLUG;

const pages: Page_[] = [
  { name: "home", path: "/" },
  { name: "directory", path: "/directory/" },
  { name: "industries", path: "/industries/" },
  { name: "stories list", path: "/stories/" },
  { name: "events list", path: "/events/" },
  { name: "nominate", path: "/nominate/", optional: true },
  { name: "get listed", path: "/get-listed/", optional: true },
  { name: "contact", path: "/contact/" },
  { name: "newsletter", path: "/newsletter/", optional: true },
  { name: "search with no results", path: "/?s=zzqqxxnomatchwiw" },
  { name: "404", path: "/this-page-does-not-exist-wiw/", status: 404 },
  { name: "profile", path: profile ? `/profile/${profile}/` : null, needs: "WIW_PROFILE_SLUG" },
  { name: "claim", path: claim ? `/claim/${claim}/` : null, needs: "WIW_CLAIM_SLUG" },
  { name: "correct", path: profile ? `/correct/${profile}/` : null, needs: "WIW_PROFILE_SLUG" },
  { name: "remove", path: profile ? `/remove/${profile}/` : null, needs: "WIW_PROFILE_SLUG" },
  { name: "report", path: profile ? `/report/${profile}/` : null, needs: "WIW_PROFILE_SLUG" },
  { name: "story", path: story ? `/stories/${story}/` : null, needs: "WIW_STORY_SLUG" },
  { name: "event", path: event ? `/events/${event}/` : null, needs: "WIW_EVENT_SLUG" },
  { name: "past event", path: pastEvent ? `/events/${pastEvent}/` : null, needs: "WIW_PAST_EVENT_SLUG" },
];

async function open(page: Page, p: Page_): Promise<void> {
  test.skip(p.path === null, `set ${p.needs}`);
  const res = await page.goto(p.path as string);
  test.skip(p.optional === true && res?.status() === 404, `${p.path} is not built yet`);
  expect(res?.status(), `${p.path} status`).toBe(p.status ?? 200);
}

test.describe("key pages sweep (REQ-043, REQ-040)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  for (const p of pages) {
    test.describe(p.name, () => {
      test("axe finds no WCAG 2.1 AA violation and the page does not scroll sideways", async ({ page }) => {
        await open(page, p);
        await expect(page.locator("html")).toHaveAttribute("lang", /^[a-z]{2}/i);
        await assertNoHorizontalScroll(page);
        await expectNoA11yViolations(page);
      });
    });
  }

  test.describe("zoom to 200 percent", () => {
    // 200 percent zoom at 1280 px is the layout of a 640 px viewport: content and the main controls must still be there.
    test.use({ viewport: { width: 640, height: 450 } });
    for (const p of pages.filter((x) => ["home", "directory", "nominate", "contact", "get listed"].includes(x.name))) {
      test(`${p.name} keeps its content and function`, async ({ page }) => {
        await open(page, p);
        await assertNoHorizontalScroll(page);
        await expect(page.locator("main h1")).toHaveCount(1);
        await expect(page.locator("main h1")).toBeVisible();
        const menu = page.locator("header [aria-controls], header summary, header nav a").first();
        await expect(menu).toBeAttached();
        for (const f of await page.locator("main form").all()) {
          await expect(f.locator("button[type=submit], input[type=submit]").first()).toBeAttached();
        }
      });
    }
  });

  test.describe("announced messages", () => {
    test("a refused form submission shows an alert region with aria-invalid fields", async ({ page }) => {
      test.skip(!profile, "set WIW_PROFILE_SLUG");
      await page.goto(`/correct/${profile}/`);
      await page.locator("form.form button[type=submit]").click();
      await expect(page.locator(".errbox[role=alert]")).toBeVisible();
      await expect(page.locator("form.form [aria-invalid=true]").first()).toBeVisible();
      await expectNoA11yViolations(page);
    });
  });
});
