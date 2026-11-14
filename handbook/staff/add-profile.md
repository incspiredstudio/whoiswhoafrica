<!-- verify-after-round-9 -->

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
2. Choose the **type**: individual, entrepreneur, organisation or institution. The form changes to show the fields for that type.
3. Fill in the fields:

| Field | Notes |
|---|---|
| Name | Required. The person's public name, or the organisation's registered or trading name. |
| Country | Required |
| Industry | Choose an existing industry |
| Current title and organisation | People only. Optional. |
| Lifespan | People who have died only, for example `1924–2013` |
| Field | People only: their field or profession, for example Music |
| Organisation type and website | Organisations and institutions only |
| Bio, Achievement, Influence, Legacy | The four sections of the profile page. Plain text; leave a blank line between paragraphs. See [Profile sections](/staff/profile-sections). |
| Photo or logo | Optional. Choose it from the media library. If you set one, the licence, the credit and the source address are required. See [Photos and credits](/staff/photos-and-credits). |
| Source address | Required, at least one |

4. Press save.

## The duplicate check

Before saving, the site looks for a profile with the same name and type (and the same Wikidata or registration number, if you gave one).

- If it finds possible matches, it lists them with links. Open them and check.
- If one of them is the same person or organisation, do not save. Edit the existing profile instead.
- If none is the same, choose **create anyway**.

## What happens next

1. The profile is created as **Imported, not public** and appears in the Review queue. Each fact you entered is recorded with your source and the method "manual".
2. The action is written to the audit log with your name.
3. Someone else reviews and approves it. You cannot approve a profile you created. See [Review a record](/staff/profile-review#review-a-record).

## From a nomination

On the Nominations screen, the profile picker of **Add profile to nominations** has a **Create profile** shortcut for someone who has no profile yet. It opens Add profile with the name filled in, and after saving takes you back to the nomination. The nomination cannot go Live until the profile is reviewed.
