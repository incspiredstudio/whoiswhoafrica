import { test, expect } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll, setReducedMotion } from "./helpers/page";
import { runHelper } from "./helpers/staff-users";

// M4-AWD-18: what the public Awards pages show in each state, with the state set by the test instead of found on the site
// (REQ-198, REQ-259, REQ-246, REQ-196). tests/e2e/helpers/seed-awards.php switches Nominations on and off and seeds one category,
// a Live nominee, a Live winner and a Not live nomination, all fictional; it refuses outside a local environment.
// The page-by-page checks that skip by state are in awards-nominate.spec.ts, awards-winners.spec.ts and awards-public.spec.ts, and the
// six-step posting flow with the emailed link is in awards-nominate-form.spec.ts (WIW_NOMINATE_FLOW and WIW_NOMINATE_VERIFY_CMD).
// Because these tests flip the one global switch, they run once (the 1280 px project) and in order, and put the switch back at the
// end. Needs WIW_THEME_READY=1, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type Seed = { category: number; live: string; winner: string; hidden: string };

const ready = () =>
  !!process.env.WIW_THEME_READY &&
  !!process.env.WIW_SECRETS_FILE &&
  process.env.WIW_E2E_TEST_MODE === "1";

test.describe("awards visibility", () => {
  test.skip(
    !ready(),
    "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)",
  );
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs once, in the 1280 px project",
  );
  test.describe.configure({ mode: "serial" });
  let seed: Seed;

  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seed = runHelper<Seed>("seed-awards.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-awards.php", "remove");
  });

  test("closed: /nominate says so, shows no form and no sign-up, and keeps the guidance (REQ-259, REQ-198)", async ({
    page,
  }) => {
    runHelper("seed-awards.php", "off");
    const res = await page.goto("/nominate/");
    test.skip(
      res?.status() === 404,
      "the Nominate page is not built on this site",
    );
    await expect(
      page.getByRole("heading", { level: 1, name: "Nominations are closed" }),
    ).toBeVisible();
    await expect(page.locator("#nominate-form, [data-nominate]")).toHaveCount(
      0,
    );
    await expect(page.locator("main form")).toHaveCount(0);
    await expect(page.locator("main input[type=email]")).toHaveCount(0);
    await expect(
      page.getByRole("heading", { level: 2, name: "What to have ready" }),
    ).toBeVisible();
    // No Nominate link in the header, drawer or footer while closed.
    await page.goto("/");
    await expect(
      page.locator("header.site-head a[href*='/nominate']"),
    ).toHaveCount(0);
    await expect(
      page.locator("footer.site-foot a[href*='/nominate']"),
    ).toHaveCount(0);
  });

  test("open: /nominate shows the six-step form for the nominator's details first (REQ-259, REQ-196)", async ({
    page,
  }) => {
    runHelper("seed-awards.php", "on");
    const res = await page.goto("/nominate/");
    test.skip(
      res?.status() === 404,
      "the Nominate page is not built on this site",
    );
    await expect(
      page.getByRole("heading", { level: 1, name: "Nominate someone" }),
    ).toBeVisible();
    const form = page.locator("[data-nominate]");
    test.skip(
      (await form.count()) === 0,
      "the nomination form is not built on this site",
    );
    await expect(
      page.getByRole("heading", { level: 1, name: "Nominations are closed" }),
    ).toHaveCount(0);
    await expect(page.locator("#f-nominator_email")).toBeVisible();
    await expect(page.locator("#f-consent")).toBeVisible();
    await assertNoHorizontalScroll(page);
  });

  test("a Not live nomination shows nowhere and never names its nominator (REQ-246, REQ-198)", async ({
    page,
    request,
  }) => {
    const res = await request.get(`/awards/nominee/${seed.hidden}/`);
    expect(res.status()).toBe(404);
    for (const path of [
      "/awards/nominees/",
      "/awards/winners/",
      `/awards/category/${seed.category}/`,
    ]) {
      const html = await (await request.get(path)).text();
      expect(html, `${path} must not show the Not live nominee`).not.toContain(
        "E2e Awards Hidden",
      );
    }
    const sitemaps = await (await request.get("/wp-sitemap.xml")).text();
    expect(sitemaps).not.toContain(seed.hidden);
    // A live nominee is shown, and no page says who nominated them.
    await page.goto(`/awards/nominee/${seed.live}/`);
    await expect(page.locator("h1")).toContainText("E2e Awards Live");
    await expect(page.getByText(/nominated by/i)).toHaveCount(0);
    await expect(
      page.getByText("A fictional note for the browser tests."),
    ).toHaveCount(0);
  });

  test("winners: the winner is listed, filters live in the address, a filter that matches nobody says so (REQ-198)", async ({
    page,
  }) => {
    await page.goto("/awards/winners/");
    await expect(page.getByText("E2e Awards Winner").first()).toBeVisible();
    await expect(page.getByText("E2e Awards Live")).toHaveCount(0);
    await page.goto("/awards/winners/?group=technology-innovation&sort=name");
    await expect(page.locator("select[name=sort]")).toHaveValue("name");
    await expect(page.getByText("E2e Awards Winner").first()).toBeVisible();
    await page.goto("/awards/winners/?q=zzzz-nobody");
    await expect(page.getByText("No winners match")).toBeVisible();
  });

  test("winners: confetti plays once on load, and not under reduced motion (REQ-246)", async ({
    page,
    browser,
  }) => {
    await page.goto("/awards/winners/");
    await expect(page.locator(".win-body")).toHaveAttribute(
      "data-confetti",
      /playing|done/,
    );
    await expect(page.locator(".win-body")).toHaveAttribute(
      "data-confetti",
      "done",
      { timeout: 10_000 },
    );
    const calm = await browser.newPage();
    await setReducedMotion(calm);
    await calm.goto("/awards/winners/");
    await expect(calm.locator(".win-body")).toHaveAttribute(
      "data-confetti",
      "skipped",
    );
    await expect(calm.locator("canvas.win-confetti")).toHaveCount(0);
    await calm.close();
  });

  test("axe finds nothing on every Awards route, open and closed (REQ-198, REQ-043)", async ({
    page,
  }) => {
    test.setTimeout(120_000);
    const routes = [
      "/awards/",
      "/awards/rules/",
      "/awards/nominees/",
      `/awards/nominee/${seed?.live}/`,
      "/awards/winners/",
      `/awards/category/${seed?.category}/`,
      "/nominate/",
    ];
    for (const state of ["on", "off"]) {
      runHelper("seed-awards.php", state);
      for (const path of routes) {
        const res = await page.goto(path);
        if (res?.status() === 404) continue; // a page that is not built on this site (checked above where it matters)
        expect(res?.status(), path).toBe(200);
        await assertNoHorizontalScroll(page);
        await expectNoA11yViolations(page);
      }
    }
  });
});
