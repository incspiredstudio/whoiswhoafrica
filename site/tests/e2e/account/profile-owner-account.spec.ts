import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import { adminUrl, loginAs, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "../helpers/staff-users";

// Profile owner accounts on the public site (REQ-274, REQ-261, REQ-253): the footer link (never in the header), sign in, the
// dashboard, an edit that waits for review, a reviewer approving it in the review queue so it goes live, an owner sent from
// /wp-admin/ to /account/, sign out, lost password, and axe on every account page. Each width project seeds its own fictional
// owner and profile (tests/e2e/helpers/seed-owner.php), so the projects never share state. The public forms ask the bot check to
// wait three seconds before a submit, so the tests wait too.
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.

type Seed = { email: string; profile_id: number; slug: string; user_id: number };

test.describe.configure({ mode: "serial", timeout: 150_000 });
test.skip(!staffReady(), STAFF_SKIP_REASON);

let seed: Seed;
const pass = (): string => process.env.E2E_STAFF_PASS as string;

test.beforeAll(({}, info) => {
  test.skip(!/^chromium-(360|1280)$/.test(info.project.name), "runs at 360 and 1280 only");
  seedStaff();
  seed = runHelper<Seed>("seed-owner.php", info.project.name.replace(/\D/g, ""));
});

test.afterAll(({}, info) => {
  if (!seed) return;
  runHelper("seed-owner.php", info.project.name.replace(/\D/g, ""), "remove");
});

async function signIn(page: Page): Promise<void> {
  await page.goto("/account/");
  await page.locator("#f-log").fill(seed.email);
  await page.locator("#f-pwd").fill(pass());
  await page.waitForTimeout(3200);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page).toHaveURL(/\/account\/$/);
}

test("the sign-in link is in the footer only, and the sign-in page passes axe", async ({ page }) => {
  await page.goto("/");
  const footer = page.locator("footer.site-foot");
  await expect(footer.getByRole("link", { name: "Profile owner sign in" })).toHaveAttribute("href", /\/account\/$/);
  await expect(page.locator("header").getByRole("link", { name: /sign in|my account/i })).toHaveCount(0);
  await page.goto((await footer.getByRole("link", { name: "Profile owner sign in" }).getAttribute("href")) as string);
  await expect(page.getByRole("heading", { level: 1 })).toContainText("Profile owner sign in");
  const robots = await page.locator('meta[name="robots"]').getAttribute("content");
  expect(robots).toContain("noindex");
  await expectNoA11yViolations(page);
});

test("a wrong password gets the generic error", async ({ page }) => {
  await page.goto("/account/");
  await page.locator("#f-log").fill(seed.email);
  await page.locator("#f-pwd").fill("Not-The-Password-1!");
  await page.waitForTimeout(3200);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByRole("alert")).toContainText("Sign in failed. Check your details and try again.");
});

test("an owner signs in, sees the dashboard, and the footer says My account", async ({ page }) => {
  await signIn(page);
  await expect(page.getByRole("heading", { name: "My profiles" })).toBeVisible();
  const item = page.locator(".account__item", { hasText: `E2E Owner Profile` });
  await expect(item).toContainText("Live");
  await expect(page.locator("footer.site-foot").getByRole("link", { name: "My account" })).toBeVisible();
  await expect(page.locator("#wpadminbar")).toHaveCount(0);
  await expectNoA11yViolations(page);
});

test("an edit is sent for review, shows Waiting for review, and a reviewer makes it live", async ({ page, browser, baseURL }) => {
  const summary = `Updated by the owner in a browser test ${Date.now()}.`;
  await signIn(page);
  await page.goto(`/account/profiles/${seed.profile_id}/edit/`);
  await expectNoA11yViolations(page);
  await page.locator("#f-summary").fill(summary);
  await page.getByRole("button", { name: "Send for review" }).click();
  await expect(page.getByRole("status").first()).toContainText("sent for review");
  await expect(page.getByText("Waiting for review")).toBeVisible();
  await expect(page.getByRole("button", { name: "Send for review" })).toHaveCount(0);
  await page.goto("/account/");
  await expect(page.locator(".account__item")).toContainText("Changes waiting for review");

  // The live text is unchanged until a reviewer approves it.
  await page.goto(`/account/profiles/${seed.profile_id}/edit/`);
  await expect(page.getByText("Waiting for review")).toBeVisible();

  const staff = await browser.newPage();
  await loginAs(staff, baseURL as string, "reviewer_a");
  await staff.goto(adminUrl(`admin.php?page=wiw-review-item&profile_id=${seed.profile_id}`));
  await Promise.all([staff.waitForEvent("load"), staff.getByRole("button", { name: "Approve this change" }).click()]);
  await expect(staff.getByRole("button", { name: "Approve this change" })).toHaveCount(0);
  await staff.close();

  // Approved: the live profile now holds the owner's text, and the account shows Live with the form open again.
  await page.goto("/account/");
  await expect(page.locator(".account__item")).toContainText("Live");
  await page.goto(`/account/profiles/${seed.profile_id}/edit/`);
  await expect(page.locator("#f-summary")).toHaveValue(summary);
});

test("an owner who opens /wp-admin/ lands on /account/, and account details pass axe", async ({ page, baseURL }) => {
  await signIn(page);
  // WordPress may live in a sub-folder (the local site does), and its admin cookie reaches only that folder's wp-admin; follow
  // /wp-admin/'s own redirect to find it. index.php by name: the local PHP server used for these tests serves no directory index.
  const probe = await page.request.get("/wp-admin/", { maxRedirects: 0 });
  const admin = new URL(probe.headers()["location"] ?? "/wp-admin/", baseURL).pathname.replace(/\/?$/, "/");
  for (const screen of ["index.php", "profile.php", "edit.php"]) {
    await page.goto(admin + screen);
    await expect(page).toHaveURL(/\/account\/$/);
  }
  await page.goto("/account/details/");
  await expect(page.locator("#f-display_name")).toHaveValue(/Fictional Owner/);
  await expectNoA11yViolations(page);
});

test("another profile id answers 404", async ({ page }) => {
  await signIn(page);
  const response = await page.goto(`/account/profiles/${seed.profile_id + 100000}/edit/`);
  expect(response?.status()).toBe(404);
});

test("sign out ends the session", async ({ page }) => {
  await signIn(page);
  await page.getByRole("link", { name: /Sign out/ }).click();
  await expect(page.getByRole("status")).toContainText("You are signed out.");
  await page.goto("/account/details/");
  await expect(page).toHaveURL(/\/account\/$/);
  await expect(page.locator("#f-log")).toBeVisible();
});

test("lost password answers the same for any address and passes axe", async ({ page }) => {
  await page.goto("/account/");
  await page.getByRole("link", { name: "Forgotten your password?" }).click();
  await expect(page).toHaveURL(/\/account\/lost-password\/$/);
  await expectNoA11yViolations(page);
  await page.locator("#f-user_email").fill("nobody@example.invalid");
  await page.waitForTimeout(3200);
  await page.getByRole("button", { name: "Send the link" }).click();
  await expect(page.getByRole("status")).toContainText("If an account uses that address");
  await page.goto("/account/lost-password/?key=not-a-key&id=1");
  await expect(page.getByText("This link has expired or has already been used.")).toBeVisible();
  await expectNoA11yViolations(page);
});
