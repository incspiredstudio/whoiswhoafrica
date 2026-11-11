# Directory and profiles

The directory is the core of the site: a browsable record of people, entrepreneurs, businesses and institutions. Each profile states facts, shows a source beside every fact, and gives anyone a way to correct, claim, report or remove it without an account.

Routes: `/directory`, `/people`, `/entrepreneurs`, `/businesses`, `/institutions`, `/industries`, `/profile/:slug` (V03 to V11, V52 to V54, V56). Requirements: REQ-003 to REQ-007, REQ-018, REQ-208, REQ-216 to REQ-218, REQ-232 (requirements).

## Lists

There are five lists, each a tab in the same layout:

| List | Contains |
|---|---|
| All (the directory) | Every public profile |
| People | Individuals |
| Entrepreneurs | Individuals flagged as founders, with their ventures |
| Businesses | Companies |
| Institutions | Universities, associations, public bodies, foundations |

Each list has a search box (name, organisation or keyword) in the header band and one quiet filter bar with the filters, a sort, the result count and a grid or list switch. Sixteen profiles show per page.

| Filter | Applies to |
|---|---|
| Industry, Country, City | All lists |
| Profession | People and Entrepreneurs |
| Organisation type | Businesses and Institutions |

Choosing a filter applies it at once; there is no Apply button. A filter that is on is underlined, the count of matching profiles is announced, and a Clear link shows only while a filter is on. Sort is Name A to Z, or Recently updated. The filters live in the address, so a filtered view can be shared.

`/industries` lists the sectors with a count each. An industry page shows the profiles and the stories in that sector. Because a list can be indexed by search engines, lists show **reviewed profiles only** (REQ-218, Q54): a profile pending review is never named on a list.

## Profile page anatomy

A profile page has the same shape for every type, with extra sections where they apply.

| Part | Content |
|---|---|
| Header | Photograph or logo, name, title and organisation, city, country, industry and type. The only image is the portrait or logo; there is no cover or banner image (REQ-247). |
| Award panel | If the profile has a published nomination or win, a panel names the category and links to the nomination page. |
| About | The biography. |
| Type sections | Ventures (entrepreneurs), mandate and history (institutions), career, education, milestones, leadership, products and services, achievements, media. Only sections with content appear. |
| Key facts and sources | A table of facts. Each shows a source and the date it was retrieved. Facts without a source are not shown (REQ-133, REQ-144). |
| Related stories | Stories that link to this profile. |
| Right rail | Contact, "Something wrong?" actions, and a share preview. |

Individual profiles hold professional facts only: name, current title and organisation, industry, public-record roles, published awards and an official website (REQ-139). The profile never shows an email or phone. Contact goes through an enquiry form that relays the message to staff, who forward it (REQ-018). A profile owner can ask staff to switch enquiries off, in which case the page says so.

**Share preview.** The "When shared" panel is a design illustration of the Open Graph card that appears when a profile is shared. It exists to show the card, not as a feature of the live page (REQ-006).

## States a visitor can meet

| State | What the visitor gets |
|---|---|
| Published and reviewed | Full page, listed in lists, search and the sitemap |
| Published, review pending | The page loads (200) with the label "Compiled automatically from public sources. Human review pending.", a link to correct or remove it, a noindex rule, no sitemap entry and no schema markup. It is not listed on lists but can be found by search or a direct link (REQ-216, REQ-217, REQ-218). Only organisations can be in this state. |
| Removed or suppressed | 410 Gone with a noindex header, out of search and the sitemap, cache purged (REQ-209) |
| Not public (draft, rejected, unpublished and hidden) | 404 for visitors. Staff see a preview banner explaining what visitors would get. |
| Slug changed | A redirect from the old address |

The pending label is one configuration value so Legal can change its wording. It is not a trust status. A pending profile cannot be claimed, linked to an arrangement or appear on an awards page until it has been reviewed (REQ-226).

## Actions on a profile

All four work without an account or payment.

**Claim this profile** (`/claim/:slug`, REQ-251). The form asks: who you are (the person, or an authorised representative of the organisation), name, role, work email, phone (optional), how staff can check it, and consent to be contacted. It creates a claim for staff to decide within five days and sends an acknowledgement. Nothing on the profile changes until a person checks the evidence. Once a claim is approved the profile is Claimed: staff see a checkmark, an assigned staff user can propose updates (each edit needs a Reviewer or Administrator's approval before it is public), and the Claim button disappears. If someone opens the claim address for a claimed profile, they see "Already claimed" with a link to the correction form. The public never sees a Claimed or Unclaimed label (REQ-252, REQ-253, D-057, D-059).

**Suggest a correction** (`/correct/:slug`). The form asks who you are, what you need (correct a fact, add a right-of-reply note, change which contact details are public, switch enquiries on or off, or request the view count and sources), which part of the profile, the new value, evidence and your email. The promise is acknowledgement within one business day and resolution within seven days. If you say you are the person or their representative, we send a single-use link to confirm your email and the request reaches staff only after you click it. A request from "someone else" goes straight in. Nothing changes publicly until staff act (REQ-232, REQ-233, REQ-158).

**Request removal** (`/remove/:slug`). Asks who you are, why, and your email. The promise is acknowledgement in one business day and, for a valid request from the person or their representative, the profile hidden within two business days. A removed profile returns 410 and its name goes on a permanent suppression list so imports cannot bring it back (REQ-147, REQ-207, REQ-209).

**Report this profile.** A short form in a dialog: reason (inaccurate, defamatory or harmful, privacy, copyright, other) and details. It lands on the Requests and reports screen (V44, REQ-090, REQ-270).

After any of these the visitor sees a "received" page with a reference and, in the prototype, a button that shows the email that would be sent. Opening an expired or already used confirmation link shows "Link expired or already used".

## Which staff screens feed it

| Public thing | Staff screen |
|---|---|
| Profiles, status, claimed mark, assigned user | Profiles list (A05) and Profile admin (A06) |
| New and edited records | Review queue (A03) |
| Enquiries | Enquiries (A12) |
| Corrections | Corrections (A64) |
| Removals and reports | Requests and reports (A35) |
| Claims | Claims (A61) |
| Removed names | Suppression list (A22) |
| Facts' sources | Sources (A59) and the field source panel on A06 |

## Prototype notes

Every profile in the prototype is fictional and shows a "Fictional sample profile" badge. Sample due dates on correction and removal requests are fixed text, not calculated. The public pages never show Verified, Featured or Sponsored on a profile (D-054, D-055).
