# Publication states

A profile is public only in two of its six states. This page explains the states, what visitors and search engines see in each, and how a profile is taken down.

Requirements: REQ-208, 209, 210, 216, 217, 218. Decisions: D-029, D-033, D-054. Records: ADR-005, ADR-006.

## The six states

Every profile has exactly one state. It is stored separately from any label.

| State | Public? | Who can see it |
|---|---|---|
| Imported, not public | No | Staff only |
| Published, review pending | Yes, with a label | Everyone, but not indexed |
| Published, reviewed | Yes | Everyone, indexed |
| Unpublished | No (410 Gone) | Staff only |
| Rejected | No (404) | Staff only |
| Removed and suppressed | No (410 Gone) | Staff only |

## Publish first, review after (for organisations only)

The acquisition pipeline never publishes. After an import, WordPress sets the state using fixed rules, first match wins (D-033):

| Condition | Resulting state |
|---|---|
| The "import everything as not public" setting is on | Imported, not public |
| The record is a probable duplicate | Imported, not public |
| The record is an individual or entrepreneur | Imported, not public |
| The record is an organisation | Published, review pending |

So **individuals and entrepreneurs are never public before a human approves them.** Organisations can be live straight away, but they must carry the label and the noindex instruction until a person reviews them.

The "import everything as not public" setting is on until the safeguards in the acquisition plan pass (Q64).

## The pending label

A profile in Published, review pending shows this text on its page, on any card and in its share preview:

> Compiled automatically from public sources. Human review pending.

It links to a correct or remove action. The wording is one configuration value, so Legal can change it (REQ-216). After a person approves the profile, the label goes away. Public pages no longer show a "Reviewed on" date (D-054).

A pending profile returns HTTP 200 with an `X-Robots-Tag: noindex` header and a robots meta tag. It has no sitemap entry, no feed entry and no structured data, including anything a plugin might add (REQ-217). The robots.txt file must not block these URLs, because a crawler has to read the page to see the noindex rule.

## Who moves a profile between states

| Transition | Who |
|---|---|
| Import into a state | The Administrator who runs the import |
| Pending or not public to Reviewed, or to Rejected | A Reviewer, Editor or Administrator who holds the review capability. The approver must not be the importer. |
| Any public state to Unpublished | A user with the single-unpublish capability, or the bulk-unpublish capability for a filter |
| Any state to Removed and suppressed | The removal flow, which also writes the suppression list |

Any other change is refused and logged. A guard on WordPress's post status makes sure a plugin, quick edit or API call cannot publish a profile by accident.

## Taking a profile down

Unpublishing must work immediately, even on pages that search engines have indexed (REQ-209).

1. The state machine sets Unpublished, or Removed and suppressed, and deletes the profile's search row.
2. The origin starts returning **410 Gone** with `X-Robots-Tag: noindex`. It reads the state from the database, not from a cache.
3. The sitemap drops the URL. It is generated from the public read model.
4. If a CDN is in front, purge jobs clear the cached page. The edge caches public pages for at most 120 seconds (60 for lists and search), so the worst case after a failed purge is about two minutes.
5. Restoring a reversible unpublish returns 200.

**Bulk unpublish by filter** takes down many profiles at once. It replaced the earlier kill switch (D-043). The filter can combine source, type, import batch and state. It needs a reason and a count confirmation, writes one audit row per profile plus one for the batch, and can be restored by batch.

## Suppression

The suppression list holds people and organisations who asked to be removed, and nominees whose removal request was upheld. It is permanent, no import can bypass it, and only the Administrator edits it by hand (REQ-147, 207). A removal handled by an Editor adds the entry through the service automatically, and nobody below the Administrator can undo it.

## Listing pages and search

Home, discover and industry lists show reviewed profiles only, so a pending profile's name cannot be indexed through a list. Pending profiles can be reached by direct URL and on-site search, always with the label (REQ-218). There is no "include pending review" view (D-054).

## Edits

A staff edit to a profile goes live on one person's sign-off. Each edit writes a version, with author, time, reason and old and new values, and an audit row. **Edits to a claimed profile made by its assigned user are held for approval** by a Reviewer or Administrator (D-059); see [Trust and labels](/concepts/trust-and-labels).

## How it is tested

One test builds a profile in every state and requests it on every surface: profile page, lists, search, sitemaps, feeds, the REST API, oEmbed, shortlinks, attachment and author pages, previews, share images and cache headers. It checks the response code, the noindex header and the label against the state table ([build: testing](/build/testing-and-ci)).

## Open points

- A customer-submitted profile awaiting approval has no state name of its own. The proposal is to reuse "Imported, not public" with an origin of customer (Q73).
- Whether imported organisations go live at all depends on Legal's view (Q35); bulk unpublish is the safety net.
