import { test, expect } from "@playwright/test";

// The child theme is the active theme and extends the parent (REQ-273). The WordPress body classes name both, and the page loads
// styles from both folders. The deploy smoke check proves the same on the live host; this proves it in the browser on every build.
test.describe("child theme is active and extends the parent (REQ-273)", () => {
  test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

  test("body classes and stylesheets name the child and the parent", async ({ page }, info) => {
    test.skip(info.project.name !== "chromium-1280", "layout-independent check runs once");
    await page.goto("/");
    const classes = (await page.locator("body").getAttribute("class")) ?? "";
    expect(classes).toContain("wp-child-theme-whoiswho-child");
    expect(classes).toContain("wp-theme-whoiswho");
    const html = await page.content();
    expect(html).toContain("/wp-content/themes/whoiswho-child/");
    expect(html).toContain("/wp-content/themes/whoiswho/");
  });

  test("the parent theme files are served beside the child", async ({ request }, info) => {
    test.skip(info.project.name !== "chromium-1280", "layout-independent check runs once");
    for (const theme of ["whoiswho", "whoiswho-child"]) {
      const res = await request.get(`/wp-content/themes/${theme}/style.css`);
      expect(res.status(), theme).toBe(200);
    }
    const child = await (await request.get("/wp-content/themes/whoiswho-child/style.css")).text();
    expect(child).toMatch(/Template:\s*whoiswho\s*$/m);
  });
});
