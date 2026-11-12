#!/usr/bin/env node
// Brand-leak check (REQ-052, REQ-190, REQ-273). Brand values live in exactly two places: the child theme's tokens file
// (colours and fonts) and its brand configuration (name, tagline, domain). This check reads those values, then fails when
// any of them appears anywhere else in the themes, templates, CSS, JS or the plugin code. A brand colour or font in the
// parent theme fails too: the parent holds structure and neutral defaults only.
//
//   node scripts/check-brand-values.js            check the repo
//   node --test scripts/__tests__/check-brand-values.test.js   run the self tests
//
// Comments are ignored (a docblock may name the product); only code and strings count.

const fs = require("fs");
const path = require("path");

const root = path.join(__dirname, "..");
const site = path.join(root, "site");

const TOKENS = "wp-content/themes/whoiswho-child/assets/css/tokens.css";
const BRAND_CONFIG = "wp-content/themes/whoiswho-child/config/brand.php";

// Files and folders where brand values are allowed. theme.json of the child is generated from the tokens file.
const ALLOWED = [
  TOKENS,
  BRAND_CONFIG,
  "wp-content/themes/whoiswho-child/assets/fonts/",
  "wp-content/themes/whoiswho-child/theme.json",
  // The plugin's own identity in the staff admin (the plugin name in its notice, the admin menu label). Staff-only, not the public site.
  "wp-content/plugins/whoiswho-core/whoiswho-core.php",
  "wp-content/plugins/whoiswho-core/src/Platform/Admin/ScreenRegistry.php",
];

// Where to look, relative to site/.
const SCAN_ROOTS = ["wp-content/themes", "wp-content/plugins/whoiswho-core", "wp-content/mu-plugins", "src"];
const EXTENSIONS = new Set([".php", ".css", ".js", ".mjs", ".json", ".html", ".svg"]);
const SKIP_DIRS = new Set(["node_modules", "vendor", "dist", ".git", "tests"]);

// Neutral colours every stylesheet needs; not brand values.
const NEUTRAL = new Set(["#ffffff", "#000000"]);

function normaliseHex(hex) {
  let h = hex.toLowerCase();
  if (/^#[0-9a-f]{3}$/.test(h)) h = "#" + h[1] + h[1] + h[2] + h[2] + h[3] + h[3];
  return h;
}

/** Brand colours: every hex literal in the tokens file, except neutral black and white. */
function brandColours(tokensCss) {
  const found = new Set();
  for (const m of tokensCss.replace(/\/\*[\s\S]*?\*\//g, "").matchAll(/#[0-9a-fA-F]{6}\b|#[0-9a-fA-F]{3}\b/g)) {
    const h = normaliseHex(m[0]);
    if (!NEUTRAL.has(h)) found.add(h);
  }
  return [...found];
}

/** Brand fonts: the family names declared in the tokens file. */
function brandFonts(tokensCss) {
  const found = new Set();
  for (const m of tokensCss.matchAll(/font-family:\s*["']([^"']+)["']/g)) found.add(m[1]);
  return [...found];
}

/** Brand strings: the values in the brand configuration (name, long name, edition, legal name, tagline, domain). */
function brandStrings(brandPhp) {
  const found = new Set();
  for (const m of brandPhp.matchAll(/'[a-z_]+'\s*=>\s*'((?:[^'\\]|\\.)*)'/g)) {
    const v = m[1].replace(/\\'/g, "'");
    if (v.length >= 4) found.add(v);
  }
  return [...found];
}

/** Remove comments so a docblock that names the product does not count. */
function stripComments(text, ext) {
  if (ext === ".json" || ext === ".html" || ext === ".svg") return text;
  let out = text.replace(/\/\*[\s\S]*?\*\//g, "");
  if (ext === ".php" || ext === ".js" || ext === ".mjs") out = out.replace(/(^|[^:\\"'])\/\/[^\n]*/g, "$1");
  if (ext === ".php") out = out.replace(/^\s*#[^\n]*/gm, "");
  return out;
}

/** Pure check of one file's text: the problems it has. */
function checkText(rel, text, brand) {
  const ext = path.extname(rel).toLowerCase();
  const code = stripComments(text, ext);
  const problems = [];
  const lower = code.toLowerCase();
  for (const colour of brand.colours) {
    const short = /^#(.)\1(.)\2(.)\3$/.test(colour) ? "#" + colour[1] + colour[3] + colour[5] : null;
    const re = new RegExp(colour.replace("#", "#") + "(?![0-9a-f])", "i");
    if (re.test(lower) || (short && new RegExp(short + "(?![0-9a-f])", "i").test(lower))) {
      problems.push(`${rel}: brand colour ${colour} outside the child tokens file`);
    }
  }
  for (const font of brand.fonts) {
    if (new RegExp("[\"']" + font.replace(/[.*+?^${}()|[\]\\]/g, "\\$&") + "[\"']").test(code)) {
      problems.push(`${rel}: brand font "${font}" outside the child tokens file`);
    }
  }
  for (const s of brand.strings) {
    if (code.includes(s)) problems.push(`${rel}: brand string "${s}" outside the brand configuration`);
  }
  return problems;
}

function isAllowed(rel) {
  return ALLOWED.some((a) => (a.endsWith("/") ? rel.startsWith(a) : rel === a));
}

/** Every file to scan under a source root, as paths relative to it. */
function walk(siteDir, rel = "", acc = []) {
  const dir = path.join(siteDir, rel);
  if (!fs.existsSync(dir)) return acc;
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const child = rel ? rel + "/" + entry.name : entry.name;
    if (entry.isDirectory()) {
      if (!SKIP_DIRS.has(entry.name)) walk(siteDir, child, acc);
    } else if (EXTENSIONS.has(path.extname(entry.name).toLowerCase())) {
      acc.push(child);
    }
  }
  return acc;
}

/** Run the check on a site folder. Returns the list of problems. */
function checkSite(siteDir) {
  const tokensPath = path.join(siteDir, TOKENS);
  const brandPath = path.join(siteDir, BRAND_CONFIG);
  if (!fs.existsSync(tokensPath)) return [`the child tokens file is missing (${TOKENS})`];
  if (!fs.existsSync(brandPath)) return [`the brand configuration is missing (${BRAND_CONFIG})`];
  const tokens = fs.readFileSync(tokensPath, "utf8");
  const brand = {
    colours: brandColours(tokens),
    fonts: brandFonts(tokens),
    strings: brandStrings(fs.readFileSync(brandPath, "utf8")),
  };
  if (brand.colours.length === 0) return ["no brand colours found in the child tokens file; the check would pass for the wrong reason"];
  const problems = [];
  for (const scanRoot of SCAN_ROOTS) {
    for (const rel of walk(siteDir, scanRoot)) {
      if (isAllowed(rel)) continue;
      problems.push(...checkText(rel, fs.readFileSync(path.join(siteDir, rel), "utf8"), brand));
    }
  }
  return problems;
}

module.exports = { brandColours, brandFonts, brandStrings, checkText, checkSite, stripComments };

if (require.main === module) {
  const problems = checkSite(site);
  if (problems.length > 0) {
    console.error("Brand values found outside the child tokens file and the brand configuration:");
    for (const p of problems) console.error("  - " + p);
    console.error("Move the value into site/" + TOKENS + " (colours, fonts) or site/" + BRAND_CONFIG + " (names), and use the token or setting.");
    process.exit(1);
  }
  console.log("Brand values: OK (colours, fonts and names appear only in the child tokens file and the brand configuration).");
}
