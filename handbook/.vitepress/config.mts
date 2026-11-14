import { defineConfig } from "vitepress";

export default defineConfig({
  title: "WHOISWHO Handbook",
  description: "How to install, run and look after WHOISWHO: getting started, the staff guide, concepts, the theme and operations.",
  lang: "en",
  cleanUrls: true,
  lastUpdated: false,
  markdown: {
    html: false,
    // Raw HTML stays off, but a whole-line HTML comment (for example the review marker
    // <!-- verify-after-round-9 -->) is dropped instead of being shown as text.
    config(md) {
      md.block.ruler.before("paragraph", "line_comment", (state, startLine, _endLine, silent) => {
        const start = state.bMarks[startLine] + state.tShift[startLine];
        const line = state.src.slice(start, state.eMarks[startLine]);
        if (!/^<!--.*-->\s*$/.test(line)) return false;
        if (!silent) state.line = startLine + 1;
        return true;
      });
    },
  },
  themeConfig: {
    search: { provider: "local" },
    outline: [2, 3],
    nav: [
      { text: "Home", link: "/" },
      { text: "Getting started", link: "/start/", activeMatch: "^/start" },
      { text: "Visitor flows", link: "/visitor/", activeMatch: "^/visitor" },
      { text: "Staff guide", link: "/staff/", activeMatch: "^/staff" },
      { text: "Concepts", link: "/concepts/", activeMatch: "^/concepts" },
      { text: "Theme and editing", link: "/theme/", activeMatch: "^/theme" },
      { text: "Operations", link: "/operations/", activeMatch: "^/operations" },
      { text: "Glossary", link: "/glossary" },
    ],
    sidebar: {
      "/start/": [
        {
          text: "Getting started",
          items: [
            { text: "Overview", link: "/start/" },
            { text: "Install the site", link: "/start/install" },
            { text: "The Setup checklist", link: "/start/setup-checklist" },
            { text: "Required plugins", link: "/start/required-plugins" },
            { text: "Set up email", link: "/start/email" },
            { text: "Turnstile spam check", link: "/start/turnstile" },
            { text: "Demo content", link: "/start/demo-content" },
            { text: "Go live", link: "/start/going-live" },
          ],
        },
      ],
      "/visitor/": [
        {
          text: "Visitor flows",
          items: [
            { text: "Overview", link: "/visitor/" },
            { text: "Directory and profiles", link: "/visitor/directory-and-profiles" },
            { text: "Search", link: "/visitor/search" },
            { text: "Stories", link: "/visitor/stories" },
            { text: "Events", link: "/visitor/events" },
            { text: "Get listed", link: "/visitor/get-listed" },
            { text: "Claim a profile", link: "/visitor/claim-a-profile" },
            { text: "Corrections and removal requests", link: "/visitor/corrections-and-removals" },
            { text: "Nominations", link: "/visitor/nominations" },
          ],
        },
      ],
      "/staff/": [
        {
          text: "Staff guide",
          items: [
            { text: "Overview", link: "/staff/" },
            { text: "Add a profile by hand", link: "/staff/add-profile" },
            { text: "Profile review and publication states", link: "/staff/profile-review" },
            { text: "Profile sections", link: "/staff/profile-sections" },
            { text: "Photos and credits", link: "/staff/photos-and-credits" },
            { text: "Import data", link: "/staff/importing" },
            { text: "Claims", link: "/staff/claims" },
            { text: "Corrections", link: "/staff/corrections" },
            { text: "Requests and reports", link: "/staff/requests-and-reports" },
            { text: "Sponsored stories", link: "/staff/sponsored-stories" },
            { text: "Events", link: "/staff/events" },
            { text: "Stories", link: "/staff/stories" },
            { text: "Award categories and nominations", link: "/staff/awards" },
            { text: "Enquiries", link: "/staff/enquiries" },
            { text: "Newsletter", link: "/staff/newsletter" },
            { text: "Audit trail", link: "/staff/audit-trail" },
            { text: "Users, roles and two-factor", link: "/staff/users-and-two-factor" },
          ],
        },
      ],
      "/concepts/": [
        {
          text: "Concepts",
          items: [
            { text: "Overview", link: "/concepts/" },
            { text: "Trust and independence", link: "/concepts/trust-and-independence" },
            { text: "Privacy and data rights", link: "/concepts/privacy-and-data-rights" },
            { text: "The no-accounts model", link: "/concepts/no-accounts" },
            { text: "Roles and what each can do", link: "/concepts/roles" },
          ],
        },
      ],
      "/theme/": [
        {
          text: "Theme and editing",
          items: [
            { text: "Overview", link: "/theme/" },
            { text: "Parent and child theme", link: "/theme/parent-and-child" },
            { text: "Brand tokens", link: "/theme/brand-tokens" },
            { text: "Theme settings", link: "/theme/theme-settings" },
            { text: "Block patterns and page copy", link: "/theme/block-patterns" },
          ],
        },
      ],
      "/operations/": [
        {
          text: "Operations",
          items: [
            { text: "Overview", link: "/operations/" },
            { text: "Launch checklist", link: "/operations/launch-checklist" },
            { text: "Backups", link: "/operations/backups" },
            { text: "Updates", link: "/operations/updates" },
            { text: "Security basics", link: "/operations/security" },
            { text: "Troubleshooting", link: "/operations/troubleshooting" },
            { text: "Who to contact", link: "/operations/contacts" },
            { text: "Runbook outline", link: "/operations/runbook" },
          ],
        },
      ],
    },
    footer: { message: "WHOISWHO product handbook." },
  },
});
