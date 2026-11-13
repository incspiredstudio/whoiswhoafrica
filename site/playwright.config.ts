import { defineConfig, devices } from "@playwright/test";

const baseURL = process.env.BASE_URL || "http://localhost:8882";
const widths = [360, 768, 1280, 1600];
const perf = !!process.env.WIW_PERF;

export default defineConfig({
  testDir: "tests/e2e",
  // The throttled performance run is its own command (WIW_PERF=1, see docs/runbook.md); the normal run never includes it.
  testIgnore: perf ? undefined : "**/perf/**",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [["list"], ["html", { open: "never", outputFolder: "playwright-report" }]],
  outputDir: "test-results",
  use: { baseURL, trace: "retain-on-failure", screenshot: "only-on-failure" },
  workers: perf ? 1 : undefined,
  projects: perf
    ? [{ name: "perf", testMatch: "**/perf/*.spec.ts", fullyParallel: false, retries: 0, use: { ...devices["Desktop Chrome"] } }]
    : [
        ...widths.map((w) => ({ name: `chromium-${w}`, use: { ...devices["Desktop Chrome"], viewport: { width: w, height: 900 } } })),
        ...(process.env.WIW_NO_WEBKIT ? [] : [{ name: "webkit-mobile", use: { ...devices["iPhone 14"] } }]),
      ],
});
