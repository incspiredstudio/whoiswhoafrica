import type { BrowserContext, Page } from "@playwright/test";
import budgets from "./budgets.json";

export { budgets };

export interface Sample {
  lcpMs: number;
  cls: number;
  scriptKb: number;
  pageKb: number;
  ttfbMs: number;
}

/** Throttling for a mid-range Android phone on a slow 4G link (the profile written in budgets.json). */
export const throttle = {
  cpuRate: 4,
  latencyMs: 150,
  downBytesPerSec: (1.6 * 1024 * 1024) / 8,
  upBytesPerSec: (750 * 1024) / 8,
};

const observer = `
  window.__wiw = { lcp: 0, cls: 0 };
  new PerformanceObserver((l) => { for (const e of l.getEntries()) window.__wiw.lcp = e.startTime; }).observe({ type: 'largest-contentful-paint', buffered: true });
  new PerformanceObserver((l) => { for (const e of l.getEntries()) if (!e.hadRecentInput) window.__wiw.cls += e.value; }).observe({ type: 'layout-shift', buffered: true });
`;

/** One throttled, cold-cache load. Chromium only (uses the DevTools protocol). */
export async function measureOnce(context: BrowserContext, page: Page, path: string): Promise<Sample> {
  const cdp = await context.newCDPSession(page);
  await cdp.send("Network.enable");
  await cdp.send("Network.setCacheDisabled", { cacheDisabled: true });
  await cdp.send("Network.emulateNetworkConditions", {
    offline: false,
    latency: throttle.latencyMs,
    downloadThroughput: throttle.downBytesPerSec,
    uploadThroughput: throttle.upBytesPerSec,
  });
  await cdp.send("Emulation.setCPUThrottlingRate", { rate: throttle.cpuRate });

  const types = new Map<string, string>();
  let scriptBytes = 0;
  let totalBytes = 0;
  cdp.on("Network.responseReceived", (e: { requestId: string; type: string }) => types.set(e.requestId, e.type));
  cdp.on("Network.loadingFinished", (e: { requestId: string; encodedDataLength: number }) => {
    totalBytes += e.encodedDataLength;
    if (types.get(e.requestId) === "Script") scriptBytes += e.encodedDataLength;
  });

  await page.addInitScript(observer);
  await page.goto(path, { waitUntil: "load" });
  await page.waitForTimeout(1500);
  const { lcp, cls } = await page.evaluate(() => (window as unknown as { __wiw: { lcp: number; cls: number } }).__wiw);
  await cdp.send("Emulation.setCPUThrottlingRate", { rate: 1 });
  await cdp.detach();
  const ttfb = await page.evaluate(() => Math.round((performance.getEntriesByType("navigation")[0] as PerformanceNavigationTiming).responseStart));
  return { ttfbMs: ttfb, lcpMs: Math.round(lcp), cls: Math.round(cls * 1000) / 1000, scriptKb: Math.round(scriptBytes / 102.4) / 10, pageKb: Math.round(totalBytes / 102.4) / 10 };
}

export function median(values: number[]): number {
  const s = [...values].sort((a, b) => a - b);
  return s[Math.floor(s.length / 2)];
}
