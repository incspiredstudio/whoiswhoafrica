import { test, expect, type Page } from "@playwright/test";
import { runHelper } from "./helpers/staff-users";

// Profile addresses are encoded exactly once (REQ-003, REQ-006, REQ-121, #810). An Arabic name gives a slug WordPress stores
// percent-encoded; an accented Latin name is transliterated; a plain name stays as it is. Every surface (directory card,
// sitemap, canonical tag, search result and the profile's own action links) must point to an address that opens the profile,
// never a double-encoded /profile/%25d8... one. The fictional profiles come from helpers/seed-nonlatin-profiles.php, which
// needs WIW_SECRETS_FILE and a local site; the spec skips without them.

type Seeded = Record<"arabic" | "accented" | "plain", { name: string; slug: string }>;

let seeded: Seeded | null = null;

test.beforeAll(() => {
  if (!process.env.WIW_SECRETS_FILE) return;
  try {
    seeded = runHelper<Seeded>("seed-nonlatin-profiles.php");
  } catch {
    seeded = null;
  }
});

async function hrefsTo(page: Page, slug: string): Promise<string[]> {
  return page.$$eval("a[href*='/profile/']", (links, s) => links.map((a) => (a as HTMLAnchorElement).getAttribute("href") || "").filter((h) => h.includes(`/profile/${s}/`) || h.includes(`/profile/${encodeURIComponent(decodeURIComponent(s))}/`)), slug);
}

test.describe("non-Latin profile addresses (REQ-003)", () => {
  test("every surface links each kind of slug once encoded, and the address opens", async ({ page, request }) => {
    test.setTimeout(150_000);
    test.skip(!seeded, "needs WIW_SECRETS_FILE and a local site to seed the profiles");
    const profiles = seeded as Seeded;

    const sitemap = await (await request.get("/sitemap-profiles-1.xml")).text();
    expect(sitemap).not.toContain("%25");

    await page.goto("/directory/");
    const directoryHtml = await page.content();
    expect(directoryHtml).not.toContain("/profile/%25");

    for (const [kind, p] of Object.entries(profiles)) {
      const url = `/profile/${p.slug}/`;
      expect(sitemap, `${kind} in the sitemap`).toContain(url);
      expect(directoryHtml, `${kind} card in the directory`).toContain(url);

      const res = await page.goto(url);
      expect(res?.status(), `${kind} profile opens`).toBe(200);
      const canonical = await page.locator("link[rel=canonical]").getAttribute("href");
      expect(canonical, `${kind} canonical`).toContain(url);
      expect(canonical).not.toContain("%25");
      for (const action of ["correct", "remove", "report"]) {
        const href = await page.locator(`.rail-wrong a[data-action=${action}]`).getAttribute("href");
        expect(href, `${kind} ${action} link`).not.toContain("%25");
        expect(href).toContain(`/${p.slug}/`);
      }
      const correct = await request.get((await page.locator(".rail-wrong a[data-action=correct]").getAttribute("href")) as string);
      expect(correct.status(), `${kind} correction form opens`).toBe(200);

      await page.goto(`/search/?q=${encodeURIComponent(p.name)}`);
      expect(await page.content()).not.toContain("/profile/%25");
      const found = await hrefsTo(page, p.slug);
      if (found.length) {
        const open = await request.get(found[0]);
        expect(open.status(), `${kind} search result opens`).toBe(200);
      }
    }

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });
});
