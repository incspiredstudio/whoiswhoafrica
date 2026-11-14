import { test, expect, type Page } from "@playwright/test";
import { resetRateLimits } from "./helpers/staff-users";

// A visitor posting a comment through wpDiscuz gets clear feedback (REQ-258, #810): the button is off while it is on its way,
// then "Thanks, your comment is waiting for moderation", the form is cleared, and sending the same text again is stopped.
// Needs the wpDiscuz plugin active on the served site and a story with comments open: WIW_WPDISCUZ_STORY names its path,
// otherwise the newest story on /stories/ is used. Skips when the story has no wpDiscuz form. WordPress refuses a second comment
// from one address within 15 seconds (flood check) and the site rate-limits comments, so run the projects one after another
// (--workers=1); with WIW_E2E_TEST_MODE=1 and WIW_SECRETS_FILE the rate-limit counters are cleared first.

async function storyPath(page: Page): Promise<string | null> {
  if (process.env.WIW_WPDISCUZ_STORY) return process.env.WIW_WPDISCUZ_STORY;
  await page.goto("/stories/");
  const href = await page.locator("main a[href*='/stories/']:not([href$='/stories/'])").first().getAttribute("href").catch(() => null);
  return href;
}

test.describe("wpDiscuz comment feedback (REQ-258)", () => {
  test("a visitor sees that the comment is waiting for moderation and cannot send it twice", async ({ page }, info) => {
    test.setTimeout(90_000);
    if (process.env.WIW_E2E_TEST_MODE && process.env.WIW_SECRETS_FILE) {
      try {
        resetRateLimits();
      } catch {
        // Not a local test site: the run may be throttled.
      }
    }
    const path = await storyPath(page);
    test.skip(!path, "no story to comment on");
    await page.goto(path as string);
    const form = page.locator("form.wpd_main_comm_form");
    test.skip((await form.count()) === 0, "wpDiscuz is not active on this site");

    const text = `Feedback check ${info.project.name} ${Date.now()}: a short, polite remark.`;
    const editor = form.locator(".ql-editor").first();
    await editor.scrollIntoViewIfNeeded();
    await editor.click();
    await editor.pressSequentially(text);
    await form.locator("input[name=wc_name]").fill("Ada Visitor");
    await form.locator("input[name=wc_email]").fill("ada.visitor@example.invalid");
    await form.locator("input[name=wiw_comment_consent]").check();

    const status = page.locator(".wiw-comment-status");
    const submit = form.locator(".wc_comm_submit");
    const isSend = (r: { url(): string; request(): { postData(): string | null } }) => r.url().includes("admin-ajax.php") && (r.request().postData() || "").includes("wpdAddComment");
    let body: { success: boolean; data: { held_moderate?: number; error?: string } } = { success: false, data: {} };
    for (let attempt = 0; attempt < 2; attempt++) {
      const sent = page.waitForResponse(isSend);
      await submit.click();
      await expect(status).toContainText(/Sending your comment|waiting for moderation|too quickly/);
      const response = await sent;
      expect(response.status()).toBe(200);
      body = await response.json();
      if (!String(body.data?.error || "").includes("too quickly")) break;
      // The other project's comment was under 15 seconds ago (WordPress's flood check): the visitor is told, and tries again.
      await expect(status).toContainText("too quickly");
      await page.waitForTimeout(16_000);
    }
    expect(body.data.held_moderate, JSON.stringify(body).slice(0, 300)).toBe(1);

    await expect(status).toContainText("Thanks, your comment is waiting for moderation", { timeout: 15_000 });
    await expect(status).toHaveAttribute("role", "status");
    await expect(editor).toHaveText("");
    await expect(submit).toBeEnabled();

    // The same text again is stopped before it is sent.
    let resent = false;
    page.on("request", (r) => {
      if (r.url().includes("admin-ajax.php") && (r.postData() || "").includes("wpdAddComment")) resent = true;
    });
    await editor.click();
    await editor.pressSequentially(text);
    await submit.click();
    await expect(status).toContainText("already sent this comment");
    await page.waitForTimeout(1500);
    expect(resent).toBe(false);

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(0);
  });
});
