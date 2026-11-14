# Users, roles and two-factor sign-in

Only staff have accounts on WHOISWHO. This page shows an Administrator how to add and remove staff, change what they can do, and how every staff member switches on two-factor sign-in. The ideas behind roles are in [Roles and what each can do](/concepts/roles).

## Who does what

| Work | Where | Who |
|---|---|---|
| Add a user, change a role, deactivate | Users | Administrator only |
| Give or take one capability from one user | Users > (hover a user) > Capabilities | Administrator only |
| Change what a role can do, or create a role | Users > User Role Editor | Administrator only |
| Switch on your own two-factor sign-in | Users > Profile | Every staff member |

## The three roles

| Role | For |
|---|---|
| Reviewer | Checking imported records and approving them; resolving duplicates; bulk unpublish |
| Editor | Everything a Reviewer does, plus profiles, stories, events, awards, claims, the inbox screens and curation |
| Administrator | Everything, including imports, money details of sponsored deals, exports, the audit log, users and settings |

Give each person the lowest role that lets them do their job. Keep the number of Administrators small (two is a good number, so one can cover for the other).

## Add a staff member

1. Open **Users > Add New User**.
2. Enter a username (not their email), their work email, first and last name.
3. Leave the generated password; tick **Send the new user an email about their account** so they set their own.
4. Choose the **Role**: Reviewer, Editor or Administrator.
5. Press **Add New User**.
6. Ask them to sign in, then switch on two-factor (below).

The change is written to the audit log.

## Change a role or one capability

- **Role:** Users, hover the person, **Edit**, change **Role**, **Update User**.
- **One capability:** Users, hover the person, **Capabilities**. Tick or untick one capability, for example give an Editor `wiw_bulk_unpublish`, and save. It takes effect at once.

Some capabilities always stay with the Administrator, whatever you tick: user and role management, settings and plugins, sponsored deal money details, the Nominations switch and the Setup screen. See [Permissions that stay with the Administrator](/concepts/roles#permissions-that-stay-with-the-administrator).

## When someone leaves

Do it on their last day.

1. Open **Users** and hover the person.
2. Press **Deactivate**. They can no longer sign in, and their name stays on the work they did. **Reactivate** reverses it.
3. If they held any shared password (for example the mail account), change it.

Deactivate rather than delete, so the audit log and the history of their edits stay readable.

## Two-factor sign-in

Two-factor sign-in asks for a code from your phone (or your email) after your password, so a stolen password alone cannot open the admin. It is **recommended for every staff member and not enforced**: the site works without it, but the Setup checklist line **Two-factor sign-in for staff (recommended)** lists who has it on, and the team should aim for everyone.

### Switch it on (each staff member)

1. Install an authenticator app on your phone, for example Microsoft Authenticator, Google Authenticator or Authy.
2. In wp-admin, open your name in the top-right corner, then **Edit Profile** (or **Users > Profile**).
3. Scroll to **Two-Factor Options**. If it asks you to revalidate, press **Revalidate now** and sign in again.
4. Tick **Authenticator App**. A QR code appears.
5. Scan the QR code with the app. The app shows a six-digit code that changes every 30 seconds.
6. Type the current code into **Authentication Code** and press **Verify**.
7. Tick **Recovery Codes** and press **Generate new recovery codes**. Ten single-use codes appear.
8. Press **Download Codes** (or **Copy Codes**) and keep them somewhere safe away from your phone, for example in the team's password manager.
9. Set **Primary Method** to the Authenticator App.
10. Press **Update Profile** at the bottom.

From now on, after your password, the sign-in page asks for the code from the app.

The **Email** method also works: the code is sent to your account email. It is easier but weaker, and only works when site email works. Prefer the app.

### Recovery codes

- Each recovery code works once. On the sign-in page choose **Use a recovery code** and type one.
- When you have used all your codes, the admin warns you. Generate a new set on your profile (**Generate new recovery codes**); the old ones stop working.
- Never share your codes.

### Lost phone and no recovery codes

1. Contact an Administrator.
2. The Administrator opens **Users**, edits your account, scrolls to **Two-Factor Options**, unticks your methods and presses **Update User**.
3. Sign in with your password only and set up two-factor again straight away.

If an Administrator is locked out and no other Administrator is available, the host or developer can help; see [Who to contact](/operations/contacts).
