<!-- verify-after-round-9 -->

# Turnstile spam check

Every public form on WHOISWHO (Get listed, Contact, corrections, removal requests, claims, nominations, newsletter) is protected by Cloudflare Turnstile, a free check that stops robots without making people solve puzzles. The site needs two keys from Cloudflare: a **site key** (public) and a **secret key** (private).

## 1. Get the keys from Cloudflare

1. Sign in at dash.cloudflare.com with the team's Cloudflare account. Your domain does not need to use Cloudflare for this.
2. In the left menu, open **Turnstile** and press **Add widget**.
3. Widget name: WHOISWHO. Hostname: your domain, for example `yourdomain.africa`.
4. Widget mode: **Managed**.
5. Press **Create**. Cloudflare shows the **Site Key** and the **Secret Key**.
6. Copy the secret key into the team's password manager.

## 2. Enter them in WordPress

1. In wp-admin, open the WHOISWHO **Settings** menu (near the top of the left menu) and find the **Turnstile** section.
2. Paste the site key and the secret key.
3. Save. The secret is never shown again: the field only says that it is saved. To change it, paste a new one.

Only an Administrator can see this section.

## 3. Check it

1. Open the site's Contact page in a private browser window.
2. The Turnstile box appears near the send button and ticks itself.
3. Send a short test message. It arrives in **Audience > Enquiries**.

## If the keys are wrong

Forms show a message that the check failed and nothing is sent. Make a new widget in Cloudflare and paste the new keys. Check that the hostname in Cloudflare is exactly your domain.
