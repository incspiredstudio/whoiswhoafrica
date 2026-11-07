/* Profile owner screens: sign up, sign in, dashboard, editor, settings, email landing pages. */
(function () {
  var W = window.W, e = W.esc;
  var OWN = { area: "own" };
  function page(html) { return '<div class="wrap section stack">' + html + "</div>"; }
  function me() { return W.profile(W.S.ownerId); }
  W.ownerTabs = function (path) {
    var tabs = [["Dashboard", "/account"], ["Edit profile", "/account/profile"], ["Preview", "/account/profile/preview"], ["Enquiries", "/account/enquiries"], ["Visibility", "/account/visibility"], ["History", "/account/history"], ["Sources", "/account/sources"], ["Settings", "/account/settings"]];
    return '<nav aria-label="Account" class="row" style="margin-bottom:16px">' + tabs.map(function (t) { return '<a class="btn sm ' + (path === t[1] ? "" : "alt") + '" href="#' + t[1] + '"' + (path === t[1] ? ' aria-current="page"' : "") + ">" + e(t[0]) + "</a>"; }).join("") + "</nav>";
  };

  /* ---------- auth ---------- */
  W.route("/login", "O03", function () {
    return page("<h1>Sign in</h1><form class=\"card stack-s\" data-form=\"login\" style=\"max-width:28rem\">" + W.field("Email", "email", { type: "email", req: true, attrs: 'autocomplete="username"' }) + W.field("Password", "password", { type: "password", req: true, attrs: 'autocomplete="current-password"' }) + '<button class="btn">Sign in</button><p class="small"><a href="#/forgot">Forgot password?</a> · <a href="#/signup">Create an account</a></p></form><p class="small muted">Wireframe: any email and password work. Staff: <a href="#/staff/signin">staff sign in</a> · Judges: <a href="#/judge/signin">judge sign in</a>. Profile owners have one login each; no organisation team accounts yet (D-030).</p>');
  });
  W.forms.login = function () { W.S.role = "owner"; W.save(); W.go("#/account"); W.toast("Signed in as profile owner (sample)."); };
  W.route("/signup", "O01", function () {
    return page("<h1>Create an account</h1><p>Accounts are for people who have been invited, or who have claimed or submitted a profile. One login per profile owner.</p><form class=\"card stack-s\" data-form=\"signup2\" style=\"max-width:32rem\">" + W.field("Your name", "name", { req: true }) + W.field("Email", "email", { type: "email", req: true }) + W.field("Password", "password", { type: "password", req: true, hint: "At least 12 characters." }) + W.field("I am 18 or over (people under 18 cannot hold a profile).", "adult", { type: "checkbox", req: true }) + W.field("I accept the <a href=\"#/terms\">Terms</a> and have read the <a href=\"#/privacy\">Privacy policy</a>.", "terms", { type: "checkbox", req: true }) + W.bot() + "<button class=\"btn\">Create account</button></form>");
  });
  W.forms.signup2 = function (d) { W.mail(d.email, "Verify your email", "Click to verify your email and finish creating your account.", "#/verify/demo"); W.save(); W.go("#/signup/sent?email=" + encodeURIComponent(d.email)); };
  W.route("/signup/sent", "O01", function (p, q) { return page('<div class="okbox"><h1>Check your email</h1><p>We sent a verification link to <strong>' + e(q.email || "") + '</strong>. The link expires; you can request another.</p></div><p><button class="btn" data-act="outbox">Open email outbox</button> ' + W.btn("Resend link", "resend", "", "alt") + "</p>"); });
  W.act.resend = function () { W.toast("Verification link sent again (sample)."); };
  W.route("/verify/:token", "O02", function () { return page('<div class="okbox"><h1>Email verified</h1><p>Your account is ready.</p></div><p><a class="btn" href="#/login">Sign in</a></p><p class="small muted">Edge case: an expired or used link shows an error with a “Send a new link” button.</p>'); });
  W.route("/expired", "O24", function () { return page('<div class="errbox"><h1>Link expired or already used</h1><p>For your security, this link no longer works.</p></div>' + W.btn("Send a new link", "resend") + " <a class=\"btn alt\" href=\"#/login\">Sign in</a>"); });
  W.route("/forgot", "O04", function () { return page('<h1>Reset your password</h1><form class="card stack-s" data-form="forgot" style="max-width:28rem">' + W.field("Email", "email", { type: "email", req: true }) + '<button class="btn">Send reset link</button></form>'); });
  W.forms.forgot = function (d) { W.mail(d.email, "Reset your password", "Click to choose a new password.", "#/reset/demo"); W.save(); W.go("#/forgot/sent"); };
  W.route("/forgot/sent", "O04", function () { return page('<div class="okbox"><h1>Check your email</h1><p>If an account exists for that address, we sent a reset link. (We say the same either way.)</p></div><button class="btn" data-act="outbox">Open email outbox</button>'); });
  W.route("/reset/:token", "O04", function () { return page('<h1>Choose a new password</h1><form class="card stack-s" data-form="reset" style="max-width:28rem">' + W.field("New password", "password", { type: "password", req: true, hint: "At least 12 characters." }) + '<button class="btn">Save password</button></form>'); });
  W.forms.reset = function () { W.toast("Password changed. All other sessions were signed out."); W.go("#/login"); };

  /* ---------- email landing: invitation and consent ---------- */
  W.route("/invite/:token", "O23", function () {
    return page('<h1>You are invited to create your profile</h1><p>Your arrangement with WHOISWHO has been agreed offline. This link starts your profile. No payment is taken on this site.</p><div class="infobox">Your profile is reviewed by a person before it is public. You can edit it any time; some changes are reviewed again.</div>' + W.btn("Start my profile", "startInvite") + ' <a class="btn alt" href="#/login">I already have an account</a>');
  });
  W.act.startInvite = function () {
    var id = "p-new", p = W.profile(id);
    if (!p) { W.S.profiles.push({ id: id, slug: "sample-organisation-b", type: "business", name: "", title: "", industry: "", city: "", status: "draft", trust: "claimed", sponsored: null, source: "subscriber", aiDrafted: false, views: 0, enquiries: true, lastReviewed: "", bio: "", achievements: "", website: "", facts: [], leadership: [], products: "", public: { website: true, email: false, phone: false }, history: [] }); }
    W.S.ownerId = id; W.S.role = "owner"; W.save(); W.go("#/account/profile");
  };
  W.route("/consent/:token", "O22", function () {
    return page('<h1>Consent to a complimentary listing</h1><p>WHOISWHO would like to publish a complimentary profile for you. It is not public until you agree and a reviewer approves it.</p>' + W.btn("I consent", "giveConsent") + ' <a class="btn alt bad" href="#/remove/sample-person-a">I do not consent</a>');
  });
  W.act.giveConsent = function () { var a = W.find("arrangements", "arr5"); if (a) a.consent = true; W.log("consent.complimentary", "arr5", "recorded"); W.save(); W.toast("Consent recorded with a timestamp."); W.go("#/"); };

  /* ---------- dashboard ---------- */
  W.route("/account", "O05", function () {
    var p = me(), sp = W.sponsorOf(p), unread = W.S.enquiries.filter(function (x) { return x.profileId === p.id && !x.read; }).length;
    var statusMsg = { draft: "Draft. Not submitted yet.", pending: "In review. A person will check it.", published: "Published.", rejected: "Rejected. See the reason and resubmit.", unpublished: "Unpublished.", suppressed: "Removed." }[p.status];
    return '<h1>Your profile</h1><div class="grid c2"><div class="card stack-s"><h2>' + e(p.name || "Untitled profile") + '</h2><div class="tagrow"><span class="pill ' + (p.status === "published" ? "ok" : p.status === "rejected" ? "bad" : "warn") + '">' + e(p.status) + "</span>" + W.trustPill(p) + W.spPill(sp) + "</div><p>" + e(statusMsg) + "</p>" +
      (p.status === "rejected" ? '<div class="errbox"><strong>Reason from reviewer:</strong> ' + e(p.reason || "Sample reason.") + "</div>" : "") + (p.edit ? '<div class="warnbox">Your edit to ' + Object.keys(p.edit).map(e).join(", ") + " is waiting for review. The live profile shows the old values meanwhile.</div>" : "") +
      '<p>Views: <strong>' + e(p.views) + '</strong> <span class="muted small">(view count only)</span></p><div class="row"><a class="btn" href="#/account/profile">Edit profile</a><a class="btn alt" href="#/account/profile/preview">Preview</a>' + (p.status === "published" ? '<a class="btn alt" href="#/profile/' + e(p.slug) + '">View public page</a>' : "") + '</div></div><div class="card stack-s"><h2>To do</h2><ul>' + (p.status === "draft" || !p.bio ? "<li>Complete your profile and submit it</li>" : "") + (unread ? '<li><a href="#/account/enquiries">' + unread + " new enquir" + (unread === 1 ? "y" : "ies") + "</a></li>" : "") + '<li><a href="#/account/confirm-details">Yearly check: confirm your details are still right</a></li></ul><p class="small muted">Verified and Featured are decided by our team and are not for sale. Your arrangement does not change them.</p></div></div>' +
      '<div class="infobox"><strong>Claim status.</strong> ' + (W.S.claims.filter(function (c) { return c.profileId === p.id; }).length ? W.S.claims.filter(function (c) { return c.profileId === p.id; }).map(function (c) { return e(c.claimant) + ": " + e(c.status); }).join("; ") + ' <a href="#/account/claims">Details</a>' : "No claim in progress.") + "</div>";
  }, OWN);
  W.route("/account/claims", "O15", function () {
    var p = me(), cs = W.S.claims.filter(function (c) { return c.profileId === p.id; });
    return "<h1>Your claim</h1>" + (cs.length ? W.table(["Claimant", "Proof", "Status", "Submitted"], cs.map(function (c) { return [e(c.claimant), e(c.route), W.stPill(c.status), e(c.at)]; })) + (cs.length > 1 ? '<div class="warnbox">More than one claim exists for this profile. Our Editor-in-Chief will contact each party (REQ-124).</div>' : "") : W.empty("No claim", "Claims are made from a public profile page.")) + '<p class="small muted">Uploaded documents are stored privately and deleted after the decision.</p>';
  }, OWN);
  W.route("/account/confirm-details", "O20", function () {
    var p = me();
    return "<h1>Yearly check</h1><p>Please confirm that this is still right. We ask once a year.</p><table class=\"facts\"><tbody><tr><th>Name</th><td>" + e(p.name) + "</td></tr><tr><th>Title</th><td>" + e(p.title) + "</td></tr><tr><th>Organisation</th><td>" + e(p.org || "") + "</td></tr></tbody></table>" + W.btn("Yes, still correct", "confirmYear") + ' <a class="btn alt" href="#/account/profile">Edit details</a>';
  }, OWN);
  W.act.confirmYear = function () { me().lastReviewed = W.TODAY; W.save(); W.toast("Thank you. Confirmed."); W.go("#/account"); };

  /* ---------- editor ---------- */
  var SENS = ["name", "title", "org", "achievements"];
  var SCAN = [[/(\+?\d[\d\s-]{8,}\d)/, "phone number"], [/[\w.+-]+@[\w-]+\.[\w.]+/, "email address"], [/\b(aged?\s*\d{2}|born\s+(in|on)\s+\d)/i, "age or date of birth"], [/\b(health|cancer|religio|pastor|imam|politic|ethnic|arrest|convict|allegation|divorce)\w*/i, "sensitive topic"]];
  W.scan = function (text) { var out = []; SCAN.forEach(function (s) { var m = String(text || "").match(s[0]); if (m) out.push({ kind: s[1], text: m[0] }); }); return out; };
  W.ui.img = "";
  W.route("/account/profile", "O06", function () {
    var p = me(), org = p.type !== "individual", d = p.draftData || {}; function v(k) { return d[k] != null ? d[k] : p[k]; }
    var locked = p.status === "pending";
    return '<h1>Edit your profile</h1><p class="muted">Fields marked <span class="pill warn">review</span> are checked again by a person before the change goes live (REQ-015).</p>' + (locked ? '<div class="warnbox">Your profile is in review. You can keep editing; changes are saved as a new draft.</div>' : "") +
      '<form data-form="profile" class="stack"><fieldset><legend>About</legend>' + W.field("Type", "type", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]], value: v("type") }) + W.field("Name (review)", "name", { req: true, value: v("name") }) + W.field("Title or tagline (review)", "title", { value: v("title") }) + (org ? "" : W.field("Current organisation (review)", "org", { value: v("org") })) + W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "Choose…", value: v("industry"), req: true }) + W.field("City", "city", { type: "select", options: W.LISTS.cities, blank: "Choose…", value: v("city"), req: true }) + W.field("Biography", "bio", { type: "textarea", value: v("bio"), hint: "Neutral and factual. No phone numbers, emails, ages or sensitive topics.", req: true }) + W.field("Achievements and awards (review)", "achievements", { type: "textarea", value: v("achievements") }) + W.field("Website", "website", { value: v("website") }) + (org ? W.field("Founded (year)", "founded", { value: v("founded") }) + W.field("Products and services", "products", { type: "textarea", value: v("products") }) : "") + "</fieldset>" +
      '<fieldset><legend>Image</legend><div class="ph avatar">Image</div><label for="f-img">Upload an image (JPG, PNG, WebP; max 5 MB)</label><input id="f-img" type="file" accept="image/jpeg,image/png,image/webp"><p class="small muted">Wireframe: try each result.</p><div class="row">' + W.btn("Simulate: valid file", "img", "ok", "alt sm") + W.btn("Too large", "img", "big", "alt sm") + W.btn("Wrong type", "img", "type", "alt sm") + W.btn("Failed scan", "img", "scan", "alt sm") + '</div><p role="status" class="' + (W.ui.img.charAt(0) === "✓" ? "okbox" : W.ui.img ? "errbox" : "") + '">' + e(W.ui.img) + "</p>" + W.field("Image licence / attribution (if not yours)", "imgLicence") + "</fieldset>" +
      '<fieldset><legend>Before you submit</legend>' + W.field("I confirm that I hold the right to submit this content, it is accurate, and I grant WHOISWHO a licence to publish it (REQ-126).", "warranty", { type: "checkbox" }) + (org ? "" : W.field("I am 18 or over.", "adult", { type: "checkbox" })) + "</fieldset>" +
      '<div id="scanout" role="alert"></div><div class="row"><button class="btn alt" name="mode" value="draft">Save draft</button><button class="btn" name="mode" value="submit">' + (p.status === "published" ? "Submit changes" : "Submit for review") + "</button></div></form>";
  }, OWN);
  W.act.img = function (k) { W.ui.img = { ok: "✓ Image accepted. It will be re-encoded and resized (sample).", big: "This file is larger than 5 MB. Choose a smaller one.", type: "This file type is not allowed. Use JPG, PNG or WebP.", scan: "This file failed our safety scan and was rejected." }[k]; W.render(); };
  W.forms.profile = function (d, form, ev) {
    var p = me(), mode = ev && ev.submitter ? ev.submitter.value : "submit";
    p.draftData = Object.assign({}, p.draftData || {}, { type: d.type, name: d.name, title: d.title, org: d.org || p.org, industry: d.industry, city: d.city, bio: d.bio, achievements: d.achievements, website: d.website, founded: d.founded, products: d.products });
    if (mode === "draft") { W.save(); W.toast("Draft saved. Nothing is public."); W.render(); return; }
    var hits = W.scan(d.bio + " " + d.achievements + " " + d.title);
    var out = [];
    if (hits.length) out.push("Remove or reword: " + hits.map(function (h) { return h.kind + " (“" + h.text + "”)"; }).join(", ") + ". Free text with these patterns cannot be submitted (REQ-067, 141).");
    if (!d.warranty) out.push("Please confirm the licence and accuracy statement.");
    if (d.type === "individual" && !d.adult) out.push("People under 18 cannot hold a profile. Please confirm you are 18 or over.");
    if (out.length) { var box = document.getElementById("scanout"); if (box) box.innerHTML = '<div class="errbox"><strong>Cannot submit yet</strong><ul>' + out.map(function (o) { return "<li>" + e(o) + "</li>"; }).join("") + "</ul></div>"; return; }
    var dd = p.draftData; p.draftData = null; p.type = dd.type;
    var dupe = W.S.profiles.some(function (x) { return x.id !== p.id && x.name && dd.name && x.name.toLowerCase() === dd.name.toLowerCase(); }); if (dupe) W.toast("A profile with this name already exists. We will check it is not a duplicate (REQ-123).");
    if (p.status === "published") {
      var edit = {}; SENS.forEach(function (k) { if (dd[k] != null && dd[k] !== (p[k] || "")) edit[k] = dd[k]; });
      ["industry", "city", "bio", "website", "founded", "products"].forEach(function (k) { if (dd[k] != null && dd[k] !== (p[k] || "")) { p[k] = dd[k]; } });
      if (Object.keys(edit).length) { p.edit = Object.assign(p.edit || {}, edit); W.mail("Reviewer queue", "Profile edit to review: " + p.name, "Sensitive fields changed: " + Object.keys(edit).join(", "), "#/admin/review"); }
      p.history.push({ at: W.TODAY, by: p.name, what: Object.keys(edit).length ? "Submitted edit for review (" + Object.keys(edit).join(", ") + ")" : "Edited (went live)" });
      W.save(); W.go("#/account/submitted?kind=" + (Object.keys(edit).length ? "edit" : "live"));
    } else {
      ["name", "title", "org", "industry", "city", "bio", "achievements", "website", "founded", "products"].forEach(function (k) { if (dd[k] != null) p[k] = dd[k]; });
      p.status = "pending"; p.reason = ""; p.drafter = p.name || "Owner"; p.history.push({ at: W.TODAY, by: p.name, what: "Submitted for review" });
      W.mail(d.email || "Owner", "We received your profile", "A reviewer will check it. You will hear from us.", "#/account"); W.mail("Reviewer queue", "New profile to review: " + p.name, "Submitted by owner.", "#/admin/review");
      W.save(); W.go("#/account/submitted?kind=new");
    }
  };
  W.route("/account/submitted", "O09", function (par, q) {
    var t = { new: ["Profile submitted", "A reviewer will check it. It is not public until approved. We will email you."], edit: ["Changes submitted", "Changes to name, title, organisation or achievements are reviewed again. The live profile shows the old values until approved."], live: ["Changes saved", "Your edits went live because they do not need re-review."] }[q.kind] || ["Saved", ""];
    return '<div class="okbox"><h1>' + e(t[0]) + "</h1><p>" + e(t[1]) + '</p></div><p><a class="btn" href="#/account">Back to dashboard</a> <button class="btn alt" data-act="outbox">See the email</button></p>';
  }, OWN);
  W.route("/account/profile/preview", "O08", function () {
    var p = me(), d = Object.assign({}, p, p.edit || {}, p.draftData || {});
    return W.note("Preview exactly as it will be published. Not public. Visitors see nothing until a reviewer approves (REQ-128, 208).") + '<div class="card stack-s"><div class="row" style="flex-wrap:nowrap">' + W.ph("Image", "avatar") + "<div><h1>" + e(d.name || "Untitled") + '</h1><div class="muted">' + e(d.title || "") + (d.org ? " · " + e(d.org) : "") + '</div><div class="tagrow">' + W.trustPill(p) + W.spPill(W.sponsorOf(p)) + "</div></div></div><h2>About</h2><p>" + e(d.bio || "(empty)") + "</p>" + (d.achievements ? "<h2>Achievements</h2><p>" + e(d.achievements) + "</p>" : "") + "</div>";
  }, OWN);

  /* ---------- other owner screens ---------- */
  W.route("/account/enquiries", "O13", function () {
    var p = me(), rows = W.S.enquiries.filter(function (x) { return x.profileId === p.id; });
    rows.forEach(function (x) { x.read = true; });
    return "<h1>Enquiries</h1><p class=\"muted\">Visitors write through a relay form. Their email is shown here only; yours is never shown to them until you reply.</p>" + (p.enquiries ? "" : '<div class="warnbox">Enquiries are switched off. <a href="#/account/visibility">Change</a></div>') + (rows.length ? rows.map(function (x) { return '<div class="card"><strong>' + e(x.name) + '</strong> <span class="muted small">' + e(x.email) + " · " + e(x.at) + "</span><p>" + e(x.message) + "</p>" + W.btn("Reply by email", "replyEnq", x.id, "sm alt") + "</div>"; }).join("") : W.empty("No enquiries yet", "When someone writes to you it appears here and by email."));
  }, OWN);
  W.act.replyEnq = function () { W.toast("Your email app opens with the reply (sample)."); };
  W.route("/account/visibility", "O12", function () {
    var p = me();
    return "<h1>Visibility and contact</h1><p>By default no contact details are public; visitors use the enquiry form (REQ-060).</p><form data-form=\"vis\" class=\"card stack-s\">" + W.field("Show my website on my profile", "website", { type: "checkbox", value: p.public.website }) + W.field("Show my email on my profile", "email", { type: "checkbox", value: p.public.email }) + W.field("Show my phone on my profile", "phone", { type: "checkbox", value: p.public.phone }) + W.field("Allow visitors to send me enquiries", "enq", { type: "checkbox", value: p.enquiries }) + '<button class="btn">Save</button></form>';
  }, OWN);
  W.forms.vis = function (d) { var p = me(); p.public = { website: !!d.website, email: !!d.email, phone: !!d.phone }; p.enquiries = !!d.enq; W.save(); W.toast("Saved. The enquiry button on your profile " + (p.enquiries ? "is on." : "is hidden.")); };
  W.route("/account/history", "O17", function () {
    var p = me();
    return "<h1>Version history</h1>" + W.table(["When", "Who", "What"], (p.history || []).map(function (h) { return [e(h.at), e(h.by), e(h.what)]; }), "No history yet");
  }, OWN);
  W.route("/account/sources", "O18", function () {
    var p = me();
    return "<h1>Sources we used about you</h1>" + W.table(["Field", "Value", "Source", "Retrieved", "Method"], p.facts.map(function (f) { return [e(f.label), e(f.value), e(f.src), e(f.retrieved), e(f.method)]; }), "No third-party sources. Everything on your profile came from you.") + '<h2>Right of reply</h2><form class="card stack-s" data-form="reply">' + W.field("Add a short note to your profile (reviewed first)", "note", { type: "textarea", req: true }) + '<button class="btn">Submit note</button></form>';
  }, OWN);
  W.forms.reply = function (d) { var p = me(); p.history.push({ at: W.TODAY, by: p.name, what: "Right-of-reply note submitted" }); W.S.corrections.unshift({ id: W.uid("cr"), profileId: p.id, kind: "Right of reply", detail: d.note, contact: "owner", at: W.TODAY, due: "2026-11-14", status: "open" }); W.save(); W.toast("Note sent for review."); };
  W.route("/account/settings", "O14", function () {
    return '<h1>Account settings</h1><div class="grid c2"><form class="card stack-s" data-form="pw"><h2>Change password</h2>' + W.field("New password", "pw", { type: "password", req: true }) + '<button class="btn">Change password</button></form><div class="card stack-s"><h2>Sessions</h2>' + W.table(["Device", "Last active", ""], [["This device (sample browser)", "Now", "Current"], ["Sample phone", "2026-11-05", W.btn("Sign out", "revoke", "", "sm alt")]]) + '</div></div><div class="card stack-s"><h2>Deactivate or delete</h2><p>You will see what happens before you confirm.</p>' + W.btn("Deactivate or delete my account", "deleteAcct", "", "bad") + "</div>";
  }, OWN);
  W.forms.pw = function () { W.toast("Password changed (sample)."); };
  W.act.revoke = function () { W.toast("Session signed out (sample)."); };
  W.act.deleteAcct = function () {
    W.openModal("Delete your account?", '<div class="warnbox"><strong>What will happen</strong><ul><li>Your profile is unpublished immediately (visitors get 410 Gone).</li><li>Your login is deleted.</li><li>Records of any arrangement stay with Finance (legal record), but no profile is shown.</li><li>You can ask us to also erase your personal data.</li></ul></div><div class="row">' + W.btn("Yes, delete my account", "deleteYes", "", "bad") + W.btn("Cancel", "closeModal", "", "alt") + "</div>");
  };
  W.act.deleteYes = function () { var p = me(); p.status = "unpublished"; p.removedReason = "Owner deleted account"; W.purge("/profile/" + p.slug, "owner deleted account"); W.log("account.delete", p.id, "owner"); W.S.role = "visitor"; W.ui.modal = null; W.save(); W.go("#/"); W.toast("Account deleted. Your profile is no longer public."); };
})();
