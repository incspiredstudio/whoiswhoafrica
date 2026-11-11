import { defineConfig, devices } from "@playwright/test";

const baseURL = process.env.BASE_URL || "http://localhost:8882";
const widths = [360, 768, 1280, 1600];

export default defineConfig({
  testDir: "tests/e2e",
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [["list"], ["html", { open: "never", outputFolder: "playwright-report" }]],
  outputDir: "test-results",
  use: { baseURL, trace: "retain-on-failure", screenshot: "only-on-failure" },
  projects: [
    ...widths.map((w) => ({ name: `chromium-${w}`, use: { ...devices["Desktop Chrome"], viewport: { width: w, height: 900 } } })),
    ...(process.env.WIW_NO_WEBKIT ? [] : [{ name: "webkit-mobile", use: { ...devices["iPhone 14"] } }]),
  ],
});
