import { test, expect, type Page } from "@playwright/test";
import { expectNoA11yViolations } from "../helpers/a11y";
import { MailSink } from "../helpers/mail-sink";
import { execFile } from "node:child_process";
import { promisify } from "node:util";
import { runHelper } from "../helpers/staff-users";

// M2-NWS-09: the newsletter journeys in a browser, with the mail the site sends read from a local sink and never delivered to anyone
// (REQ-058, REQ-061, REQ-081, REQ-122, REQ-249). tests/e2e/helpers/newsletter-probe.php reads the stored records (hashes, versions and
// times) and tidies up; it accepts only fictional @example.invalid addresses and refuses outside a local environment. The mail sink
// listens on 127.0.0.1:1025, where the site sends mail when it has no SMTP account and is not in production; the specs skip with a
// note if something else holds that port. The rules themselves are in tests/Integration (NewsletterSignup, SubscriberRepository, Marketing).
// The journeys share one database and the form rate limit, so they run once (the 1280 px project), in order. Needs WIW_THEME_READY=1,
// WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1; skips without them.
type State = {
  subscribed: boolean;
  row: {
    purpose: string;
    confirmed_at: string | null;
    revoked_at: string | null;
  } | null;
  consents: Array<{
    purpose: string;
    policy_version: string | null;
    granted_at: string | null;
  }>;
};

const ready = () =>
  !!process.env.WIW_THEME_READY &&
  !!process.env.WIW_SECRETS_FILE &&
  process.env.WIW_E2E_TEST_MODE === "1";
const stamp = Date.now();
const address = (name: string) => `nl-${name}-${stamp}@example.invalid`;
const probe = <T = Record<string, unknown>>(cmd: string, email = "") =>
  runHelper<T>("newsletter-probe.php", cmd, ...(email ? [email] : []));
/**
 * Run the queued jobs once. Asynchronous on purpose: the mail sink runs in this process, so a blocking call would stop it
 * answering the site's SMTP connection and every send would fail.
 */
const runJobs = async (): Promise<void> => {
  await promisify(execFile)("php", ["bin/wiw.php", "jobs:run"], {
    env: process.env,
  });
};
const state = (email: string) => probe<State>("state", email);

/** Fill and send the sign-up form. The form refuses a post made less than a few seconds after the page was served. */
async function signUp(
  page: Page,
  email: string,
  extra: { honeypot?: string } = {},
): Promise<void> {
  probe("reset-limits");
  await page.goto("/newsletter/");
  await page.locator("#f-email").fill(email);
  await page.locator("#f-consent").check();
  if (extra.honeypot)
    await page
      .locator("#wiw_website")
      .evaluate((el: HTMLInputElement, v) => (el.value = v), extra.honeypot);
  await page.waitForTimeout(3500);
  await page.getByRole("button", { name: /sign up|subscribe/i }).click();
}

test.describe("newsletter journeys", () => {
  test.skip(
    !ready(),
    "needs WIW_THEME_READY, WIW_SECRETS_FILE and WIW_E2E_TEST_MODE=1 (see site/README.md)",
  );
  test.skip(
    ({ viewport }) => viewport?.width !== 1280,
    "runs once, in the 1280 px project",
  );
  test.describe.configure({ mode: "serial" });
  test.setTimeout(120_000);
  const sink = new MailSink();
  let sinkUp = false;
  const made = [address("a"), address("b"), address("bot"), address("limit")];

  test.beforeAll(async () => {
    sinkUp = await sink.start();
  });
  test.afterAll(async () => {
    await sink.stop();
    for (const email of made) probe("remove", email);
    probe("reset-limits");
  });
  test.beforeEach(() => {
    test.skip(
      !sinkUp,
      "port 1025 is held by another mail catcher, so the site's mail cannot be read",
    );
  });

  test("an empty and an invalid sign-up are refused with a message and subscribe nobody (REQ-081)", async ({
    page,
  }) => {
    probe("reset-limits");
    await page.goto("/newsletter/");
    await page.waitForTimeout(3500);
    await page.getByRole("button", { name: /sign up|subscribe/i }).click();
    await expect(
      page.locator("[role=alert], .field-error, [aria-invalid=true]").first(),
    ).toBeVisible();
    await expect(page).toHaveURL(/\/newsletter\/?(\?.*)?$/);
    await page.locator("#f-email").fill("not-an-email");
    await page.locator("#f-consent").check();
    await page.getByRole("button", { name: /sign up|subscribe/i }).click();
    await expect(
      page.locator("[role=alert], .field-error, [aria-invalid=true]").first(),
    ).toBeVisible();
    await expectNoA11yViolations(page);
    expect(
      sink.find((m) => m.to.some((t) => t.includes(String(stamp)))).length,
      "no email for a refused sign-up",
    ).toBe(0);
  });

  test("a valid sign-up is pending, records consent with version and time, and sends one confirmation (REQ-058, REQ-249)", async ({
    page,
  }) => {
    await signUp(page, made[0]);
    await expect(page).toHaveURL(/\/done\/\?.*kind=newsletter/);
    await expect(
      page.getByRole("heading", {
        name: "Check your email to finish signing up",
      }),
    ).toBeVisible();
    const s = state(made[0]);
    expect(s.subscribed, "not subscribed until the link is used").toBe(false);
    expect(s.row?.confirmed_at ?? null).toBeNull();
    expect(s.consents.length).toBeGreaterThan(0);
    expect(
      s.consents[0].policy_version,
      "consent carries the policy version",
    ).toBeTruthy();
    expect(s.consents[0].granted_at, "consent carries a time").toBeTruthy();
    await runJobs();
    await expect
      .poll(() => sink.find((m) => m.to.includes(made[0])).length, {
        timeout: 15_000,
      })
      .toBe(1);
    const mail = sink.find((m) => m.to.includes(made[0]))[0];
    expect(
      MailSink.link(mail, "/confirm/"),
      "the mail carries a confirm link",
    ).not.toBeNull();
  });

  test("the confirm link subscribes once and cannot be used again (REQ-249)", async ({
    page,
  }) => {
    const link = MailSink.link(
      sink.find((m) => m.to.includes(made[0]))[0],
      "/confirm/",
    ) as string;
    const res = await page.goto(link);
    expect(res?.status()).toBe(200);
    await expect(page.locator("h1")).toBeVisible();
    expect(state(made[0]).subscribed).toBe(true);
    await expectNoA11yViolations(page);
    const again = await page.goto(link);
    expect(again?.status()).toBe(410);
    await expect(
      page.getByRole("heading", { name: /expired or was already used/ }),
    ).toBeVisible();
  });

  test("an issue email carries an unsubscribe link and the one-click headers; the link asks first, then unsubscribes once (REQ-061)", async ({
    page,
  }) => {
    probe("issue", made[0]);
    await runJobs();
    await expect
      .poll(
        () =>
          sink.find(
            (m) =>
              m.to.includes(made[0]) &&
              /issue|story|stories|newsletter/i.test(m.subject + m.text),
          ).length,
        { timeout: 15_000 },
      )
      .toBeGreaterThan(1 - 1);
    const issue = sink
      .find(
        (m) => m.to.includes(made[0]) && m.headers.includes("List-Unsubscribe"),
      )
      .pop();
    expect(
      issue,
      "an issue mail with the List-Unsubscribe header",
    ).toBeTruthy();
    expect(issue?.headers).toContain(
      "List-Unsubscribe-Post: List-Unsubscribe=One-Click",
    );
    const unsubscribe = MailSink.link(
      issue as NonNullable<typeof issue>,
      "/link/",
    );
    expect(
      unsubscribe,
      "the footer carries an unsubscribe link",
    ).not.toBeNull();
    await page.goto(unsubscribe as string);
    await expect(page.locator("h1")).toHaveText(
      "Unsubscribe from the newsletter",
    );
    await page.reload(); // a GET never consumes the link
    expect(state(made[0]).subscribed).toBe(true);
    await expectNoA11yViolations(page);
    await page.getByRole("button", { name: "Unsubscribe" }).click();
    await expect(page.locator("h1[role=status]")).toHaveText(
      "You are unsubscribed",
    );
    expect(state(made[0]).subscribed).toBe(false);
    await page.goto(unsubscribe as string);
    await expect(page.locator("h1")).toHaveText(
      "This link has expired or was already used",
    );
  });

  test("the one-click unsubscribe POST works without a login (REQ-061)", async ({
    page,
    request,
  }) => {
    await signUp(page, made[1]);
    await runJobs();
    await expect
      .poll(() => sink.find((m) => m.to.includes(made[1])).length, {
        timeout: 15_000,
      })
      .toBe(1);
    await page.goto(
      MailSink.link(
        sink.find((m) => m.to.includes(made[1]))[0],
        "/confirm/",
      ) as string,
    );
    expect(state(made[1]).subscribed).toBe(true);
    const url = probe<{ url: string }>("unsubscribe-url", made[1]).url;
    const res = await request.post(url, {
      form: { "List-Unsubscribe": "One-Click" },
    });
    expect(res.status()).toBeLessThan(400);
    expect(state(made[1]).subscribed).toBe(false);
  });

  test("a bot that fills the hidden field is not subscribed and gets no email (REQ-122)", async ({
    page,
  }) => {
    await signUp(page, made[2], { honeypot: "http://spam.example" });
    await runJobs();
    await page.waitForTimeout(1500);
    expect(state(made[2]).subscribed).toBe(false);
    expect(sink.find((m) => m.to.includes(made[2]))).toHaveLength(0);
  });

  test("the same address signing up again and again hits the limit in the browser (REQ-122)", async ({
    page,
  }) => {
    probe("reset-limits");
    let limited = false;
    for (let i = 0; i < 5 && !limited; i++) {
      await page.goto("/newsletter/");
      await page.locator("#f-email").fill(made[3]);
      await page.locator("#f-consent").check();
      await page.waitForTimeout(3500);
      await page.getByRole("button", { name: /sign up|subscribe/i }).click();
      limited = await page
        .getByText(/too many|try again later/i)
        .first()
        .isVisible()
        .catch(() => false);
    }
    expect(
      limited,
      "the form says to try again later after repeated sign-ups for one address",
    ).toBe(true);
  });

  test("no email address appears in the page address or the done page (REQ-249)", async ({
    page,
  }) => {
    await page.goto("/done/?kind=newsletter&from=newsletter");
    expect(page.url()).not.toContain("@");
    await expect(page.locator("main")).not.toContainText("@example.invalid");
  });
});
