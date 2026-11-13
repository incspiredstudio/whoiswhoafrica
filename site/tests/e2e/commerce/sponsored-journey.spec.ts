import { test, expect } from "@playwright/test";
import {
  adminUrl,
  loginAs,
  removeStaff,
  runHelper,
  seedStaff,
  staffReady,
  STAFF_SKIP_REASON,
} from "../helpers/staff-users";

// M3-COM-17: the sponsored story journey in a browser (REQ-269, REQ-263, REQ-239, REQ-155). The Administrator makes a deal and
// records its payment on the Sponsored stories screen; the story and slot steps have no screen yet, so tests/e2e/helpers/seed-sponsored.php
// does them through the same services and moves "today" for the placement job (the time travel). A visitor sees the label between
// the slot dates and not after. Editor and Reviewer never see amounts or revenue tiles. Fictional records only; the helpers refuse to
// run outside a local environment (staff users also need WIW_E2E_TEST_MODE=1). The label markup and profile and event surfaces are
// also covered in tests/e2e/stories/stories-journeys.spec.ts. The journeys share one database, so they run once (1280 px), in order.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
const SPONSOR = `E2E Sponsor ${Date.now()}`;
const SCREEN = () => adminUrl("admin.php?page=wiw-sponsored-stories");
const iso = (offsetDays: number) =>
  new Date(Date.now() + offsetDays * 86_400_000).toISOString().slice(0, 10);
const PRICE = /(?:[₦$€£]\s?\d)|\b(?:NGN|USD|kobo)\b/i;

test.describe("sponsored story journey", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs once, in the 1280 px project",
  );
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);

  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("seed-sponsored.php", "remove");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-sponsored.php", "remove");
    removeStaff();
  });

  test("the Administrator creates a deal with its payment and sees the amount (REQ-269)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(SCREEN());
    await page.locator("input[name=customer_name]").fill(SPONSOR);
    await page.locator("input[name=amount]").fill("500000");
    await page.locator("input[name=method]").fill("Bank transfer");
    await page.locator("input[name=reference]").fill("E2E-REF-1");
    await page.locator("input[name=received_on]").fill(iso(0));
    await Promise.all([
      page.waitForURL(/wiw-sponsored-stories/, { timeout: 60_000 }),
      page.getByRole("button", { name: "Create deal" }).click(),
    ]);
    const row = page.locator("tr", { hasText: SPONSOR });
    await expect(row).toBeVisible();
    await expect(row).toContainText(/500,000/);
  });

  test("a Reviewer cannot open the screen and an Editor sees no amounts, no deal form and no revenue tiles (REQ-239, REQ-269)", async ({
    page,
    browser,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    expect((await page.goto(SCREEN()))?.status()).toBe(403);
    const editor = await (await browser.newContext({ baseURL })).newPage();
    await loginAs(editor, baseURL as string, "editor");
    expect((await editor.goto(SCREEN()))?.status()).toBe(200);
    const html = await editor.locator("#wpbody-content").innerText();
    expect(html).toContain(SPONSOR);
    expect(html).not.toMatch(/500,000|Amount|NGN/);
    await expect(editor.locator("input[name=amount]")).toHaveCount(0);
    await expect(
      editor.getByRole("button", { name: "Create deal" }),
    ).toHaveCount(0);
    await editor.goto(adminUrl("index.php"));
    await expect(editor.locator("#wpbody-content")).not.toContainText(
      /Confirmed revenue|Counted deals/i,
    );
    await page.goto(adminUrl("index.php"));
    await expect(page.locator("#wpbody-content")).not.toContainText(
      /Confirmed revenue|Counted deals/i,
    );
  });

  test("the Administrator's dashboard shows the commercial tiles (REQ-239)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("index.php"));
    await expect(page.locator("#wpbody-content")).toContainText(
      /Confirmed revenue|Counted deals/i,
    );
  });

  test("between the slot dates a visitor sees the badge and sponsor name, and after the end date nothing (REQ-263)", async ({
    page,
    request,
  }) => {
    runHelper("seed-sponsored.php", "place", SPONSOR);
    await page.goto("/stories/e2e-sponsored-journey-story/");
    await expect(page.locator(".badge--sponsored")).toHaveText(
      `Sponsored by ${SPONSOR}`,
    );
    await expect(page.locator(".notice--sponsored")).toBeVisible();
    runHelper("seed-sponsored.php", "travel", iso(3));
    const after = await page.goto("/stories/e2e-sponsored-journey-story/");
    expect(after?.status()).toBe(200); // the story stays published
    await expect(page.locator(".badge--sponsored")).toHaveCount(0);
    await expect(page.locator(".notice--sponsored")).toHaveCount(0);
    expect(await (await request.get("/stories/")).text()).not.toContain(
      `Sponsored by ${SPONSOR}`,
    );
  });

  test("no public page shows a price, and no profile or event shows Sponsored (REQ-239, REQ-155)", async ({
    request,
  }) => {
    runHelper("seed-sponsored.php", "travel", iso(0));
    for (const path of [
      "/",
      "/stories/",
      "/stories/e2e-sponsored-journey-story/",
      "/events/",
      "/directory/",
      "/awards/winners/",
    ]) {
      const res = await request.get(path);
      if (res.status() === 404) continue;
      const body = (await res.text()).replace(
        /<script[\s\S]*?<\/script>|<style[\s\S]*?<\/style>/gi,
        "",
      );
      expect(body, `${path} must show no price`).not.toMatch(PRICE);
      expect(body, `${path} must not show the amount`).not.toContain("500,000");
    }
    for (const path of ["/directory/", "/events/"]) {
      const res = await request.get(path);
      expect(
        await res.text(),
        `${path} must not carry the Sponsored badge`,
      ).not.toContain("badge--sponsored");
    }
  });
});
