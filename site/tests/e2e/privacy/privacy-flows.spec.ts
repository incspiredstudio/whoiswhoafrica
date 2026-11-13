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

// M3-PRV-19: the privacy rules that need a browser and a signed-in user (REQ-069, REQ-100, REQ-058). All users are fictional and
// created by tests/e2e/helpers/seed-staff.php, which refuses to run outside a local environment with WIW_E2E_TEST_MODE=1.
// Where the rest of the privacy acceptance list is tested:
//  - no analytics script before Accept or after Decline: tests/e2e/cookie-notice.spec.ts (network assertions);
//  - a privacy link on every form that collects personal data: tests/e2e/privacy-notice.spec.ts;
//  - consent rows carry the policy version and time: newsletter/newsletter-journeys.spec.ts (a real sign-up) and
//    tests/Integration/Compliance/ConsentRecorderTest.php;
//  - export and erase for one email, and the role matrix: tests/Integration/Compliance/PersonalDataTest.php and ExportGateTest.php.
// The Your data rights page (M3-PRV-11, issue #266) is not built yet, so the data-request journey in a browser is a fixme below.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
const exports = (path = "export.php?download=true&content=all") =>
  adminUrl(path);
const audited = (): { count: number; actors: number[] } =>
  runHelper("audit-probe.php", "export.download");

test.describe("privacy flows", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs once, in the 1280 px project",
  );
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  let ids: Record<string, number> = {};
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    ids = seedStaff();
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    removeStaff();
  });

  test("a Reviewer is refused the data export and nothing is recorded as exported (REQ-069)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    const before = audited().count;
    const screen = await page.request.get(exports("export.php"));
    const download = await page.request.get(exports());
    for (const res of [screen, download]) {
      const body = await res.text();
      expect(
        res.status() >= 400 ||
          /not allowed|do not have permission|cannot export/i.test(body),
        "the export is refused",
      ).toBe(true);
      expect(body).not.toContain("<wp:wxr_version>");
    }
    expect(audited().count).toBe(before);
  });

  test("an Administrator's export download is recorded in the audit log (REQ-069)", async ({
    page,
    baseURL,
  }) => {
    await loginAs(page, baseURL as string, "admin");
    const before = audited();
    const res = await page.request.get(exports());
    expect(res.status()).toBe(200);
    expect(await res.text()).toContain("<wp:wxr_version>");
    const after = audited();
    expect(after.count).toBe(before.count + 1);
    expect(after.actors).toContain(ids["e2e_admin"]);
  });

  test("the Terms and Privacy pages are published (REQ-100)", async ({
    request,
  }) => {
    for (const path of ["/terms/", "/privacy/", "/cookies/"]) {
      const res = await request.get(path);
      expect(res.status(), path).toBe(200);
    }
  });

  test.fixme("a data request for one email is confirmed, exported and erased through the public page (REQ-059)", async () => {
    // Waits for the Your data rights page (M3-PRV-11, #266). The rules are tested without a browser in PersonalDataTest and
    // DataRightsIntakeTest.
  });
});
