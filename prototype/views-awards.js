/* Awards, nominate, nominee response screens (visitor, nominator, nominee). */
(function () {
  var W = window.W, e = W.esc;
  function page(html) { return '<div class="wrap section stack">' + html + "</div>"; }
  function cats() { var out = []; W.S.awards.groups.forEach(function (g) { g.cats.forEach(function (c) { out.push({ name: c, group: g.name, i: out.length }); }); }); return out; }
  W.cats = cats;
  function flagPill(on, label) { return '<span class="pill ' + (on ? "flag-on" : "flag-off") + '">' + e(label) + ": " + (on ? "ON" : "OFF") + "</span>"; }
  function sponsorStrip() {
    var sp = W.S.sponsors.filter(function (s) { return s.attached.some(function (a) { return /^Awards/.test(a); }); });
    return sp.length ? '<div class="card"><h2>Sponsors</h2><div class="row">' + sp.map(function (s) { return '<span class="card" style="padding:8px 16px"><span class="muted small">[logo]</span> ' + e(s.name) + "</span>"; }).join("") + '</div><p class="small muted">Sponsors do not choose, score or influence nominees or winners (REQ-199).</p></div>' : "";
  }

  W.route("/awards", "V18", function () {
    var A = W.S.awards;
    return page("<h1>" + e(W.brand.edition) + '</h1><div class="row">' + flagPill(W.ok("nominations"), "Nominations") + '</div><p class="small muted">Wireframe: this chip reads the nominations flag, the only feature flag. Staff turn it on in Admin › Feature flag with the CEO’s approval reference. Shortlist and winner pages appear when staff publish them.</p>' +
      '<div class="grid side"><div class="stack"><div class="card"><h2>How recognition works</h2><ol>' + W.LISTS.stages.map(function (s) { return "<li>" + e(s) + "</li>"; }).join("") + '</ol><p class="small muted">Payment, subscription or sponsorship never affects nominations, screening or judging. There is no public voting.</p></div>' +
      '<div class="card"><h2>Eligibility</h2><p>' + e(A.eligibility) + '</p><p><a href="#/awards/rules">Full awards rules</a></p></div></div><div class="stack">' +
      (W.nomOpen() ? '<div class="okbox"><h2>Nominations are open</h2><a class="btn" href="#/nominate">Nominate someone</a></div>' : '<div class="infobox"><h2>Nominations open soon</h2><a class="btn" href="#/nominate">Get notified</a></div>') + sponsorStrip() + "</div></div>" +
      "<h2>Categories <span class=\"pill warn\">proposed; CEO approves the final list</span></h2>" + A.groups.map(function (g) {
        return "<h3>" + e(g.name) + '</h3><div class="grid c3">' + g.cats.map(function (c) { var i = cats().filter(function (x) { return x.name === c; })[0].i; return '<a class="card link" href="#/awards/category/' + i + '"><strong>' + e(c) + "</strong></a>"; }).join("") + "</div>";
      }).join(""));
  });
  W.route("/awards/category/:i", "V19", function (p) {
    var c = cats()[Number(p.i)]; if (!c) return W.notFound.fn();
    var noms = W.S.nominations.filter(function (n) { return n.category === c.name && W.nomPublic(n); });
    var win = W.S.awards.published ? W.S.awards.winners.filter(function (w) { return w.cat === c.name; })[0] : null;
    return page(W.crumbs([["Awards", "#/awards"], [c.name]]) + "<h1>" + e(c.name) + '</h1><p class="muted">' + e(c.group) + "</p><p>Sample category description and criteria summary. Anyone may nominate. Criteria and weights are configured by staff.</p>" +
      (win ? '<div class="okbox"><h2>Winner</h2><a href="#/awards/nominee/' + e(win.ref) + '">' + e(win.name) + "</a></div>" : "") +
      "<h2>Shortlist</h2>" + (noms.length ? '<div class="grid c3">' + noms.map(function (n) { return '<a class="card link" href="#/awards/nominee/' + e(n.ref) + '"><strong>' + e(n.nominee) + '</strong><div class="small muted">Shortlisted</div></a>'; }).join("") + "</div>" : W.empty("No shortlist yet", "Nominee pages appear only at the shortlist stage, after screening and nominee consent (REQ-198).")) +
      "<p>" + (W.nomOpen() ? '<a class="btn" href="#/nominate">Nominate in this category</a>' : '<a class="btn alt" href="#/nominate">Nominations open soon</a>') + "</p>");
  });
  W.route("/awards/nominee/:ref", "V20", function (p, q, m) {
    var n = W.S.nominations.filter(function (x) { return x.ref === p.ref; })[0];
    var vis = n && W.nomPublic(n);
    var staff = ["reviewer", "editor", "eic", "admin"].indexOf(W.S.role) >= 0;
    if (!n || (!vis && !staff)) { m.inv = "V13"; return W.notFound.fn(); }
    var pr = W.profile(n.profileId), prOK = pr && W.isPublic(pr);
    return (vis ? "" : '<div class="banner staffprev"><div class="wrap">Staff preview: not public until staff publish the nominee page (shortlist stage, nominee accepted).</div></div>') + page(W.crumbs([["Awards", "#/awards"], [n.nominee]]) + "<h1>" + e(n.nominee) + '</h1><div class="tagrow"><span class="pill ok">Shortlisted</span><span class="pill gray">' + e(n.category) + '</span></div><p>Built from the reviewed profile and sourced facts only. The nominator’s identity is never shown.</p>' + (prOK ? '<div class="grid c3">' + W.card(pr) + "</div>" : '<p class="muted">Profile not yet public.</p>') + '<p class="small muted">Sponsorship, subscription and payment have no effect on this page or on judging.</p>');
  });
  W.route("/awards/winners", "V21", function () {
    var A = W.S.awards;
    if (!A.published) return page("<h1>Winners</h1>" + W.empty("Winners will be announced", "Staff prepare winners in Admin › Shortlist and winners and publish the announcement with the CEO’s approval. Nothing is shown until then."));
    return page("<h1>Winners</h1><div class=\"grid c3\">" + A.winners.map(function (w) { return '<a class="card link" href="#/awards/nominee/' + e(w.ref) + '"><span class="pill ok">Winner</span><h3>' + e(w.name) + '</h3><div class="small muted">' + e(w.cat) + "</div></a>"; }).join("") + "</div>");
  });
  W.route("/awards/rules", "V33", function () {
    return page("<h1>Awards rules</h1><p class=\"preview-note\">Placeholder. Governance and conflict-of-interest policy come from the Venture CEO (N8).</p><h2>Eligibility</h2><p>" + e(W.S.awards.eligibility) + "</p><h2>Nominations</h2><p>Anyone may nominate. The nominee is contacted only after eligibility screening and may accept, decline or correct. Declining removes them and they are never listed.</p><h2>Judging</h2><p>Independent judges score against published criteria. Judges declare conflicts and never see payment or sponsorship data.</p><h2>No public voting</h2><p>Outcomes are decided by judges, not by votes or payments.</p>");
  });

  /* ---------- nominate ---------- */
  W.ui.nom = { step: 0, d: {} };
  var NSTEPS = ["Your details", "Verify email", "Nominee", "Category and evidence", "Conflict declaration", "Review and submit"];
  W.route("/nominate", "V22", function (p, q, m) {
    if (!W.nomOpen()) {
      var done = q.signed === "1";
      return page("<h1>Nominate</h1><div class=\"infobox\"><h2>Nominations open soon</h2><p>The Maiden Edition rules and eligibility page come first. Leave your email and we will tell you when nominations open.</p></div>" +
        (done ? '<div class="okbox" role="status">Thank you. Check your email to confirm. Consent recorded with policy version 0.1.</div>' : '<form class="card stack-s" data-form="signup">' + W.field("Email", "email", { type: "email", req: true }) + W.field("I agree to receive one email when nominations open. I can unsubscribe at any time. <a href=\"#/privacy\">Privacy notice</a>", "consent", { type: "checkbox", req: true }) + W.bot() + "<button class=\"btn\">Notify me</button></form>") +
        '<p><a href="#/awards">Read the awards overview</a></p>');
    }
    m.inv = "N01";
    var n = W.ui.nom, d = n.d, body = "";
    if (n.step === 0) body = '<form data-form="nom0" class="stack-s">' + W.field("Your name", "name", { req: true, value: d.name }) + W.field("Your email", "email", { type: "email", req: true, value: d.email, hint: "We verify it. It is never public." }) + W.field("Your relationship to the nominee", "rel", { type: "select", options: ["Colleague", "Customer or partner", "Member of the public", "Other"], value: d.rel, req: true }) + W.field("I have read the <a href=\"#/privacy\">privacy notice</a>.", "ok", { type: "checkbox", req: true }) + W.bot() + "<button class=\"btn\">Send verification link</button></form>";
    else if (n.step === 1) body = '<div class="stack-s"><h2>Check your email</h2><p>We sent a single-use link to <strong>' + e(d.email) + '</strong>. Open it to verify your email, then you continue (REQ-196, 233). No account is created. <span class="muted small">(Wireframe: open the outbox and click the link.)</span></p><div class="row"><button type="button" class="btn alt" data-act="nomBack">Back</button><button type="button" class="btn" data-act="outbox">Open email outbox</button></div></div>';
    else if (n.step === 2) body = '<form data-form="nom2" class="stack-s">' + (n.dup ? '<div class="warnbox" role="alert"><strong>This nominee may already be nominated.</strong> You can still continue; staff merge duplicates. We do not show who nominated them.</div>' : "") + W.field("Nominee name", "nominee", { req: true, value: d.nominee }) + W.field("Nominee is a", "nomineeType", { type: "select", options: [["individual", "Person"], ["business", "Business"], ["institution", "Institution"]], value: d.nomineeType, req: true }) + W.field("Nominee email (optional)", "nomineeEmail", { type: "email", value: d.nomineeEmail, hint: "Used only after screening, to invite them to respond." }) + '<div class="row"><button type="button" class="btn alt" data-act="nomBack">Back</button><button class="btn">' + (n.dup ? "Continue anyway" : "Continue") + "</button></div></form>";
    else if (n.step === 3) body = '<form data-form="nom3" class="stack-s">' + W.field("Category", "category", { type: "select", options: cats().map(function (c) { return [c.name, c.name + " (" + c.group + ")"]; }), value: d.category, req: true }) + W.field("Why should they be recognised? Evidence", "evidence", { type: "textarea", req: true, value: d.evidence, hint: "Professional facts only. Do not include phone numbers, emails, ages, health, religion, politics or allegations." }) + (n.err ? '<p class="err" role="alert">' + e(n.err) + "</p>" : "") + W.field("Link to evidence (optional)", "link", { value: d.link }) + '<div class="row"><button type="button" class="btn alt" data-act="nomBack">Back</button><button class="btn">Continue</button></div></form>';
    else if (n.step === 4) body = '<form data-form="nom4" class="stack-s"><fieldset><legend>Conflict of interest</legend><label class="check"><input type="radio" name="coi" value="none" required' + (d.coi === "none" ? " checked" : "") + '> I have no conflict of interest with the nominee</label><label class="check"><input type="radio" name="coi" value="yes"' + (d.coi === "yes" ? " checked" : "") + '> I have a relationship I should declare</label></fieldset>' + W.field("If yes, describe", "coiText", { type: "textarea", value: d.coiText }) + '<div class="row"><button type="button" class="btn alt" data-act="nomBack">Back</button><button class="btn">Continue</button></div></form>';
    else body = '<form data-form="nom5" class="stack-s"><h2>Check your nomination</h2><table class="facts"><tbody>' + [["Nominator", d.name + " (" + d.email + ", verified)"], ["Nominee", d.nominee + " (" + d.nomineeType + ")"], ["Category", d.category], ["Evidence", d.evidence], ["Conflict", d.coi === "yes" ? d.coiText : "None declared"]].map(function (r) { return "<tr><th scope=\"row\">" + e(r[0]) + "</th><td>" + e(r[1]) + "</td></tr>"; }).join("") + '</tbody></table><div class="infobox">Your nomination stays private until eligibility screening is complete. Nominees can then accept, decline or correct.</div>' + W.bot() + '<div class="row"><button type="button" class="btn alt" data-act="nomBack">Back</button><button class="btn">Submit nomination</button></div></form>';
    var rl = W.S.rate.nominate >= 3 ? '<div class="errbox" role="alert"><strong>Too many nominations from this device.</strong> Please try again later (rate limit, REQ-196).</div>' : "";
    return page("<h1>Nominate someone</h1>" + W.steps(NSTEPS, n.step) + (rl || body));
  });
  W.act.nomBack = function () { var n = W.ui.nom; n.err = ""; n.dup = false; n.step = Math.max(0, n.step - 1); if (n.step === 1) n.step = 0; W.render(); };
  W.forms.signup = function (d) { W.S.signups.push({ email: d.email, at: W.TODAY, policy: "0.1" }); W.mail(d.email, "Confirm: tell me when nominations open", "Click to confirm. You can unsubscribe at any time.", "#/unsubscribe"); W.save(); W.go("#/nominate?signed=1"); };
  W.forms.nom0 = function (d) { var n = W.ui.nom; n.d = Object.assign(n.d, d); n.step = 1; n.err = ""; W.mail(d.email, "Verify your email to continue your nomination", "Single-use link. It works once and expires.", "#/nominate/verify"); W.save(); W.render(); };
  W.route("/nominate/verify", "N12", function () {
    var n = W.ui.nom;
    if (n.step === 1) { n.d.verified = true; n.step = 2; n.err = ""; W.log("nominator.verify", n.d.email || "nominator", "email verified by signed link"); W.save(); return { redirect: "#/nominate" }; }
    if (n.d && n.d.verified) return { redirect: "#/nominate" };
    return W.expiredPage();
  });
  W.forms.nom2 = function (d) {
    var n = W.ui.nom, name = d.nominee.trim().toLowerCase(); n.d = Object.assign(n.d, d);
    var dup = W.S.nominations.some(function (x) { return x.nominee.toLowerCase() === name; }) || W.S.profiles.some(function (p) { return p.name.toLowerCase() === name && p.status === "suppressed"; });
    if (dup && !n.dup) { n.dup = true; W.render(); return; }
    n.dup = false; n.step = 3; W.render();
  };
  W.forms.nom3 = function (d) {
    var n = W.ui.nom; n.d = Object.assign(n.d, d);
    if (/(\d{4}[- ]?\d{3}|@|\bage\s*\d|health|religio|politic|allegation)/i.test(d.evidence)) { n.err = "Please remove personal contact details or sensitive information from the evidence (REQ-067, 141)."; W.render(); return; }
    n.err = ""; n.step = 4; W.render();
  };
  W.forms.nom4 = function (d) { var n = W.ui.nom; n.d = Object.assign(n.d, d); n.step = 5; W.render(); };
  W.forms.nom5 = function () {
    var n = W.ui.nom, d = n.d, ref = "NOM-" + ("000" + (W.S.nominations.length + 1)).slice(-4);
    if (W.S.rate.nominate >= 3) { W.render(); return; }
    W.S.rate.nominate++;
    W.S.nominations.push({ id: W.uid("n"), ref: ref, nominator: d.name, nominatorEmail: d.email, verified: true, nominee: d.nominee, nomineeType: d.nomineeType, nomineeEmail: d.nomineeEmail || "", category: d.category, evidence: d.evidence, coi: d.coi === "yes" ? d.coiText : "", status: "submitted", profileId: "", token: "t-" + W.uid("") });
    W.mail(d.email, "Nomination received: " + ref, "Your nomination is private until screening.", "");
    W.log("nomination.submit", ref, "public form"); W.ui.nom = { step: 0, d: {} }; W.save(); W.go("#/nominate/done?ref=" + ref);
  };
  W.route("/nominate/done", "N04", function (p, q) {
    return page('<div class="okbox"><h1>Nomination submitted</h1><p>Reference <strong class="mono">' + e(q.ref || "") + "</strong>. It is private until eligibility screening is complete. After screening, the nominee is emailed to accept, decline or correct their details. Nothing is published without their response and shortlist approval.</p></div><p><a class=\"btn\" href=\"#/awards\">Awards</a> <a class=\"btn alt\" href=\"#/nominate\">Nominate another</a></p>");
  });

  /* ---------- nominee response (email landing) ---------- */
  W.route("/nominee/:token", "N06", function (p, q, m) {
    var n = W.S.nominations.filter(function (x) { return x.token === p.token; })[0];
    if (!n) { m.inv = "N09"; return page("<h1>This link is not valid</h1><p>It may have expired or been used already. Contact us if you think this is a mistake.</p><a class=\"btn\" href=\"#/contact\">Contact</a>"); }
    if (n.status === "declined") { m.inv = "N07"; return page('<div class="okbox"><h1>You declined</h1><p>You are removed from this nomination and will not be listed. We keep your name on a permanent suppression list so we do not add you again.</p></div>'); }
    if (n.status !== "invited") { m.inv = "N09"; return page("<h1>Response recorded</h1><p>Status: <strong>" + e(n.status) + "</strong>. Thank you.</p>"); }
    return page("<h1>You have been nominated</h1><p>Someone nominated <strong>" + e(n.nominee) + "</strong> for <strong>" + e(n.category) + "</strong>. Nothing is public. You decide what happens next.</p><div class=\"grid c3\"><div class=\"card stack-s\"><h2>Accept</h2><p class=\"small\">You may be shortlisted. A public page appears only at shortlist.</p>" + W.btn("Accept", "nomAccept", n.token) + "</div><div class=\"card stack-s\"><h2>Correct details</h2><p class=\"small\">Fix your name, title or organisation, then accept.</p>" + W.btn("Correct and accept", "nomCorrect", n.token, "alt") + "</div><div class=\"card stack-s\"><h2>Decline</h2><p class=\"small\">You are removed and never listed.</p>" + W.btn("Decline", "nomDecline", n.token, "bad") + "</div></div><p class=\"small muted\">Payment or sponsorship has no effect on nominations.</p>");
  });
  W.act.nomAccept = function (t) { var n = W.S.nominations.filter(function (x) { return x.token === t; })[0]; n.status = "accepted"; W.log("nominee.accept", n.ref, ""); W.save(); W.render(); W.toast("Thank you. You accepted the nomination."); };
  W.act.nomDecline = function (t) { W.openModal("Decline this nomination?", '<p>You will be removed and added to the permanent suppression list. This cannot be undone from this page.</p><div class="row"><button class="btn bad" data-act="nomDeclineYes" data-arg="' + e(t) + '">Yes, decline</button><button class="btn alt" data-act="closeModal">Cancel</button></div>'); };
  W.act.nomDeclineYes = function (t) {
    var n = W.S.nominations.filter(function (x) { return x.token === t; })[0]; n.status = "declined";
    W.S.suppression.unshift({ id: W.uid("sp"), name: n.nominee, kind: "Declined nominee", at: W.TODAY, reason: "Nominee declined (" + n.ref + ")" });
    var p = W.profile(n.profileId); if (p) { p.status = "suppressed"; W.purge("/profile/" + p.slug, "nominee declined"); W.purge("/awards/nominee/" + n.ref, "nominee declined"); }
    W.log("nominee.decline", n.ref, "suppressed"); W.ui.modal = null; W.save(); W.render();
  };
  W.act.nomCorrect = function (t) {
    W.openModal("Correct your details", '<form data-form="nomCorrect" class="stack-s"><input type="hidden" name="t" value="' + e(t) + '">' + W.field("Name as it should appear", "name", { req: true }) + W.field("Current title", "title") + W.field("Current organisation", "org") + '<button class="btn">Save and accept</button></form>');
  };
  W.forms.nomCorrect = function (d) {
    var n = W.S.nominations.filter(function (x) { return x.token === d.t; })[0]; n.status = "accepted"; n.corrected = d.name;
    W.S.corrections.unshift({ id: W.uid("cr"), profileId: n.profileId, kind: "Nominee correction", detail: "Name: " + d.name + "; title: " + (d.title || "") + "; org: " + (d.org || ""), contact: n.nomineeEmail, at: W.TODAY, due: "2026-11-14", status: "open" });
    W.log("nominee.correct", n.ref, ""); W.ui.modal = null; W.save(); W.render(); W.toast("Details sent for review. You accepted the nomination.");
  };
})();
