# Trust, legal and data rights

These pages explain who we are, how the record is kept honest, what we promise when something is wrong, and what a person can ask us to do with their data. They are mostly text, and most of that text is a draft until Legal and Brand sign it off.

Routes: `/about`, `/faq`, `/complaints`, `/data-rights`, `/privacy`, `/terms`, `/cookies`, `/unsubscribe`, `/sitemap.xml` (V02, V28, V35, V50, V29 to V31, V45, V48, V49). Requirements: REQ-058 to REQ-062, REQ-071, REQ-100, REQ-120, REQ-121, REQ-158 (requirements). Decisions: D-016, D-054, D-055, D-059 (decisions).

## About (`/about`)

About says what the platform is and why it exists, in plain terms.

| Section | Content |
|---|---|
| Hero | "Africa's influence, on the record." and a paragraph: WHOISWHO is an African discovery, recognition and media platform that documents people, businesses and institutions, tells their stories and recognises achievement through independent awards. |
| Why we exist | The purpose statement: to make African influence visible, celebrate meaningful achievement and preserve the stories and legacies that shape the continent. |
| The gap | Who runs what, who built what and who achieved what is scattered across registries, annual reports and press releases. WHOISWHO brings it together with a source beside every fact, and is a curated destination, not another social network. |
| Influence, Achievement, Legacy | The three words that organise the brand, each with its definition. |
| Three ways into the record | Links to the directory, the stories and the awards. |
| Built to be checked | Sourced, reviewed by people, corrected in the open. |
| Figures | Counts of public profiles, industries, countries and stories, calculated from the data. |
| Closing call | Contact us and Read the questions. |

The copy follows the brief (purpose in section 2.3, tone in section 11.1). It is draft wording and carries a "Draft copy for review" note: the final mission, vision and values come from Brand.

## FAQ (`/faq`)

The FAQ answers the questions a visitor is likely to ask, in five groups. The first question is open and the rest are collapsed.

| Group | Questions it answers |
|---|---|
| Being listed | How to get listed; whether it costs anything; why there is a profile of me I did not create; how to claim it |
| Accuracy and removal | Fixing a wrong fact; where facts come from; what "Compiled automatically" means; removal; whether paying buys a better position (it does not) |
| Awards | Who can nominate; how winners are chosen; whether payment helps (it does not) |
| Stories | What a sponsored story is |
| Your data | Whether an account is needed (no); what we hold and how to see or change it |

The answers state the promises the site makes: a correction is acknowledged within one business day and resolved within seven, a valid removal from the subject hides the profile within two business days, and a claim is decided within five days. It ends with a link to Contact. Each answer must match the real process, so a change to any of these time limits means changing the FAQ (REQ-158, REQ-256). In WordPress the FAQ is a repeatable question-and-answer block with groups, edited in the page editor rather than in code (REQ-260, Q98).

## Complaints and takedown (`/complaints`)

For defamation, privacy, copyright or accuracy concerns. No account and no payment.

The page lists three commitments: acknowledge every complaint within one business day, hide a profile within two business days of a valid removal request from the subject, and resolve corrections within seven days. It tells the visitor to open the profile and use Suggest a correction, Request removal or Report this profile, says disputes go to an Editor, and explains that a valid removal returns 410 Gone, leaves search and the sitemap, and keeps the name on a list so it is not added again (REQ-071, REQ-158, REQ-207, REQ-209). The page carries a draft note for Legal.

## Your data rights (`/data-rights`)

A form for access, correction, erasure, objection and portability requests (REQ-059). It asks for the request type, name, email and details, with a bot challenge. The visitor receives a reference and an email, and the request is tracked with a due date. The page also says that for a public profile the fastest route is the form on that profile. Staff handle these on the Data requests screen (A35). The prototype's due date is fixed sample text.

## Legal pages

| Page | What it covers |
|---|---|
| Privacy policy (`/privacy`, version 0.1) | Whose data we hold, why (legitimate interests, with a right to object), your rights, how long we keep it, who processes it |
| Terms (`/terms`) | Using the site, submitted content, liability |
| Cookie policy (`/cookies`) | Essential cookies always; analytics only after you agree; change your mind with Cookie preferences in the footer |
| Awards rules (`/awards/rules`) | See [Awards](/pages/public/awards) |

All legal pages carry "Draft sample text for the prototype. Legal supplies the final wording." Legal review is currently deferred, and the defaults in the question list stand until it happens (D-016, Q35). There is no public commercial terms page (REQ-120, D-055). A "How we compile profiles" page was dropped (D-054), which leaves the pending-review label and the FAQ as the only public explanation of where profile data comes from.

## Cookies and consent

The cookie notice is a small card that appears once the visitor has scrolled a little, or at once on a short page, with Decline and Accept. Only essential cookies run before Accept. The footer's Cookie preferences button reopens the choice (REQ-058, REQ-062, REQ-248).

## Unsubscribe (`/unsubscribe`)

One click, no account. The page offers "Unsubscribe me" and then confirms "You are unsubscribed: We will not email you again about this list." Every marketing email carries this link (REQ-061).

## Sitemap and robots (`/sitemap.xml`)

The prototype page previews what the sitemap contains and what it leaves out, built from the same visibility rule as pages and search (REQ-006, REQ-121, REQ-208, REQ-209).

| In the sitemap | Not in the sitemap |
|---|---|
| Core pages, every reviewed public profile, every published story | Pending-review profiles (200 with noindex), removed or suppressed profiles (410 with noindex), anything hidden |

`robots.txt` allows all and points to the sitemap. It must not block pending-review addresses, because a crawler has to read the page to see the noindex rule (REQ-217).

## Error pages

A missing page is a 404 (V13), a removed profile is a 410 with an explanation and links to the directory and complaints policy (V12), and a server fault is a 500 (V14).

## Open points

- Final About copy, the mission, vision and values come from Brand.
- All legal text comes from Legal.
- Retention periods are not yet agreed, so the privacy page says they will be published once the schedule is agreed.
- The public name is WHOISWHO; the logo wordmark is its shortened form (D-060).
