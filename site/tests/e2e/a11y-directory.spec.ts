import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// M1-DIR-21: every list and every profile type and state, scanned with axe and checked for horizontal scroll at each width the
// projects run (360, 768, 1280 and 1600 px), plus no cover or banner image (REQ-247) and no email or phone for an individual
// (REQ-040, REQ-041, REQ-043, REQ-247). tests/e2e/helpers/seed-profiles.php adds a reviewed individual (with an email and a phone
// number stored on the record), a reviewed organisation and a pending individual; it refuses outside a local environment.
// Largest Contentful Paint is measured and reported as a test annotation against the 2.5 s target (REQ-041, an assumed target);
// it does not fail the test until M5. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type Seed = { person: string; org: string; pending: string };

const ready = () =>
  !!process.env.WIW_THEME_READY &&
  !!process.env.WIW_SECRETS_FILE &&
  process.env.WIW_E2E_TEST_MODE === "1";
const lists = [
  "/directory/",
  "/people/",
  "/entrepreneurs/",
  "/businesses/",
  "/institutions/",
];

async function lcpMs(page: Page): Promise<number | null> {
  return page.evaluate(
    () =>
      new Promise<number | null>((resolve) => {
        let value: number | null = null;
        const observer = new PerformanceObserver((list) => {
          const entries = list.getEntries();
          value = entries[entries.length - 1]?.startTime ?? value;
        });
        try {
          observer.observe({
            type: "largest-contentful-paint",
            buffered: true,
          });
        } catch {
          resolve(null);
          return;
        }
        setTimeout(() => {
          observer.disconnect();
          resolve(value);
        }, 300);
      }),
  );
}

test.describe("lists and profiles: accessibility and layout", () => {
  test.skip(
    !ready(),
    "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)",
  );
  let seed: Seed;
  test.beforeAll(() => {
    seed = runHelper<Seed>("seed-profiles.php");
  });

  for (const path of lists) {
    test(`${path} passes axe with no horizontal scroll`, async ({ page }) => {
      const res = await page.goto(path);
      expect(res?.status()).toBe(200);
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
      const lcp = await lcpMs(page);
      test
        .info()
        .annotations.push({
          type: "LCP",
          description:
            lcp === null
              ? "not measured"
              : `${Math.round(lcp)} ms (target 2500 ms, not blocking until M5)`,
        });
    });
  }

  for (const [kind, key] of [
    ["an individual", "person"],
    ["an organisation", "org"],
    ["a profile pending review", "pending"],
  ] as const) {
    test(`${kind}: profile passes axe, has no horizontal scroll and no cover or banner image`, async ({
      page,
    }) => {
      const res = await page.goto(`/profile/${seed[key]}/`);
      expect(res?.status()).toBe(200);
      await expect(page.locator("h1")).toHaveCount(1);
      await assertNoHorizontalScroll(page);
      await expectNoA11yViolations(page);
      await expect(
        page.locator(
          "main img[class*=cover], main img[class*=banner], main [class*=cover-image], main [class*=banner-image]",
        ),
      ).toHaveCount(0);
      expect(
        await page.locator("main [style*='background-image']").count(),
        "no inline background image in the profile",
      ).toBe(0);
      const lcp = await lcpMs(page);
      test
        .info()
        .annotations.push({
          type: "LCP",
          description:
            lcp === null
              ? "not measured"
              : `${Math.round(lcp)} ms (target 2500 ms, not blocking until M5)`,
        });
    });
  }

  test("an individual's profile prints no email address and no phone number (REQ-247)", async ({
    request,
  }) => {
    const html = await (await request.get(`/profile/${seed.person}/`)).text();
    expect(html).not.toContain("e2e-private-person@example.invalid");
    expect(html).not.toContain("555 0100");
    expect(html).not.toMatch(/href=["'](mailto|tel):/i);
    const list = await (await request.get("/people/")).text();
    expect(list).not.toContain("e2e-private-person@example.invalid");
    expect(list).not.toMatch(/href=["'](mailto|tel):/i);
  });
});
