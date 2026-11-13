import { execFileSync } from "node:child_process";
import { test, expect, type Page } from "@playwright/test";
import { adminUrl, loginAs, removeStaff, runHelper, seedStaff, staffReady, STAFF_SKIP_REASON } from "./helpers/staff-users";

// M4-PGE-12: staff edit, reorder and publish the editable pages in the real block editor (REQ-260, REQ-190). Records are
// fictional: the starter pages come from `php bin/wiw.php pages:seed` (idempotent, never overwrites a page that exists) and the
// text added to them is removed again. The journeys share one database, so they run once, in order (the 1280 px project).
// Needs WIW_THEME_READY=1, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md); skips without them.
// Slug and revision rules at the service level are in tests/Integration/Pages/PageUrlsTest.php.

type Ids = Record<string, number | null>;
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Wp = any;

const MARK = "E2E editor was here";
const NEW_QUESTION = "E2E: a question added in the editor?";

async function openEditor(page: Page, id: number): Promise<void> {
  await page.goto(adminUrl(`post.php?post=${id}&action=edit`));
  await page.waitForFunction(() => {
    const wp = (window as unknown as { wp?: Wp }).wp;
    return !!wp?.data?.select("core/editor")?.getCurrentPostId?.() && wp.data.select("core/block-editor").getBlocks().length >= 0;
  });
  await page.evaluate(() => {
    const wp = (window as unknown as { wp: Wp }).wp;
    wp.data.dispatch("core/preferences")?.set("core/edit-post", "welcomeGuide", false);
    wp.data.dispatch("core/preferences")?.set("core/edit-post", "fullscreenMode", false);
  });
}

/** Press the editor's own Update button and wait until the save finishes. */
async function update(page: Page): Promise<void> {
  const button = page.locator("button.editor-post-publish-button");
  await expect(button).toBeEnabled();
  await button.click();
  await page.waitForFunction(() => {
    const ed = (window as unknown as { wp: Wp }).wp.data.select("core/editor");
    return !ed.isSavingPost() && ed.didPostSaveRequestSucceed();
  });
}

test.describe("editable pages", () => {
  test.skip(!staffReady(), STAFF_SKIP_REASON);
  test.skip(({ viewport }) => viewport?.width !== 1280, "runs once, in the 1280 px project");
  test.describe.configure({ mode: "serial" });
  test.setTimeout(180_000); // the block editor is slow on a single-threaded local PHP server
  let ids: Ids;
  test.beforeAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    seedStaff();
    try {
      execFileSync("php", ["bin/wiw.php", "pages:seed"], { encoding: "utf8", env: process.env, stdio: "pipe" });
    } catch {
      // The seed command ships with M4-PGE-10; without it the pages that already exist are used, and the journeys skip when one is missing.
    }
    ids = runHelper<Ids>("page-ids.php", "about", "faq", "awards/rules", "privacy");
  });
  test.afterAll(({}, info) => {
    if (info.project.name !== "chromium-1280") return;
    removeStaff();
  });

  test("an Editor edits About and publishes it; visitors see the change, then it is put back (REQ-260)", async ({ page, request, baseURL }) => {
    test.skip(!ids.about, "no About page (run pages:seed)");
    await loginAs(page, baseURL as string, "editor");
    await openEditor(page, ids.about as number);
    await page.evaluate((text) => {
      const wp = (window as unknown as { wp: Wp }).wp;
      const block = wp.blocks.createBlock("core/paragraph", { content: text, className: "e2e-mark" });
      wp.data.dispatch("core/block-editor").insertBlock(block, wp.data.select("core/block-editor").getBlockCount());
    }, MARK);
    await update(page);
    expect((await (await request.get("/about/")).text()).includes(MARK), "the edit is on /about/").toBe(true);

    await openEditor(page, ids.about as number);
    await page.evaluate(() => {
      const wp = (window as unknown as { wp: Wp }).wp;
      const mark = wp.data.select("core/block-editor").getBlocks().filter((b: { attributes: { className?: string } }) => b.attributes.className === "e2e-mark");
      wp.data.dispatch("core/block-editor").removeBlocks(mark.map((b: { clientId: string }) => b.clientId));
    });
    await update(page);
    expect((await (await request.get("/about/")).text()).includes(MARK), "the edit is gone again").toBe(false);
  });

  test("a Reviewer cannot open the page editor or the pages list (REQ-260)", async ({ page, baseURL }) => {
    await loginAs(page, baseURL as string, "reviewer_a");
    for (const id of [ids.about, ids.faq, ids["awards/rules"], ids.privacy]) {
      test.skip(!id, "pages are not seeded");
      const res = await page.goto(adminUrl(`post.php?post=${id}&action=edit`));
      expect(res?.status(), `page ${id}`).toBe(403);
      await expect(page.locator("body")).toContainText(/not allowed/i);
    }
    const list = await page.goto(adminUrl("edit.php?post_type=page"));
    expect(list?.status()).toBe(403);
  });

  test("an Editor adds a question and a group to the FAQ and reorders the groups; /faq/ follows (REQ-260)", async ({ page, request, baseURL }) => {
    test.skip(!ids.faq, "no FAQ page (run pages:seed)");
    await loginAs(page, baseURL as string, "editor");
    await openEditor(page, ids.faq as number);
    await page.evaluate(
      ({ question }) => {
        const wp = (window as unknown as { wp: Wp }).wp;
        const be = wp.data.select("core/block-editor");
        const groups = be.getBlocks().filter((b: { name: string }) => b.name === "whoiswho/faq-group");
        const answer = wp.blocks.createBlock("core/paragraph", { content: "Answered in the editor." });
        const q = wp.blocks.createBlock("whoiswho/faq-question", { title: question }, [answer]);
        wp.data.dispatch("core/block-editor").replaceInnerBlocks(groups[0].clientId, [q, ...groups[0].innerBlocks]);
        const group = wp.blocks.createBlock("whoiswho/faq-group", { heading: "E2E new group" }, [
          wp.blocks.createBlock("whoiswho/faq-question", { title: "E2E question in the new group?" }, [wp.blocks.createBlock("core/paragraph", { content: "Yes." })]),
        ]);
        wp.data.dispatch("core/block-editor").insertBlock(group, 1);
        // Reorder: the new group moves above the second group of the page.
        wp.data.dispatch("core/block-editor").moveBlocksUp([group.clientId]);
      },
      { question: NEW_QUESTION },
    );
    await update(page);
    const html = await (await request.get("/faq/")).text();
    expect(html.includes(NEW_QUESTION), "the new question is on /faq/").toBe(true);
    expect(html.includes("E2E new group"), "the new group is on /faq/").toBe(true);
    // The new question sits inside the first group, ahead of its older questions.
    expect(html.indexOf(NEW_QUESTION)).toBeLessThan(html.indexOf("How do I get listed?"));
    // The new group moved above the one that was second, so the order of group headings changed.
    const first = html.indexOf("E2E new group");
    expect(first).toBeGreaterThan(0);
    expect(first).toBeLessThan(html.indexOf("Accuracy and removal"));

    // Put the page back.
    await openEditor(page, ids.faq as number);
    await page.evaluate((question) => {
      const wp = (window as unknown as { wp: Wp }).wp;
      const be = wp.data.select("core/block-editor");
      const remove: string[] = [];
      for (const g of be.getBlocks()) {
        if (g.attributes.heading === "E2E new group") remove.push(g.clientId);
        for (const q of g.innerBlocks) if (q.attributes.title === question) remove.push(q.clientId);
      }
      wp.data.dispatch("core/block-editor").removeBlocks(remove);
    }, NEW_QUESTION);
    await update(page);
    expect((await (await request.get("/faq/")).text()).includes(NEW_QUESTION), "the question is gone again").toBe(false);
  });

  test("changing a page slug redirects the old address and the revisions keep author and time (REQ-260)", async ({ page, request, baseURL }) => {
    await loginAs(page, baseURL as string, "admin");
    await page.goto(adminUrl("edit.php?post_type=page"));
    const made = await page.evaluate(async () => {
      const wp = (window as unknown as { wp: Wp }).wp;
      const api = wp.apiFetch;
      const page = await api({ path: "/wp/v2/pages", method: "POST", data: { title: "E2E scratch page", slug: "e2e-scratch-old", status: "publish", content: "First text" } });
      await api({ path: `/wp/v2/pages/${page.id}`, method: "POST", data: { content: "Second text" } });
      await api({ path: `/wp/v2/pages/${page.id}`, method: "POST", data: { slug: "e2e-scratch-new", content: "Third text" } });
      const revisions = await api({ path: `/wp/v2/pages/${page.id}/revisions` });
      return { id: page.id as number, revisions: revisions.map((r: { author: number; date: string }) => ({ author: r.author, date: r.date })) };
    });
    try {
      const old = await request.get("/e2e-scratch-old/", { maxRedirects: 0 });
      expect(old.status()).toBe(301);
      expect(old.headers()["location"]).toContain("/e2e-scratch-new/");
      expect((await request.get("/e2e-scratch-new/")).status()).toBe(200);
      expect(made.revisions.length).toBeGreaterThanOrEqual(2);
      for (const r of made.revisions) {
        expect(r.author).toBeGreaterThan(0);
        expect(Date.parse(r.date)).not.toBeNaN();
      }
    } finally {
      await page.evaluate(async (id) => {
        await (window as unknown as { wp: Wp }).wp.apiFetch({ path: `/wp/v2/pages/${id}?force=true`, method: "DELETE" });
      }, made.id);
    }
  });

  test("a colour token changed in one place changes every page, with no template edited (REQ-190)", async ({ page }) => {
    for (const path of ["/", "/about/", "/faq/", "/awards/rules/", "/privacy/", "/contact/"]) {
      const res = await page.goto(path);
      if (!res || res.status() >= 400) continue;
      const hardcoded = await page.evaluate(() => Array.from(document.querySelectorAll("[style]")).filter((e) => /#[0-9a-f]{3,8}\b|rgb\(/i.test(e.getAttribute("style") ?? "")).length);
      expect(hardcoded, `${path}: inline colours`).toBe(0);
      // The home page paints its own bands over the body, so the page-level tokens are checked on the other pages.
      if (path === "/") continue;
      const changed = await page.evaluate(() => {
        const root = document.documentElement;
        const before = { bg: getComputedStyle(document.body).backgroundColor, text: getComputedStyle(document.body).color };
        root.style.setProperty("--c-bg", "rgb(1, 2, 3)");
        root.style.setProperty("--c-text", "rgb(4, 5, 6)");
        const after = { bg: getComputedStyle(document.body).backgroundColor, text: getComputedStyle(document.body).color };
        return { before, after };
      });
      expect(changed.after.bg, `${path}: body background follows --c-bg`).toBe("rgb(1, 2, 3)");
      expect(changed.after.text, `${path}: body text follows --c-text`).toBe("rgb(4, 5, 6)");
      expect(changed.before.bg).not.toBe("rgb(1, 2, 3)");
    }
  });
});
