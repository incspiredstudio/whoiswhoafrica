/* Wireframe inventory: the single source for docs/wireframe-inventory.md and the Inventory page.
   Fields: id, audience, name, REQ numbers, decisions, tag (S = SPECIFIED, I = IMPLIED), route, note.
   Run scripts/build-inventory.py to regenerate the markdown. */
(function () {
  var W = (window.W = window.W || {});
  var AUD = { V: "Visitor", O: "Profile owner", N: "Nominee / nominator", A: "Reviewer and admin staff", J: "Judge", X: "Wireframe tools" };
  W.AUD = AUD;
  // [id, name, reqs, decisions, tag, route, note]
  var R = [
    ["V01", "Home", [1, 2, 24, 106, 195], "D-029", "S", "#/", "Hero is a labelled placeholder. Intended motion: slow 3D element rotating on scroll, headline reveal, cards rising in. Reduced-motion users get a static image. Built later, not now. The first screen on a phone must answer what / who / how (REQ-002). Awards block changes with the nominations flag."],
    ["V02", "About", [1], "D-001", "S", "#/about", "Copy comes from Brand (N1). Values block is a placeholder."],
    ["V03", "Discover People", [1, 7, 8, 24, 26, 40, 193, 208], "D-013, D-029", "S", "#/people", "Filters write to the URL so results can be shared. Try the kill switch: unclaimed individuals disappear."],
    ["V04", "Discover Businesses", [1, 7, 24], "D-002", "S", "#/businesses", "Same pattern as People."],
    ["V05", "Discover Institutions", [1, 5, 7], "", "S", "#/institutions", "Adds organisation-type filter."],
    ["V06", "Industries index", [1, 7, 9], "", "S", "#/industries", "Counts come from reviewed profiles only."],
    ["V07", "Industry page", [7, 28], "", "I", "#/industries/Technology", "Spec lists Industries but not what the page holds. Shows profiles and stories for one industry."],
    ["V08", "Search results", [8, 79, 208, 209], "D-029", "S", "#/search?q=sample", "Ranked: name matches first. Unreviewed, removed and hidden profiles never appear."],
    ["V09", "Individual profile", [3, 6, 18, 24, 60, 82, 133, 139, 140, 144, 151, 155, 156, 162], "D-013, D-022", "S", "#/profile/sample-person-b", "Stub shows only the permitted fields, each with a source, plus claim / correct / remove / report. Owner-supplied profile: #/profile/sample-person-a"],
    ["V10", "Business profile", [4, 6, 18, 24, 132, 144, 151, 155, 156], "D-002", "S", "#/profile/sample-agritech-co", "Unclaimed label, sources and last-reviewed date."],
    ["V11", "Institution profile", [5, 6, 24, 156], "", "S", "#/profile/sample-university-a", "Adds mandate and a longer history section."],
    ["V12", "Profile removed (410)", [193, 207, 209], "D-029", "S", "#/profile/sample-person-g", "What a visitor sees at an unpublished, suppressed or kill-switched URL. Real response is 410 plus noindex."],
    ["V13", "Page not found (404)", [121, 208], "", "S", "#/profile/not-a-real-slug", "Also what visitors get for any profile that is not yet approved. Reveals nothing about it."],
    ["V14", "Server error (500)", [121], "", "S", "#/error", "Friendly error page with a reference. No personal data."],
    ["V15", "Unreviewed profile: visitor vs staff view", [14, 208], "D-029", "I", "#/profile/sample-energy-group", "Visitor gets 404. Staff and the owner get a banner: staff preview, not public. Switch role to compare."],
    ["V16", "Stories", [27, 29], "", "S", "#/stories", "Filter by type and industry. Sponsored cards carry the label."],
    ["V17", "Article", [26, 27, 28, 182], "D-006", "S", "#/stories/sample-spotlight-sponsored", "Sponsored disclosure box and label on page, card and share preview."],
    ["V18", "Awards / Maiden Edition", [32, 33, 106, 199], "D-009, D-019", "S", "#/awards", "Reads the three award flags. 25 proposed categories; CEO approves the final list."],
    ["V19", "Award category", [33, 198], "D-021", "S", "#/awards/category/0", "Shortlist appears only when the judging flag is on."],
    ["V20", "Nominee page (shortlist stage)", [33, 64, 198], "D-021", "S", "#/awards/nominee/NOM-0001", "Public only at shortlist. Turn judging on to see it as a visitor."],
    ["V21", "Winners", [38, 194], "D-009", "S", "#/awards/winners", "Empty states for flag off and not yet published."],
    ["V22", "Nominate (closed) with email sign-up", [106, 194, 195], "D-009", "S", "#/nominate", "'Nominations open soon' plus consented email sign-up while the flag is OFF."],
    ["V23", "Sign-up confirmation", [58, 61, 195], "D-009", "I", "#/nominate?signed=1", "Confirmation after the sign-up. Consent is stored with policy version."],
    ["V24", "Subscribe / Get Featured", [81, 122, 177], "D-005, D-028", "S", "#/subscribe", "Informational plus an enquiry form. No packages, tiers, cart or checkout."],
    ["V25", "Lead confirmation", [177], "D-005", "I", "#/done?kind=lead&ref=LEAD-0099", "Says terms are agreed offline and nothing is charged."],
    ["V26", "Contact", [39], "", "S", "#/contact", "Goes to the staff inbox and is logged."],
    ["V27", "Contact confirmation", [39], "", "I", "#/done?kind=contact&ref=msg1", "Confirmation page."],
    ["V28", "FAQ", [120], "", "S", "#/faq", "Spec lists an FAQ page. Placeholder questions."],
    ["V29", "Privacy policy", [58, 100, 120], "D-016", "S", "#/privacy", "Text from Legal (N9). Versioned; the version is stored with consents."],
    ["V30", "Terms and conditions", [100, 120, 126], "", "S", "#/terms", "Text from Legal."],
    ["V31", "Cookie policy", [62, 120], "", "I", "#/cookies", "Needed because analytics wait for consent."],
    ["V32", "Commercial terms", [100, 120], "D-005", "S", "#/commercial-terms", "Offline arrangements; sponsorship never buys status."],
    ["V33", "Awards rules", [32, 120], "D-019", "S", "#/awards/rules", "Governance text is the CEO's (N8)."],
    ["V34", "How we compile profiles and what labels mean", [24, 130, 153, 155, 163, 170], "D-003, D-017", "S", "#/how-we-compile", "Status ladder, Sponsored, human review and the AI disclosure (wording is Legal's)."],
    ["V35", "Complaints and takedown", [71, 158], "", "S", "#/complaints", "Response targets: acknowledge 1 business day, hide 2, corrections 7 days."],
    ["V36", "Sponsored disclosure states", [26, 155, 182], "D-003", "I", "#/stories/sample-spotlight-sponsored", "Dashed purple label, never styled like Verified. Appears on cards, pages and share previews."],
    ["V37", "Share preview / Open Graph card", [6, 82], "", "I", "#/profile/sample-fintech-ltd", "Side panel on each profile shows how a share looks, including the Sponsored label."],
    ["V38", "Search with no results", [8, 208], "", "I", "#/search?q=zzzz", "Empty state with a way out."],
    ["V39", "Suggest a correction", [156, 157, 158], "D-013", "S", "#/correct/sample-agritech-co", "No account or payment needed."],
    ["V40", "Request removal", [147, 156, 157, 158, 207], "D-013", "S", "#/remove/sample-person-c", "No account or payment. Approving it (staff) hides, suppresses and purges."],
    ["V41", "Generic confirmation page", [156, 157], "", "I", "#/done?kind=correction&ref=cr1", "Reference number and what happens next."],
    ["V42", "Claim this profile (3 steps)", [124, 152, 156], "D-022", "S", "#/claim/sample-university-a", "Org: domain email or document. Individual: document and manual approval. A second claim triggers a dispute."],
    ["V43", "Enquiry form (relay) and rate limit", [18, 60, 81, 122], "D-010", "S", "#/profile/sample-person-a", "Opens in a modal. After three sends the rate-limit error shows. Owner's contact is never shown."],
    ["V44", "Report this profile", [81, 90], "D-022", "S", "#/profile/sample-media-house", "Modal. Enters the staff abuse queue."],
    ["V45", "Unsubscribe landing", [61], "", "S", "#/unsubscribe", "One click, no login."],
    ["V46", "Cookie banner and preferences", [58, 62], "", "S", "#/", "Banner appears until you choose. Footer button reopens it."],
    ["V47", "Enquiries switched off", [18], "", "I", "#/profile/sample-person-a", "Owner turns enquiries off in Visibility; the button disappears from the profile."],
    ["V48", "Sitemap preview", [6, 121, 208, 209], "D-029", "I", "#/sitemap.xml", "Built from the same visibility rule. Shows what is excluded and what visitors get instead."],
    ["V49", "robots.txt preview", [121], "", "I", "#/sitemap.xml", "Shown on the same preview page."],
    ["V50", "Your data rights (request form)", [59], "D-016", "I", "#/data-rights", "Access, correction, erasure, objection, portability. Creates a tracked request with a due date."],

    ["O01", "Sign up and check your email", [58, 68, 107], "D-030", "I", "#/signup", "18+ confirmation. One login per profile owner."],
    ["O02", "Email verified", [107], "", "I", "#/verify/demo", "Email landing page."],
    ["O03", "Sign in", [108], "", "I", "#/login", "Any credentials work in the wireframe."],
    ["O04", "Forgot / reset password", [108], "", "I", "#/forgot", "Neutral confirmation message. Reset signs out other sessions."],
    ["O05", "Owner dashboard", [14, 19, 150], "D-030", "S", "#/account", "Status, view count only, to-dos, claim status."],
    ["O06", "Profile editor and intake", [3, 4, 5, 13, 67, 68, 126, 141], "D-005", "S", "#/account/profile", "Sensitive-text scan blocks submit. Warranty and 18+ checks. Reached through an admin-issued invitation, no payment step."],
    ["O07", "Image upload (valid, too large, wrong type, failed scan)", [13, 127, 203], "D-024", "S", "#/account/profile", "Four result states to try. Licence and attribution field."],
    ["O08", "Preview as published", [128, 208], "", "S", "#/account/profile/preview", "Owner-only. Public only after approval."],
    ["O09", "Submission confirmation", [14, 94], "", "I", "#/account/submitted?kind=new", "Three variants: new, edit needing review, edit that went live."],
    ["O10", "Rejected state with reason", [84, 94], "", "I", "#/account", "Reviewer rejects in the queue; the owner dashboard shows the reason and a resubmit path."],
    ["O11", "Editing a published profile", [15, 125], "", "S", "#/account/profile", "Changes to name, title, organisation, achievements wait for review. Other changes go live."],
    ["O12", "Visibility and contact settings", [18, 60], "D-010", "S", "#/account/visibility", "Defaults: no contact details public."],
    ["O13", "Owner enquiry inbox", [18], "", "I", "#/account/enquiries", "Shows relayed enquiries."],
    ["O14", "Account settings, sessions, delete", [108, 110], "", "I", "#/account/settings", "Delete shows the effect before confirming."],
    ["O15", "Claim status", [152], "D-022", "I", "#/account/claims", "Pending / approved / denied."],
    ["O16", "Claim dispute notice", [124, 174], "", "I", "#/account/claims", "Appears when two claims exist for one profile."],
    ["O17", "Version history", [125], "", "S", "#/account/history", "Who changed what and when."],
    ["O18", "Sources used about you", [173], "", "S", "#/account/sources", "Claimed owners can see the platform's sources."],
    ["O19", "Right of reply", [172], "", "S", "#/account/sources", "Short note, reviewed before showing."],
    ["O20", "Yearly check", [160], "", "S", "#/account/confirm-details", "Annual prompt for claimed owners."],
    ["O21", "Not authorised (403)", [109], "", "I", "#/admin/flags", "View as Visitor and open a staff page."],
    ["O22", "Consent to a complimentary listing", [175], "", "S", "#/consent/demo", "Email landing page."],
    ["O23", "Invitation landing", [13], "D-005", "S", "#/invite/demo", "Admin sends it after the offline agreement. Starts a draft profile."],
    ["O24", "Link expired or already used", [107], "", "I", "#/expired", "Email link edge case."],
    ["O25", "Sign-in required", [109], "", "I", "#/account", "View as Visitor and open the account area."],

    ["N01", "Nominate: form (6 steps)", [30, 196], "D-021", "S", "#/nominate", "Needs the nominations flag ON. Steps: your details, verify, nominee, category and evidence, conflict declaration, review."],
    ["N02", "Verify nominator email", [196], "D-021", "I", "#/nominate", "Six-digit code (123456 in the outbox)."],
    ["N03", "Duplicate nomination warning", [196], "D-021", "S", "#/nominate", "Nominate 'Sample Fintech Ltd.' to see it. The existing nominator is not revealed."],
    ["N04", "Nomination submitted", [64, 197], "D-021", "I", "#/nominate/done?ref=NOM-0099", "Says it is private until screening."],
    ["N05", "Rate limit reached", [122, 196], "D-021", "S", "#/nominate", "After three submissions."],
    ["N06", "Nominee response (accept, correct, decline)", [197], "D-021", "S", "#/nominee/t-n6", "Email landing. Open from the outbox."],
    ["N07", "Declined confirmation", [198, 207], "D-021", "S", "#/nominee/t-n6", "Decline removes the nominee and suppresses them for good."],
    ["N08", "Correct my details", [197], "D-021", "S", "#/nominee/t-n6", "Modal. Goes to staff as a correction."],
    ["N09", "Invalid or answered nominee link", [197], "", "I", "#/nominee/bad-token", "Edge case."],
    ["N10", "Conflict-of-interest declaration", [30], "D-021", "S", "#/nominate", "Step 5 of the form."],
    ["N11", "Evidence blocked for sensitive content", [67, 141], "", "I", "#/nominate", "Step 4 rejects phone numbers, emails, ages and sensitive words."],

    ["A01", "Staff sign in with MFA", [47, 83], "", "S", "#/staff/signin", "Code is 000000."],
    ["A02", "Dashboard", [88, 102, 105, 149, 171, 181, 189, 191, 206], "D-008", "S", "#/admin", "Both sprint numbers, revenue per stream, workload and backlog warning, expiry reminders, rejection reasons, profiles by type and status."],
    ["A03", "Review queue", [84, 148, 149, 206], "D-015", "S", "#/admin/review", "Assign to me. Edits and AI-drafted items are marked."],
    ["A04", "Review item: diff, sources, checklist, approve or reject", [84, 123, 132, 133, 141, 142, 143, 148, 159, 163, 166, 168], "D-015, D-017", "S", "#/admin/review/b5", "Try approving as Reviewer: blocked until scan flag and unsourced field are cleared."],
    ["A05", "Profiles list", [91, 208, 209], "D-029", "S", "#/admin/profiles", "Unpublish with reason, restore."],
    ["A06", "Profile admin: labels, history, redirects, sync", [16, 25, 124, 125, 129, 135, 145, 150, 153, 154, 155, 161, 164, 182, 183, 184, 187, 188], "D-003", "S", "#/admin/profiles/b2", "Verified checklist ignores payment. Featured needs a reason. Sponsored is separate."],
    ["A07", "Create profile on behalf of a customer", [85, 175], "D-006", "S", "#/admin/profiles/new", "Flagged and attributed. Complimentary needs recorded consent."],
    ["A08", "Claims queue", [152, 174], "D-022", "S", "#/admin/claims", "Disputes flagged."],
    ["A09", "Claim review", [124, 152, 174], "D-022", "S", "#/admin/claims/cl2", "Approve moves Unclaimed to Claimed."],
    ["A10", "Abuse reports", [90, 91], "D-022", "S", "#/admin/reports", "Hide profile, dismiss, resolve."],
    ["A11", "Corrections and removals", [147, 158, 164, 207], "D-013", "S", "#/admin/corrections", "Due dates and overdue flags. Approving a removal suppresses and purges."],
    ["A12", "Support inbox", [89, 174], "", "S", "#/admin/inbox", "Enquiries, claims, reports, complaints and escalations with owner, status and age."],
    ["A13", "Leads", [178, 205], "D-005", "S", "#/admin/leads", "Status, owner, notes, convert, CSV."],
    ["A14", "Customers", [13, 205], "D-006", "S", "#/admin/customers", "Send an invitation link."],
    ["A15", "Commercial register", [21, 22, 23, 87, 179, 180], "D-006, D-028", "S", "#/admin/register", "Computed status and counting rule. CSV with counting status."],
    ["A16", "Arrangement edit", [22, 86, 112, 115, 116, 179, 182, 188], "D-028", "S", "#/admin/register/arr1", "Live counting check. Reason required on every change. End or cancel."],
    ["A17", "Counting-rule explainer", [22, 23], "D-008", "S", "#/admin/register", "Panel at the top of the register and live in the edit form."],
    ["A18", "Sponsors", [186], "", "S", "#/admin/sponsors", "Attach to awards, categories and pages."],
    ["A19", "Source registry", [136, 137, 138, 192], "D-014", "S", "#/admin/sources", "Approve needs a named person who checked terms, licence and robots."],
    ["A20", "Bulk import with dry run", [92, 132, 134, 141, 146, 192, 207], "D-014", "S", "#/admin/import", "Sample CSV shows ok, error, duplicate, suppressed and blocked rows."],
    ["A21", "Duplicates", [123, 145, 146], "", "S", "#/admin/duplicates", "Humans merge. Never automatic."],
    ["A22", "Suppression list", [147, 207], "D-013", "S", "#/admin/suppression", "No remove button, by design."],
    ["A23", "AI drafting", [165, 166, 167, 168, 169], "D-017", "S", "#/admin/ai", "Off by default; claims without a source are dropped."],
    ["A24", "Articles: list and editor", [16, 27, 28, 182], "", "S", "#/admin/articles", "Create, schedule, publish, sponsor label."],
    ["A25", "Controlled lists", [9], "D-010", "S", "#/admin/lists", "Industries, cities, org types, professions."],
    ["A26", "Feature flags", [194], "D-009, D-019", "S", "#/admin/flags", "Admin only; approval reference required to turn on."],
    ["A27", "Kill switch", [193, 209], "D-013", "S", "#/admin/killswitch", "Named roles only; confirm by typing KILL; reversible."],
    ["A28", "Nominations", [31], "D-021", "S", "#/admin/nominations", "Filter, export, curated nominations."],
    ["A29", "Nomination detail and stages", [34, 187, 199, 202], "D-021", "S", "#/admin/nominations/n2", "Separate steps with named approvers. No commercial data on the screen."],
    ["A30", "Awards setup", [32, 200], "D-019", "S", "#/admin/awards", "Criteria, weights, shortlist size, judges per category."],
    ["A31", "Judges and conflicts", [36, 201], "D-019", "S", "#/admin/judges", "Assignments and declared conflicts."],
    ["A32", "Announcements", [38, 194], "D-019", "S", "#/admin/announcements", "Disabled unless the announcements flag is on."],
    ["A33", "Audit log", [51, 188], "", "S", "#/admin/audit", "Hash-chained; verify button."],
    ["A34", "Users and roles", [47, 69, 83, 109], "", "S", "#/admin/users", "MFA status and who can open what."],
    ["A35", "Data requests", [59], "D-016", "I", "#/admin/dsr", "Intake, tracking and due dates."],
    ["A36", "Escalations to the Editor-in-Chief", [174], "", "S", "#/admin/inbox", "Appear in the support inbox."],
    ["A37", "Compliance: processors and retention", [57, 63, 65, 72], "D-023", "S", "#/admin/compliance", "Processor register and retention schedule."],
    ["A38", "Breach log", [66], "", "S", "#/admin/compliance", "Logs the incident and shows the checklist."],
    ["A39", "Stale records", [160], "", "S", "#/admin/stale", "Re-check at 6 months, stale at 12, unpublish at 18."],
    ["A40", "Email log", [75, 94, 204], "", "S", "#/admin/emails", "Every notification sent."],
    ["A41", "Exports", [69, 77, 87, 131], "D-011", "S", "#/admin/exports", "CSV downloads."],
    ["A42", "Prospects", [93, 176], "", "S", "#/admin/prospects", "Separate store; cannot go public except through review."],
    ["A43", "Internal notes", [187], "D-006", "S", "#/admin/profiles/b2", "On profiles, customers, claims, arrangements, nominations. Never public, never visible to judges."],
    ["A44", "Expiry reminders", [116, 189], "D-018", "S", "#/admin", "Dashboard block for arrangements ending in 30 days."],
    ["A45", "Issue invitation or consent link", [13, 175], "D-005", "S", "#/admin/customers", "Sends the email whose landing pages are O22 and O23."],
    ["A46", "Review throughput and rejection reasons", [149, 206], "D-015", "S", "#/admin", "Dashboard block."],
    ["A47", "Settings: thresholds and default revenue streams", [180, 181], "D-008", "S", "#/admin/settings", "10 subscriptions and NGN 5m are settings."],
    ["A48", "Purge and unpublish log", [209], "D-029", "I", "#/admin/purges", "Shows each CDN purge queued by an unpublish."],

    ["J01", "Judge sign in with MFA", [35, 47], "D-019", "I", "#/judge/signin", "Code is 000000."],
    ["J02", "Assignments", [35, 37, 201], "D-019", "S", "#/judge", "Only assigned items. Empty state when judging is off."],
    ["J03", "Declare a conflict", [36], "D-019", "S", "#/judge", "Removes the nomination from the judge's list and tells staff."],
    ["J04", "Score a nomination", [35, 37, 200, 201], "D-019", "S", "#/judge/score/n4", "Criteria and weights come from staff configuration. Running weighted total."],
    ["J05", "Judging not open", [194], "D-009", "I", "#/judge", "Shown while the judging flag is OFF."],
    ["J06", "Judging guidelines", [36, 37], "D-019", "I", "#/judge/guidelines", "Text from the CEO."],
    ["J07", "Score submitted and locked", [35], "", "I", "#/judge/score/n4", "After submit, fields lock."],

    ["X01", "Inventory page", [], "", "I", "#/inventory", "Lists every screen, its REQs and a link to try it."],
    ["X02", "State viewer", [], "", "I", "#/state", "Shows the sample state that drives cross-screen effects."],
    ["X03", "Flows menu", [], "", "I", "#/", "Floating button, bottom left."],
    ["X04", "Design notes panel", [], "", "I", "#/", "Floating button, bottom right. Shows the note for the current screen."]
  ];
  W.INV = R.map(function (r) { return { id: r[0], aud: AUD[r[0].charAt(0)], name: r[1], reqs: r[2], dec: r[3], tag: r[4] === "S" ? "SPECIFIED" : "IMPLIED", route: r[5], note: r[6] }; });
  W.invById = function (id) { return W.INV.filter(function (x) { return x.id === id; })[0]; };
  function rq(a) { return a.length ? a.map(function (n) { return "REQ-" + ("00" + n).slice(-3); }).join(", ") : "—"; }
  W.INV.forEach(function (x) { x.reqs = x.reqs; x.reqText = rq(x.reqs); });
  var _i = W.invById; W.invById = function (id) { var x = _i(id); if (x) { x = Object.assign({}, x, { reqs: x.reqText + (x.dec ? " · " + x.dec : "") }); } return x; };

  var page = function (h) { return '<div class="wrap section stack">' + h + "</div>"; };
  W.route("/error", "V14", function () { return page('<div class="errbox"><h1>Something went wrong (500)</h1><p>We have been told. Reference: <span class="mono">ERR-SAMPLE-1</span>. No personal data is in the error.</p></div><a class="btn" href="#/">Home</a>'); });
  W.route("/inventory", "X01", function (p, q) {
    var groups = {}; W.INV.forEach(function (x) { (groups[x.aud] = groups[x.aud] || []).push(x); });
    return page("<h1>Inventory of screens and states</h1><p class=\"muted\">" + W.INV.length + " entries. SPECIFIED = named in the documents. IMPLIED = needed but never stated. Open any entry to try it. Full table: docs/wireframe-inventory.md.</p>" + Object.keys(groups).map(function (a) {
      return "<h2>" + W.esc(a) + " (" + groups[a].length + ")</h2>" + W.table(["ID", "Screen", "Tag", "Serves", "Try it"], groups[a].map(function (x) { return ['<span id="' + x.id + '" class="mono">' + x.id + "</span>", W.esc(x.name), '<span class="pill ' + (x.tag === "SPECIFIED" ? "ok" : "warn") + '">' + x.tag + "</span>", W.esc(x.reqText + (x.dec ? " · " + x.dec : "")), '<a class="btn sm alt" href="' + W.esc(x.route) + '">Open</a>']; }));
    }).join(""));
  });
  W.route("/inventory/:id", "X01", function (p) { return page('<p><a href="#/inventory">← Inventory</a></p>' + (function () { var x = W.INV.filter(function (i) { return i.id === p.id; })[0]; return x ? "<h1>" + W.esc(x.id + " · " + x.name) + "</h1><p><span class=\"pill gray\">" + x.tag + "</span> " + W.esc(x.reqText + (x.dec ? " · " + x.dec : "")) + "</p><p>" + W.esc(x.note) + '</p><p><a class="btn" href="' + W.esc(x.route) + '">Try it</a></p>' : "<h1>Unknown id</h1>"; })()); });
  W.route("/state", "X02", function () {
    var S = W.S;
    return page("<h1>Sample state</h1><p class=\"muted\">This is what carries across screens. Stored in your browser only. Use Reset to start over.</p>" + W.table(["Item", "Value"], [["Role", W.esc(S.role)], ["Flags", Object.keys(S.flags).map(function (k) { return W.esc(k + "=" + (S.flags[k].on ? "ON" : "off")); }).join(", ")], ["Kill switch", S.kill.on ? "ON" : "off"], ["Profiles (published / pending / removed)", S.profiles.filter(function (p) { return p.status === "published"; }).length + " / " + S.profiles.filter(function (p) { return p.status === "pending"; }).length + " / " + S.profiles.filter(function (p) { return p.status === "unpublished" || p.status === "suppressed"; }).length], ["Arrangements counted as paid", S.arrangements.filter(W.counts).length], ["Leads", S.leads.length], ["Nominations", S.nominations.length], ["Emails in outbox", S.emails.length], ["Audit rows", S.audit.length], ["Purges queued", S.purges.length]]));
  });
})();
