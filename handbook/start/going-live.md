# Go live

These are the steps that turn the installed site into the public WHOISWHO. Do them in order. The full list of things that must be true at launch, with an owner for each, is the [Launch checklist](/operations/launch-checklist).

## Before you open the site

1. **Setup is green.** Every line on **WHOISWHO > Setup** is Ready, or Recommended with a reason you accept.
2. **Demo content is gone.** On the Setup screen, press **Remove demo content**. See [Demo content](/start/demo-content#remove-demo-content).
3. **Email works.** Send a FluentSMTP test to a work address and check it arrived, not in spam. See [Email](/start/email).
4. **Turnstile works.** Send a test through the Contact form. See [Turnstile](/start/turnstile).
5. **Backups are on.** Take a full cPanel backup and download it. See [Backups](/operations/backups).
6. **Staff accounts exist** with the right roles, and each staff member has switched on two-factor sign-in (recommended). See [Users, roles and two-factor](/staff/users-and-two-factor).
7. **Real content is in.** Profiles are imported and reviewed, the pages (About, FAQ, legal pages) carry their final wording, and the Home page curation is set.
8. **Legal pages are approved** by the person responsible.

## Open the site

1. In wp-admin open **Settings > Reading**.
2. Untick **Discourage search engines from indexing this site** and press **Save Changes**.
3. Open the site in a private browser window and check Home, the directory, one profile, one story, events, awards and the Contact page.
4. Check that an individual who is not yet approved does not appear anywhere: search for their name on the site.
5. Check the site map at your domain followed by `/sitemap.xml`. It lists only reviewed profiles.

## The first week

- Look at **WHOISWHO > Setup** and the Dashboard each day for warnings.
- Check **Audience > Email log** for failed emails.
- Check the Review queue, Enquiries, Corrections and Requests and reports every working day. Their time limits start from the first submission.
