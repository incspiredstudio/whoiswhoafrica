import { test, expect } from "@playwright/test";
import { setReducedMotion } from "./helpers/page";

// M5-A11-02: what axe cannot prove. Keyboard use reaches controls with a visible focus state and never gets trapped; reduced-motion
// visitors get no endless animation or smooth scrolling. Runs on a fixed list of pages that exist today; M0-QAX-04 (#237) adds
// helpers/routes.ts as the one shared list, after which this spec should read it. REQ-043, REQ-040.
// Needs a site whose active theme is whoiswho-child (set WIW_THEME_READY=1).
const paths = ["/", "/directory/", "/stories/", "/events/", "/contact/", "/get-listed/"];

for (const path of paths) {
  test.describe(`keyboard and motion on ${path} (REQ-043)`, () => {
    test.skip(!process.env.WIW_THEME_READY, "theme not active yet");

    test("Tab reaches controls, each shows a focus indicator, and focus is never trapped", async ({ page, isMobile }) => {
      test.skip(isMobile === true, "a touch profile has no keyboard");
      const res = await page.goto(path);
      test.skip(res?.status() === 404, `${path} is not built yet`);
      await page.keyboard.press("Tab");
      const seen = new Set<string>();
      const bare: string[] = [];
      let sameInARow = 0;
      let last = "";
      for (let i = 0; i < 60; i++) {
        const info = await page.evaluate(() => {
          const el = document.activeElement as HTMLElement | null;
          if (!el || el === document.body) return null;
          const ringed = (node: Element): boolean => {
            const s = getComputedStyle(node);
            return (s.outlineStyle !== "none" && parseFloat(s.outlineWidth) > 0 && s.outlineColor !== "rgba(0, 0, 0, 0)") || s.boxShadow !== "none";
          };
          const cs = getComputedStyle(el);
          const bg = cs.textDecorationLine.includes("underline");
          // A card may draw the ring around itself while its link has focus (:focus-within), as the profile cards do. That counts,
          // but only on a close ancestor (up to four levels), so a page-wide outline cannot pass for a focus indicator.
          let around = false;
          let ancestor: Element | null = el.parentElement;
          for (let up = 0; up < 4 && ancestor && !around; up++) {
            around = ancestor.matches(":focus-within") && ringed(ancestor);
            ancestor = ancestor.parentElement;
          }
          const id = el.tagName + "|" + (el.getAttribute("href") ?? el.id ?? el.getAttribute("name") ?? "") + "|" + (el.textContent ?? "").trim().slice(0, 30);
          return { id, visible: ringed(el) || bg || around };
        });
        if (info) {
          seen.add(info.id);
          if (!info.visible) bare.push(info.id);
          sameInARow = info.id === last ? sameInARow + 1 : 0;
          last = info.id;
          expect(sameInARow, `keyboard trap at ${info.id}`).toBeLessThan(3);
        }
        await page.keyboard.press("Tab");
      }
      expect(seen.size, "Tab reached interactive controls").toBeGreaterThan(3);
      expect(bare, `focused with no visible indicator: ${bare.join(", ")}`).toEqual([]);
    });

    test("with reduced motion nothing animates endlessly and scrolling is not smooth", async ({ page }) => {
      await setReducedMotion(page);
      const res = await page.goto(path);
      test.skip(res?.status() === 404, `${path} is not built yet`);
      await page.waitForTimeout(600);
      const behavior = await page.evaluate(() => getComputedStyle(document.documentElement).scrollBehavior);
      expect(behavior).not.toBe("smooth");
      const endless = await page.evaluate(() =>
        document
          .getAnimations()
          .filter((a) => a.playState === "running" && a.effect?.getComputedTiming().iterations === Infinity)
          .map((a) => ((a.effect as KeyframeEffect).target as HTMLElement | null)?.className ?? "?"),
      );
      expect(endless, "endless animations under reduced motion").toEqual([]);
      await expect(page.locator("html")).not.toHaveClass(/lenis/);
    });
  });
}
