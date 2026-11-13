import { execFileSync } from "node:child_process";
import { expect, type Page } from "@playwright/test";

/**
 * Seeded staff users for browser tests of the admin screens. TEST ENVIRONMENT ONLY, in two layers:
 *  1. seed-staff.php refuses unless the site is a local environment and WIW_E2E_TEST_MODE=1, so no login is ever created on production;
 *  2. loginAs() refuses unless WIW_E2E_TEST_MODE=1 and the base URL is localhost or 127.0.0.1.
 * Run the specs with WIW_SECRETS_FILE (the file the site uses), WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (any throw-away value of 12+
 * characters; it is only ever the password of the throw-away users in the scratch database). Nothing is committed.
 */
export type StaffRole = "reviewer_a" | "reviewer_b" | "editor" | "admin";

export const staffReady = (): boolean =>
  process.env.WIW_E2E_TEST_MODE === "1" && !!process.env.WIW_SECRETS_FILE && !!process.env.E2E_STAFF_PASS && !!process.env.WIW_THEME_READY;

export const STAFF_SKIP_REASON = "needs WIW_THEME_READY, WIW_SECRETS_FILE, WIW_E2E_TEST_MODE=1 and E2E_STAFF_PASS (see site/README.md)";

/** Run a PHP helper from tests/e2e/helpers against the site; returns the last line of output parsed as JSON. */
export function runHelper<T = Record<string, unknown>>(script: string, ...args: string[]): T {
  const out = execFileSync("php", [`tests/e2e/helpers/${script}`, ...args], { encoding: "utf8", env: process.env });
  return JSON.parse(out.trim().split("\n").pop() as string) as T;
}

/** Create the staff users (idempotent). Returns login name to user id. */
export function seedStaff(): Record<string, number> {
  return runHelper<Record<string, number>>("seed-staff.php");
}

export function removeStaff(): void {
  runHelper("seed-staff.php", "remove");
}

/** Run the queued background jobs once (bulk unpublish and restore run as jobs). */
export function runJobs(): void {
  execFileSync("php", ["bin/wiw.php", "jobs:run"], { encoding: "utf8", env: process.env });
}

let adminBase = "/wp-admin/";

/** The address of an admin page. WordPress may live in a sub-folder (the local site does, at /wordpress/), and its cookies only reach that folder. */
export const adminUrl = (path: string): string => adminBase + path.replace(/^\/+/, "");

/** Sign in as a seeded staff user in the page's browser context. */
export async function loginAs(page: Page, baseURL: string, role: StaffRole): Promise<void> {
  const host = new URL(baseURL).hostname;
  if (process.env.WIW_E2E_TEST_MODE !== "1" || !["localhost", "127.0.0.1"].includes(host)) {
    throw new Error("loginAs is refused: it needs WIW_E2E_TEST_MODE=1 and a local base URL");
  }
  const pass = process.env.E2E_STAFF_PASS;
  if (!pass) throw new Error("E2E_STAFF_PASS is required");
  await page.goto("/wp-login.php");
  await page.fill("#user_login", `e2e_${role}`);
  await page.fill("#user_pass", pass);
  await page.click("#wp-submit");
  await expect(page).toHaveURL(/wp-admin/);
  const base = new URL(page.url()).pathname.match(/^(.*\/wp-admin\/)/);
  if (base) adminBase = base[1];
}

/** Clear the form rate limits (comments, enquiries, newsletter, nominations and so on) so repeated runs are not throttled. */
export function resetRateLimits(): void {
  runHelper("reset-limits.php");
}

/** Remove the support items and suppression entries that a browser run created (their addresses end in @example.invalid). */
export function cleanupSupport(): void {
  runHelper("cleanup-support.php");
}
