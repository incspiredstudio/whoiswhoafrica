<!-- verify-after-round-9 -->

# Set up email

The site sends email for confirmations, single-use links, claim decisions, reminders and nominations. It sends them through a mail account on your own domain, using the FluentSMTP plugin. This page creates the mail account in cPanel, connects it in FluentSMTP and sends a test.

Install FluentSMTP first from [Required plugins](/start/required-plugins).

::: tip Passwords
Never write the mail password into this handbook, a chat or a ticket. Keep it in the team's password manager. The examples below use `yourdomain.africa` and a made-up account name.
:::

## 1. Create the mail account in cPanel

1. In cPanel, open **Email Accounts** (in the Email section) and press **+ Create**.
2. Domain: your site's domain. Username: `noreply` (so the address is `noreply@yourdomain.africa`).
3. Press **Generate** for a strong password, copy it into the password manager, and press **+ Create**.
4. Next to the new account press **Connect Devices**. Under "Mail Client Manual Settings" note the **Outgoing Server** name, usually `mail.yourdomain.africa`.

## 2. Check the domain's email records

These records stop your mail being marked as spam.

1. In cPanel, open **Email Deliverability**.
2. If your domain shows a problem next to **DKIM** or **SPF**, press **Repair** and confirm.
3. DMARC: open **Zone Editor**, press **Manage** next to the domain, then **Add Record**. Type **TXT**, name `_dmarc`, value `v=DMARC1; p=none;`. Save. The team can tighten this later.

## 3. Connect the account in FluentSMTP

1. In wp-admin, open **Settings > FluentSMTP** (this one is in the WordPress Settings menu).
2. Under connection settings, choose **Other SMTP**.
3. Fill in the form:

| Field | What to enter |
|---|---|
| From Email | `noreply@yourdomain.africa` |
| Force From Email | Ticked |
| From Name | WHOISWHO |
| SMTP Host | The outgoing server from step 1, for example `mail.yourdomain.africa` |
| SMTP Port | `587` |
| Encryption | **TLS** |
| Use Auto TLS | On |
| Authentication | Yes |
| SMTP Username | The full address, `noreply@yourdomain.africa` |
| SMTP Password | The password from your password manager |
| Where to keep the password | Choose the database option. Do not choose wp-config. |

4. Press **Save Connection Settings**.

## 4. Send a test

1. In FluentSMTP open the **Email Test** tab.
2. Send To: your own work address.
3. Press **Send Test Email**. FluentSMTP says whether it was sent.
4. Check your inbox and your spam folder. The message should come from `noreply@yourdomain.africa`.
5. Open **WHOISWHO > Setup**. The Email settings line is now Ready.

## Email logs

WHOISWHO keeps its own list of every email the site sends, without the content, under **Audience > Email log** (Administrators). FluentSMTP can also keep copies of whole emails, which contain personal data. Keep FluentSMTP's email logging off, or set it to delete logs after 7 days, in FluentSMTP's general settings.

## If the test fails

See [Troubleshooting: email not sending](/operations/troubleshooting#email-not-sending).
