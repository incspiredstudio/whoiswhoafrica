import { test, expect } from "@playwright/test";
import config from "../../playwright.config";

// M5-A11-05: the browser matrix named in docs/build/testing-and-ci.md is configured: Chromium at the four theme widths (phone-sized
// 360 px up to 1600 px) and a WebKit mobile profile. WebKit is left out only when WIW_NO_WEBKIT is set (CI installs Chromium only
// while the runners' apt is unreliable; see .github/workflows/ci.yml). REQ-055, REQ-040.
test("playwright.config projects chromium and webkit-mobile (REQ-055)", () => {
  const names = (config.projects ?? []).map((p) => p.name);
  for (const w of [360, 768, 1280, 1600]) expect(names).toContain(`chromium-${w}`);
  if (!process.env.WIW_NO_WEBKIT) expect(names).toContain("webkit-mobile");
  const phone = (config.projects ?? []).find((p) => p.name === "chromium-360");
  expect(phone?.use?.viewport?.width).toBe(360);
});

test("this run uses the project it names (REQ-055)", ({ browserName }, info) => {
  if (info.project.name === "webkit-mobile") expect(browserName).toBe("webkit");
  else expect(browserName).toBe("chromium");
});
