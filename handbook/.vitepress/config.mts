import { defineConfig } from "vitepress";

export default defineConfig({
  title: "WHOISWHO Handbook",
  description: "How WHOISWHO works: visitor flows, the staff guide, concepts, the theme and operations.",
  lang: "en",
  cleanUrls: true,
  lastUpdated: false,
  markdown: { html: false },
  themeConfig: {
    search: { provider: "local" },
    outline: [2, 3],
    nav: [
      { text: "Home", link: "/" },
      { text: "Visitor flows", link: "/visitor/", activeMatch: "^/visitor" },
      { text: "Staff guide", link: "/staff/", activeMatch: "^/staff" },
      { text: "Concepts", link: "/concepts/", activeMatch: "^/concepts" },
      { text: "Theme and editing", link: "/theme/", activeMatch: "^/theme" },
      { text: "Operations", link: "/operations/", activeMatch: "^/operations" },
      { text: "Glossary", link: "/glossary" },
    ],
    sidebar: {
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
            { text: "Profile review and publication states", link: "/staff/profile-review" },
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
            { text: "Runbook outline", link: "/operations/runbook" },
          ],
        },
      ],
    },
    footer: { message: "WHOISWHO product handbook." },
  },
});
