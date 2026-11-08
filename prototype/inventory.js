/* Wireframe inventory: the single source for docs/wireframe-inventory.md and the Inventory page.
   Fields: id, audience, name, REQ numbers, decisions, tag (S = SPECIFIED, I = IMPLIED), route, note.
   Run scripts/build-inventory.py to regenerate the markdown. */
(function () {
  var W = (window.W = window.W || {});
  var AUD = { V: "Visitor", O: "Customer pages (single-use link, no account)", N: "Nominee / nominator", A: "Staff (wp-admin preview)", X: "Wireframe tools" };
  W.AUD = AUD;
  // [id, name, reqs, decisions, tag, route, note]
  var R = [
    ["V01", "Home", [1, 2, 24, 106, 195], "D-029", "S", "#/", "Hero is a labelled placeholder. Intended motion: slow 3D element rotating on scroll, headline reveal, cards rising in. Reduced-motion users get a static image. Built later, not now. The first screen on a phone must answer what / who / how (REQ-002). Awards block changes with the nominations flag."],
    ["V02", "About", [1], "D-001", "S", "#/about", "Copy comes from Brand (N1). Values block is a placeholder."],
    ["V03", "Discover People", [1, 7, 8, 24, 26, 40, 193, 208], "D-013, D-029, D-036", "S", "#/people", "Filters (industry, city, country, profession, label) write to the URL so results can be shared. Try the kill switch: imported individual profiles disappear, even Verified ones; customer-submitted ones stay."],
    ["V04", "Discover Businesses", [1, 7, 24], "D-002", "S", "#/businesses", "Same pattern as People."],
    ["V05", "Discover Institutions", [1, 5, 7], "", "S", "#/institutions", "Adds organisation-type filter."],
    ["V06", "Industries index", [1, 7, 9], "", "S", "#/industries", "Counts come from reviewed profiles only."],
    ["V07", "Industry page", [7, 28], "", "I", "#/industries/Technology", "Spec lists Industries but not what the page holds. Shows profiles and stories for one industry."],
    ["V08", "Search results", [8, 79, 208, 209], "D-029", "S", "#/search?q=sample", "Ranked: name matches first. Unreviewed, removed and hidden profiles never appear."],
    ["V09", "Individual profile", [3, 6, 18, 24, 60, 82, 133, 139, 140, 144, 155, 156, 162], "D-013, D-036", "S", "#/profile/sample-person-b", "Shows only the permitted fields, each with a source, the review state (Reviewed on <date>), and the correct / remove / report actions. No claim button and no login anywhere. Customer-submitted example: #/profile/sample-person-a"],
    ["V10", "Business profile", [4, 6, 18, 24, 132, 144, 155, 156], "D-002, D-036", "S", "#/profile/sample-agritech-co", "Compiled-from-public-sources note, sources and the Reviewed on label."],
    ["V11", "Institution profile", [5, 6, 24, 156], "", "S", "#/profile/sample-university-a", "Adds mandate and a longer history section."],
    ["V12", "Profile removed (410)", [193, 207, 209], "D-029", "S", "#/profile/sample-person-g", "What a visitor sees at an unpublished, suppressed or kill-switched URL. Real response is 410 plus noindex."],
    ["V13", "Page not found (404)", [121, 208], "", "S", "#/profile/not-a-real-slug", "Also what visitors get for any profile that is not yet approved. Reveals nothing about it."],
    ["V14", "Server error (500)", [121], "", "S", "#/error", "Friendly error page with a reference. No personal data."],
    ["V15", "Unreviewed profile: visitor vs staff view", [14, 208], "D-029", "I", "#/profile/sample-energy-group", "Visitor gets 404. Staff get a banner: staff preview, not public. Switch role to compare."],
    ["V16", "Stories", [27, 29], "", "S", "#/stories", "Filter by type and industry. Sponsored cards carry the label."],
    ["V17", "Article", [26, 27, 28, 182], "D-006", "S", "#/stories/sample-spotlight-sponsored", "Sponsored disclosure box and label on page, card and share preview."],
    ["V18", "Awards / Maiden Edition", [32, 33, 106, 199], "D-009, D-019, D-037", "S", "#/awards", "The nominations chip reads the only feature flag. 25 proposed categories; CEO approves the final list."],
    ["V19", "Award category", [33, 198], "D-021, D-037", "S", "#/awards/category/0", "A shortlist appears only after staff publish the nominee pages (no flag)."],
    ["V20", "Nominee page (shortlist stage)", [33, 64, 198], "D-021, D-037", "S", "#/awards/nominee/NOM-0001", "Public only after staff publish it (Shortlist and winners). Staff see a preview banner before that."],
    ["V21", "Winners", [38], "D-009, D-037", "S", "#/awards/winners", "Empty state until staff publish the announcement. No flag."],
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
    ["V34", "How we compile profiles and what labels mean", [24, 130, 153, 155, 163, 170], "D-003, D-017, D-036", "S", "#/how-we-compile", "Labels: Reviewed on <date>, Verified, Featured, and the separate Sponsored disclosure; human review and the AI disclosure (wording is Legal's)."],
    ["V35", "Complaints and takedown", [71, 158], "", "S", "#/complaints", "Response targets: acknowledge 1 business day, hide 2, corrections 7 days."],
    ["V36", "Sponsored disclosure states", [26, 155, 182], "D-003", "I", "#/stories/sample-spotlight-sponsored", "Dashed purple label, never styled like Verified. Appears on cards, pages and share previews."],
    ["V37", "Share preview / Open Graph card", [6, 82], "", "I", "#/profile/sample-fintech-ltd", "Side panel on each profile shows how a share looks, including the Sponsored label."],
    ["V38", "Search with no results", [8, 208], "", "I", "#/search?q=zzzz", "Empty state with a way out."],
    ["V39", "Correction request", [18, 60, 156, 157, 158, 172, 232, 236], "D-013, D-034, D-040", "S", "#/correct/sample-agritech-co", "No account or payment. Lands in the Support inbox as a Correction. Used to report errors, add a right-of-reply note, set contact visibility, switch enquiries on or off, or ask for views and sources. Requesters who are the person or their representative confirm their email by a single-use link (V51)."],
    ["V40", "Request removal", [147, 156, 157, 158, 207], "D-013", "S", "#/remove/sample-person-c", "No account or payment. Approving it (staff) hides, suppresses and purges."],
    ["V41", "Generic confirmation page", [156, 157], "", "I", "#/done?kind=correction&ref=cr1", "Reference number and what happens next."],
    ["V43", "Enquiry form (relay) and rate limit", [18, 60, 81, 122], "D-010, D-034", "S", "#/profile/sample-person-a", "Opens in a modal. After three sends the rate-limit error shows. The enquiry goes to the staff Support inbox; the profile's contact is never shown."],
    ["V44", "Report this profile", [81, 90], "D-022, D-040", "S", "#/profile/sample-media-house", "Modal. Lands in the Support inbox as a Report with a due date."],
    ["V45", "Unsubscribe landing", [61], "", "S", "#/unsubscribe", "One click, no login."],
    ["V46", "Cookie banner and preferences", [58, 62], "", "S", "#/", "Banner appears until you choose. Footer button reopens it."],
    ["V47", "Enquiries switched off", [18], "D-034, D-040", "I", "#/profile/sample-person-a", "Staff switch enquiries off on request (A55); the button disappears from the profile."],
    ["V48", "Sitemap preview", [6, 121, 208, 209], "D-029", "I", "#/sitemap.xml", "Built from the same visibility rule. Shows what is excluded and what visitors get instead."],
    ["V49", "robots.txt preview", [121], "", "I", "#/sitemap.xml", "Shown on the same preview page."],
    ["V50", "Your data rights (request form)", [59], "D-016", "I", "#/data-rights", "Access, correction, erasure, objection, portability. Creates a tracked request with a due date."],
    ["V51", "Email confirmation landing (correction request)", [232, 233], "D-034, D-036", "S", "#/confirm/t-cl4", "Single-use link from the outbox. After the click the request is open in the Support inbox; a second visit says the link is used. Send a correction as the person named to get a link."],

    ["O06", "Customer profile form (single-use link)", [3, 4, 5, 13, 67, 68, 126, 141, 233], "D-005, D-034", "S", "#/invite/demo/form", "Opens from the invitation (O23) after you press Start. Sensitive-text scan blocks submit. Warranty and 18+ checks. No account; the draft is held against the link. Open O23 first."],
    ["O07", "Image upload (valid, too large, wrong type, failed scan)", [13, 127, 203], "D-024", "S", "#/invite/demo/form", "Four result states to try. Licence and attribution field."],
    ["O09", "Submission confirmation", [14, 94], "D-034", "I", "#/invited/done", "After submit the link is used up and the profile waits for a reviewer."],
    ["O21", "Not authorised (403)", [109, 235], "D-041", "I", "#/admin/flags", "View as Visitor or Reviewer and open Feature flag, Taxonomies or Import."],
    ["O22", "Consent to a complimentary listing (single-use link)", [175, 233], "D-034", "S", "#/consent/demo", "Email landing page, no account. Sets the consent field on the arrangement."],
    ["O23", "Customer invitation landing (single-use link)", [13, 229, 233], "D-005, D-034", "S", "#/invite/demo", "Staff send it after the offline agreement. No account. Starts a draft profile held against the link."],
    ["O24", "Link expired or already used", [233], "D-034", "I", "#/expired", "Shown for any used or expired single-use link. Staff can reissue (REQ-233)."],

    ["N01", "Nominate: form (6 steps)", [30, 196], "D-021", "S", "#/nominate", "Needs the nominations flag ON. Steps: your details, email link, nominee, category and evidence, conflict declaration, review."],
    ["N02", "Verify nominator email (link sent)", [196, 233], "D-021, D-034", "I", "#/nominate", "Waiting state after step 1. The single-use link is in the outbox."],
    ["N03", "Duplicate nomination warning", [196], "D-021", "S", "#/nominate", "Nominate 'Sample Fintech Ltd.' to see it. The existing nominator is not revealed."],
    ["N04", "Nomination submitted", [64, 197], "D-021", "I", "#/nominate/done?ref=NOM-0099", "Says it is private until screening."],
    ["N05", "Rate limit reached", [122, 196], "D-021", "S", "#/nominate", "After three submissions."],
    ["N06", "Nominee response (accept, correct, decline)", [197, 233], "D-021, D-034", "S", "#/nominee/t-n6", "Email landing with a single-use link, no account. Open from the outbox."],
    ["N07", "Declined confirmation", [198, 207], "D-021", "S", "#/nominee/t-n6", "Decline removes the nominee and suppresses them for good."],
    ["N08", "Correct my details", [197], "D-021", "S", "#/nominee/t-n6", "Modal. Goes to staff as a correction."],
    ["N09", "Invalid or answered nominee link", [197], "", "I", "#/nominee/bad-token", "Edge case."],
    ["N10", "Conflict-of-interest declaration", [30], "D-021", "S", "#/nominate", "Step 5 of the form."],
    ["N11", "Evidence blocked for sensitive content", [67, 141], "", "I", "#/nominate", "Step 4 rejects phone numbers, emails, ages and sensitive words."],
    ["N12", "Nominator email verification by single-use link", [196, 233], "D-021, D-034", "S", "#/nominate/verify", "Landing for the link. It works once; afterwards it shows the expired page."],

    ["A01", "Staff sign in with two-factor (wp-admin preview)", [47, 83, 229], "D-034", "S", "#/staff/signin", "Code is 000000. The only sign-in on the site; there is no public login."],
    ["A02", "Dashboard (differs by role)", [88, 102, 105, 149, 171, 181, 189, 239], "D-008, D-035, D-041", "S", "#/admin", "Counts by state (imported, pending human review, reviewed, published); no directory target and no capacity figure. Reviewers see only review and directory tiles. Sprint target, revenue per stream and arrangements show for Administrator, Finance and Sales/Support. Switch Viewing as to preview each role."],
    ["A03", "Review queue", [84, 148, 149], "D-015", "S", "#/admin/review", "Assign to me. Edits and AI-drafted items are marked. Source URL and retrieval date shown read-only beside each claim."],
    ["A04", "Review item: diff, sources, checklist, approve or reject", [84, 123, 132, 133, 141, 142, 143, 148, 159, 163, 166, 168, 241], "D-015, D-017, D-038", "S", "#/admin/review/b5", "Try approving as Reviewer: blocked until scan flag and unsourced field are cleared. Sources are read-only (approved in the acquisition pipeline, not here). Shows the pending-review label a live page would carry."],
    ["A05", "Profiles list", [91, 208, 209], "D-029", "S", "#/admin/profiles", "Unpublish with reason, restore. Label column shows Verified or Featured only."],
    ["A06", "Profile admin: labels, history, redirects, sync", [16, 25, 125, 129, 135, 145, 150, 153, 154, 155, 161, 164, 182, 183, 184, 187, 188], "D-003, D-036, D-040", "S", "#/admin/profiles/b2", "Verified checklist ignores payment. Featured needs a reason. Sponsored is separate. Remove label. Staff edit fields directly (A58)."],
    ["A07", "Create profile on behalf of a customer", [85, 175], "D-006", "S", "#/admin/profiles/new", "Flagged and attributed. Complimentary needs recorded consent."],
    ["A12", "Support inbox", [89, 90, 156, 157, 158, 174, 232], "D-040", "S", "#/admin/inbox", "Enquiries and the public Correction, Removal and Report forms as typed items with status and a due-date countdown. Staff act in the profile admin; edits and unpublishes are audited; a valid removal also suppresses."],
    ["A13", "Leads", [178, 205], "D-005", "S", "#/admin/leads", "Status, owner, notes, convert, CSV."],
    ["A14", "Customers", [13, 205], "D-006", "S", "#/admin/customers", "Send an invitation link."],
    ["A15", "Commercial register", [21, 22, 23, 87, 179, 180], "D-006, D-028", "S", "#/admin/register", "Computed status and counting rule. CSV with counting status."],
    ["A16", "Arrangement edit", [22, 86, 112, 115, 116, 179, 182, 188], "D-028", "S", "#/admin/register/arr1", "Live counting check. Reason required on every change. End or cancel."],
    ["A17", "Counting-rule explainer", [22, 23], "D-008", "S", "#/admin/register", "Panel at the top of the register and live in the edit form."],
    ["A18", "Sponsors", [186], "", "S", "#/admin/sponsors", "Attach to awards, categories and pages."],
    ["A20", "Import from the acquisition pipeline, with dry run", [92, 132, 134, 141, 146, 207, 237], "D-014, D-038, D-039", "S", "#/admin/import", "Dry run validates against the import contract: required fields, country code, terms that exist in Taxonomies, suppression, duplicates, free-text patterns. No source toggle: sources are approved in the pipeline."],
    ["A21", "Duplicates", [123, 145, 146], "", "S", "#/admin/duplicates", "Humans merge. Never automatic."],
    ["A22", "Suppression list", [147, 207], "D-013", "S", "#/admin/suppression", "No remove button, by design. Read-only for Reviewers; manual add for the Editor-in-Chief and Administrator."],
    ["A24", "Articles: list and editor", [16, 27, 28, 182], "", "S", "#/admin/articles", "Create, schedule, publish, sponsor label."],
    ["A26", "Feature flag (nominations only)", [194], "D-009, D-037", "S", "#/admin/flags", "Administrator only; approval reference required to turn on. The kill switch is shown separately in plain words."],
    ["A27", "Kill switch", [193, 209], "D-013, D-036", "S", "#/admin/killswitch", "Named roles only; confirm by typing KILL; reversible. Hides every imported individual profile, even if later Verified, Featured or Sponsored; customer-submitted individual profiles are not affected (default pending Q68)."],
    ["A28", "Nominations", [31], "D-021", "S", "#/admin/nominations", "Filter, export, curated nominations."],
    ["A29", "Nomination detail and stages", [34, 187, 199, 202], "D-021, D-037", "S", "#/admin/nominations/n2", "Separate steps with named approvers; Publish nominee page at shortlist stage. No commercial data on the screen."],
    ["A30", "Awards setup", [32, 200], "D-019", "S", "#/admin/awards", "Criteria, weights, shortlist size, panel size per category."],
    ["A31", "Judging panel and conflicts", [36, 200, 234], "D-019, D-034", "S", "#/admin/judges", "Judges are names, not users. Assignments, and conflicts judges declared offline, entered by the Awards manager."],
    ["A32", "Shortlist and winners (publish step)", [38], "D-019, D-037", "S", "#/admin/announcements", "Publish each nominee page, then the winners announcement. No feature flag."],
    ["A33", "Audit log", [51, 188], "", "S", "#/admin/audit", "Hash-chained; verify button."],
    ["A34", "Users and roles", [47, 83, 109, 229, 235], "D-034", "S", "#/admin/users", "Staff only. Two-factor status, deactivate a leaver, link to the matrix."],
    ["A35", "Data requests", [59], "D-016", "I", "#/admin/dsr", "Intake, tracking and due dates."],
    ["A37", "Compliance: processors and retention", [57, 63, 65, 72], "D-023", "S", "#/admin/compliance", "Processor register and retention schedule."],
    ["A38", "Breach log", [66], "", "S", "#/admin/compliance", "Logs the incident and shows the checklist."],
    ["A39", "Stale records", [160], "", "S", "#/admin/stale", "Re-check at 6 months, stale at 12, unpublish at 18."],
    ["A40", "Email log", [75, 94, 204], "", "S", "#/admin/emails", "Every notification sent."],
    ["A41", "Exports", [69, 77, 87, 131], "D-011, D-040", "S", "#/admin/exports", "CSV downloads. Administrator only; every export is logged."],
    ["A42", "Prospects", [93, 176], "", "S", "#/admin/prospects", "Separate store; cannot go public except through review."],
    ["A43", "Internal notes", [187], "D-006", "S", "#/admin/profiles/b2", "On profiles, customers, arrangements, nominations. Never public and never on score screens."],
    ["A44", "Expiry reminders", [116, 189], "D-018", "S", "#/admin", "Dashboard block for arrangements ending in 30 days."],
    ["A45", "Issue invitation or consent link", [13, 175], "D-005", "S", "#/admin/customers", "Sends the email whose landing pages are O22 and O23."],
    ["A46", "Review throughput and rejection reasons", [149, 206], "D-015", "S", "#/admin", "Dashboard block."],
    ["A47", "Settings: thresholds and default revenue streams", [180, 181], "D-008", "S", "#/admin/settings", "10 subscriptions and NGN 5m are settings."],
    ["A48", "Purge and unpublish log", [209], "D-029", "I", "#/admin/purges", "Shows each CDN purge queued by an unpublish."],
    ["A51", "Scores and results", [37, 234], "D-019, D-034", "S", "#/admin/scores", "Offline judging: staff enter scores. Panel, scores in, weighted average, status. No flag."],
    ["A52", "Score entry grid for one nomination", [36, 37, 200, 234], "D-019, D-034", "S", "#/admin/scores/n4", "Judge by criterion, 0 to 10, weights from Awards setup. Each save records who entered it. Shortlist and winner marking."],
    ["A53", "Score entry blocked by a recorded conflict", [36, 234], "D-019, D-034", "I", "#/admin/scores/n5", "Sample Judge 2 has a recorded conflict: no score boxes for that judge."],
    ["A54", "Roles and permissions", [47, 83, 235, 240], "D-034, D-040, D-041", "S", "#/admin/roles", "Capability matrix for the seven staff roles, the left menu for each role, and the rules: Administrator-only export, Reviewers cannot approve sources or edit terms, no Judge role."],
    ["A55", "Contact visibility and enquiry switch (on request)", [18, 60], "D-034, D-040", "S", "#/admin/profiles/b2", "Staff set which contact details show and switch enquiries on or off after someone asks through the correction form."],
    ["A56", "Send views and sources on request", [236], "D-034, D-040", "S", "#/admin/profiles/b2", "Staff email the view count and the sources used to the requester."],
    ["A57", "Taxonomy term screens (Industries, Cities, Countries, Organisation types, Professions)", [9], "D-010, D-039, D-041", "S", "#/admin/taxonomy/industries", "Preview of the native WordPress term screen: add, edit, delete, parent (cities under countries), count. Editors and Administrators only. Reviewers can only assign existing terms on the profile."],
    ["A58", "Staff edit a profile directly", [15, 125, 188, 232], "D-040", "S", "#/admin/profiles/b1", "Edit fields and assign existing terms, give a reason; the page changes at once and the audit log records it. No second-approver step, no owner change request."],


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
