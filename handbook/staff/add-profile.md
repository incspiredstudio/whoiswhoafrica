# Add a profile by hand

Most profiles arrive through an [import](/staff/importing). When someone is missing, an Editor or Administrator adds them on the **Add profile** screen. A profile added by hand is never public straight away: it goes to the Review queue like any imported record, and a second person approves it.

## Who can do it

| Work | Screen | Capability | Default roles |
|---|---|---|---|
| Add a profile by hand | Directory > Add profile | `wiw_add_profile` | Editor, Administrator |
| Approve it | Review > Review queue | `wiw_review_records` | Reviewer, Editor, Administrator |

## Before you start

Have at least one public source address (a web page) for the facts you will enter: an official biography, the organisation's own site, a reputable news article or a public register. A profile cannot be saved without a source.

## Add the profile

1. Open **Directory > Add profile**.
2. Choose the **Type**: Person, Entrepreneur, Organisation or Institution, and press **Continue**. The form then shows the fields for that type. To pick another type, press **Change type**.
3. Fill in the fields:

| Field | Notes |
|---|---|
| Name | Required. The person's public name, or the organisation's registered or trading name. |
| Country | Required |
| Industry | Choose an existing industry |
| Current title, Current organisation | People only. Optional. |
| Lifespan | People who have died only, for example `1924–2013` |
| Field | People only: their field or profession, for example Music |
| Organisation type, Official website and the other organisation details | Organisations and institutions only. Organisation type is required. |
| Wikidata id, Registration number | Optional. Registration number is for organisations and institutions only. They help the duplicate check. |
| Bio, Achievement, Influence, Legacy | Under **Profile sections**: the four sections of the profile page. Each is optional. Plain text; leave a blank line between paragraphs. See [Profile sections](/staff/profile-sections). |
| Photo or logo | Optional. Press **Choose from media library**. If you set one, the **Licence**, the **Credit** and the **Image source URL** are required. See [Photos and credits](/staff/photos-and-credits). |
| Source URLs | Required: one web address per line, at least one and at most 10 |

Every paragraph box (the four sections, the summary and the mandate) shows a counter under it, for example "0 / 6000 characters", and stops at its limit. The field names are the same ones the Edit profile screen and the Review queue show.

4. Press **Save profile**.

WordPress's own **Profiles > Add New** is not used: it is not in the menu, and its address brings you to **Directory > Add profile**.

## The duplicate check

Before saving, the site looks for a profile with a similar name and the same type (and the same Wikidata or registration number, if you gave one).

- If it finds possible matches, it lists them with links. Open them and check.
- If one of them is the same person or organisation, do not save. Edit the existing profile instead.
- If none is the same, tick **None of the matches above is the same. Create anyway.** and press **Save profile** again.

## What happens next

1. The profile is created as **Imported, not public** and appears in the Review queue. Each fact you entered is recorded with your source and the method "manual".
2. The action is written to the audit log with your name.
3. Someone else reviews and approves it. You cannot approve a profile you created. See [Review a record](/staff/profile-review#review-a-record).

## From a nomination

On the Nominations screen, the profile picker of **Add profile to nominations** has a **Create profile** shortcut for someone who has no profile yet. It opens Add profile with the name filled in, and after saving takes you back to the nomination. The nomination cannot go Live until the profile is reviewed.
