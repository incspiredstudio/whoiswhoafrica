/* Account-free pages reached from a single-use signed email link (D-034, REQ-229, 233):
   customer intake, consent for a complimentary listing, the expired-link page, the claim and nominator link landings live in their own files.
   There is no sign up, sign in, password, account area or owner dashboard anywhere on the public site. */
(function () {
  var W = window.W, e = W.esc;
  function page(html) { return '<div class="wrap section stack">' + html + "</div>"; }
  function expiredPage() {
    return page('<div class="errbox"><h1>Link expired or already used</h1><p>For your security, each link works once and expires (default 14 days, Q58). Nothing was changed.</p></div><p>Ask the team to send a new link: <a class="btn" href="#/contact">Contact us</a></p><p class="small muted">Staff can revoke and reissue links (REQ-233).</p>');
  }
  W.expiredPage = expiredPage;
  W.route("/expired", "O24", function () { return expiredPage(); });
  function intake(token) { var id = W.S.intake[token]; return id ? W.profile(id) : null; }

  /* ---------- customer intake by single-use link ---------- */
  W.route("/invite/:token", "O23", function (par) {
    if (W.S.used[par.token]) return expiredPage();
    return page('<h1>You are invited to create your profile</h1><p>Your arrangement with ' + e(W.brand.name) + ' has been agreed offline. This single-use link starts your profile. There is no account to create and no payment is taken on this site.</p><div class="infobox">A reviewer checks your profile before it is public. After that, ask for changes through the correction form on your public page. Staff apply them.</div>' + W.btn("Start my profile", "startInvite", par.token) + '<p class="small muted">Single-use link, no account. The draft is held against this link until you submit or it expires.</p>');
  });
  W.act.startInvite = function (token) {
    var id = "p-new", p = W.profile(id);
    if (!p) { W.S.profiles.push({ id: id, slug: "sample-organisation-b", type: "business", name: "", title: "", industry: "", city: "", status: "draft", trust: "unclaimed", sponsored: null, source: "subscriber", aiDrafted: false, views: 0, enquiries: true, lastReviewed: "", bio: "", achievements: "", website: "", facts: [], leadership: [], products: "", public: { website: true, email: false, phone: false }, history: [] }); }
    W.S.intake[token] = id; W.save(); W.go("#/invite/" + token + "/form");
  };

  /* ---------- consent for a complimentary listing ---------- */
  W.route("/consent/:token", "O22", function (par) {
    if (W.S.used[par.token]) return expiredPage();
    return page('<h1>Consent to a complimentary listing</h1><p>' + e(W.brand.name) + ' would like to publish a complimentary profile for you. It is not public until you agree and a reviewer approves it.</p>' + W.btn("I consent", "giveConsent", par.token) + ' <a class="btn alt bad" href="#/remove/sample-person-a">I do not consent</a><p class="small muted">Single-use link, no account. Your choice is recorded on the arrangement with a timestamp (REQ-175).</p>');
  });
  W.act.giveConsent = function (token) { var a = W.find("arrangements", "arr5"); if (a) a.consent = true; W.S.used[token] = true; W.log("consent.complimentary", "arr5", "recorded via signed link"); W.save(); W.toast("Consent recorded with a timestamp."); W.go("#/"); };

  /* ---------- the profile form (same checks as before; no account) ---------- */
  var SCAN = [[/(\+?\d[\d\s-]{8,}\d)/, "phone number"], [/[\w.+-]+@[\w-]+\.[\w.]+/, "email address"], [/\b(aged?\s*\d{2}|born\s+(in|on)\s+\d)/i, "age or date of birth"], [/\b(health|cancer|religio|pastor|imam|politic|ethnic|arrest|convict|allegation|divorce)\w*/i, "sensitive topic"]];
  W.scan = function (text) { var out = []; SCAN.forEach(function (s) { var m = String(text || "").match(s[0]); if (m) out.push({ kind: s[1], text: m[0] }); }); return out; };
  W.ui.img = "";
  W.route("/invite/:token/form", "O06", function (par) {
    var p = intake(par.token); if (!p) return expiredPage();
    var org = p.type !== "individual", d = p.draftData || {}; function v(k) { return d[k] != null ? d[k] : p[k]; }
    var locked = p.status === "pending";
    return '<h1>Your profile</h1><p class="muted">This page opens from your single-use link. There is no account and no password. The draft is held against the link until you submit. A reviewer checks everything before it is public (REQ-013, 233).</p>' +
      '<form data-form="profile" class="stack"><fieldset><legend>About</legend>' + '<input type="hidden" name="token" value="' + e(par.token) + '">' + W.field("Type", "type", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]], value: v("type") }) + W.field("Name", "name", { req: true, value: v("name") }) + W.field("Title or tagline", "title", { value: v("title") }) + (org ? "" : W.field("Current organisation", "org", { value: v("org") })) + W.field("Industry", "industry", { type: "select", options: W.LISTS.industries, blank: "Choose…", value: v("industry"), req: true }) + W.field("City", "city", { type: "select", options: W.LISTS.cities, blank: "Choose…", value: v("city"), req: true }) + W.field("Biography", "bio", { type: "textarea", value: v("bio"), hint: "Neutral and factual. No phone numbers, emails, ages or sensitive topics.", req: true }) + W.field("Achievements and awards", "achievements", { type: "textarea", value: v("achievements") }) + W.field("Website", "website", { value: v("website") }) + (org ? W.field("Founded (year)", "founded", { value: v("founded") }) + W.field("Products and services", "products", { type: "textarea", value: v("products") }) : "") + "</fieldset>" +
      '<fieldset><legend>Image</legend><div class="ph avatar">Image</div><label for="f-img">Upload an image (JPG, PNG, WebP; max 5 MB)</label><input id="f-img" type="file" accept="image/jpeg,image/png,image/webp"><p class="small muted">Wireframe: try each result.</p><div class="row">' + W.btn("Simulate: valid file", "img", "ok", "alt sm") + W.btn("Too large", "img", "big", "alt sm") + W.btn("Wrong type", "img", "type", "alt sm") + W.btn("Failed scan", "img", "scan", "alt sm") + '</div><p role="status" class="' + (W.ui.img.charAt(0) === "✓" ? "okbox" : W.ui.img ? "errbox" : "") + '">' + e(W.ui.img) + "</p>" + W.field("Image licence / attribution (if not yours)", "imgLicence") + "</fieldset>" +
      '<fieldset><legend>Contact for the reviewer</legend>' + W.field("Your email", "email", { type: "email", req: true, hint: "Used only to tell you the outcome. Never public." }) + '</fieldset>' +
      '<fieldset><legend>Before you submit</legend>' + W.field("I confirm that I hold the right to submit this content, it is accurate, and I grant " + W.brand.name + " a licence to publish it (REQ-126).", "warranty", { type: "checkbox" }) + (org ? "" : W.field("I am 18 or over.", "adult", { type: "checkbox" })) + "</fieldset>" +
      '<div id="scanout" role="alert"></div><div class="row"><button class="btn alt" name="mode" value="draft">Save draft</button><button class="btn" name="mode" value="submit">' + "Submit for review" + "</button></div></form>";
  });
  W.act.img = function (k) { W.ui.img = { ok: "✓ Image accepted. It will be re-encoded and resized (sample).", big: "This file is larger than 5 MB. Choose a smaller one.", type: "This file type is not allowed. Use JPG, PNG or WebP.", scan: "This file failed our safety scan and was rejected." }[k]; W.render(); };

  W.forms.profile = function (d, form, ev) {
    var p = intake(d.token); if (!p) { W.go("#/expired"); return; }
    var mode = ev && ev.submitter ? ev.submitter.value : "submit";
    p.draftData = Object.assign({}, p.draftData || {}, { type: d.type, name: d.name, title: d.title, org: d.org || p.org, industry: d.industry, city: d.city, bio: d.bio, achievements: d.achievements, website: d.website, founded: d.founded, products: d.products });
    if (mode === "draft") { W.save(); W.toast("Draft saved against your link. Nothing is public."); W.render(); return; }
    var hits = W.scan(d.bio + " " + d.achievements + " " + d.title);
    var out = [];
    if (hits.length) out.push("Remove or reword: " + hits.map(function (h) { return h.kind + " (\u201c" + h.text + "\u201d)"; }).join(", ") + ". Free text with these patterns cannot be submitted (REQ-067, 141).");
    if (!d.warranty) out.push("Please confirm the licence and accuracy statement.");
    if (d.type === "individual" && !d.adult) out.push("People under 18 cannot hold a profile. Please confirm you are 18 or over.");
    if (out.length) { var box = document.getElementById("scanout"); if (box) box.innerHTML = '<div class="errbox"><strong>Cannot submit yet</strong><ul>' + out.map(function (o) { return "<li>" + e(o) + "</li>"; }).join("") + "</ul></div>"; return; }
    var dd = p.draftData; p.draftData = null; p.type = dd.type;
    var dupe = W.S.profiles.some(function (x) { return x.id !== p.id && x.name && dd.name && x.name.toLowerCase() === dd.name.toLowerCase(); }); if (dupe) W.toast("A profile with this name already exists. We will check it is not a duplicate (REQ-123).");
    ["name", "title", "org", "industry", "city", "bio", "achievements", "website", "founded", "products"].forEach(function (k) { if (dd[k] != null) p[k] = dd[k]; });
    p.status = "pending"; p.reason = ""; p.drafter = p.name || "Customer"; p.contactEmail = d.email; p.history.push({ at: W.TODAY, by: p.name || "Customer (signed link)", what: "Submitted for review via single-use link" });
    W.S.used[d.token] = true;
    W.mail(d.email, "We received your profile", "A reviewer will check it. You will hear from us.", ""); W.mail("Reviewer queue", "New profile to review: " + p.name, "Submitted by the customer through the intake link.", "#/admin/review");
    W.log("intake.submit", p.id, "signed link"); W.save(); W.go("#/invited/done");
  };
  W.route("/invited/done", "O09", function () {
    return page('<div class="okbox"><h1>Profile submitted</h1><p>A reviewer will check it. It is not public until approved. We will email you. The link you used no longer works (single use).</p></div><p><a class="btn" href="#/">Home</a> <button class="btn alt" data-act="outbox">See the email</button></p>');
  });
})();
