#!/usr/bin/env python3
"""First 1,000 run: notable organisations and entrepreneurs into a local SQLite DB.

Stages (each resumable; run in order):
  seed      Wikidata candidates per country
  enrich    Wikidata details, keeping allowlisted fields only (REQ-139, REQ-140)
  sites     organisations' own sites: robots check, description, logo
  select    apply exclusions and pick the final records
  images    Commons photos and logos, with licence and credit
  finalise  bios, seven checks, provenance, content hash

Data stays in acquisition/data/ (git-ignored). Nothing here publishes anything.
Stdlib only. Run with: python -I acquisition/pipeline/run_first_1000.py <stage>
"""
import concurrent.futures as cf
import hashlib
import html
import html.parser
import ipaddress
import json
import pathlib
import re
import socket
import sqlite3
import sys
import threading
import time
import unicodedata
import urllib.error
import urllib.parse
import urllib.request
import urllib.robotparser

ROOT = pathlib.Path(__file__).resolve().parents[2]
DATA = ROOT / "acquisition" / "data"
DB_PATH = DATA / "staging.sqlite"
IMG = DATA / "images"
UA = "WHOISWHO-bot/1.0 (+https://wiwi.africa/bot) python-urllib"
RUN_ID = "first-1000-2026-11-09"
TARGET_ORGS = int(sys.argv[2]) if len(sys.argv) > 2 else 600
TARGET_PEOPLE = 1000 - TARGET_ORGS
NIGERIA_SHARE = 0.85

COUNTRIES = {"Q1033": "Nigeria", "Q117": "Ghana", "Q114": "Kenya", "Q258": "South Africa"}
ORG_CLASSES = ["Q4830453", "Q783794", "Q22687", "Q6881511", "Q3918", "Q38723", "Q163740",
               "Q157031", "Q327333", "Q31855", "Q2401749"]
EXCLUDED_ORG_CLASSES = {"Q7278", "Q1530022", "Q476028", "Q847017", "Q9826", "Q3914", "Q16917"}
PAN_AFRICAN = ["African Union", "African Development Bank", "African Export-Import Bank",
               "Economic Community of West African States", "Africa Centres for Disease Control and Prevention",
               "African Continental Free Trade Area", "Africa Finance Corporation",
               "East African Community", "Southern African Development Community"]
ENTREPRENEUR_OCCUPATIONS = ["Q131524", "Q43845"]
POLITICIAN = "Q82955"
SENSITIVE = re.compile(
    r"\b(pastor|bishop|imam|evangelist|cleric|archbishop|apostle|prophet|muslim|christian|islam\w*|catholic|"
    r"anglican|pentecostal|ethnic\w*|hiv|aids|cancer|disabled|convict\w*|fraud\w*|arrest\w*|terror\w*|indict\w*|"
    r"money launder\w*|scandal|lgbt\w*|lesbian|gay|queer|transgender|intersex|sexual\w*|sex work\w*|reproductive health|APC|PDP|APGA|NNPP|political party|born (?:on|in) \d|aged? \d+|\d+ years? old)\b",
    re.I)
CONTACT = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+|(?:\+?234|\b0[789][01])[\s-]?\d{3}[\s-]?\d{3,4}[\s-]?\d{0,4}")
EXCLUDED_TYPE_LABEL = re.compile(
    r"\b(diocese|archdiocese|eparchy|church|denomination|parish|mosque|temple|religious|seminary|monastery|"
    r"political party|football|soccer|rugby|cricket|basketball|sports?|team|club|stadium|embassy|consulate|high commission|library|"
    r"wikimedia|wikipedia|film|song|album|television series|local government|olympic|"
    r"secret service|intelligence|municipal|genootschap|ecclesiastical|catholic|apostolic|trappist|nunnery|vicariate|nunciature|hate group|vigilante|militia|terror\w*|political initiative|pressure group|advocacy group|secretariat|executive branch|cabinet|fort|castle|botanical garden|amusement park|estate|dyeworks|faculty|nursing faculty|handball|hockey|golf|tennis|athletics|multi-level marketing|pyramid scheme|ponzi|youth system|lyc.e|campaign|protest|"
    r"(secondary|primary|private|public|girls'|boys'|boarding|high|junior|elementary|grammar|international|nursery) school|preschool)\b|^school$", re.I)
CATEGORY_RULES = [
    ("Education", re.compile(r"universit|polytechnic|college|school|academic|education|institute of technology", re.I)),
    ("Government", re.compile(r"government|ministr|agency|regulat|federal|parliament|legislat|lower house|upper house|central bank|commission|law enforcement|armed|military", re.I)),
    ("NGO or association", re.compile(r"nonprofit|non-profit|non-governmental|foundation|charit|association|union|society|intergovernmental|international organi", re.I)),
    ("Research", re.compile(r"research", re.I)),
]
NON_BUSINESS_OCC = re.compile(
    r"\b(politician|president|vice president|governor|senator|minister|legislator|diplomat|statesperson|party|military|soldier|general|monarch|"
    r"traditional ruler|judge|cleric|actor|actress|singer|rapper|musician|songwriter|record producer|disc jockey|footballer|"
    r"basketball|athlete|player|boxer|sprinter|model|beauty pageant|film|television|comedian|presenter|broadcaster|influencer|"
    r"writer|author|novelist|poet|playwright|artist|painter|sculptor|photographer|journalist|activist|feminist|academic|professor|scientist|physician|nurse)\b", re.I)
BUSINESS_WORDS = re.compile(r"\b(entrepreneur|business\w*|founder|co-founder|ceo|chief executive|banker|investor|financier|executive|industrialist|tycoon|magnate|chairman|chairperson|managing director|fintech|startup)\b", re.I)
NAME_EXCLUDE = re.compile(r"\b(war|ponzi|ecclesiastical|mmm)\b", re.I)
NON_AFRICAN = re.compile(r"\b(American|British|English|Scottish|Welsh|Irish|Canadian|Australian|German|French|Italian|Spanish|Dutch|Belgian|Swiss|Swedish|Norwegian|Danish|Finnish|Russian|Ukrainian|Hungarian|Polish|Indian|Pakistani|Chinese|Japanese|Korean|Israeli|Lebanese|Emirati|Saudi|Turkish|Brazilian|Mexican|Portuguese|Greek|Bahamian|Jamaican)\b")
AFRICAN = re.compile(r"\b(Nigerian|Ghanaian|Kenyan|South African|African)\b")
BIRTH_BITS = re.compile(r"\s*\((?:born|b\.|né|née)?\s*[^()]*\b(?:1[89]|20)\d\d\b[^()]*\)|,?\s*\bborn\b[^,.;]*", re.I)
POLITICS = re.compile(r"\b(political candidate|paramount chief|politician|president of|former president|vice president|governor|senator|minister|legislator|statesperson|member of (the )?(house|parliament|national assembly))\b", re.I)
ALLOWED_IMAGE_MAGIC = [(b"\x89PNG", ".png"), (b"\xff\xd8\xff", ".jpg"), (b"GIF8", ".gif"), (b"\x00\x00\x01\x00", ".ico")]

_host_lock = threading.Lock()
_host_next = {}


def db():
    DATA.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(DB_PATH, timeout=60)
    con.row_factory = sqlite3.Row
    con.execute("PRAGMA journal_mode=WAL")
    con.executescript("""
    CREATE TABLE IF NOT EXISTS sources(source_id TEXT PRIMARY KEY, name TEXT, url TEXT, access TEXT, licence TEXT,
      approved_by TEXT, approved_on TEXT, note TEXT);
    CREATE TABLE IF NOT EXISTS candidates(qid TEXT PRIMARY KEY, kind TEXT, country TEXT, sitelinks INT, exchange INT DEFAULT 0);
    CREATE TABLE IF NOT EXISTS founder_links(person_qid TEXT, org_qid TEXT, PRIMARY KEY(person_qid, org_qid));
    CREATE TABLE IF NOT EXISTS entities(qid TEXT PRIMARY KEY, kind TEXT, data TEXT, retrieved_at TEXT);
    CREATE TABLE IF NOT EXISTS labels(qid TEXT PRIMARY KEY, label TEXT);
    CREATE TABLE IF NOT EXISTS class_sets(name TEXT, qid TEXT, PRIMARY KEY(name, qid));
    CREATE TABLE IF NOT EXISTS sites(qid TEXT PRIMARY KEY, url TEXT, final_url TEXT, status TEXT, http INT,
      description TEXT, logo_url TEXT, logo_kind TEXT, retrieved_at TEXT);
    CREATE TABLE IF NOT EXISTS images(qid TEXT, role TEXT, status TEXT, path TEXT, source_url TEXT, licence TEXT, credit TEXT,
      retrieved_at TEXT, PRIMARY KEY(qid, role));
    CREATE TABLE IF NOT EXISTS records(record_id TEXT PRIMARY KEY, type TEXT, qid TEXT, name TEXT, country TEXT, city TEXT,
      org_type TEXT, org_category TEXT, industry TEXT, website TEXT, website_status TEXT, founded_year TEXT, exchange TEXT, current_title TEXT,
      current_org TEXT, roles TEXT, awards TEXT, sitelinks INT, description TEXT, bio TEXT, site_description TEXT,
      criterion TEXT, criterion_met INT, evidence_url TEXT, wikipedia_lead TEXT,
      photo_path TEXT, photo_source TEXT, photo_licence TEXT, photo_credit TEXT,
      logo_path TEXT, logo_source TEXT, logo_licence TEXT, logo_credit TEXT,
      flags TEXT, checks TEXT, content_hash TEXT, run_id TEXT, retrieved_at TEXT);
    CREATE TABLE IF NOT EXISTS provenance(record_id TEXT, field TEXT, source_id TEXT, source_url TEXT, retrieved_at TEXT,
      confidence TEXT, method TEXT, PRIMARY KEY(record_id, field));
    CREATE TABLE IF NOT EXISTS exclusions(qid TEXT PRIMARY KEY, kind TEXT, reason TEXT);
    CREATE TABLE IF NOT EXISTS fetch_log(at TEXT, url TEXT, status TEXT);
    """)
    return con


def clean_description(desc):
    """REQ-140: birth dates and years never reach the record, even inside free text."""
    return BIRTH_BITS.sub("", desc or "").strip(" ,;") or None


def now():
    return time.strftime("%Y-%m-%dT%H:%M:%SZ", time.gmtime())


def safe_public_url(url):
    try:
        u = urllib.parse.urlparse(url)
        if u.scheme not in ("http", "https") or not u.hostname:
            return False
        for info in socket.getaddrinfo(u.hostname, None):
            if not ipaddress.ip_address(info[4][0]).is_global:
                return False
        return True
    except (OSError, ValueError):
        return False


def wait_host(url, gap):
    host = urllib.parse.urlparse(url).hostname or ""
    while True:
        with _host_lock:
            t = _host_next.get(host, 0)
            n = time.time()
            if n >= t:
                _host_next[host] = n + gap
                return
        time.sleep(min(max(t - n, 0.05), 1))


def http(url, gap=5.0, timeout=25, accept=None, max_bytes=6_000_000, tries=3):
    """Polite GET. Returns (status, final_url, bytes, content_type). Never bypasses a block."""
    for attempt in range(tries):
        wait_host(url, gap)
        req = urllib.request.Request(url, headers={"User-Agent": UA, **({"Accept": accept} if accept else {})})
        try:
            with urllib.request.urlopen(req, timeout=timeout) as r:
                body = r.read(max_bytes + 1)
                if len(body) > max_bytes:
                    return (413, r.geturl(), b"", "")
                return (r.status, r.geturl(), body, r.headers.get("Content-Type", ""))
        except urllib.error.HTTPError as e:
            if e.code in (429, 500, 502, 503, 504) and attempt < tries - 1:
                retry = e.headers.get("Retry-After", "") if e.headers else ""
                time.sleep(min(int(retry), 120) if retry.isdigit() else 5 * 2 ** attempt)
                continue
            return (e.code, url, b"", "")
        except Exception:
            if attempt < tries - 1:
                time.sleep(3 * (attempt + 1))
                continue
            return (0, url, b"", "")
    return (0, url, b"", "")


def sparql(query, retries=2):
    url = "https://query.wikidata.org/sparql?" + urllib.parse.urlencode({"query": query})
    st, _, body, _ = http(url, gap=1.5, timeout=70, accept="application/sparql-results+json", max_bytes=60_000_000, tries=4)
    if st != 200:
        if retries > 0:
            time.sleep(30)
            return sparql(query, retries - 1)
        print(f"  query failed HTTP {st}; skipped", flush=True)
        return []
    try:
        return json.loads(body)["results"]["bindings"]
    except ValueError:
        if retries > 0:  # WDQS can truncate a body when it hits its 60-second limit
            time.sleep(20)
            return sparql(query, retries - 1)
        print("  query failed after retries; skipped", flush=True)
        return []


def qid_of(uri):
    return uri.rsplit("/", 1)[-1]


# ------------------------------------------------------------- seed
def stage_seed():
    con = db()
    con.executemany("INSERT OR REPLACE INTO sources VALUES(?,?,?,?,?,?,?,?)", [
        ("wikidata", "Wikidata", "https://www.wikidata.org/", "A", "CC0", "Supervisor instruction", "2026-11-09", "Plan 2.2-2.5"),
        ("commons", "Wikimedia Commons", "https://commons.wikimedia.org/", "A", "Per file", "Supervisor instruction", "2026-11-09", "Plan 2.5, images section"),
        ("own_site", "Organisation's own website", "(per domain)", "P", "Varies; robots checked per domain", "Supervisor instruction", "2026-11-09", "Plan 2.2 source class"),
    ])
    con.commit()
    occ = " ".join("wd:" + c for c in ENTREPRENEUR_OCCUPATIONS)
    org_classes = set(ORG_CLASSES)
    for cls in ORG_CLASSES:
        org_classes |= {qid_of(x["c"]["value"]) for x in sparql(f"SELECT ?c WHERE {{ ?c wdt:P279+ wd:{cls}. }}")}
    org_classes -= EXCLUDED_ORG_CLASSES
    print("organisation classes", len(org_classes), flush=True)
    for cq, cname in COUNTRIES.items():
        print("seed", cname, flush=True)
        rows = sparql(f"""SELECT ?o ?t ?sl WHERE {{ ?o wdt:P17 wd:{cq}; wdt:P31 ?t; wikibase:sitelinks ?sl. FILTER(?sl >= 1) }}""")
        listed_here = {qid_of(x["o"]["value"]) for x in sparql(f"""SELECT DISTINCT ?o WHERE {{ ?o wdt:P17 wd:{cq}; wdt:P414 ?x. }}""")}
        print("  rows", len(rows), "listed", len(listed_here), flush=True)
        for row in rows:
            t, q = qid_of(row["t"]["value"]), qid_of(row["o"]["value"])
            if t in org_classes:
                con.execute("""INSERT INTO candidates VALUES(?,?,?,?,?) ON CONFLICT(qid) DO UPDATE SET
                               exchange=max(exchange, excluded.exchange)""",
                            (q, "organisation", cname, int(row["sl"]["value"]), 1 if q in listed_here else 0))
        con.commit()
        # DOB is used only inside the query to keep out minors; it is never returned or stored (REQ-140).
        people = f"""SELECT DISTINCT ?p ?sl WHERE {{
              ?p wdt:P31 wd:Q5; wdt:P27 wd:{cq}; wikibase:sitelinks ?sl. FILTER(?sl >= 1)
              ?p wdt:P106/wdt:P279* ?occ. VALUES ?occ {{ {occ} }}
              FILTER NOT EXISTS {{ ?p wdt:P570 ?d }}
              OPTIONAL {{ ?p wdt:P569 ?dob }} FILTER(!BOUND(?dob) || YEAR(?dob) < 2006) }}"""
        for row in sparql(people):
            con.execute("INSERT OR REPLACE INTO candidates(qid,kind,country,sitelinks) VALUES(?,?,?,?)",
                        (qid_of(row["p"]["value"]), "entrepreneur", cname, int(row["sl"]["value"])))
        con.commit()
        founders = f"""SELECT DISTINCT ?p ?o ?sl WHERE {{
              ?o wdt:P17 wd:{cq}; wdt:P112 ?p. ?p wdt:P31 wd:Q5; wikibase:sitelinks ?sl.
              FILTER NOT EXISTS {{ ?p wdt:P570 ?d }}
              OPTIONAL {{ ?p wdt:P569 ?dob }} FILTER(!BOUND(?dob) || YEAR(?dob) < 2006) }}"""
        for row in sparql(founders):
            p, o = qid_of(row["p"]["value"]), qid_of(row["o"]["value"])
            con.execute("INSERT OR IGNORE INTO founder_links VALUES(?,?)", (p, o))
            con.execute("INSERT OR IGNORE INTO candidates(qid,kind,country,sitelinks) VALUES(?,?,?,?)",
                        (p, "entrepreneur", cname, int(row["sl"]["value"])))
        con.commit()
    for name in PAN_AFRICAN:
        q = urllib.parse.urlencode({"action": "wbsearchentities", "search": name, "language": "en", "type": "item",
                                    "limit": 3, "format": "json"})
        st, _, body, _ = http("https://www.wikidata.org/w/api.php?" + q, gap=0.5)
        if st == 200:
            for hit in json.loads(body).get("search", []):
                if hit.get("label", "").lower().replace("–", "-") == name.lower():
                    con.execute("INSERT OR REPLACE INTO candidates(qid,kind,country,sitelinks) VALUES(?,?,?,?)",
                                (hit["id"], "organisation", "Pan-African", 99))
                    break
    con.commit()
    for r in con.execute("SELECT kind,country,COUNT(*) n,SUM(exchange) x FROM candidates GROUP BY 1,2"):
        print(dict(r))


# ------------------------------------------------------------- enrich
def claim_values(ent, prop, current_only=False):
    out = []
    for st in ent.get("claims", {}).get(prop, []):
        if st.get("rank") == "deprecated":
            continue
        if current_only and "P582" in st.get("qualifiers", {}):
            continue
        dv = st.get("mainsnak", {}).get("datavalue")
        if dv:
            out.append(dv["value"])
    return out


def item_ids(ent, prop, current_only=False):
    return [v["id"] for v in claim_values(ent, prop, current_only) if isinstance(v, dict) and "id" in v]


def trim_entity(ent, kind):
    """Allowlist step (REQ-139, REQ-140): only these leave memory. DOB, party, religion etc. are never read."""
    d = {
        "name": ent.get("labels", {}).get("en", {}).get("value"),
        "description": clean_description(ent.get("descriptions", {}).get("en", {}).get("value")),
        "enwiki": ent.get("sitelinks", {}).get("enwiki", {}).get("title"),
        "sitelinks": len(ent.get("sitelinks", {})),
        "website": next(iter(claim_values(ent, "P856", True)), None),
        "countries": item_ids(ent, "P17") if kind == "organisation" else item_ids(ent, "P27"),
        "image": next(iter(claim_values(ent, "P18")), None),
    }
    if kind == "organisation":
        inception = claim_values(ent, "P571")
        d.update(types=item_ids(ent, "P31"), hq=item_ids(ent, "P159") or item_ids(ent, "P131"), industry=item_ids(ent, "P452"),
                 exchange=item_ids(ent, "P414"), logo=next(iter(claim_values(ent, "P154")), None),
                 founded=(inception[0].get("time", "")[1:5] if inception and isinstance(inception[0], dict) else None),
                 dissolved=bool(claim_values(ent, "P576")))
    else:
        d.update(occupations=item_ids(ent, "P106"), employers=item_ids(ent, "P108", True), positions=item_ids(ent, "P39", True),
                 awards=item_ids(ent, "P166"), owner_of=item_ids(ent, "P1830", True),
                 deceased=bool(claim_values(ent, "P570")))
    return d


def api_entities(ids, props):
    q = urllib.parse.urlencode({"action": "wbgetentities", "ids": "|".join(ids), "props": props, "languages": "en",
                                "sitefilter": "" , "format": "json", "maxlag": 5})
    st, _, body, _ = http("https://www.wikidata.org/w/api.php?" + q, gap=0.4, timeout=60, max_bytes=60_000_000, tries=5)
    if st != 200:
        return {}
    return json.loads(body).get("entities", {})


def stage_enrich():
    con = db()
    todo = [(r["qid"], r["kind"]) for r in con.execute(
        "SELECT qid,kind FROM candidates WHERE qid NOT IN (SELECT qid FROM entities) ORDER BY sitelinks DESC")]
    print("to enrich", len(todo), flush=True)
    for i in range(0, len(todo), 50):
        chunk = todo[i:i + 50]
        ents = api_entities([q for q, _ in chunk], "labels|descriptions|claims|sitelinks")
        kinds = dict(chunk)
        for q, ent in ents.items():
            if q in kinds and "missing" not in ent:
                con.execute("INSERT OR REPLACE INTO entities VALUES(?,?,?,?)",
                            (q, kinds[q], json.dumps(trim_entity(ent, kinds[q]), ensure_ascii=False), now()))
        con.commit()
        if i % 500 == 0:
            print("enriched", i, flush=True)
    refs = set()
    for r in con.execute("SELECT data FROM entities"):
        d = json.loads(r[0])
        for k in ("countries", "types", "hq", "industry", "exchange", "occupations", "employers", "positions", "awards", "owner_of"):
            refs.update(d.get(k) or [])
    refs.update(r[0] for r in con.execute("SELECT org_qid FROM founder_links"))
    have = {r[0] for r in con.execute("SELECT qid FROM labels")}
    refs = sorted(refs - have)
    print("labels to fetch", len(refs), flush=True)
    for i in range(0, len(refs), 50):
        for q, ent in api_entities(refs[i:i + 50], "labels").items():
            label = ent.get("labels", {}).get("en", {}).get("value")
            if label:
                con.execute("INSERT OR REPLACE INTO labels VALUES(?,?)", (q, label))
        con.commit()
    print("entities", con.execute("SELECT COUNT(*) FROM entities").fetchone()[0])


# ------------------------------------------------------------- own sites
class SiteParser(html.parser.HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.desc = self.og_desc = self.touch = self.icon = self.og_image = self.logo_img = None

    def handle_starttag(self, tag, attrs):
        a = {k.lower(): (v or "") for k, v in attrs}
        if tag == "meta":
            key = (a.get("name") or a.get("property") or "").lower()
            if key == "description" and not self.desc:
                self.desc = a.get("content")
            elif key == "og:description" and not self.og_desc:
                self.og_desc = a.get("content")
            elif key == "og:image" and not self.og_image:
                self.og_image = a.get("content")
        elif tag == "link":
            rel = a.get("rel", "").lower()
            if "apple-touch-icon" in rel and not self.touch:
                self.touch = a.get("href")
            elif "icon" in rel and not self.icon:
                self.icon = a.get("href")
        elif tag == "img" and not self.logo_img:
            hay = " ".join((a.get("src", ""), a.get("alt", ""), a.get("class", ""), a.get("id", ""))).lower()
            if "logo" in hay and a.get("src") and not a["src"].startswith("data:"):
                self.logo_img = a.get("src")


def robots_ok(url):
    """False if our bot, or AI crawlers by name, may not fetch this URL (plan 3.4). Unreachable robots: do not fetch."""
    u = urllib.parse.urlparse(url)
    st, _, body, _ = http(f"{u.scheme}://{u.netloc}/robots.txt", gap=5.0, timeout=20, max_bytes=500_000, tries=2)
    if st == 0 or st >= 500:
        return None
    if st >= 400:
        return True
    rp = urllib.robotparser.RobotFileParser()
    rp.parse(body.decode("utf-8", "replace").splitlines())
    return all(rp.can_fetch(agent, url) for agent in ("WHOISWHO-bot", "ClaudeBot", "anthropic-ai"))


def fetch_site(row):
    qid, url = row
    res = dict(qid=qid, url=url, final_url=None, status="not_fetched", http=None, description=None,
               logo_url=None, logo_kind=None, retrieved_at=now())
    if not safe_public_url(url):
        res["status"] = "bad_url"
        return res
    ok = robots_ok(url)
    if ok is None:
        res["status"] = "unreachable"
        return res
    if ok is False:
        res["status"] = "robots_blocked"
        return res
    st, final, body, ctype = http(url, gap=5.0, timeout=25, max_bytes=3_000_000, tries=2)
    res.update(http=st, final_url=final)
    if st != 200 or "html" not in ctype.lower():
        res["status"] = "http_error" if st else "unreachable"
        return res
    res["status"] = "ok"
    p = SiteParser()
    try:
        p.feed(body.decode("utf-8", "replace"))
    except Exception:
        pass
    desc = (p.desc or p.og_desc or "").strip()
    res["description"] = re.sub(r"\s+", " ", html.unescape(desc))[:500] or None
    for cand, kind in ((p.logo_img, "site_logo"), (p.touch, "site_touch_icon"), (p.og_image, "site_og_image"), (p.icon, "site_icon")):
        if cand:
            res["logo_url"], res["logo_kind"] = urllib.parse.urljoin(final, cand.strip()), kind
            break
    return res


def excluded_org_type(con, types):
    return any(EXCLUDED_TYPE_LABEL.search(label(con, t) or "") for t in types or [])


def org_site_rows(con, limit_other=260):
    """Website checks for every eligible Nigerian organisation, plus the strongest others."""
    rows, others = [], 0
    done = {r[0] for r in con.execute("SELECT qid FROM sites")}
    q = """SELECT c.qid,c.country,e.data FROM candidates c JOIN entities e USING(qid) WHERE c.kind='organisation'
           ORDER BY c.exchange DESC, c.sitelinks DESC"""
    for r in con.execute(q).fetchall():
        d = json.loads(r["data"])
        if not d.get("website") or d.get("dissolved") or excluded_org_type(con, d.get("types")):
            continue
        if r["country"] != "Nigeria":
            others += 1
            if others > limit_other:
                continue
        if r["qid"] not in done:
            rows.append((r["qid"], d["website"]))
    return rows


def stage_sites():
    con = db()
    rows = org_site_rows(con)
    print("sites to fetch", len(rows), flush=True)
    done = 0
    with cf.ThreadPoolExecutor(16) as ex:
        for res in ex.map(fetch_site, rows):
            con.execute("""INSERT OR REPLACE INTO sites VALUES(:qid,:url,:final_url,:status,:http,:description,:logo_url,:logo_kind,:retrieved_at)""", res)
            done += 1
            if done % 25 == 0:
                con.commit()
                print("sites", done, flush=True)
    con.commit()
    for r in con.execute("SELECT status,COUNT(*) FROM sites GROUP BY 1"):
        print(tuple(r))


# ------------------------------------------------------------- select
def label(con, q):
    r = con.execute("SELECT label FROM labels WHERE qid=?", (q,)).fetchone()
    return r[0] if r else None


def suppression_set():
    f = DATA / "suppression.txt"
    return {l.strip().lower() for l in f.read_text("utf-8").splitlines() if l.strip()} if f.exists() else set()


def class_set(con, name, roots):
    have = {r[0] for r in con.execute("SELECT qid FROM class_sets WHERE name=?", (name,))}
    if have:
        return have
    found = set(roots)
    for root in roots:
        found |= {qid_of(x["c"]["value"]) for x in sparql(f"SELECT ?c WHERE {{ ?c wdt:P279+ wd:{root}. }}")}
    con.executemany("INSERT OR IGNORE INTO class_sets VALUES(?,?)", [(name, q) for q in found])
    con.commit()
    return found


def org_category(con, types, business):
    labels = [label(con, t) or "" for t in types or []]
    for cat, rx in CATEGORY_RULES:
        if any(rx.search(l) for l in labels):
            return cat
    return "Business" if set(types or []) & business else "Other"


def business_founder_orgs(con, qid, founders, business):
    """Founder links only count when the organisation is a business in the candidate pool."""
    out = []
    for o in founders.get(qid, []):
        r = con.execute("SELECT data FROM entities WHERE qid=? AND kind='organisation'", (o,)).fetchone()
        if r:
            d = json.loads(r[0])
            if not d.get("dissolved") and not excluded_org_type(con, d.get("types")) and org_category(con, d.get("types"), business) == "Business":
                out.append(o)
    return out


def country_qid(name):
    return next((q for q, n in COUNTRIES.items() if n == name), None)


def stage_select():
    con = db()
    con.execute("DELETE FROM exclusions")
    suppressed = suppression_set()
    business = class_set(con, "business", ["Q4830453", "Q783794", "Q6881511", "Q22687", "Q1378841", "Q46970"])
    biz_occ = class_set(con, "business_occupation", ENTREPRENEUR_OCCUPATIONS)
    orgs, people = [], []
    site = {r["qid"]: r for r in con.execute("SELECT * FROM sites")}
    founders = {}
    for r in con.execute("SELECT person_qid,org_qid FROM founder_links"):
        founders.setdefault(r[0], []).append(r[1])
    listed = {r[0] for r in con.execute("SELECT qid FROM candidates WHERE exchange=1")}
    for r in con.execute("SELECT c.qid,c.kind,c.country,c.sitelinks,c.exchange,e.data FROM candidates c JOIN entities e USING(qid)").fetchall():
        d = json.loads(r["data"])
        d["description"] = clean_description(d.get("description"))
        reason = None
        text = f"{d.get('name') or ''} {d.get('description') or ''}"
        if not d.get("name"):
            reason = "no_english_name"
        elif d["name"].lower() in suppressed:
            reason = "suppressed"
        elif SENSITIVE.search(text) or CONTACT.search(text):
            reason = "sensitive_term"
        elif r["kind"] == "organisation":
            type_text = " ".join(label(con, t) or "" for t in d.get("types") or [])
            if d.get("dissolved"):
                reason = "dissolved"
            elif SENSITIVE.search(type_text) or NAME_EXCLUDE.search(d["name"]):
                reason = "sensitive_term"
            elif set(d.get("types") or []) & EXCLUDED_ORG_CLASSES or excluded_org_type(con, d.get("types")):
                reason = "excluded_type"
        else:
            occ_ids = d.get("occupations") or []
            occ_text = " ".join(label(con, o) or "" for o in occ_ids) + " " + (d.get("description") or "")
            biz_founded = business_founder_orgs(con, r["qid"], founders, business)
            is_biz_occ = bool(set(occ_ids) & biz_occ)
            e1 = any(o in listed for o in biz_founded)
            biz_desc = bool(BUSINESS_WORDS.search(d.get("description") or ""))
            if d.get("deceased") or re.search(r"\b(\d{1,2}(st|nd|rd|th)[- ]century|late|historical|deceased)\b", d.get("description") or "", re.I):
                reason = "deceased"
            elif country_qid(r["country"]) not in (d.get("countries") or []):
                reason = "not_citizen_of_listed_country"
            elif NON_AFRICAN.search(d.get("description") or "") and not AFRICAN.search(d.get("description") or ""):
                reason = "not_african"
            elif SENSITIVE.search(occ_text) or any(SENSITIVE.search(label(con, o) or "") for o in biz_founded):
                reason = "sensitive_term"
            elif POLITICIAN in occ_ids or POLITICS.search(occ_text):
                reason = "politician"
            elif NON_BUSINESS_OCC.search(occ_text) and not (e1 or biz_desc):
                reason = "non_business_primary_role"
            elif not is_biz_occ and not biz_founded:
                reason = "not_entrepreneur"
        if reason:
            con.execute("INSERT OR REPLACE INTO exclusions VALUES(?,?,?)", (r["qid"], r["kind"], reason))
            continue
        if r["kind"] == "organisation":
            st = site.get(r["qid"])
            status = st["status"] if st else ("no_website" if not d.get("website") else "not_checked")
            cat = org_category(con, d.get("types"), business)
            score = (2 if r["exchange"] else 0, {"ok": 2, "robots_blocked": 1}.get(status, 0), r["sitelinks"])
            orgs.append((score, r["qid"], r["country"], cat))
        else:
            tier = 5 if e1 else 4 if (biz_founded and is_biz_occ) else 3 if biz_founded else 2 if biz_desc else 1
            score = (tier, 1 if d.get("image") else 0, r["sitelinks"])
            people.append((score, r["qid"], r["country"], None))

    def pick(pool, n, caps=None):
        pool.sort(key=lambda x: x[0], reverse=True)
        chosen, used, counts = [], set(), {}

        def take(items, limit, use_caps):
            taken = 0
            for it in items:
                if taken >= limit:
                    break
                if it[1] in used:
                    continue
                if use_caps and caps and counts.get(it[3], 0) >= int(caps.get(it[3], 1) * n):
                    continue
                chosen.append(it)
                used.add(it[1])
                counts[it[3]] = counts.get(it[3], 0) + 1
                taken += 1

        take([p for p in pool if p[2] == "Nigeria"], int(n * NIGERIA_SHARE), True)
        by_country = {}
        for it in pool:
            if it[2] != "Nigeria":
                by_country.setdefault(it[2], []).append(it)
        interleaved = []
        while any(by_country.values()):
            for k in list(by_country):
                if by_country[k]:
                    interleaved.append(by_country[k].pop(0))
        take(interleaved, n - len(chosen), True)
        take(pool, n - len(chosen), False)
        return [c[1] for c in chosen]

    org_caps = {"Education": 0.22, "Government": 0.13, "NGO or association": 0.08, "Research": 0.03, "Other": 0.08, "Business": 1.0}
    sel_orgs, sel_people = pick(orgs, TARGET_ORGS, org_caps), pick(people, TARGET_PEOPLE)
    con.execute("DROP TABLE IF EXISTS selected")
    con.execute("CREATE TABLE selected(qid TEXT PRIMARY KEY, kind TEXT)")
    con.executemany("INSERT INTO selected VALUES(?,?)", [(q, "organisation") for q in sel_orgs] + [(q, "entrepreneur") for q in sel_people])
    con.commit()
    print("pool orgs", len(orgs), "people", len(people), "selected", len(sel_orgs), len(sel_people))
    cats = {o[3] for o in orgs}
    print("org pool by category", {c: sum(1 for o in orgs if o[3] == c) for c in cats})
    print("selected org by category", {c: sum(1 for o in orgs if o[3] == c and o[1] in set(sel_orgs)) for c in cats})
    print("people pool tiers", {t: sum(1 for p in people if p[0][0] == t) for t in (5, 4, 3, 2, 1)})
    print("people tiers selected", {t: sum(1 for p in people if p[0][0] == t and p[1] in set(sel_people)) for t in (5, 4, 3, 2, 1)})
    for r in con.execute("SELECT kind,reason,COUNT(*) FROM exclusions GROUP BY 1,2"):
        print(tuple(r))
    for r in con.execute("SELECT s.kind,c.country,COUNT(*) FROM selected s JOIN candidates c USING(qid) GROUP BY 1,2"):
        print(tuple(r))


# ------------------------------------------------------------- images
def strip_tags(s):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", " ", s or ""))).strip()


def save_image(body, qid, role):
    ext = next((e for m, e in ALLOWED_IMAGE_MAGIC if body.startswith(m)), None)
    if not ext and body[:4] == b"RIFF" and body[8:12] == b"WEBP":
        ext = ".webp"
    if not ext and b"<svg" in body[:2000].lower():
        ext = ".svg"
    if not ext or len(body) < 200:
        return None
    folder = IMG / ("photos" if role == "photo" else "logos")
    folder.mkdir(parents=True, exist_ok=True)
    p = folder / f"{qid}{ext}"
    p.write_bytes(body)
    return p.relative_to(DATA).as_posix()


def commons_info(files):
    titles = "|".join("File:" + f for f in files)
    q = urllib.parse.urlencode({"action": "query", "titles": titles, "prop": "imageinfo", "iiprop": "url|extmetadata",
                                "iiurlwidth": 330, "iiextmetadatafilter": "LicenseShortName|Artist|Credit", "format": "json"})
    st, _, body, _ = http("https://commons.wikimedia.org/w/api.php?" + q, gap=0.5, timeout=45, max_bytes=20_000_000, tries=4)
    if st != 200:
        return {}
    data = json.loads(body).get("query", {})
    norm = {n["to"]: n["from"] for n in data.get("normalized", [])}
    out = {}
    for page in data.get("pages", {}).values():
        ii = (page.get("imageinfo") or [None])[0]
        if ii:
            meta = ii.get("extmetadata", {})
            title = norm.get(page["title"], page["title"]).replace("File:", "", 1)
            out[title] = dict(thumb=ii.get("thumburl") or ii.get("url"), page=ii.get("descriptionurl"),
                              licence=strip_tags(meta.get("LicenseShortName", {}).get("value")),
                              credit=strip_tags(meta.get("Artist", {}).get("value") or meta.get("Credit", {}).get("value"))[:300])
    return out


def stage_images():
    con = db()
    jobs = {}
    for r in con.execute("SELECT s.qid,s.kind,e.data FROM selected s JOIN entities e USING(qid)"):
        d = json.loads(r["data"])
        if d.get("image"):
            jobs[(r["qid"], "photo")] = d["image"]
        if d.get("logo"):
            jobs[(r["qid"], "logo")] = d["logo"]
    done = {(r[0], r[1]) for r in con.execute("SELECT qid,role FROM images WHERE status='ok'")}
    pending = [(k, v) for k, v in jobs.items() if k not in done]
    print("commons images to fetch", len(pending), flush=True)
    for i in range(0, len(pending), 40):
        chunk = pending[i:i + 40]
        infos = commons_info(sorted({v for _, v in chunk}))
        for (qid, role), fname in chunk:
            info = infos.get(fname) or infos.get(fname.replace("_", " "))
            if not info or not info["thumb"] or not info["licence"]:
                con.execute("INSERT OR REPLACE INTO images VALUES(?,?,?,?,?,?,?,?)", (qid, role, "no_licence_info", None, None, None, None, now()))
                continue
            st, _, body, _ = http(info["thumb"], gap=0.7, timeout=40, max_bytes=5_000_000)
            path = save_image(body, qid, role) if st == 200 else None
            con.execute("INSERT OR REPLACE INTO images VALUES(?,?,?,?,?,?,?,?)",
                        (qid, role, "ok" if path else f"http_{st}", path, info["page"], info["licence"], info["credit"], now()))
        con.commit()
        print("commons", min(i + 40, len(pending)), flush=True)
    site_jobs = [(r["qid"], r["logo_url"], r["logo_kind"]) for r in con.execute(
        """SELECT s.qid,t.logo_url,t.logo_kind FROM selected s JOIN sites t USING(qid)
           WHERE s.kind='organisation' AND t.status='ok' AND t.logo_url IS NOT NULL
             AND s.qid NOT IN (SELECT qid FROM images WHERE role='logo' AND status='ok')""")]
    print("site logos to fetch", len(site_jobs), flush=True)

    def get_logo(job):
        qid, url, kind = job
        if not safe_public_url(url):
            return qid, "bad_url", None, url, kind
        ok = robots_ok(url)
        if not ok:
            return qid, "robots", None, url, kind
        st, _, body, _ = http(url, gap=5.0, timeout=25, max_bytes=5_000_000, tries=2)
        path = save_image(body, qid, "logo") if st == 200 else None
        return qid, "ok" if path else f"http_{st}", path, url, kind

    with cf.ThreadPoolExecutor(12) as ex:
        n = 0
        for qid, status, path, url, kind in ex.map(get_logo, site_jobs):
            con.execute("INSERT OR REPLACE INTO images VALUES(?,?,?,?,?,?,?,?)",
                        (qid, "logo", status, path, url, "Not licensed: owner's copyright or trademark" if path else None, f"Organisation's own site ({kind})" if path else None, now()))
            n += 1
            if n % 25 == 0:
                con.commit()
                print("site logos", n, flush=True)
    con.commit()
    for r in con.execute("SELECT role,status,COUNT(*) FROM images GROUP BY 1,2"):
        print(tuple(r))


# ------------------------------------------------------------- finalise
def join_list(items):
    items = [i for i in items if i]
    return items[0] if len(items) == 1 else ", ".join(items[:-1]) + " and " + items[-1] if items else ""


def a_an(word):
    w = word.lower()
    vowel = w[:1] in "aeiou" and not w.startswith(("uni", "eu", "one ", "us"))
    return ("an " if vowel else "a ") + word


GENERIC_FIRST = {"company", "business", "university", "bank", "organization", "organisation", "airline", "private", "public",
                 "government", "agency", "institution", "non-profit", "nonprofit", "polytechnic", "college", "hotel", "website"}


def opening(desc):
    """Turn a Wikidata description (CC0) into the start of a sentence."""
    desc = desc.strip().rstrip(".")
    first = desc.split(" ", 1)[0]
    if first.lower() in ("a", "an", "the"):
        return desc[0].lower() + desc[1:]
    if first.lower() in GENERIC_FIRST:
        desc = desc[0].lower() + desc[1:]
    return a_an(desc)


def cap(t):
    return t[0].upper() + t[1:] if t else t


def stage_finalise():
    con = db()
    con.execute("DROP TABLE records")
    con.commit()
    con.close()
    con = db()
    con.execute("DELETE FROM provenance")
    business = class_set(con, "business", [])
    founders = {}
    for r in con.execute("SELECT person_qid,org_qid FROM founder_links"):
        founders.setdefault(r[0], []).append(r[1])
    listed = {r[0] for r in con.execute("SELECT qid FROM candidates WHERE exchange=1")}
    selected_orgs = {r[0] for r in con.execute("SELECT qid FROM selected WHERE kind='organisation'")}
    names_seen = {}
    rows = con.execute("""SELECT s.qid,s.kind,c.country,c.sitelinks,e.data,e.retrieved_at FROM selected s
                          JOIN candidates c USING(qid) JOIN entities e USING(qid)""").fetchall()
    for r in rows:
        key = re.sub(r"[^a-z0-9]", "", unicodedata.normalize("NFKD", json.loads(r["data"])["name"]).lower())
        names_seen.setdefault(key, []).append(r["qid"])
    site_clean = 0
    for r in rows:
        d, qid, kind = json.loads(r["data"]), r["qid"], r["kind"]
        d["description"] = clean_description(d.get("description"))
        wd_url = f"https://www.wikidata.org/wiki/{qid}"
        at = r["retrieved_at"]
        flags = []
        rec = dict(record_id="wd:" + qid, type=kind, qid=qid, name=d["name"], country=r["country"], sitelinks=d["sitelinks"],
                   description=d.get("description"), website=d.get("website"), run_id=RUN_ID, retrieved_at=at,
                   wikipedia_lead=("https://en.wikipedia.org/wiki/" + urllib.parse.quote(d["enwiki"].replace(" ", "_"))) if d.get("enwiki") else None)
        prov = {"name": ("wikidata", wd_url, "high", "API"), "country": ("wikidata", wd_url, "high", "API")}
        if d.get("description"):
            prov["description"] = ("wikidata", wd_url, "medium", "API")
        if d.get("website"):
            prov["website"] = ("wikidata", wd_url, "medium", "API")
        site = con.execute("SELECT * FROM sites WHERE qid=?", (qid,)).fetchone()
        rec["website_status"] = site["status"] if site else ("no_website" if not d.get("website") else "not_checked")
        if kind == "organisation":
            types = [label(con, t) for t in d.get("types") or []]
            types = [t for t in types if t]
            rec["org_type"] = cap(types[0]) if types else None
            rec["org_category"] = org_category(con, d.get("types"), business)
            hq = [label(con, h) for h in d.get("hq") or []]
            rec["city"] = next((h for h in hq if h), None)
            inds = [label(con, i) for i in d.get("industry") or []]
            rec["industry"] = ", ".join(i for i in inds[:3] if i) or None
            rec["founded_year"] = d.get("founded")
            ex = [label(con, x) for x in d.get("exchange") or []]
            rec["exchange"] = ", ".join(x for x in ex if x) or None
            met = bool(d.get("exchange"))
            rec["criterion"], rec["criterion_met"] = ("O1", 1) if met else ("O5", 1)
            rec["evidence_url"] = wd_url
            kind_word = rec["org_type"] or "organisation"
            place = None if r["country"] == "Pan-African" else r["country"]
            city = rec["city"] if rec["city"] and rec["city"] != r["country"] else None
            if d.get("description"):
                bio = f"{d['name']} is {opening(d['description'])}."
                if city:
                    bio += f" Based in {city}."
            else:
                where = ", ".join(x for x in (city, place) if x)
                bio = f"{d['name']} is {a_an(kind_word)}" + (f" based in {where}" if where else "") + "."
            if rec["founded_year"] and rec["founded_year"].isdigit():
                bio += f" Founded in {rec['founded_year']}."
            if rec["exchange"]:
                bio += f" Listed on {rec['exchange']}."
            if rec["industry"]:
                bio += f" Industry: {rec['industry']}."
            rec["bio"] = bio
            for f in ("org_type", "city", "industry", "founded_year", "exchange"):
                if rec.get(f):
                    prov[f] = ("wikidata", wd_url, "medium", "API")
            desc = site["description"] if site and site["status"] == "ok" else None
            if desc and (SENSITIVE.search(desc) or CONTACT.search(desc)):
                desc = None
                site_clean += 1
                flags.append("site_description_dropped_by_scan")
            rec["site_description"] = desc
            if desc:
                prov["site_description"] = ("own_site", site["final_url"], "medium", "fetch")
            if rec["website_status"] not in ("ok",):
                flags.append("website_" + rec["website_status"])
        else:
            orgs = [(o, label(con, o)) for o in business_founder_orgs(con, qid, founders, business)]
            orgs = [(o, n) for o, n in orgs if n]
            owned = [(o, label(con, o)) for o in d.get("owner_of") or []]
            emp = [(o, label(con, o)) for o in d.get("employers") or []]
            pos = [label(con, p) for p in d.get("positions") or []]
            pos = [p for p in pos if p]
            occs = [label(con, o) for o in d.get("occupations") or []]
            occs = [o for o in occs if o]
            awards = [label(con, a) for a in d.get("awards") or []]
            awards = [a for a in awards if a][:6]
            biz_occ_ids = class_set(con, "business_occupation", [])
            biz_labels = [label(con, o) for o in d.get("occupations") or [] if o in biz_occ_ids]
            biz_labels = [o for o in biz_labels if o]
            occupation = cap(biz_labels[0]) if biz_labels else (cap(occs[0]) if occs else "Entrepreneur")
            employer_line = None
            if orgs:
                best = sorted(orgs, key=lambda x: (x[0] in listed, x[0] in selected_orgs), reverse=True)[0]
                rec["current_org"], rec["current_title"] = best[1], "Founder"
                flags.append("founder_role_not_confirmed_on_company_site")
            elif owned and owned[0][1]:
                rec["current_org"], rec["current_title"] = owned[0][1], "Owner"
            elif emp and emp[0][1]:
                rec["current_org"], rec["current_title"] = emp[0][1], cap(pos[0]) if pos else occupation
                if not pos:
                    employer_line = emp[0][1]
            elif pos:
                rec["current_title"] = cap(pos[0])
            else:
                rec["current_title"] = occupation
            rec["industry"] = None
            rec["roles"] = json.dumps(pos[:6], ensure_ascii=False)
            rec["awards"] = json.dumps(awards, ensure_ascii=False)
            e1 = any(o in listed for o, _ in orgs)
            rec["criterion"], rec["criterion_met"] = ("E1", 1) if e1 else ("LEAD", 0)
            rec["evidence_url"] = wd_url
            if not e1:
                flags.append("does_not_yet_meet_E1_E2")
            where = "" if r["country"] == "Pan-African" else f" from {r['country']}"
            if d.get("description"):
                bio = f"{d['name']} is {opening(d['description'])}."
            else:
                bio = f"{d['name']} is {a_an(occupation[0].lower() + occupation[1:])}{where}."
            if rec.get("current_org"):
                t = rec["current_title"]
                if employer_line:
                    bio += f" Works with {employer_line.rstrip('.')}."
                else:
                    org_name = rec["current_org"].rstrip(".")
                    bio += f" {t} of {org_name}." if t in ("Founder", "Owner") else f" {t}, {org_name}."
            elif rec["current_title"] != occupation:
                bio += f" {rec['current_title']}."
            if pos:
                extra = [x for x in pos[:3] if x.lower() != (rec["current_title"] or "").lower()]
                if extra:
                    bio += f" Public roles: {join_list(extra)}."
            if awards:
                bio += f" Awards: {join_list(awards[:3])}."
            rec["bio"] = bio
            for f in ("current_title", "current_org", "roles", "awards"):
                if rec.get(f):
                    prov[f] = ("wikidata", wd_url, "medium", "API")
        prov["bio"] = ("wikidata", wd_url, "medium", "template")
        for role, prefix in (("photo", "photo"), ("logo", "logo")):
            im = con.execute("SELECT * FROM images WHERE qid=? AND role=? AND status='ok'", (qid, role)).fetchone()
            if im:
                rec[prefix + "_path"], rec[prefix + "_source"], rec[prefix + "_licence"], rec[prefix + "_credit"] = im["path"], im["source_url"], im["licence"], im["credit"]
                src = "own_site" if (im["licence"] or "").startswith("Not licensed") else "commons"
                prov[prefix] = (src, im["source_url"], "medium", "API" if src == "commons" else "fetch")
        dup = names_seen.get(re.sub(r"[^a-z0-9]", "", unicodedata.normalize("NFKD", d["name"]).lower()), [])
        if len(dup) > 1:
            flags.append("possible_duplicate")
        scan_text = " ".join(str(rec.get(k) or "") for k in ("bio", "description", "site_description"))
        checks = {
            "1_source_approved": True,
            "2_source_url_success": True,
            "3_claim_quotes": "not_applicable_no_ai_text",
            "4_allowlisted_fields_only": True,
            "5_sensitive_scan": not (SENSITIVE.search(scan_text) or CONTACT.search(scan_text)),
            "6_suppression_check": "passed_but_no_list_exists_yet" if not suppression_set() else True,
            "7_duplicates_flagged": "possible_duplicate" in flags,
        }
        if checks["5_sensitive_scan"] is False:
            rec["bio"] = None
            flags.append("sensitive_scan_hit")
        rec["flags"] = json.dumps(flags)
        rec["checks"] = json.dumps(checks)
        canon = {k: rec.get(k) for k in sorted(rec) if k not in ("retrieved_at", "run_id", "flags", "checks", "photo_path", "logo_path")}
        canon = json.dumps(canon, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
        rec["content_hash"] = "sha256:" + hashlib.sha256(unicodedata.normalize("NFC", canon).encode("utf-8")).hexdigest()
        cols = [c[1] for c in con.execute("PRAGMA table_info(records)")]
        con.execute(f"INSERT INTO records({','.join(cols)}) VALUES({','.join('?' * len(cols))})", [rec.get(c) for c in cols])
        con.executemany("INSERT OR REPLACE INTO provenance VALUES(?,?,?,?,?,?,?)",
                        [(rec["record_id"], f, *p[:2], at, *p[2:]) for f, p in prov.items()])
    con.commit()
    print("records", con.execute("SELECT COUNT(*) FROM records").fetchone()[0], "site descriptions dropped", site_clean)


STAGES = dict(seed=stage_seed, enrich=stage_enrich, sites=stage_sites, select=stage_select, images=stage_images, finalise=stage_finalise)

if __name__ == "__main__":
    if len(sys.argv) < 2 or sys.argv[1] not in STAGES:
        sys.exit("usage: run_first_1000.py " + "|".join(STAGES) + " [target_orgs]")
    STAGES[sys.argv[1]]()
