// Thin asset pipeline: esbuild, content-hashed output in dist/, manifest at dist/manifest.json.
// Entries are declared in build/entries.json; pass --sample to build the bundled sample entry.
import { build } from "esbuild";
import { readFileSync, writeFileSync, mkdirSync, rmSync } from "node:fs";
import { basename, extname } from "node:path";

const sample = process.argv.includes("--sample");
const declared = JSON.parse(readFileSync(new URL("./entries.json", import.meta.url), "utf8")).entries;
const entries = sample ? { sample: "src/sample/sample.js", "sample-style": "src/sample/sample.css" } : declared;

rmSync("dist", { recursive: true, force: true });
mkdirSync("dist", { recursive: true });

const manifest = {};
if (Object.keys(entries).length) {
  const result = await build({
    entryPoints: entries,
    bundle: true,
    minify: true,
    outdir: "dist",
    entryNames: "[name].[hash]",
    metafile: true,
    target: ["es2020"],
    logLevel: "warning",
  });
  for (const [file, meta] of Object.entries(result.metafile.outputs)) {
    if (!meta.entryPoint) continue;
    const name = Object.keys(entries).find((k) => entries[k] === meta.entryPoint);
    const out = basename(file);
    manifest[name + extname(out)] = out;
  }
}
writeFileSync("dist/manifest.json", JSON.stringify(manifest, null, 2) + "\n");
console.log("built " + Object.keys(manifest).length + " file(s)");
