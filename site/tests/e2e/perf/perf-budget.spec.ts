import { test, expect } from "@playwright/test";
import { budgets, measureOnce, median, type Sample } from "./measure";

// REQ-041, REQ-042. Throttled mid-range Android profile on the base templates; numbers come from budgets.json.
// One command: BASE_URL=http://127.0.0.1:<port> WIW_PERF=1 npx playwright test
// Pages with real profile, story or directory content are added by M5-PRF-01 (#43) once those templates exist.
const pages = ["/", "/directory/", "/stories/", "/events/", "/contact/"];

for (const path of pages) {
  test(`REQ-041 REQ-042 ${path} stays within the performance budget`, async ({ browser, browserName }, testInfo) => {
    test.skip(browserName !== "chromium", "needs the DevTools protocol");
    test.skip(path === "/" && !process.env.WIW_THEME_READY, "theme not active yet");
    const samples: Sample[] = [];
    for (let i = 0; i < budgets.runs; i++) {
      const context = await browser.newContext({ viewport: { width: 412, height: 823 }, deviceScaleFactor: 2.625, isMobile: true, hasTouch: true });
      const page = await context.newPage();
      samples.push(await measureOnce(context, page, path));
      await context.close();
    }
    const result: Sample = {
      lcpMs: median(samples.map((s) => s.lcpMs)),
      cls: median(samples.map((s) => s.cls)),
      scriptKb: median(samples.map((s) => s.scriptKb)),
      pageKb: median(samples.map((s) => s.pageKb)),
      ttfbMs: median(samples.map((s) => s.ttfbMs)),
    };
    testInfo.annotations.push({ type: "perf", description: `${path} ${JSON.stringify(result)} runs=${JSON.stringify(samples)}` });
    console.log(`PERF ${path} ${JSON.stringify(result)}`);

    expect(result.lcpMs, "LCP ms").toBeGreaterThan(0);
    if (result.lcpMs > budgets.lcpTargetMs) {
      testInfo.annotations.push({ type: "above-target", description: `LCP ${result.lcpMs} ms is above the REQ-041 target of ${budgets.lcpTargetMs} ms` });
      console.log(`WARN ${path} LCP ${result.lcpMs} ms is above the REQ-041 target ${budgets.lcpTargetMs} ms`);
    }
    expect(result.lcpMs, `LCP ms (budget ${budgets.lcpMs})`).toBeLessThanOrEqual(budgets.lcpMs);
    expect(result.cls, `CLS (budget ${budgets.cls})`).toBeLessThanOrEqual(budgets.cls);
    expect(result.scriptKb, `script KB (budget ${budgets.scriptKb})`).toBeLessThanOrEqual(budgets.scriptKb);
    expect(result.pageKb, `page KB (budget ${budgets.pageKb})`).toBeLessThanOrEqual(budgets.pageKb);
  });
}
