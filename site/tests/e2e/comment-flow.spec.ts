import { test, expect, type APIRequestContext, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "./helpers/a11y";
import { assertNoHorizontalScroll } from "./helpers/page";
import { adminUrl, loginAs, removeStaff, resetRateLimits, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M4-CMT-06: a guest comment from submission through moderation to public display (REQ-258, REQ-122, REQ-040). The story is
// fictional and created by tests/e2e/helpers/seed-stories.php; staff users by seed-staff.php (both refuse outside a local
// environment with WIW_E2E_TEST_MODE=1). The journeys share one database, so they run once, in order (the 1280 px project).
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
// The rules at the service level are in tests/Integration/Comments.

const STORY = "/stories/e2e-story-newest/";
const NAME = "Ada E2E Commenter";
const EMAIL = "ada.e2e@example.invalid";
const TEXT = "E2E comment: a thoughtful remark about the story.";

/** Fill the visible comment form as a visitor would. */
async function fill(page: Page, name: string, email: string, text: string, consent = true): Promise<void> {
  const form = page.locator("#commentform");
  await form.getByLabel("Your name").fill(name);
  await form.getByLabel("Your email").fill(email);
  await form.getByLabel("Your comment").fill(text);
  if (consent) await form.locator("input[name=wiw_comment_consent]").check();
}

/** The comment form's own address and the post id, so a bot-style direct post can be made. */
async function formTarget(page: Page): Promise<{ action: string; postId: string }> {
  await page.goto(STORY);
  const action = (await page.locator("#commentform").getAttribute("action")) as string;
  const postId = await page.locator("#commentform input[name=comment_post_ID]").inputValue();
  return { action, postId };
}

function direct(request: APIRequestContext, target: { action: string; postId: string }, fields: Record<string, string>) {
  return request.post(target.action, { form: { comment_post_ID: target.postId, comment_parent: "0", ...fields }, maxRedirects: 0 });
}

test.describe("comment flow", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    runHelper("seed-stories.php");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    runHelper("seed-stories.php", "remove"); // deleting the story deletes its comments
    removeStaff();
    resetRateLimits();
  });
  test.beforeEach(() => resetRateLimits());

  test("the form shows its notes and the story page passes axe with comments (REQ-258, REQ-040)", async ({ page }) => {
    await page.goto(STORY);
    const section = page.locator("#comments");
    await expect(section.getByLabel("Your name")).toBeVisible();
    await expect(section.getByLabel("Your email")).toBeVisible();
    await expect(section.locator("input[name=wiw_comment_consent]")).toHaveCount(1);
    await expect(section.getByText("Comments are checked before they appear")).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a submitted comment shows a thank-you and is hidden from other visitors until approved (REQ-258)", async ({ page, request }) => {
    await page.goto(STORY);
    await fill(page, NAME, EMAIL, TEXT);
    await page.locator("#commentform").getByRole("button", { name: /post comment/i }).click();
    await expect(page).toHaveURL(/wiw_comment=held/);
    await expect(page.getByRole("status").filter({ hasText: "will appear once it is approved" })).toBeVisible();
    // Another visitor (no cookies) does not see it, and the page never carries the email.
    const html = await (await request.get(STORY)).text();
    expect(html.includes(TEXT), "held comment hidden from another visitor").toBe(false);
    expect(html.includes(EMAIL), "email never printed").toBe(false);
  });

  test("refusals: a missing name, a bad email and a missing consent tick are each refused (REQ-258)", async ({ page, request }) => {
    const target = await formTarget(page);
    const ok = { author: NAME, email: EMAIL, comment: "E2E refused comment", wiw_comment_consent: "1" };
    // Core answers a missing field with a plain error page (status 200), the consent rule with an error status; none redirects to the thank-you.
    const refusal = async (changes: Record<string, string>) => {
      const res = await direct(request, target, { ...ok, ...changes });
      return { status: res.status(), location: res.headers()["location"] ?? "", body: await res.text() };
    };
    const noName = await refusal({ author: "" });
    expect(noName.location, "no name is not accepted").toBe("");
    expect(noName.body).toMatch(/fill the required fields/i);
    const badEmail = await refusal({ email: "not-an-email" });
    expect(badEmail.location, "a bad email is not accepted").toBe("");
    expect(badEmail.body).toMatch(/valid email/i);
    const noConsent = await refusal({ wiw_comment_consent: "" });
    expect(noConsent.status, "no consent is refused with an error status").toBeGreaterThanOrEqual(400);
    expect(noConsent.body).toMatch(/tick the box/i);
    // The browser form also stops an empty name before it is sent.
    await page.goto(STORY);
    await fill(page, "", EMAIL, "E2E refused comment");
    await page.locator("#commentform").getByRole("button", { name: /post comment/i }).click();
    await expect(page).not.toHaveURL(/wiw_comment=held/);
  });

  test("an Editor approves it; the page then shows the name and text and no email (REQ-258)", async ({ page, request, baseURL }) => {
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("edit-comments.php?comment_status=moderated"));
    const row = page.locator("#the-comment-list tr.comment", { hasText: TEXT });
    await expect(row).toHaveCount(1);
    // The refused comments never reached the queue.
    await expect(page.locator("#the-comment-list tr.comment", { hasText: "E2E refused comment" })).toHaveCount(0);
    // The Approve link's own address (what it does without scripts); the list's script call is a convenience on top of it.
    const approve = (await row.locator(".row-actions .approve a").getAttribute("href")) as string;
    await page.goto(new URL(approve, page.url()).toString());
    await page.goto(adminUrl("edit-comments.php?comment_status=moderated"));
    await expect(page.locator("#the-comment-list tr.comment", { hasText: TEXT })).toHaveCount(0);

    const res = await request.get(STORY);
    const html = await res.text();
    expect(html.includes(TEXT), "approved comment is public").toBe(true);
    expect(html.includes(NAME), "the commenter name is shown").toBe(true);
    expect(html.includes(EMAIL), "email never printed").toBe(false);
    expect(html.includes("gravatar"), "no Gravatar request").toBe(false);
    // The REST comments endpoint does not give the email to a visitor either.
    const rest = await request.get("/wp-json/wp/v2/comments");
    expect((await rest.text()).includes(EMAIL), "email not in REST").toBe(false);
  });

  test("the story page with an approved comment passes axe and has no sideways scroll (REQ-258, REQ-040)", async ({ page }) => {
    await page.goto(STORY);
    await expect(page.locator(".cm-item").filter({ hasText: NAME })).toBeVisible();
    await assertNoHorizontalScroll(page);
    await expectNoA11yViolations(page);
  });

  test("a bot that fills the honeypot field is marked spam, not queued (REQ-122)", async ({ page, request, baseURL }) => {
    const target = await formTarget(page);
    const text = "E2E honeypot comment from a bot";
    const res = await direct(request, target, { author: "Bot", email: "bot.e2e@example.invalid", comment: text, wiw_comment_consent: "1", wiw_comment_url: "http://spam.example.invalid/" });
    expect(res.status()).toBeLessThan(400);
    await loginAs(page, baseURL as string, "editor");
    await page.goto(adminUrl("edit-comments.php?comment_status=moderated"));
    await expect(page.locator("#the-comment-list tr.comment", { hasText: text })).toHaveCount(0);
    await page.goto(adminUrl("edit-comments.php?comment_status=spam"));
    await expect(page.locator("#the-comment-list tr.comment", { hasText: text })).toHaveCount(1);
    expect((await (await request.get(STORY)).text()).includes(text)).toBe(false);
  });

  test("a second comment straight after the first from one device is refused with 429 (REQ-122)", async ({ page, request }) => {
    // WordPress's own flood control (one comment per 15 seconds from a device) answers first, so a bot cannot reach the plugin's
    // five-in-ten-minutes allowance by speed; that allowance is covered at the service level (tests/Integration/Comments).
    await page.waitForTimeout(16_000); // let the previous test's comment pass the 15 second flood window
    const target = await formTarget(page);
    const first = await direct(request, target, { author: "Reader 1", email: "reader1.e2e@example.invalid", comment: "E2E flood comment 1", wiw_comment_consent: "1" });
    expect(first.status(), "the first comment is accepted").toBeLessThan(400);
    const second = await direct(request, target, { author: "Reader 2", email: "reader2.e2e@example.invalid", comment: "E2E flood comment 2", wiw_comment_consent: "1" });
    expect(second.status()).toBe(429);
  });
});
