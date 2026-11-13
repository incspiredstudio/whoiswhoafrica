// Concurrency check for the local build (M5-PRF-04; REQ-054): N concurrent anonymous visitors, each walking home, directory,
// a profile, a story and search for a fixed number of rounds. Records response times and the error rate, prints one JSON line,
// and exits non-zero when a budget is broken. No dependencies: Node 18+ fetch only.
//
//   BASE_URL=http://127.0.0.1:8940 node tests/Performance/concurrency.mjs
//   CONCURRENCY=50 ROUNDS=4 P95_MS=3000 MAX_ERROR_RATE=0 node tests/Performance/concurrency.mjs
//   INCLUDE_SEARCH=1 adds the uncached search page; WARM=0 skips the cache warm-up.
//
// It reads pages only (GET), sends no cookies and never writes. The local PHP built-in server has no page cache, so these figures
// are a worst case; repeat on staging in the launch rehearsal (M5-LCH-04).
const base = (process.env.BASE_URL || "http://127.0.0.1:8940").replace(/\/$/, "");
const concurrency = Number(process.env.CONCURRENCY || 50);
const rounds = Number(process.env.ROUNDS || 4);
const p95Budget = Number(process.env.P95_MS || 3000);
const maxErrorRate = Number(process.env.MAX_ERROR_RATE || 0);

async function text(path) {
  const res = await fetch(base + path, { redirect: "follow" });
  return { status: res.status, body: await res.text() };
}

// Find one real profile and one real story from the list pages, so the walk includes the detail templates.
async function discover(listPath, pattern) {
  const { body } = await text(listPath);
  const match = body.match(pattern);
  return match ? match[1] : null;
}

const profile = await discover("/directory/", /href="(?:https?:\/\/[^"/]+)?(\/(?:people|businesses|organisations|profile)\/[a-z0-9-]+\/)"/i);
const story = await discover("/stories/", /href="(?:https?:\/\/[^"/]+)?(\/stories\/[a-z0-9-]+\/)"/i);
// Search has a query string, which the page cache never stores, so it is left out unless INCLUDE_SEARCH=1 (an uncached worst case).
const walk = ["/", "/directory/", profile, story, process.env.INCLUDE_SEARCH ? "/search/?q=a" : null].filter(Boolean);

// Warm the cache once, one request per page, so the run measures the cached site (set WARM=0 to measure cold).
if (process.env.WARM !== "0") {
  for (const path of walk) await text(path);
}

const timings = [];
let errors = 0;
let requests = 0;

async function visitor() {
  for (let round = 0; round < rounds; round++) {
    for (const path of walk) {
      const started = performance.now();
      requests++;
      try {
        const res = await fetch(base + path, { redirect: "follow" });
        await res.arrayBuffer();
        timings.push({ path, ms: performance.now() - started });
        if (res.status >= 400) errors++;
      } catch {
        errors++;
      }
    }
  }
}

const startedAll = performance.now();
await Promise.all(Array.from({ length: concurrency }, visitor));
const seconds = (performance.now() - startedAll) / 1000;

const sorted = timings.map((t) => t.ms).sort((a, b) => a - b);
const pick = (q) => (sorted.length ? Math.round(sorted[Math.min(sorted.length - 1, Math.floor(q * sorted.length))]) : 0);
const perPath = {};
for (const path of walk) {
  const own = timings.filter((t) => t.path === path).map((t) => t.ms).sort((a, b) => a - b);
  perPath[path] = { n: own.length, p50: own.length ? Math.round(own[Math.floor(own.length / 2)]) : 0, p95: own.length ? Math.round(own[Math.min(own.length - 1, Math.floor(own.length * 0.95))]) : 0 };
}
const result = {
  concurrency,
  rounds,
  requests,
  errors,
  errorRate: requests ? errors / requests : 0,
  seconds: Math.round(seconds * 10) / 10,
  requestsPerSecond: Math.round((requests / seconds) * 10) / 10,
  p50: pick(0.5),
  p95: pick(0.95),
  max: Math.round(sorted[sorted.length - 1] || 0),
  perPath,
};
console.log(JSON.stringify(result));
if (result.errorRate > maxErrorRate || result.p95 > p95Budget) {
  console.error(`FAIL: error rate ${result.errorRate} (max ${maxErrorRate}), p95 ${result.p95} ms (budget ${p95Budget} ms)`);
  process.exit(1);
}
