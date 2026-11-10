import { defineConfig } from "vitepress";
import fs from "node:fs";
import path from "node:path";

// The WHOISWHO platform docs: what it is, the features we are building, and where each one lives in the
// theme, the plugins and the pages. Working records (requirements, decisions, the question log, the briefs,
// the ADRs, the review notes) stay in the repository but are not part of this site.
// docs/open-questions.md is generated from docs/questions.md by scripts/build-open-questions.js.

const root = path.resolve(__dirname, "..");

function title(file: string): string {
  const t = fs.readFileSync(file, "utf8");
  const m = /^# (.+)$/m.exec(t);
  return m ? m[1].trim() : path.basename(file, ".md");
}

// Sidebar items for every page in a folder, index first, then in the given order, then alphabetically.
function items(dir: string, order: string[] = []) {
  const abs = path.join(root, dir);
  if (!fs.existsSync(abs)) return [];
  const files = fs.readdirSync(abs).filter((f) => f.endsWith(".md"));
  const rank = (f: string) => (f === "index.md" ? -1 : order.includes(f.replace(/\.md$/, "")) ? order.indexOf(f.replace(/\.md$/, "")) : 999);
  files.sort((a, b) => rank(a) - rank(b) || a.localeCompare(b));
  return files.map((f) => ({
    text: f === "index.md" ? "Overview" : title(path.join(abs, f)),
    link: "/" + dir + "/" + (f === "index.md" ? "" : f.replace(/\.md$/, "")),
  }));
}

const group = (text: string, dir: string, order: string[] = []) => ({ text, items: items(dir, order) });

const platform = [
  { text: "Platform", items: [{ text: "What WHOISWHO is", link: "/overview" }] },
  group("Concepts", "concepts", ["publication-states", "trust-and-labels", "independence-and-money", "no-accounts-model", "privacy-and-data-rights", "data-from-acquisition"]),
];
const features = [group("Features", "features")];
const pages = [
  group("Public pages", "pages/public", ["home", "directory-and-profiles", "search", "stories", "events", "awards", "get-listed-and-contact", "trust-legal-and-data-rights"]),
  group("Admin screens", "pages/admin", ["roles-and-capabilities", "review-and-publishing", "profiles-and-claims", "support-and-requests", "commercial-and-sponsors", "awards-admin", "curation-and-content", "events", "audit-and-compliance"]),
];
const theme = [group("Theme", "theme", ["templates", "blocks-and-patterns", "theme-settings", "seo-and-sharing", "brand-and-tokens", "components", "motion-and-performance", "responsive-and-accessibility", "theme-acceptance", "prototype-guide"])];
const plugins = [group("Plugins", "plugins", ["directory", "review", "import", "trust", "commerce", "content", "awards", "compliance", "platform", "guard", "third-party"])];
const engineering = [
  group("Engineering", "build", ["stack", "data-model", "roles-in-code", "security", "testing-and-ci", "deployment", "launch-checklist"]),
  { text: "Interfaces", items: [{ text: "Import contract", link: "/contracts/import-format" }] },
];
const started = [{ text: "Platform", items: [{ text: "What WHOISWHO is", link: "/overview" }] }, group("Getting started", "getting-started", ["run-the-prototype", "run-the-checks", "working-in-this-repo", "writing-these-docs", "glossary"])];

export default defineConfig({
  title: "WHOISWHO",
  description: "The WHOISWHO platform: what it is, the features we are building, and where each one lives.",
  lang: "en",
  cleanUrls: true,
  lastUpdated: false,
  ignoreDeadLinks: false,
  markdown: { html: false },
  // Working records stay in the repository but are not published in the docs site.
  srcExclude: [
    "node_modules/**", "brand/**", "archive/**", "brief/**", "adr/**",
    "requirements.md", "decisions.md", "questions.md", "architecture.md", "spec.md", "GUIDE.md",
    "wireframe-inventory.md", "wireframe-findings.md", "wordpress-fit-report.md", "design-inspiration.md",
  ],
  themeConfig: {
    search: { provider: "local" },
    nav: [
      { text: "Platform", link: "/overview", activeMatch: "^/(overview|concepts)" },
      { text: "Features", link: "/features/", activeMatch: "^/features" },
      { text: "Pages", link: "/pages/public/", activeMatch: "^/pages" },
      { text: "Theme", link: "/theme/", activeMatch: "^/theme" },
      { text: "Plugins", link: "/plugins/", activeMatch: "^/plugins" },
      { text: "Engineering", link: "/build/", activeMatch: "^/(build|contracts)" },
      { text: "Open questions", link: "/open-questions" },
      { text: "Getting started", link: "/getting-started/", activeMatch: "^/getting-started" },
    ],
    sidebar: {
      "/overview": platform,
      "/concepts/": platform,
      "/features/": features,
      "/pages/": pages,
      "/theme/": theme,
      "/plugins/": plugins,
      "/build/": engineering,
      "/contracts/": engineering,
      "/getting-started/": started,
    },
    outline: [2, 3],
    footer: { message: "Describes the platform as specified. Working records live in the repository." },
  },
});
