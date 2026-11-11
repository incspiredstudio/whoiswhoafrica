import { test, expect } from "@playwright/test";
import { staffLogin } from "./staff-login";

test("REQ-055 staff login helper is refused without the test constant", async ({ page }) => {
  delete process.env.WIW_E2E_TEST_MODE;
  await expect(staffLogin(page, "http://localhost:8882")).rejects.toThrow(/refused/);
});

test("REQ-055 staff login helper is refused for a non-local URL even in test mode", async ({ page }) => {
  process.env.WIW_E2E_TEST_MODE = "1";
  await expect(staffLogin(page, "https://wiwi.africa")).rejects.toThrow(/refused/);
});
