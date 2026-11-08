/* SAMPLE DATA ONLY. Every name, number and date here is a labelled placeholder.
   No real people or companies, no real statistics. */
(function () {
  var W = (window.W = window.W || {});
  W.TODAY = "2026-11-07";
  W.LISTS = {
    industries: ["Agriculture & Agritech", "Financial Services & Fintech", "Technology", "Health & Life Sciences", "Education", "Energy", "Media & Creative", "Legal & Professional Services", "Manufacturing", "Public Sector"],
    cities: ["Lagos", "Abuja", "Port Harcourt", "Ibadan", "Kano", "Enugu", "Accra", "Nairobi"],
    countries: ["Nigeria", "Ghana", "Kenya", "South Africa", "Rwanda", "Egypt", "Pan-African (bodies)"],
    orgTypes: ["Company", "University / school", "Professional association", "Public body", "NGO / social enterprise"],
    professions: ["Executive", "Founder", "Academic", "Legal", "Medical", "Creative", "Public servant"],
    roles: { visitor: "Visitor (anonymous, no account)", reviewer: "Reviewer", editor: "Editor", admin: "Administrator" },
    streams: { subscription: "Subscription", media: "Media / editorial", sponsorship: "Sponsorship" },
    arrTypes: { sponsored_post: "Sponsored post", sponsored_profile: "Sponsored profile", media_package: "Media / editorial package", awards_sponsorship: "Awards sponsorship", other: "Other" },
    defaultStream: { sponsored_post: "media", sponsored_profile: "subscription", media_package: "media", awards_sponsorship: "sponsorship", other: "subscription" },
    stages: ["Category definition", "Nominations", "Evidence collection", "Eligibility screening", "Independent assessment", "Shortlisting", "Final judging and approval", "Winner notification", "Public announcement", "Awards presentation"]
  };

  /* Controlled lists are WordPress custom taxonomies (D-039): terms with name, slug, optional parent. Countries are Nigeria-first and include other African countries and pan-African bodies (amended D-010). */
  W.TAXES = { industries: ["Industries", "Industry", false], cities: ["Cities", "City", true], countries: ["Countries", "Country", false], orgTypes: ["Organisation types", "Organisation type", false], professions: ["Professions", "Profession", false] };
  W.slugify = function (s) { return String(s || "").toLowerCase().replace(/&/g, "and").replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, ""); };
  W.seedTax = function () {
    var T = {}, L = W.LISTS, cityParent = { Accra: "Ghana", Nairobi: "Kenya" };
    Object.keys(W.TAXES).forEach(function (k) { T[k] = L[k].map(function (n) { return { name: n, slug: W.slugify(n), parent: k === "cities" ? (cityParent[n] || "Nigeria") : "" }; }); });
    return T;
  };
  W.syncLists = function () { var T = W.S && W.S.tax; if (!T) return; Object.keys(T).forEach(function (k) { W.LISTS[k] = T[k].map(function (t) { return t.name; }); }); };

  function f(label, value, src, conf, method, date) {
    return { label: label, value: value, src: src || "", url: src ? "https://example.org/sources/" + String(src).toLowerCase().replace(/[^a-z0-9]+/g, "-").replace(/^-|-$/g, "") : "", conf: conf || "high", method: method || "manual", retrieved: date || "2026-10-30" };
  }
  function acq(extra) { return extra; }

  /* Import batches and per-source import errors are written by the acquisition import, not edited on the site (D-038, D-042). */
  W.BATCHES = { "B-2026-11-01": "2026-11-01", "B-2026-11-03": "2026-11-03", "B-2026-11-05": "2026-11-05", "B-2026-11-06": "2026-11-06" };
  W.SRC_ERRORS = { "Sample Registry A": 3, "Sample Exchange Listings": 1, "Sample Regulator PDF": 5, "Wikidata (SPARQL)": 0, "Sample Accreditation List": 2, "Sample Annual Report": 7, "Customer submission": 0 };

  W.seed = function () {
    var S = {
      v: 6, role: "visitor", consent: null, rate: { enquiry: 0, nominate: 0 }, used: {}, intake: {},
      flags: { nominations: { on: false, ref: "" } },
      bulkLog: [],
      thresholds: { subs: 10, revenue: 500000000 },
      profiles: [], articles: [], sponsors: [], customers: [], arrangements: [], leads: [],
      reports: [], corrections: [], removals: [], enquiries: [], nominations: [], signups: [],
      dsr: [], suppression: [], imports: [], emails: [], audit: [], purges: [], notes: {}, scores: {}, coi: {}, escalations: [],
      judgeCoi: { "Sample Judge 2": ["n5"] }, annDraft: null,
      tax: W.seedTax(),
      dupes: [{ id: "dp1", aId: "b1", bName: "Sample Agritech Company", basis: "normalised name + city", kind: "organisation" }, { id: "dp2", aId: "ip2", bName: "Sample Person B.", basis: "name + organisation", kind: "individual" }]
    };
    var P = S.profiles;
    P.push({ id: "p-a", slug: "sample-person-a", type: "individual", name: "Sample Person A", title: "Chief Sample Officer", org: "Sample Agritech Co.", industry: "Agriculture & Agritech", city: "Lagos", profession: "Executive", status: "published", trust: "", sponsored: null, source: "subscriber", aiDrafted: false, views: 42, enquiries: true, lastReviewed: "2026-11-02",
      bio: "Sample biography text written by the profile owner. It describes a sample professional career in a neutral, factual tone.",
      achievements: "Sample achievement one (2024). Sample achievement two (2025).", website: "https://example.org/sample-person-a",
      public: { website: true, email: false, phone: false },
      facts: [f("Current title", "Chief Sample Officer", "Owner-supplied", "high", "manual", "2026-11-02"), f("Organisation", "Sample Agritech Co.", "Owner-supplied", "high", "manual", "2026-11-02"), f("Industry", "Agriculture & Agritech", "Owner-supplied", "high", "manual", "2026-11-02")],
      history: [{ at: "2026-11-02 10:14", by: "Sample Person A", what: "Submitted profile" }, { at: "2026-11-02 15:40", by: "Sample Reviewer 1", what: "Approved and published" }] });
    P.push({ id: "b1", slug: "sample-agritech-co", type: "business", name: "Sample Agritech Co.", title: "Agriculture technology company", industry: "Agriculture & Agritech", city: "Lagos", orgType: "Company", status: "published", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 18, enquiries: true, lastReviewed: "2026-11-01", founded: "2015",
      bio: "Sample Agritech Co. is a sample company used to show how an organisation profile compiled from public sources looks.",
      achievements: "Listed as a sample licensee (2022).", website: "https://example.org/sample-agritech",
      facts: [f("Registered name", "Sample Agritech Co.", "Sample Registry A", "high", "import", "2026-10-28"), f("Registration no.", "RC-SAMPLE-0001", "Sample Registry A", "high", "import", "2026-10-28"), f("Founded", "2015", "Sample Registry A", "high", "import", "2026-10-28"), f("Headquarters", "Lagos", "Official website (sample)", "medium", "manual", "2026-10-29")],
      leadership: ["Sample Person B (Chief Executive)", "Sample Person C (Chief Operating Officer)"], products: "Sample crop-monitoring software; sample advisory services.", history: [] });
    P.push({ id: "b2", slug: "sample-fintech-ltd", type: "business", name: "Sample Fintech Ltd.", title: "Payments technology company", industry: "Financial Services & Fintech", city: "Lagos", orgType: "Company", status: "published", trust: "verified", sponsored: null, source: "subscriber", aiDrafted: false, views: 61, enquiries: true, lastReviewed: "2026-11-03", founded: "2018",
      bio: "Sample Fintech Ltd. is a sample company that supplied its own profile and was verified using the checklist.", achievements: "Sample industry recognition (2025).", website: "https://example.org/sample-fintech",
      facts: [f("Founded", "2018", "Owner-supplied", "high", "manual", "2026-11-03"), f("Headquarters", "Lagos", "Owner-supplied", "high", "manual", "2026-11-03")],
      leadership: ["Sample Person D (Founder)"], products: "Sample payment tools.", history: [] });
    P.push({ id: "b3", slug: "sample-logistics-plc", type: "business", name: "Sample Logistics Plc", title: "Freight and logistics", industry: "Technology", city: "Port Harcourt", orgType: "Company", status: "published", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 9, enquiries: true, lastReviewed: "2026-10-31", founded: "2009",
      bio: "Sample Logistics Plc is a sample listed company profile.", achievements: "", website: "https://example.org/sample-logistics",
      facts: [f("Founded", "2009", "Sample Exchange Listings", "high", "import", "2026-10-27")], leadership: [], products: "Sample freight services.", history: [] });
    P.push({ id: "b4", slug: "sample-health-network", type: "business", name: "Sample Health Network", title: "Healthcare provider group", industry: "Health & Life Sciences", city: "Abuja", orgType: "Company", status: "published", trust: "featured", sponsored: null, source: "acquired", aiDrafted: false, views: 33, enquiries: true, lastReviewed: "2026-11-04", founded: "2011",
      bio: "Sample Health Network is a sample organisation that editors chose to feature.", achievements: "Sample editorial feature (2026).", website: "https://example.org/sample-health",
      facts: [f("Founded", "2011", "Sample Registry A", "high", "import", "2026-10-28")], leadership: [], products: "Sample clinical services.", history: [], featuredReason: "Sample editorial reason: spotlight series." });
    P.push({ id: "b5", slug: "sample-energy-group", type: "business", name: "Sample Energy Group", title: "Energy services", industry: "Energy", city: "Lagos", orgType: "Company", status: "pending", trust: "", sponsored: null, source: "acquired", aiDrafted: true, views: 0, enquiries: true, lastReviewed: "", founded: "2012", submittedAt: "2026-11-02", importedBy: "Sample Importer", drafter: "AI + Sample Importer",
      bio: "Sample Energy Group provides sample energy services. Contact the head office on 0800-000-0000 for details.", achievements: "", website: "https://example.org/sample-energy",
      facts: [f("Founded", "2012", "Sample Regulator PDF", "medium", "AI", "2026-11-05"), f("Headquarters", "Lagos", "Official website (sample)", "high", "AI", "2026-11-05"), f("Reputation claim", "Widely regarded as a leader", "", "low", "AI", "2026-11-05")],
      scan: [{ kind: "Phone pattern", text: "0800-000-0000", cleared: false }], leadership: [], products: "Sample energy services.", history: [],
      aiClaims: [{ claim: "Founded in 2012", passage: "p3", quote: "Sample Energy Group was founded in 2012.", ok: true }, { claim: "Headquartered in Lagos", passage: "p5", quote: "The head office is in Lagos.", ok: true }, { claim: "Widely regarded as a leader", passage: "", quote: "", ok: false }] });
    P.push({ id: "b6", slug: "sample-media-house", type: "business", name: "Sample Media House", title: "Publishing and broadcast", industry: "Media & Creative", city: "Ibadan", orgType: "Company", status: "published", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 5, enquiries: true, lastReviewed: "2025-04-15", founded: "2003",
      bio: "Sample Media House is a sample media organisation.", achievements: "", website: "https://example.org/sample-media",
      facts: [f("Founded", "2003", "Wikidata (SPARQL)", "high", "API", "2026-10-26")], leadership: [], products: "", history: [] });
    P.push({ id: "i1", slug: "sample-university-a", type: "institution", name: "Sample University A", title: "University", industry: "Education", city: "Ibadan", orgType: "University / school", status: "published", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 11, enquiries: true, lastReviewed: "2026-10-31", founded: "1962", mandate: "Sample mandate: teaching, research and public service.",
      bio: "Sample University A is a sample accredited institution.", achievements: "", website: "https://example.org/sample-university",
      facts: [f("Type", "University", "Sample Accreditation List", "high", "import", "2026-10-27"), f("Founded", "1962", "Sample Accreditation List", "high", "import", "2026-10-27")], leadership: [], products: "", longHistory: "Sample long history section for institutions. Dated, sourced and neutral.", history: [] });
    P.push({ id: "i2", slug: "sample-professional-association", type: "institution", name: "Sample Professional Association", title: "Professional association", industry: "Legal & Professional Services", city: "Abuja", orgType: "Professional association", status: "published", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 7, enquiries: true, lastReviewed: "2026-10-30", founded: "1980", mandate: "Sample mandate: set professional standards.",
      bio: "A sample association profile.", achievements: "", website: "https://example.org/sample-association", facts: [], leadership: [], products: "", longHistory: "Sample history.", history: [] });
    P.push({ id: "i3", slug: "sample-foundation", type: "institution", name: "Sample Foundation", title: "NGO / social enterprise", industry: "Public Sector", city: "Kano", orgType: "NGO / social enterprise", status: "pending", trust: "", sponsored: null, source: "acquired", aiDrafted: false, views: 0, enquiries: true, lastReviewed: "", founded: "2001", importedBy: "Sample Reviewer 1", drafter: "Sample Reviewer 1", submittedAt: "2026-11-04", mandate: "Sample mandate.",
      bio: "Sample Foundation works on sample community projects.", achievements: "", website: "https://example.org/sample-foundation", facts: [f("Founded", "2001", "Sample Registry A", "high", "import", "2026-11-04")], leadership: [], products: "", longHistory: "", history: [], scan: [] });
    var ind = [
      ["ip2", "sample-person-b", "Sample Person B", "Chief Executive", "Sample Agritech Co.", "Agriculture & Agritech", "Lagos", "Executive", "published", ""],
      ["ip3", "sample-person-c", "Sample Person C", "Chief Operating Officer", "Sample Agritech Co.", "Agriculture & Agritech", "Lagos", "Executive", "published", ""],
      ["ip4", "sample-person-d", "Sample Person D", "Founder", "Sample Fintech Ltd.", "Financial Services & Fintech", "Lagos", "Founder", "published", "verified"],
      ["ip5", "sample-person-e", "Sample Person E", "Professor of Sample Studies", "Sample University A", "Education", "Ibadan", "Academic", "published", "featured"],
      ["ip6", "sample-person-f", "Sample Person F", "Director", "Sample Energy Group", "Energy", "Lagos", "Executive", "pending", ""],
      ["ip7", "sample-person-g", "Sample Person G", "Board member", "Sample Media House", "Media & Creative", "Ibadan", "Executive", "unpublished", ""],
      ["ip8", "sample-person-h", "Sample Person H", "Legal Counsel", "Sample Professional Association", "Legal & Professional Services", "Abuja", "Legal", "published", ""]
    ];
    ind.forEach(function (r) {
      P.push({ id: r[0], slug: r[1], type: "individual", name: r[2], title: r[3], org: r[4], industry: r[5], city: r[6], profession: r[7], status: r[8], trust: r[9], sponsored: null, source: "acquired", aiDrafted: r[0] === "ip6", views: 3, enquiries: true, lastReviewed: r[8] === "published" ? "2026-11-01" : "",
        bio: r[2] + " is a sample individual. Professional facts only, each with a source.", achievements: r[9] === "featured" ? "Sample published award (2025)." : "", website: "https://example.org/" + r[1],
        facts: [f("Current title", r[3], "Sample Annual Report", "high", "manual", "2026-10-29"), f("Current organisation", r[4], "Sample Annual Report", "high", "manual", "2026-10-29"), f("Public-record role", "Board / office holder (sample)", "Sample Regulator Filing", "medium", "manual", "2026-10-29")],
        importedBy: "Sample Importer", drafter: r[0] === "ip6" ? "AI + Sample Importer" : "Sample Importer", scan: [], history: [], removedReason: r[0] === "ip7" ? "Removal request from the subject, accepted (sample)" : "",
        featuredReason: r[9] === "featured" ? "Sample editorial reason." : "" });
    });
    P.forEach(function (p) { if (!p.history) p.history = []; if (!p.public) p.public = { website: true, email: false, phone: false }; p.origin = p.source === "acquired" ? "import" : "customer"; p.country = "Nigeria"; });

    S.articles = [
      { id: "a1", slug: "sample-interview-one", type: "Interview", title: "Sample interview: building in a sample market", industry: "Agriculture & Agritech", author: "Sample Editor", date: "2026-11-03", status: "published", sponsor: null, profiles: ["b1"], body: "Sample interview body text. Placeholder paragraphs only." },
      { id: "a2", slug: "sample-feature-one", type: "Feature", title: "Sample feature: ten sample ideas", industry: "Technology", author: "Sample Editor", date: "2026-11-02", status: "published", sponsor: null, profiles: ["b3"], body: "Sample feature body text." },
      { id: "a3", slug: "sample-spotlight-sponsored", type: "Spotlight", title: "Sample spotlight on a sample company", industry: "Financial Services & Fintech", author: "Sample Editor", date: "2026-11-04", status: "published", sponsor: "Sample Sponsor X", profiles: ["b2"], body: "Sample sponsored spotlight text. The sponsor did not write or approve verification." },
      { id: "a4", slug: "sample-opinion-one", type: "Opinion", title: "Sample opinion: a sample argument", industry: "Education", author: "Sample Columnist", date: "2026-10-30", status: "published", sponsor: null, profiles: [], body: "Sample opinion text." },
      { id: "a5", slug: "sample-announcement-one", type: "Announcement", title: "Sample announcement: site now live", industry: "Media & Creative", author: "Sample Editor", date: "2026-11-01", status: "published", sponsor: null, profiles: [], body: "Sample announcement." },
      { id: "a6", slug: "sample-news-one", type: "News", title: "Sample news: a sample milestone", industry: "Energy", author: "Sample Editor", date: "2026-11-05", status: "published", sponsor: null, profiles: ["b4"], body: "Sample news text." },
      { id: "a7", slug: "sample-scheduled-article", type: "Feature", title: "Sample scheduled feature", industry: "Health & Life Sciences", author: "Sample Editor", date: "2026-11-20", status: "scheduled", sponsor: null, profiles: [], body: "Scheduled for later; not public yet." }
    ];
    S.sponsors = [{ id: "s1", name: "Sample Sponsor X", link: "https://example.org/sponsor-x", logo: "Logo placeholder", attached: ["Awards: Maiden Edition", "Article: Sample spotlight"] }, { id: "s2", name: "Sample Sponsor Y", link: "https://example.org/sponsor-y", logo: "Logo placeholder", attached: [] }];
    S.customers = [
      { id: "c1", name: "Sample Fintech Ltd.", segment: "Business", contact: "Sample Contact 1", ext: "CUS-0001", profileId: "b2" },
      { id: "c2", name: "Sample Logistics Plc", segment: "Business", contact: "Sample Contact 2", ext: "CUS-0002", profileId: "b3" },
      { id: "c3", name: "Sample Sponsor X", segment: "Sponsor", contact: "Sample Contact 3", ext: "CUS-0003", profileId: "" },
      { id: "c4", name: "Sample Person A", segment: "Individual", contact: "Sample Person A", ext: "CUS-0004", profileId: "p-a" }
    ];
    S.arrangements = [
      { id: "arr1", type: "sponsored_profile", customerId: "c1", linkedProfile: "b2", amount: 20000000, currency: "NGN", method: "Bank transfer", ref: "SAMPLE-REF-001", received: "2026-11-03", confirmedBy: "Sample Admin", onboarding: true, start: "2026-11-01", end: "2027-01-31", complimentary: false, agreed: true, cancelled: false, endedManual: false, stream: "subscription", notes: "Sample arrangement. Sponsored label applies between start and end dates.", consent: false },
      { id: "arr2", type: "sponsored_post", customerId: "c3", linkedProfile: "", linkedArticle: "a3", amount: 5000000, currency: "NGN", method: "", ref: "", received: "", confirmedBy: "", onboarding: false, start: "2026-11-04", end: "2026-12-04", complimentary: false, agreed: true, cancelled: false, endedManual: false, stream: "media", notes: "Awaiting payment.", consent: false },
      { id: "arr3", type: "awards_sponsorship", customerId: "c3", linkedProfile: "", amount: 100000000, currency: "NGN", method: "Bank transfer", ref: "SAMPLE-REF-002", received: "2026-11-05", confirmedBy: "Sample Admin", onboarding: true, start: "2026-11-05", end: "2027-03-31", complimentary: false, agreed: true, cancelled: false, endedManual: false, stream: "sponsorship", notes: "Sample awards sponsorship. Has no effect on nominations or judging.", consent: false },
      { id: "arr4", type: "media_package", customerId: "c2", linkedProfile: "", amount: 8000000, currency: "NGN", method: "", ref: "", received: "", confirmedBy: "", onboarding: false, start: "", end: "", complimentary: false, agreed: false, cancelled: false, endedManual: false, stream: "media", notes: "Proposed.", consent: false },
      { id: "arr5", type: "other", customerId: "c4", linkedProfile: "p-a", amount: 0, currency: "NGN", method: "", ref: "", received: "", confirmedBy: "Sample Admin", onboarding: true, start: "2026-11-01", end: "2027-01-31", complimentary: true, agreed: true, cancelled: false, endedManual: false, stream: "subscription", notes: "Complimentary listing; customer consent recorded.", consent: true }
    ];
    S.leads = [
      { id: "l1", ext: "LEAD-0001", name: "Sample Contact L1", org: "Sample Org L1", interest: "Sponsored profile", contact: "lead1@example.org", status: "new", owner: "", notes: "", at: "2026-11-05" },
      { id: "l2", ext: "LEAD-0002", name: "Sample Contact L2", org: "Sample Org L2", interest: "Awards sponsorship", contact: "lead2@example.org", status: "contacted", owner: "Sample Editor", notes: "Sample call booked.", at: "2026-11-04" },
      { id: "l3", ext: "LEAD-0003", name: "Sample Contact L3", org: "Sample Org L3", interest: "Media / editorial work", contact: "lead3@example.org", status: "qualified", owner: "Sample Editor", notes: "", at: "2026-11-03" }
    ];
    S.reports = [{ id: "r1", profileId: "b6", reason: "Inaccurate information", detail: "Sample report text.", at: "2026-11-05", due: "2026-11-12", status: "open", owner: "" }];
    S.corrections = [{ id: "cr1", profileId: "b1", kind: "Correction", detail: "Sample: headquarters is wrong.", contact: "sample@example.org", emailVerified: true, at: "2026-11-05", due: "2026-11-12", status: "open" },
      { id: "cr2", profileId: "p-a", kind: "Correction", requesterRole: "The person named on this profile", detail: "Change title to: Chief Sample Officer and Director", evidence: "https://example.org/sample-announcement", contact: "sample-person-a@example.org", emailVerified: true, at: "2026-11-06", due: "2026-11-13", status: "open" }];
    S.removals = [{ id: "rm1", profileId: "ip3", kind: "Removal", detail: "Sample removal request from the subject.", contact: "sample@example.org", at: "2026-11-04", due: "2026-11-06", status: "open" }];
    S.enquiries = [{ id: "e1", profileId: "p-a", name: "Sample Visitor 1", email: "visitor1@example.org", message: "Sample enquiry message.", at: "2026-11-05", read: false }];
    S.dsr = [{ id: "d1", kind: "Access", who: "Sample Requester 1", at: "2026-11-03", due: "2026-12-03", status: "open" }, { id: "d2", kind: "Objection", who: "Sample Requester 2", at: "2026-11-01", due: "2026-12-01", status: "in progress" }];
    S.suppression = [{ id: "sp1", name: "Sample Person X", kind: "Removed individual", at: "2026-10-28", reason: "Removal request" }];
    S.imports = [];
    S.nominations = [
      { id: "n1", ref: "NOM-0001", nominator: "Sample Nominator 1", nominatorEmail: "nominator1@example.org", verified: true, nominee: "Sample Fintech Ltd.", nomineeType: "business", nomineeEmail: "nominee1@example.org", category: "Outstanding Fintech Company", evidence: "Sample evidence text.", status: "shortlisted", profileId: "b2", token: "t-n1" },
      { id: "n2", ref: "NOM-0002", nominator: "Sample Nominator 2", nominatorEmail: "nominator2@example.org", verified: true, nominee: "Sample Agritech Co.", nomineeType: "business", nomineeEmail: "", category: "Startup of the Year", evidence: "Sample evidence.", status: "submitted", profileId: "b1", token: "t-n2" },
      { id: "n3", ref: "NOM-0003", nominator: "Sample Nominator 3", nominatorEmail: "nominator3@example.org", verified: true, nominee: "Sample Person D", nomineeType: "individual", nomineeEmail: "nominee3@example.org", category: "Entrepreneur of the Year", evidence: "Sample evidence.", status: "shortlisted", profileId: "ip4", token: "t-n3" },
      { id: "n4", ref: "NOM-0004", nominator: "Sample Nominator 4", nominatorEmail: "nominator4@example.org", verified: true, nominee: "Sample Person E", nomineeType: "individual", nomineeEmail: "nominee4@example.org", category: "Outstanding Educator", evidence: "Sample evidence.", status: "assessment", profileId: "ip5", token: "t-n4" },
      { id: "n5", ref: "NOM-0005", nominator: "Sample Nominator 5", nominatorEmail: "nominator5@example.org", verified: true, nominee: "Sample Health Network", nomineeType: "business", nomineeEmail: "nominee5@example.org", category: "Outstanding Healthcare Institution", evidence: "Sample evidence.", status: "assessment", profileId: "b4", token: "t-n5" },
      { id: "n6", ref: "NOM-0006", nominator: "Sample Nominator 6", nominatorEmail: "nominator6@example.org", verified: true, nominee: "Sample Person B", nomineeType: "individual", nomineeEmail: "nominee6@example.org", category: "Outstanding Business Leader", evidence: "Sample evidence.", status: "invited", profileId: "ip2", token: "t-n6" }
    ];
    S.awards = {
      shortlistSize: 5, judgesPerCategory: 3,
      groups: [
        { name: "Leadership & Public Impact", cats: ["Outstanding Public Sector Leader", "Outstanding Business Leader", "Emerging Leader of the Year", "Outstanding Woman Leader", "Outstanding Community Impact"] },
        { name: "Entrepreneurship & Business", cats: ["Entrepreneur of the Year", "Startup of the Year", "SME of the Year", "Outstanding Business Innovation", "Company of the Year"] },
        { name: "Technology & Innovation", cats: ["Technology Innovator of the Year", "Outstanding Fintech Company", "Digital Transformation Leader", "AI Innovation of the Year", "Outstanding Technology Entrepreneur"] },
        { name: "Professional Excellence", cats: ["Outstanding Legal Professional", "Outstanding Healthcare Professional", "Outstanding Educator", "Outstanding Financial Professional", "Outstanding Creative Professional"] },
        { name: "Industry & Institutional Recognition", cats: ["Outstanding Educational Institution", "Outstanding Healthcare Institution", "Outstanding NGO or Social Enterprise", "Outstanding Professional Association", "Outstanding Corporate Institution"] }
      ],
      criteria: [{ id: "k1", name: "Impact", weight: 30 }, { id: "k2", name: "Achievement", weight: 30 }, { id: "k3", name: "Credibility and integrity", weight: 20 }, { id: "k4", name: "Leadership or innovation", weight: 20 }],
      eligibility: "Sample eligibility text: individuals and organisations in Nigeria first, then other African countries; professional record in the last five years; no sensitive personal data. Final text is for the Venture CEO.",
      winners: [],
      screeners: { screening: "Sample Reviewer 1", shortlist: "Sample Editor", final: "Sample Venture CEO" }
    };
    /* Every profile carries a source name and an import batch (REQ-242). Customer-submitted profiles have neither. */
    var SRC = { b1: ["Sample Registry A", "B-2026-11-01"], b3: ["Sample Exchange Listings", "B-2026-11-01"], b4: ["Sample Registry A", "B-2026-11-01"], b5: ["Sample Regulator PDF", "B-2026-11-03"], b6: ["Wikidata (SPARQL)", "B-2026-11-03"], i1: ["Sample Accreditation List", "B-2026-11-03"], i2: ["Sample Accreditation List", "B-2026-11-03"], i3: ["Sample Registry A", "B-2026-11-03"] };
    S.profiles.forEach(function (q) {
      var x = SRC[q.id];
      if (!x && /^ip/.test(q.id)) x = ["Sample Annual Report", q.id === "ip7" || q.id === "ip8" ? "B-2026-11-06" : "B-2026-11-05"];
      q.srcName = x ? x[0] : "Customer submission"; q.batch = x ? x[1] : "";
    });
    S.judgeAssign = { "Sample Judge 1": ["n4", "n5", "n3"], "Sample Judge 2": ["n4", "n5"], "Sample Judge 3": ["n4", "n3"] };
    S.scores = { n4: { by: { "Sample Judge 1": { v: { k1: 8, k2: 7, k3: 9, k4: 8 }, by: "Sample Editor", at: "2026-11-06" } } } };
    S.users = [
      { name: "Sample Admin", role: "Administrator", mfa: true, last: "2026-11-07" }, { name: "Sample Reviewer 1", role: "Reviewer", mfa: true, last: "2026-11-07" },
      { name: "Sample Reviewer 2", role: "Reviewer", mfa: true, last: "2026-11-06" }, { name: "Sample Editor", role: "Editor", mfa: true, last: "2026-11-06" }
    ];
    S.processors = [
      { name: "Web hosting (cPanel host)", role: "Hosts site and database", location: "TBC (Q26)", dpa: "Not yet" },
      { name: "Email provider", role: "Transactional email", location: "TBC", dpa: "Not yet" },
      { name: "Analytics (privacy-friendly)", role: "Visit counts", location: "TBC", dpa: "Not yet" },
      { name: "AI provider (acquisition pipeline, not the website)", role: "Drafts records before import", location: "TBC; Legal to confirm transfer", dpa: "Not yet" }
    ];
    S.emails = [
      { id: "m1", to: "Sample Person B (nominee)", subject: "You have been nominated", body: "Accept, decline or correct your details. Nothing is public.", link: "#/nominee/t-n6", at: W.TODAY },
      { id: "m2", to: "Sample Customer", subject: "You are invited to create your " + W.brand.name + " profile", body: "Your arrangement is agreed. Click to start.", link: "#/invite/demo", at: W.TODAY },
      { id: "m3", to: "Sample Person A", subject: "Consent to a complimentary listing", body: "Click to give or withhold consent.", link: "#/consent/demo", at: W.TODAY }
    ];
    S.audit = [];
    return S;
  };
})();
