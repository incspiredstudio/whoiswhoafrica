// Generates the child theme's theme.json from its brand tokens file, so the block editor and the CSS agree and there is one source.
//   node build/theme-json.mjs           write wp-content/themes/whoiswho-child/theme.json
//   node build/theme-json.mjs --check   fail if the file on disk differs from what the tokens would produce
import { readFileSync, writeFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { dirname, join } from "node:path";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const themeDir = join(root, "wp-content", "themes", "whoiswho-child");
const tokensFile = join(themeDir, "assets", "css", "tokens.css");
const outFile = join(themeDir, "theme.json");

/** Custom properties declared in the :root block, in order. */
export function parseTokens(css) {
  const rootBlock = /:root\s*\{([\s\S]*?)\n\}/.exec(css);
  if (!rootBlock) throw new Error("No :root block in tokens.css");
  const tokens = {};
  for (const m of rootBlock[1].matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]+);/g)) tokens[m[1]] = m[2].trim();
  return tokens;
}

/** Resolve var(--x) references to a literal value. */
export function resolve(value, tokens, depth = 0) {
  if (depth > 10) throw new Error("Token reference loop: " + value);
  return value.replace(/var\((--[a-z0-9-]+)\)/g, (_, name) => {
    if (!(name in tokens)) throw new Error("Unknown token " + name);
    return resolve(tokens[name], tokens, depth + 1);
  });
}

const PALETTE = [
  ["bg", "Background", "--c-bg"],
  ["bg-alt", "Background alternate", "--c-bg-alt"],
  ["surface", "Surface", "--c-surface"],
  ["text", "Text", "--c-text"],
  ["muted", "Muted text", "--c-muted"],
  ["line", "Line", "--c-line"],
  ["link", "Link", "--c-link"],
  ["accent", "Accent", "--c-accent"],
  ["cta", "Call to action", "--c-cta"],
  ["on-cta", "Text on call to action", "--c-on-cta"],
  ["dark", "Dark", "--c-dark"],
  ["on-dark", "Text on dark", "--c-on-dark"],
];

export function build(css) {
  const tokens = parseTokens(css);
  const faces = (family) =>
    [...css.matchAll(/@font-face\s*\{([^}]*)\}/g)]
      .map((m) => m[1])
      .filter((body) => new RegExp("font-family:\\s*[\"']?" + family + "[\"']?\\s*;").test(body))
      .map((body) => ({
        fontFamily: family,
        fontWeight: /font-weight:\s*(\d+)/.exec(body)[1],
        fontStyle: "normal",
        fontDisplay: "swap",
        src: ["file:./assets/fonts/" + /url\(["']?\.\.\/fonts\/([^"')]+)["']?\)/.exec(body)[1]],
      }));
  const family = (slug, name, token, brand) => ({
    slug,
    name,
    fontFamily: resolve(tokens[token], tokens),
    fontFace: faces(brand),
  });
  return {
    $schema: "https://schemas.wp.org/trunk/theme.json",
    version: 3,
    _generated: "Generated from assets/css/tokens.css by `npm run theme-json` in site/. Do not edit by hand.",
    settings: {
      color: { palette: PALETTE.map(([slug, name, token]) => ({ slug, name, color: resolve(tokens[token], tokens) })) },
      typography: {
        fontFamilies: [family("display", "Display", "--font-display", "Repline"), family("body", "Body", "--font-body", "Montreal")],
      },
    },
  };
}

if (process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]) {
  const generated = JSON.stringify(build(readFileSync(tokensFile, "utf8")), null, 2) + "\n";
  if (process.argv.includes("--check")) {
    let current = "";
    try {
      current = readFileSync(outFile, "utf8").replace(/\r\n/g, "\n");
    } catch {
      /* missing */
    }
    if (current !== generated) {
      console.error("whoiswho-child/theme.json is out of date. Run: npm run theme-json");
      process.exit(1);
    }
    console.log("whoiswho-child/theme.json matches its tokens");
  } else {
    writeFileSync(outFile, generated);
    console.log("wrote wp-content/themes/whoiswho-child/theme.json");
  }
}
