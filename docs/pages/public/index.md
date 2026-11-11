# The public site

The public site is what a visitor sees at the WHOISWHO address: a directory of people, businesses and institutions, a stories section, and the awards. This page maps every public route and states the rules that apply to all of them.

The pages in this section describe how the site is meant to behave and how the prototype shows it. They do not replace the requirements; they explain them.

## Rules that apply to every public page

**There are no public accounts.** A visitor never signs up or signs in. Every action works without one: search, corrections, claims, removal requests, nominations, enquiries, comments and data requests. Where we need to be sure of an email address, we send a single-use link (REQ-229, REQ-233, D-034). Only staff have accounts, and they use the WordPress admin.

**Only reviewed, published content is public.** A profile is visible only in a published state, and individuals and entrepreneurs are public only after a person on the team approves them. Organisations may go live before review, but they carry a label and a noindex rule (REQ-208, REQ-216, D-033). The same visibility rule drives pages, lists, search, the sitemap and share previews.

**Money never buys recognition.** Payment never affects nominations, judging, winners, profile ranking, Featured, Claimed or any label (REQ-183, REQ-199, D-062). Listings have the same format for everyone.

**Only stories can be Sponsored.** A sponsor can pay for a story to sit in a labelled slot for set dates. The label always shows the sponsor's name and the slot never reorders other stories or search. Profiles are never Sponsored or Featured (REQ-155, REQ-263, D-062).

**Profiles show no trust badge.** A visitor sees facts and the source beside each one. The only status label a profile can show is the pending-review label described in [Directory and profiles](/pages/public/directory-and-profiles). Claimed is a staff-only mark and is never shown publicly (REQ-150, D-054, D-059).

**Public forms are protected.** Every form has a bot challenge, rate limits per IP and per email or link token, and a privacy notice at the point of collection (REQ-058, REQ-081, REQ-122).

## Route map

The prototype uses hash routes (`#/people`). The WordPress build uses normal paths. Screen ids (V01 and so on) refer to the wireframe inventory.

| Route | What it is | Page in this guide |
|---|---|---|
| `/` | Home (V01) | [Home](/pages/public/home) |
| `/directory`, `/people`, `/entrepreneurs`, `/businesses`, `/institutions` | Directory lists with search and filters (V52, V03, V53, V04, V05) | [Directory and profiles](/pages/public/directory-and-profiles) |
| `/industries`, `/industries/:name` | Browse by sector, with profiles and stories (V06, V07) | [Directory and profiles](/pages/public/directory-and-profiles) |
| `/profile/:slug` | A person, business, institution or entrepreneur (V09 to V11, V54, V56) | [Directory and profiles](/pages/public/directory-and-profiles) |
| `/claim/:slug`, `/correct/:slug`, `/remove/:slug` | Claim, correction and removal forms (V57, V39, V40) | [Directory and profiles](/pages/public/directory-and-profiles) |
| `/confirm/:token`, `/done`, `/expired` | Email confirmation, "request received" pages, expired link | [Directory and profiles](/pages/public/directory-and-profiles) |
| `/search` | Search across profiles and stories (V08, V38) | [Search](/pages/public/search) |
| `/stories`, `/stories/:slug` | Stories grid and a single story (V16, V17) | [Stories](/pages/public/stories) |
| `/events`, `/events/:slug` | Events list and a single event | [Events](/pages/public/events) |
| `/awards`, `/awards/category/:i`, `/awards/nominees`, `/awards/nominee/:ref`, `/awards/winners`, `/awards/rules` | The awards (V18 to V21, V33, V55) | [Awards](/pages/public/awards) |
| `/nominate`, `/nominate/verify`, `/nominate/done` | Nomination form, email check, confirmation (V22, N01 to N05) | [Awards](/pages/public/awards) |
| `/subscribe`, `/contact` | Get listed enquiry and general contact (V24, V26) | [Get listed and contact](/pages/public/get-listed-and-contact) |
| `/about`, `/faq` | About and FAQ (V02, V28) | [Trust, legal and data rights](/pages/public/trust-legal-and-data-rights) |
| `/complaints`, `/data-rights`, `/privacy`, `/terms`, `/cookies`, `/unsubscribe`, `/sitemap.xml` | Complaints, data requests, legal pages, unsubscribe, sitemap (V35, V50, V29 to V31, V45, V48) | [Trust, legal and data rights](/pages/public/trust-legal-and-data-rights) |
| `/invite/:token`, `/consent/:token` | Customer invitation and consent links (O22, O23) | [Get listed and contact](/pages/public/get-listed-and-contact) |

Error states: a profile or page that does not exist returns 404 (V13), a removed profile returns 410 Gone (V12), and a server fault shows a 500 page (V14) (REQ-121).

## Header, footer and cookie notice

The header has five groups: Directory, Awards, Stories, Events and About, plus a search icon and a "Get listed" button. Directory, Awards and About open menus; Events is a plain link. The footer has an Events link too. The Nominate links are hidden while nominations are closed. On phones the header becomes a drawer. The footer carries the trust and legal links and a "Cookie preferences" button.

The cookie notice is a small card that appears after the visitor scrolls a little, with Decline and Accept. Analytics load only after Accept (REQ-062, REQ-248).

## What the prototype is and is not

The prototype is a clickable demonstration with sample data. All people and organisations are fictional, the photographs are sample photos, and the legal pages and rules carry "draft" notes. The public name is WHOISWHO; the logo's wordmark is its shortened form (D-060).

## Where the content comes from

| Content | Fed by |
|---|---|
| Profiles | The acquisition import and the review queue, then the profiles screens (A03 to A06) |
| Stories | WordPress posts, with sponsors set on the Sponsored stories screen |
| Awards | The Award categories and Nominations screens, with the open or closed toggle |
| Forms | The Enquiries, Corrections, Requests and reports and Claims screens |

For the staff side see the wireframe inventory.
