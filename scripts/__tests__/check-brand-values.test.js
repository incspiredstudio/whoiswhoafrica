// Self tests for scripts/check-brand-values.js (REQ-052, REQ-190, REQ-273). Run: node --test scripts/__tests__
const test = require("node:test");
const assert = require("node:assert");
const fs = require("fs");
const os = require("os");
const path = require("path");
const { brandColours, brandFonts, brandStrings, checkSite, stripComments } = require("../check-brand-values.js");

const TOKENS = `:root { --orange: #ffa600; /* guide */ --charcoal-900: #2f2f2f; --c-white: #fff; --short: #f0a; }
@font-face { font-family: "Repline"; src: url("../fonts/repline.woff2"); }
@font-face { font-family: "Montreal"; src: url("../fonts/m.woff2"); }`;
const BRAND = `<?php
return array(
	'name'    => 'WHOISWHO',
	'tagline' => 'Influence. Achievement. Legacy.',
	'domain'  => 'wiwi.africa',
);`;

/** Build a fake site folder and return its path; extra maps relative path to file text. */
function makeSite(extra = {}) {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), "brand-"));
  const files = {
    "wp-content/themes/whoiswho-child/assets/css/tokens.css": TOKENS,
    "wp-content/themes/whoiswho-child/config/brand.php": BRAND,
    "wp-content/themes/whoiswho/style.css": "/* Theme Name: WHOISWHO */\nbody { color: #222; }",
    "wp-content/themes/whoiswho/templates/page.php": "<?php echo esc_html( $title ); ?>",
    ...extra,
  };
  for (const [rel, text] of Object.entries(files)) {
    const file = path.join(dir, rel);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    fs.writeFileSync(file, text);
  }
  return dir;
}

test("the values are read from the tokens file and the brand configuration", () => {
  assert.deepStrictEqual(brandColours(TOKENS).sort(), ["#2f2f2f", "#ffa600", "#ff00aa"].sort());
  assert.deepStrictEqual(brandFonts(TOKENS), ["Repline", "Montreal"]);
  assert.ok(brandStrings(BRAND).includes("WHOISWHO"));
  assert.ok(brandStrings(BRAND).includes("wiwi.africa"));
});

test("a clean site passes, and comments naming the product are ignored", () => {
  const dir = makeSite({ "wp-content/plugins/whoiswho-core/src/A.php": "<?php\n/** The WHOISWHO plugin. */\n// WHOISWHO again\nreturn 1;" });
  assert.deepStrictEqual(checkSite(dir), []);
});

test("a hard-coded #ffa600 in a template fails", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/templates/hero.php": '<div style="color:#FFA600">x</div>' });
  const problems = checkSite(dir);
  assert.ok(problems.some((p) => /hero\.php.*#ffa600/.test(p)), problems.join("\n"));
});

test("the same colour in the child tokens file passes", () => {
  assert.deepStrictEqual(checkSite(makeSite()), []);
});

test("a short hex form of a brand colour fails", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/assets/a.css": ".a { color: #f0a; }" });
  assert.ok(checkSite(dir).some((p) => /a\.css/.test(p)));
});

test("a longer hex that only starts like a brand colour passes", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/assets/a.css": ".a { color: #ffa60033; }" });
  assert.deepStrictEqual(checkSite(dir), []);
});

test("a brand name literal outside the brand configuration fails", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/templates/footer.php": "<?php echo 'WHOISWHO'; ?>" });
  assert.ok(checkSite(dir).some((p) => /footer\.php.*WHOISWHO/.test(p)));
  const tagline = makeSite({ "wp-content/themes/whoiswho/templates/tag.php": "<p>Influence. Achievement. Legacy.</p>" });
  assert.ok(checkSite(tagline).some((p) => /tag\.php.*Legacy/.test(p)));
});

test("a brand font name in a template or the parent theme fails", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/assets/b.css": 'h1 { font-family: "Montreal", sans-serif; }' });
  assert.ok(checkSite(dir).some((p) => /b\.css.*Montreal/.test(p)));
});

test("a change to brand values in the parent theme fails", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho/assets/css/tokens.base.css": ":root { --orange: #ffa600; --font: \"Repline\"; }" });
  const problems = checkSite(dir);
  assert.ok(problems.some((p) => /tokens\.base\.css.*#ffa600/.test(p)), problems.join("\n"));
});

test("the plugin code is scanned too", () => {
  const dir = makeSite({ "wp-content/plugins/whoiswho-core/src/Mail.php": "<?php return 'Sent by WHOISWHO';" });
  assert.ok(checkSite(dir).some((p) => /Mail\.php/.test(p)));
});

test("the generated child theme.json and the fonts folder are allowed", () => {
  const dir = makeSite({ "wp-content/themes/whoiswho-child/theme.json": '{"color":"#ffa600","fontFamily":"Montreal"}' });
  assert.deepStrictEqual(checkSite(dir), []);
});

test("a site without the tokens file fails loudly instead of passing", () => {
  const dir = makeSite();
  fs.rmSync(path.join(dir, "wp-content/themes/whoiswho-child/assets/css/tokens.css"));
  assert.ok(checkSite(dir)[0].includes("tokens file is missing"));
});

test("comment stripping keeps strings that contain slashes", () => {
  assert.ok(stripComments("$u = 'https://x.test/a'; // note", ".php").includes("https://x.test/a"));
});
