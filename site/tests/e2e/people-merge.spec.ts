import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// People holds individuals and entrepreneurs, and the whole card opens the profile (REQ-001, REQ-040, D-072, #869).
// tests/e2e/helpers/seed-people-merge.php adds a reviewed individual and a reviewed entrepreneur whose names sort first on /people/.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type Seed = Record<"individual" | "entrepreneur", { slug: string; name: string }>;

const ready = () => !!process.env.WIW_THEME_READY && !!process.env.WIW_SECRETS_FILE && process.env.WIW_E2E_TEST_MODE === "1";
const pathOf = (location: string | undefined) => {
  const url = new URL(location ?? "", "http://x");
  return url.pathname + url.search;
};

test.describe("People holds individuals and entrepreneurs; the whole card opens the profile", () => {
  test.skip(!ready(), "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)");
  let seed: Seed;
  test.beforeAll(() => {
    seed = runHelper<Seed>("seed-people-merge.php");
  });

  test("the directory tabs show four lists and People lists the entrepreneur", async ({ page }) => {
    const res = await page.goto("/people/");
    expect(res?.status()).toBe(200);
    await expect(page.locator("nav[aria-label='Directory lists'] a")).toHaveText(["All", "People", "Businesses", "Institutions"]);
    await expect(page.locator("nav[aria-label='Directory lists'] a[aria-current='page']")).toHaveText("People");
    await expect(page.locator(`.pcard[data-profile='${seed.entrepreneur.slug}']`)).toHaveCount(1);
    await expect(page.locator(`.pcard[data-profile='${seed.individual.slug}']`)).toHaveCount(1);
    await expect(page.locator("main")).not.toContainText("Entrepreneur");
    await assertNoHorizontalScroll(page);
  });

  test("the old Entrepreneurs address moves permanently to People, keeping the other arguments", async ({ request }) => {
    const old = await request.get("/entrepreneurs/?sort=updated", { maxRedirects: 0 });
    expect(old.status()).toBe(301);
    expect(pathOf(old.headers()["location"])).toBe("/people/?sort=updated");
    const typed = await request.get("/directory/?type=entrepreneur&q=aaa", { maxRedirects: 0 });
    expect(typed.status()).toBe(301);
    expect(pathOf(typed.headers()["location"])).toBe("/people/?q=aaa");
  });

  test("clicking a card's picture opens the profile", async ({ page }) => {
    await page.goto("/people/");
    await page.locator(`.pcard[data-profile='${seed.entrepreneur.slug}'] .pcard-media`).click();
    await expect(page).toHaveURL(new RegExp(`/profile/${seed.entrepreneur.slug}/?$`));
    await expect(page.locator("h1")).toHaveText(seed.entrepreneur.name);
  });

  test("clicking a card's text away from the name also opens the profile", async ({ page }) => {
    await page.goto("/people/");
    const box = await page.locator(`.pcard[data-profile='${seed.individual.slug}'] .pcard-body`).boundingBox();
    expect(box).not.toBeNull();
    if (box) {
      await page.mouse.click(box.x + box.width - 4, box.y + box.height - 2);
    }
    await expect(page).toHaveURL(new RegExp(`/profile/${seed.individual.slug}/?$`));
  });

  test("the tab key stops once on each card, with a visible focus ring", async ({ page }) => {
    await page.goto("/people/");
    const count = await page.locator(".pcard").count();
    expect(count).toBeGreaterThanOrEqual(2);
    await page.locator(".pcard").first().locator(".pcard-name a").focus();
    // Focus set by script is not :focus-visible; one Shift+Tab and Tab makes it keyboard focus.
    await page.keyboard.press("Shift+Tab");
    await page.keyboard.press("Tab");
    const visited: string[] = [];
    for (let i = 0; i < Math.min(count, 4); i++) {
      const info = await page.evaluate(() => {
        const el = document.activeElement as HTMLElement | null;
        const card = el?.closest(".pcard") as HTMLElement | null;
        return {
          slug: card?.dataset.profile ?? "",
          isName: !!el?.closest(".pcard-name"),
          outline: card ? getComputedStyle(card).outlineStyle : "",
        };
      });
      expect(info.isName, "focus lands on the name link").toBe(true);
      expect(info.outline, "the card shows a focus ring").not.toBe("none");
      visited.push(info.slug);
      await page.keyboard.press("Tab");
    }
    expect(new Set(visited).size, "each Tab press moves to the next card").toBe(visited.length);
  });

  test("/people/ passes axe", async ({ page }) => {
    await page.goto("/people/");
    await expectNoA11yViolations(page);
  });
});
