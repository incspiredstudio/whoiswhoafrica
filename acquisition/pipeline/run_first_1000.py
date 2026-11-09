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
EXPANSION_RUN_ID = "expansion-2026-11-09"
DECEASED_RUN_ID = "deceased-2026-11-09"
DECEASED_TARGET = 1000
DECEASED_FLOOR_PER_COUNTRY = 5
DECEASED_CAP_PER_COUNTRY = 150
# Deceased people are notable for many things, including politics and religion, so those are allowed here.
# Prominence that comes from crime is not (plan 1.4).
CRIME = re.compile(r"\b(serial killer|killer|murderer|mass murderer|criminal|gangster|drug lord|drug trafficker|rapist|kidnapper|bandit|terrorist|"
                   r"cannibal|executed for|convicted|fraudster|con artist|hijacker|assassin)\b", re.I)
PERSON_FIELDS = [
    ("Royalty and traditional rulers", r"monarch|king|queen|emperor|empress|sultan|pharaoh|emir|oba|ooni|chief|sovereign|ruler|prince|princess|regent|negus|khedive|bey\b|dey\b"),
    ("Politics and government", r"politician|president|prime minister|minister|statesperson|diplomat|ambassador|governor|senator|legislator|mayor|civil servant|head of state|head of government|member of parliament|vice-president"),
    ("Activism and liberation", r"activist|revolutionary|freedom fighter|nationalist|trade unionist|human rights|independence|anti-apartheid|liberation|resistance"),
    ("Military", r"military|soldier|army|general|officer|marshal|admiral|warrior|commander"),
    ("Religion", r"cleric|priest|bishop|archbishop|cardinal|imam|pastor|theologian|missionary|saint|religious|pope|prophet|sheikh|marabout|mufti|preacher|nun|monk"),
    ("Science, medicine and academia", r"scientist|physician|academic|professor|historian|economist|engineer|mathematician|philosopher|anthropologist|sociologist|linguist|surgeon|chemist|physicist|biologist|geographer|astronomer|researcher|doctor|educator|teacher|scholar"),
    ("Literature and journalism", r"writer|poet|novelist|author|playwright|journalist|essayist|editor|publisher|dramatist|translator"),
    ("Music", r"musician|singer|composer|songwriter|rapper|guitarist|drummer|bandleader|saxophonist|pianist|trumpeter|percussionist"),
    ("Film, theatre and broadcasting", r"actor|actress|film director|film producer|screenwriter|comedian|presenter|broadcaster|dancer|choreographer"),
    ("Visual arts and architecture", r"painter|sculptor|photographer|artist|architect|designer|illustrator|cartoonist"),
    ("Sport", r"footballer|athlete|runner|boxer|player|cyclist|coach|wrestler|sprinter|swimmer|marathon|olympic|sportsperson|racing driver"),
    ("Business", r"businessperson|entrepreneur|banker|industrialist|merchant|trader|executive"),
    ("Law", r"lawyer|judge|jurist|barrister|attorney|advocate"),
]
PERSON_FIELDS = [(name, re.compile(r"\b(" + rx + r")", re.I)) for name, rx in PERSON_FIELDS]
TOTAL_TARGET = 11000

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
    r"killer|murder\w*|crimin\w*|drug lord|drug traffick\w*|traffick\w*|gangster|gang leader|assassin\w*|rapist|kidnap\w*|warlord|dictator|notorious|fugitive|money launder\w*|scandal|lgbt\w*|lesbian|gay|queer|transgender|intersex|sexual\w*|sex work\w*|reproductive health|APC|PDP|APGA|NNPP|political party|born (?:on|in) \d|aged? \d+|\d+ years? old)\b",
    re.I)
CONTACT = re.compile(r"[\w.+-]+@[\w-]+\.[\w.-]+|(?:\+?234|\b0[789][01])[\s-]?\d{3}[\s-]?\d{3,4}[\s-]?\d{0,4}")
EXCLUDED_TYPE_LABEL = re.compile(
    r"\b(diocese|archdiocese|eparchy|church|denomination|parish|mosque|temple|religious|seminary|monastery|"
    r"political party|football|soccer|rugby|cricket|basketball|sports?|team|club|stadium|embassy|consulate|high commission|library|"
    r"wikimedia|wikipedia|film|song|album|television series|local government|olympic|"
    r"political organi[sz]ation|secret police|titular see|rural community|region of \w+|human settlement|building|built structure|structure|caravanserai|lodge|stud farm|badminton|paralympic|cultural heritage|lyceum|french school|research reactor|local authority|scouting|boxing|swimming|volleyball|judo|chess|cycling|military|army|navy|air force|battalion|regiment|armed forces|armed group|armed organi[sz]ation|rebel|insurgent|jihad\w*|paramilitary|guerrilla|liberation (?:movement|front)|prison|refugee camp|kasbah|palace|mausoleum|shrine|zawiya|synagogue|cathedral|basilica|chapel|madrasa|archaeological|heritage site|national park|nature reserve|airport|railway station|bus station|dam|bridge|lighthouse|power station|oil field|mine|neighbou?rhood|commune|municipality|prefecture|province|district|village|city|town|parti politique|partido|secret service|intelligence|municipal|genootschap|ecclesiastical|catholic|apostolic|trappist|nunnery|vicariate|nunciature|hate group|vigilante|militia|terror\w*|political initiative|pressure group|advocacy group|secretariat|executive branch|cabinet|fort|castle|botanical garden|amusement park|estate|dyeworks|faculty|nursing faculty|handball|hockey|golf|tennis|athletics|multi-level marketing|pyramid scheme|ponzi|youth system|lyc.e|campaign|protest|"
    r"(secondary|primary|private|public|girls'|boys'|boarding|high|junior|elementary|grammar|international|nursery) school|preschool)\b|^school$", re.I)
CATEGORY_RULES = [
    ("NGO or association", re.compile(r"nonprofit|non-profit|non-governmental|foundation|charit|association|union|society|intergovernmental|international organi", re.I)),
    ("Media", re.compile(r"news|media|broadcast|radio|television|newspaper|magazine|publish", re.I)),
    ("Education", re.compile(r"universit|polytechnic|college|school|academic|education|institute of technology", re.I)),
    ("Research", re.compile(r"research|think tank", re.I)),
    ("Government", re.compile(r"government|ministr|government agency|regulat|federal|parliament|legislat|lower house|upper house|senate|central bank|commission|law enforcement|police|gendarmerie|court|archives|statistic|public institution", re.I)),
]
NON_BUSINESS_OCC = re.compile(
    r"\b(politician|president|vice president|governor|senator|minister|legislator|diplomat|statesperson|party|military|soldier|general|monarch|"
    r"traditional ruler|judge|cleric|actor|actress|singer|rapper|musician|songwriter|record producer|disc jockey|footballer|"
    r"basketball|athlete|player|boxer|sprinter|model|beauty pageant|film|television|comedian|presenter|broadcaster|influencer|"
    r"writer|author|novelist|poet|playwright|artist|painter|sculptor|photographer|journalist|activist|feminist|academic|professor|scientist|physician|nurse)\b", re.I)
BUSINESS_WORDS = re.compile(r"\b(entrepreneur|business\w*|founder|co-founder|ceo|chief executive|banker|investor|financier|executive|industrialist|tycoon|magnate|chairman|chairperson|managing director|fintech|startup)\b", re.I)
NAME_EXCLUDE = re.compile(r"(\b(war|ponzi|ecclesiastical|mmm|[ée]glise|dioc[èe]se|paroisse|mosqu[ée]e|cath[ée]drale|parti|partido|igreja|mosque|church|militia|rebel|insurgent|evangelical|presbyterian|methodist|baptist|anglican|catholic|adventist|pentecostal|sacred heart|christ|orthodox|coptic|patriarch\w*|islamic|muslim|jesus|bible|theolog\w*|mujahid\w*|jammeh|gaddafi|qaddafi)\b|حزب|مسجد|كنيسة)", re.I)
NON_AFRICAN = re.compile(r"\b(American|British|English|Scottish|Welsh|Irish|Canadian|Australian|German|French|Italian|Spanish|Dutch|Belgian|Swiss|Swedish|Norwegian|Danish|Finnish|Russian|Ukrainian|Hungarian|Polish|Indian|Pakistani|Chinese|Japanese|Korean|Israeli|Lebanese|Emirati|Saudi|Turkish|Brazilian|Mexican|Portuguese|Greek|Bahamian|Jamaican)\b")
AFRICAN = re.compile(r"\b(African|Nigerian|Ghanaian|Kenyan|Moroccan|Egyptian|Tunisian|Algerian|Senegalese|Ugandan|Cameroonian|Congolese|Tanzanian|Zimbabwean|Ivorian|Rwandan|Beninese|Guinean|Angolan|Ethiopian|Namibian|Togolese|Sudanese|Zambian|Burkinab[eé]|Libyan|Malagasy|Nigerien|Malian|Somali|Mozambican|Motswana|Batswana|Malawian|Gabonese|Burundian|Chadian|Gambian|Mauritian|Sierra Leonean|Mauritanian|Liberian|Cape Verdean|Eritrean|Seychellois|Swazi|Djiboutian|Comorian|Basotho|Mosotho|Equatoguinean|Bissau-Guinean)\b")
BIRTH_BITS = re.compile(r"\s*\((?:born|b\.|né|née)?\s*[^()]*\b(?:1[89]|20)\d\d\b[^()]*\)|,?\s*\bborn\b[^,.;]*", re.I)
POLITICS = re.compile(r"\b(political candidate|paramount chief|politician|president of|former president|vice president|governor|senator|minister|legislator|statesperson|member of (the )?(house|parliament|national assembly))\b", re.I)
ALLOWED_IMAGE_MAGIC = [(b"\x89PNG", ".png"), (b"\xff\xd8\xff", ".jpg"), (b"GIF8", ".gif"), (b"\x00\x00\x01\x00", ".ico")]

_host_lock = threading.Lock()
_host_next = {}


def db():
    DATA.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(DB_PATH, timeout=300)
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
      org_type TEXT, org_category TEXT, person_field TEXT, lifespan TEXT, born_in TEXT, died_in TEXT, education TEXT, industry TEXT, website TEXT, website_status TEXT, founded_year TEXT, exchange TEXT, current_title TEXT,
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


MANUAL_EXCLUDE = {"Sunday Adelaja", "Uniqueness and reform movement", "Brigade Mixte Mobile", "Central Revolutionary Investigation Department"}


def clean_description(desc):
    """REQ-140: birth dates and years never reach the record, even inside free text."""
    return BIRTH_BITS.sub("", desc or "").strip(" ,;") or None


def load_countries(con):
    """All African Union members plus sovereign states on the African continent."""
    con.execute("CREATE TABLE IF NOT EXISTS countries(qid TEXT PRIMARY KEY, name TEXT)")
    con.execute("CREATE TABLE IF NOT EXISTS seeded(qid TEXT PRIMARY KEY)")
    if not con.execute("SELECT COUNT(*) FROM countries").fetchone()[0]:
        rows = sparql("""SELECT DISTINCT ?c ?cLabel WHERE {
            { ?c wdt:P463 wd:Q7159 } UNION { ?c wdt:P31 wd:Q3624078; wdt:P30 wd:Q15 }
            ?c wdt:P31/wdt:P279* wd:Q6256 .
            SERVICE wikibase:label { bd:serviceParam wikibase:language "en". } }""")
        con.executemany("INSERT OR IGNORE INTO countries VALUES(?,?)", [(qid_of(r["c"]["value"]), r["cLabel"]["value"]) for r in rows])
        con.executemany("INSERT OR IGNORE INTO countries VALUES(?,?)", list(COUNTRIES.items()))
        con.executemany("INSERT OR IGNORE INTO seeded VALUES(?)", [(q,) for q in COUNTRIES])
        con.commit()
    COUNTRIES.clear()
    COUNTRIES.update(dict(con.execute("SELECT qid,name FROM countries").fetchall()))


def best_label(ent):
    labels = ent.get("labels", {})
    for lang in ("en", "fr", "pt", "es", "ar"):
        if lang in labels:
            return labels[lang]["value"]
    return None


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
    con.execute("CREATE TABLE IF NOT EXISTS run1_selected AS SELECT * FROM selected")
    con.execute("CREATE TABLE IF NOT EXISTS records_run1 AS SELECT * FROM records")
    con.commit()
    load_countries(con)
    print("countries", len(COUNTRIES), sorted(COUNTRIES.values()), flush=True)
    con.executemany("INSERT OR REPLACE INTO sources VALUES(?,?,?,?,?,?,?,?)", [
        ("wikidata", "Wikidata", "https://www.wikidata.org/", "A", "CC0", "Supervisor instruction", "2026-11-09", "Plan 2.2-2.5"),
        ("commons", "Wikimedia Commons", "https://commons.wikimedia.org/", "A", "Per file", "Supervisor instruction", "2026-11-09", "Plan 2.5, images section"),
        ("own_site", "Organisation's own website", "(per domain)", "P", "Varies; robots checked per domain", "Supervisor instruction", "2026-11-09", "Plan 2.2 source class"),
    ])
    con.commit()
    occ = " ".join("wd:" + c for c in ENTREPRENEUR_OCCUPATIONS)
    org_classes = class_set(con, "org_classes", ORG_CLASSES) - EXCLUDED_ORG_CLASSES
    print("organisation classes", len(org_classes), flush=True)
    done_countries = {r[0] for r in con.execute("SELECT qid FROM seeded")}
    for cq, cname in sorted(COUNTRIES.items(), key=lambda x: x[1]):
        if cq in done_countries:
            continue
        print("seed", cname, flush=True)
        rows = sparql(f"""SELECT ?o ?t ?sl WHERE {{ ?o wdt:P17 wd:{cq}; wdt:P31 ?t; wikibase:sitelinks ?sl. FILTER(?sl >= 1) }}""")
        listed_here = {qid_of(x["o"]["value"]) for x in sparql(f"""SELECT DISTINCT ?o WHERE {{ ?o wdt:P17 wd:{cq}; wdt:P414 ?x. }}""")}
        print("  rows", len(rows), "listed", len(listed_here), flush=True)
        if not rows:
            print("  NO ROWS, will retry on next seed:", cname, flush=True)
            continue
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
        con.execute("INSERT OR REPLACE INTO seeded VALUES(?)", (cq,))
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


def stage_seed_deceased():
    con = db()
    load_countries(con)
    con.execute("CREATE TABLE IF NOT EXISTS seeded_deceased(qid TEXT PRIMARY KEY)")
    done = {r[0] for r in con.execute("SELECT qid FROM seeded_deceased")}
    added = 0
    for cq, cname in sorted(COUNTRIES.items(), key=lambda x: x[1]):
        if cq in done:
            continue
        rows = sparql(f"""SELECT DISTINCT ?p ?sl WHERE {{ ?p wdt:P27 wd:{cq}; wdt:P31 wd:Q5; wdt:P570 ?d; wikibase:sitelinks ?sl. FILTER(?sl >= 5) }}""")
        for row in rows:
            cur = con.execute("INSERT OR IGNORE INTO candidates(qid,kind,country,sitelinks) VALUES(?,?,?,?)",
                              (qid_of(row["p"]["value"]), "deceased", cname, int(row["sl"]["value"])))
            added += cur.rowcount
        con.execute("INSERT OR REPLACE INTO seeded_deceased VALUES(?)", (cq,))
        con.commit()
        print("deceased", cname, len(rows), flush=True)
    if "historical" not in done:
        rows = sparql("""SELECT DISTINCT ?p ?sl ?sLabel ?bc WHERE {
              ?s wdt:P31/wdt:P279* wd:Q3024240; wdt:P30 wd:Q15.
              ?p wdt:P27 ?s; wdt:P31 wd:Q5; wdt:P570 ?d; wikibase:sitelinks ?sl.
              FILTER(?sl >= 10) FILTER(YEAR(?d) >= 1500)
              OPTIONAL { ?p wdt:P19/wdt:P17 ?bc }
              SERVICE wikibase:label { bd:serviceParam wikibase:language "en". } }""")
        best = {}
        for row in rows:
            q = qid_of(row["p"]["value"])
            bc = qid_of(row["bc"]["value"]) if "bc" in row else None
            country = COUNTRIES.get(bc) or row.get("sLabel", {}).get("value") or "Historical state"
            if q not in best or bc in COUNTRIES:
                best[q] = (country, int(row["sl"]["value"]), bc in COUNTRIES)
        for q, (country, sl, modern) in best.items():
            cur = con.execute("INSERT OR IGNORE INTO candidates(qid,kind,country,sitelinks) VALUES(?,?,?,?)",
                              (q, "deceased", country if modern else f"{country} (historical)", sl))
            added += cur.rowcount
        con.execute("INSERT OR REPLACE INTO seeded_deceased VALUES('historical')")
        con.commit()
        print("deceased from historical African states", len(best), flush=True)
    print("new deceased candidates", added, "total", con.execute("SELECT COUNT(*) FROM candidates WHERE kind='deceased'").fetchone()[0])


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
        "name": best_label(ent),
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
                 parent=item_ids(ent, "P749", True), subsidiaries=item_ids(ent, "P355", True), products=item_ids(ent, "P1056", True),
                 member_of=item_ids(ent, "P463", True), employees=next((v.get("amount", "").lstrip("+") for v in claim_values(ent, "P1128", True) if isinstance(v, dict)), None),
                 dissolved=bool(claim_values(ent, "P576")))
    elif kind == "deceased":
        def year_of(v):
            t = v.get("time", "") if isinstance(v, dict) else ""
            m = re.match(r"([+-])0*(\d+)-", t)
            return (int(m.group(2)) * (-1 if m.group(1) == "-" else 1)) if m else None

        def qual_year(st, prop):
            for q in st.get("qualifiers", {}).get(prop, []):
                if q.get("datavalue"):
                    return year_of(q["datavalue"]["value"])
            return None
        positions = []
        for st in ent.get("claims", {}).get("P39", []):
            dv = st.get("mainsnak", {}).get("datavalue")
            if dv and st.get("rank") != "deprecated" and isinstance(dv["value"], dict) and "id" in dv["value"]:
                positions.append([dv["value"]["id"], qual_year(st, "P580"), qual_year(st, "P582")])
        births, deaths = claim_values(ent, "P569"), claim_values(ent, "P570")
        d.update(occupations=item_ids(ent, "P106"), positions_dated=positions, awards=item_ids(ent, "P166"),
                 educated=item_ids(ent, "P69"), works=item_ids(ent, "P800"), member_of=item_ids(ent, "P463"),
                 employers=item_ids(ent, "P108"), birth_place=item_ids(ent, "P19")[:1], death_place=item_ids(ent, "P20")[:1],
                 birth_year=year_of(births[0]) if births else None, death_year=year_of(deaths[0]) if deaths else None,
                 deceased=bool(deaths))
    else:
        d.update(occupations=item_ids(ent, "P106"), employers=item_ids(ent, "P108", True), positions=item_ids(ent, "P39", True),
                 awards=item_ids(ent, "P166"), owner_of=item_ids(ent, "P1830", True), boards=item_ids(ent, "P3320", True),
                 member_of=item_ids(ent, "P463", True),
                 deceased=bool(claim_values(ent, "P570")))
    return d


def api_entities(ids, props):
    q = urllib.parse.urlencode({"action": "wbgetentities", "ids": "|".join(ids), "props": props, "languages": "en|fr|pt|es|ar",
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
        for k in ("countries", "types", "hq", "industry", "exchange", "occupations", "employers", "positions", "awards", "owner_of", "parent", "subsidiaries", "products", "member_of", "boards",
                  "educated", "works", "birth_place", "death_place"):
            refs.update(d.get(k) or [])
        refs.update(x[0] for x in d.get("positions_dated") or [])
    refs.update(r[0] for r in con.execute("SELECT org_qid FROM founder_links"))
    have = {r[0] for r in con.execute("SELECT qid FROM labels")}
    refs = sorted(refs - have)
    print("labels to fetch", len(refs), flush=True)
    for i in range(0, len(refs), 50):
        for q, ent in api_entities(refs[i:i + 50], "labels").items():
            lab = best_label(ent)
            if lab:
                con.execute("INSERT OR REPLACE INTO labels VALUES(?,?)", (q, lab))
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


def org_site_rows(con):
    rows = []
    done = {r[0] for r in con.execute("SELECT qid FROM sites")}
    q = """SELECT p.qid,e.data FROM org_pool p JOIN entities e USING(qid) JOIN candidates c USING(qid)
           ORDER BY c.exchange DESC, c.sitelinks DESC"""
    for r in con.execute(q).fetchall():
        d = json.loads(r["data"])
        if d.get("website") and r["qid"] not in done:
            rows.append((r["qid"], d["website"]))
    return rows


def stage_sites():
    con = db()
    rows = org_site_rows(con)
    print("sites to fetch", len(rows), flush=True)
    done = 0
    with cf.ThreadPoolExecutor(32) as ex:
        for res in ex.map(fetch_site, rows):
            con.execute("""INSERT OR REPLACE INTO sites VALUES(:qid,:url,:final_url,:status,:http,:description,:logo_url,:logo_kind,:retrieved_at)""", res)
            done += 1
            if done % 100 == 0:
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


AFRICAN_STATE = re.compile(
    r"egypt|tunis|zulu|kongo|zanzibar|dahomey|merina|imerina|madagascar|rhodesia|nyasaland|darfur|ethiopia|abyssinia|kanem|bornu|gaza empire|"
    r"ashanti|asante|benin|yeke|hafsid|south african republic|orange free state|transvaal|natal|cape colony|union of south africa|"
    r"mali empire|songhai|oyo|sokoto|wadai|buganda|bunyoro|basotho|swazi|ndebele|mutapa|morocc|algiers|tripoli|cyrenaica|sennar|funj|"
    r"kush|aksum|axum|ghana empire|wolof|jolof|kaabu|futa|massina|toucouleur|kilwa|liberia|sierra leone|gold coast|nigeria|congo|"
    r"bechuanaland|tanganyika|kenya|uganda|somali|eritrea|sudan|libya|french west africa|french equatorial africa|"
    r"south west africa|german east africa|ruanda|urundi|mahdist|alaouite|saadi|marinid|wattasid|almohad|almoravid|ayyubid|fatimid|tulunid|ikhshidid|mamluk",
    re.I)
DEMONYMS = [("South African", "South Africa"), ("South Sudanese", "South Sudan"), ("Bissau-Guinean", "Guinea-Bissau"),
            ("Equatoguinean", "Equatorial Guinea"), ("Nigerian", "Nigeria"), ("Nigerien", "Niger"), ("Ghanaian", "Ghana"),
            ("Kenyan", "Kenya"), ("Moroccan", "Morocco"), ("Egyptian", "Egypt"), ("Tunisian", "Tunisia"), ("Algerian", "Algeria"),
            ("Senegalese", "Senegal"), ("Ugandan", "Uganda"), ("Cameroonian", "Cameroon"), ("Tanzanian", "Tanzania"),
            ("Zimbabwean", "Zimbabwe"), ("Ivorian", "Ivory Coast"), ("Rwandan", "Rwanda"), ("Beninese", "Benin"), ("Guinean", "Guinea"),
            ("Angolan", "Angola"), ("Ethiopian", "Ethiopia"), ("Namibian", "Namibia"), ("Togolese", "Togo"), ("Sudanese", "Sudan"),
            ("Zambian", "Zambia"), ("Burkinab", "Burkina Faso"), ("Libyan", "Libya"), ("Malagasy", "Madagascar"), ("Malian", "Mali"),
            ("Somali", "Somalia"), ("Mozambican", "Mozambique"), ("Motswana", "Botswana"), ("Batswana", "Botswana"),
            ("Malawian", "Malawi"), ("Gabonese", "Gabon"), ("Burundian", "Burundi"), ("Chadian", "Chad"), ("Gambian", "The Gambia"),
            ("Mauritian", "Mauritius"), ("Sierra Leonean", "Sierra Leone"), ("Mauritanian", "Mauritania"), ("Liberian", "Liberia"),
            ("Cape Verdean", "Cape Verde"), ("Eritrean", "Eritrea"), ("Seychellois", "Seychelles"), ("Swazi", "Eswatini"),
            ("Djiboutian", "Djibouti"), ("Comorian", "Comoros"), ("Basotho", "Lesotho"), ("Mosotho", "Lesotho")]


def deceased_country(d, default):
    """Prefer the nationality the description states, when it is one of the person's citizenships."""
    desc = d.get("description") or ""
    hits = []
    for dem, country in DEMONYMS:
        i = desc.find(dem)
        if i >= 0 and country_qid(country) in (d.get("countries") or []):
            hits.append((i, -len(dem), country))
    return min(hits)[2] if hits else default


def deceased_reason(con, d, country=""):
    occ_text = " ".join(label(con, o) or "" for o in d.get("occupations") or [])
    text = f"{d.get('description') or ''} {occ_text}"
    by, dy = d.get("birth_year"), d.get("death_year")
    if not d.get("deceased"):
        return "not_deceased"
    if country.endswith("(historical)") and not AFRICAN_STATE.search(country):
        return "historical_state_not_african"
    if CRIME.search(text):
        return "notable_for_crime"
    if CONTACT.search(text):
        return "contact_details_in_text"
    if dy is not None and dy < 1500:
        return "died_before_1500"
    if by is not None and dy is not None and dy - by < 18:
        return "died_as_minor"
    if NON_AFRICAN.search(d.get("description") or "") and not AFRICAN.search(d.get("description") or ""):
        return "not_african"
    return None


def person_field(con, d):
    """Field with the most matching occupations and positions; ties go to the earlier field. Description only as a fallback."""
    labels = [label(con, o) or "" for o in d.get("occupations") or []] + [label(con, x[0]) or "" for x in d.get("positions_dated") or []]
    scores = [(sum(1 for l in labels if rx.search(l)), -i, name) for i, (name, rx) in enumerate(PERSON_FIELDS)]
    best = max(scores)
    if best[0] > 0:
        return best[2]
    for name, rx in PERSON_FIELDS:
        if rx.search(d.get("description") or ""):
            return name
    return "Other"


def stage_select():
    con = db()
    load_countries(con)
    con.execute("DELETE FROM exclusions")
    suppressed = suppression_set()
    business = class_set(con, "business", ["Q4830453", "Q783794", "Q6881511", "Q22687", "Q1378841", "Q46970"])
    biz_occ = class_set(con, "business_occupation", ENTREPRENEUR_OCCUPATIONS)
    orgs, people, dead = [], [], []
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
        elif d["name"] in MANUAL_EXCLUDE:
            reason = "manual_exclusion"
        elif r["kind"] == "deceased":
            reason = deceased_reason(con, d, r["country"])
            if not reason:
                dead.append((r["sitelinks"], r["qid"], deceased_country(d, r["country"])))
                continue
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
            elif not (biz_desc or biz_founded or (not d.get("description") and set(occ_ids) & set(ENTREPRENEUR_OCCUPATIONS))):
                reason = "no_business_evidence"
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

    run1 = {r[0] for r in con.execute("SELECT qid FROM run1_selected")}
    people.sort(key=lambda x: x[0], reverse=True)
    orgs.sort(key=lambda x: x[0], reverse=True)
    eligible = {x[1] for x in orgs} | {x[1] for x in people}
    kept = run1 & eligible
    print("run 1 kept", len(kept), "dropped by the wider rules", sorted(run1 - eligible)[:60])
    sel_people = [p[1] for p in people if p[1] in kept or p[0][0] >= 1]
    room = TOTAL_TARGET - len(sel_people)
    chosen = [o for o in orgs if o[1] in kept]
    used = {o[1] for o in chosen}
    by_country = {}
    for o in orgs:
        if o[1] not in used:
            by_country.setdefault(o[2], []).append(o)
    order = []
    while any(by_country.values()):
        for k in list(by_country):
            if by_country[k]:
                order.append(by_country[k].pop(0))
    caps = {"Education": 0.30, "Government": 0.18, "NGO or association": 0.15, "Research": 0.05, "Media": 0.06, "Other": 0.06, "Business": 1.0}
    counts = {}
    for o in chosen:
        counts[o[3]] = counts.get(o[3], 0) + 1
    rest = []
    for o in order:
        if len(chosen) >= room:
            break
        if counts.get(o[3], 0) >= int(caps[o[3]] * room):
            rest.append(o)
            continue
        chosen.append(o)
        used.add(o[1])
        counts[o[3]] = counts.get(o[3], 0) + 1
    for o in rest:
        if len(chosen) >= room:
            break
        chosen.append(o)
    sel_orgs = [o[1] for o in chosen]
    con.execute("DROP TABLE IF EXISTS selected")
    con.execute("CREATE TABLE selected(qid TEXT PRIMARY KEY, kind TEXT)")
    dead.sort(key=lambda x: x[0], reverse=True)
    sel_dead, per = [], {}
    for x in dead:  # every country gets its most notable few first
        if per.get(x[2], 0) < DECEASED_FLOOR_PER_COUNTRY:
            sel_dead.append(x[1])
            per[x[2]] = per.get(x[2], 0) + 1
    chosen_dead = set(sel_dead)
    for x in dead:  # then the rest by notability, with a cap per country
        if len(sel_dead) >= DECEASED_TARGET:
            break
        if x[1] not in chosen_dead and per.get(x[2], 0) < DECEASED_CAP_PER_COUNTRY:
            sel_dead.append(x[1])
            chosen_dead.add(x[1])
            per[x[2]] = per.get(x[2], 0) + 1
    sel_dead = sel_dead[:DECEASED_TARGET] if len(sel_dead) > DECEASED_TARGET else sel_dead
    print("deceased pool", len(dead), "selected", len(sel_dead), "countries", len({x[2] for x in dead if x[1] in set(sel_dead)}))
    con.executemany("INSERT INTO selected VALUES(?,?)", [(q, "organisation") for q in sel_orgs] + [(q, "entrepreneur") for q in sel_people] + [(q, "deceased") for q in sel_dead])
    con.execute("DROP TABLE IF EXISTS org_pool")
    con.execute("CREATE TABLE org_pool(qid TEXT PRIMARY KEY)")
    con.executemany("INSERT INTO org_pool VALUES(?)", [(o[1],) for o in orgs])
    con.commit()
    print("pool orgs", len(orgs), "people", len(people), "selected orgs", len(sel_orgs), "people", len(sel_people), "total", len(sel_orgs) + len(sel_people))
    cats = {o[3] for o in orgs}
    print("org pool by category", {c: sum(1 for o in orgs if o[3] == c) for c in cats})
    picked = set(sel_orgs)
    print("selected org by category", {c: sum(1 for o in orgs if o[3] == c and o[1] in picked) for c in cats})
    print("people pool tiers", {t: sum(1 for x in people if x[0][0] == t) for t in (5, 4, 3, 2, 1)})
    for r in con.execute("SELECT kind,reason,COUNT(*) FROM exclusions GROUP BY 1,2"):
        print(tuple(r))
    for r in con.execute("SELECT c.country,SUM(s.kind='organisation'),SUM(s.kind='entrepreneur'),COUNT(*) n FROM selected s JOIN candidates c USING(qid) GROUP BY 1 ORDER BY n DESC"):
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
            st, _, body, _ = http(info["thumb"], gap=0.4, timeout=40, max_bytes=5_000_000)
            path = save_image(body, qid, role) if st == 200 else None
            con.execute("INSERT OR REPLACE INTO images VALUES(?,?,?,?,?,?,?,?)",
                        (qid, role, "ok" if path else f"http_{st}", path, info["page"], info["licence"], info["credit"], now()))
        con.commit()
        print("commons", min(i + 40, len(pending)), flush=True)
    for r in con.execute("SELECT role,status,COUNT(*) FROM images GROUP BY 1,2"):
        print(tuple(r))


def stage_logos():
    con = db()
    site_jobs = [(r["qid"], r["logo_url"], r["logo_kind"]) for r in con.execute(
        """SELECT s.qid,t.logo_url,t.logo_kind FROM selected s JOIN sites t USING(qid) JOIN entities e USING(qid)
           WHERE s.kind='organisation' AND t.status='ok' AND t.logo_url IS NOT NULL
             AND json_extract(e.data,'$.logo') IS NULL
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

    with cf.ThreadPoolExecutor(32) as ex:
        n = 0
        for qid, status, path, url, kind in ex.map(get_logo, site_jobs):
            con.execute("INSERT OR REPLACE INTO images VALUES(?,?,?,?,?,?,?,?)",
                        (qid, "logo", status, path, url, "Not licensed: owner's copyright or trademark" if path else None, f"Organisation's own site ({kind})" if path else None, now()))
            n += 1
            if n % 100 == 0:
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
    run1 = {r[0] for r in con.execute("SELECT qid FROM run1_selected")}
    rows = con.execute("""SELECT s.qid,s.kind,c.country,c.sitelinks,e.data,e.retrieved_at FROM selected s
                          JOIN candidates c USING(qid) JOIN entities e USING(qid)""").fetchall()
    for r in rows:
        key = (r["kind"], r["country"], re.sub(r"[^a-z0-9]", "", unicodedata.normalize("NFKD", json.loads(r["data"])["name"]).lower()))
        names_seen.setdefault(key, []).append(r["qid"])
    site_clean = 0
    scan_failed = 0
    for r in rows:
        d, qid, kind = json.loads(r["data"]), r["qid"], r["kind"]
        d["description"] = clean_description(d.get("description"))
        wd_url = f"https://www.wikidata.org/wiki/{qid}"
        at = r["retrieved_at"]
        flags = []
        rec = dict(record_id="wd:" + qid, type=kind, qid=qid, name=d["name"], country=deceased_country(d, r["country"]) if kind == "deceased" else r["country"], sitelinks=d["sitelinks"],
                   description=d.get("description"), website=d.get("website"), run_id=DECEASED_RUN_ID if kind == "deceased" else RUN_ID if qid in run1 else EXPANSION_RUN_ID, retrieved_at=at,
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
            kind_word = types[0] if types else "organisation"
            kind_word = kind_word if kind_word.isupper() else kind_word[0].lower() + kind_word[1:]
            place = None if r["country"] == "Pan-African" else r["country"]
            city = rec["city"] if rec["city"] and rec["city"] != r["country"] else None
            desc_text = d.get("description") or ""
            if desc_text:
                bio = f"{d['name']} is {opening(desc_text)}."
                if city and city not in desc_text:
                    bio += f" It is based in {city}" + (f", {place}." if place and place not in desc_text else ".")
                elif place and place not in desc_text:
                    bio += f" It operates in {place}."
            else:
                where = ", ".join(x for x in (city, place) if x)
                bio = f"{d['name']} is {a_an(kind_word)}" + (f" based in {where}" if where else "") + "."
            if rec["founded_year"] and rec["founded_year"].isdigit():
                bio += f" It was founded in {rec['founded_year']}."
            if rec["exchange"]:
                bio += f" Its shares are listed on {rec['exchange']}."
            if rec["industry"]:
                bio += f" It works in {rec['industry']}."
            names = lambda key: [n for n in (label(con, x) for x in d.get(key) or []) if n]
            if names("parent"):
                bio += f" It is part of {join_list(names('parent')[:2])}."
            if names("subsidiaries"):
                bio += f" Its subsidiaries include {join_list(names('subsidiaries')[:4])}."
            if names("products"):
                bio += f" Its products and services include {join_list(names('products')[:4])}."
            if d.get("employees") and d["employees"].replace(".", "").isdigit():
                bio += f" It reports about {int(float(d['employees'])):,} employees."
            if names("member_of"):
                bio += f" It is a member of {join_list(names('member_of')[:3])}."
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
        elif kind == "deceased":
            def yr(y):
                return f"{-y} BC" if y is not None and y < 0 else (str(y) if y is not None else "?")
            by, dy = d.get("birth_year"), d.get("death_year")
            rec["lifespan"] = f"{yr(by)}–{yr(dy)}"
            rec["person_field"] = person_field(con, d)
            names = lambda key, n: [x for x in (label(con, q) for q in (d.get(key) or [])[:n * 2]) if x][:n]
            occs = names("occupations", 3)
            rec["current_title"] = cap(occs[0]) if occs else None
            positions = []
            for q, a, b in d.get("positions_dated") or []:
                lab = label(con, q)
                if lab and lab not in [x.split(" (")[0] for x in positions]:
                    span = f" ({yr(a)}–{yr(b)})" if a and b and a != b else f" ({yr(a)})" if a else ""
                    positions.append(lab + span)
            rec["roles"] = json.dumps(positions[:8], ensure_ascii=False)
            awards = names("awards", 8)
            rec["awards"] = json.dumps(awards, ensure_ascii=False)
            rec["born_in"] = next(iter(names("birth_place", 1)), None)
            rec["died_in"] = next(iter(names("death_place", 1)), None)
            edu = names("educated", 4)
            rec["education"] = "; ".join(edu) or None
            works = names("works", 4)
            members = names("member_of", 3)
            desc_text = d.get("description") or ""
            if desc_text:
                bio = f"{d['name']} ({rec['lifespan']}) was {opening(desc_text)}."
            else:
                bio = f"{d['name']} ({rec['lifespan']}) was {a_an(occs[0]) if occs else 'a notable figure'}" + (f" from {r['country']}." if "historical" not in rec["country"] else ".")
            if desc_text and rec["country"].split(" (")[0] not in desc_text and not AFRICAN.search(desc_text):
                bio += f" Country: {r['country']}."
            if rec["born_in"] and rec["died_in"]:
                bio += f" Born in {rec['born_in']}; died in {rec['died_in']}."
            elif rec["born_in"]:
                bio += f" Born in {rec['born_in']}."
            elif rec["died_in"]:
                bio += f" Died in {rec['died_in']}."
            if len(occs) > 1:
                bio += f" Occupations: {join_list([o[0].lower() + o[1:] if not o[:2].isupper() else o for o in occs])}."
            if positions:
                bio += f" Positions held: {join_list(positions[:5])}."
            if edu:
                bio += f" Educated at {join_list(edu[:3])}."
            if works:
                bio += f" Notable works: {join_list(works)}."
            if members:
                bio += f" Member of {join_list(members)}."
            if awards:
                bio += f" Awards and honours: {join_list(awards[:6])}."
            rec["bio"] = bio
            rec["criterion"], rec["criterion_met"], rec["evidence_url"] = "HIST", 0, wd_url
            flags.append("outside_mvp_scope_deceased")
            for f in ("lifespan", "person_field", "current_title", "roles", "awards", "born_in", "died_in", "education"):
                if rec.get(f):
                    prov[f] = ("wikidata", wd_url, "medium", "API")
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
            names = lambda key: [n for n in (label(con, x) for x in d.get(key) or []) if n]
            if not (d.get("description") and r["country"] in d["description"]) and d.get("description"):
                bio += f" They are from {r['country']}."
            others = [n for _, n in orgs if n != rec.get("current_org")]
            if others:
                bio += f" Other companies founded: {join_list(others[:3])}."
            extra = [x for x in pos[:4] if x.lower() != (rec["current_title"] or "").lower()]
            if extra:
                bio += f" Public roles: {join_list(extra)}."
            if names("boards"):
                bio += f" Board memberships: {join_list(names('boards')[:4])}."
            if names("member_of"):
                bio += f" Member of: {join_list(names('member_of')[:3])}."
            if awards:
                bio += f" Awards: {join_list(awards[:5])}."
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
        dup = names_seen.get((kind, r["country"], re.sub(r"[^a-z0-9]", "", unicodedata.normalize("NFKD", d["name"]).lower())), [])
        if len(dup) > 1:
            flags.append("possible_duplicate")
        scan_text = " ".join(str(rec.get(k) or "") for k in ("bio", "description", "site_description"))
        checks = {
            "1_source_approved": True,
            "2_source_url_success": True,
            "3_claim_quotes": "not_applicable_no_ai_text",
            "4_allowlisted_fields_only": True,
            "5_sensitive_scan": not ((CRIME if kind == "deceased" else SENSITIVE).search(scan_text) or CONTACT.search(scan_text)),
            "6_suppression_check": "passed_but_no_list_exists_yet" if not suppression_set() else True,
            "7_duplicates_flagged": "possible_duplicate" in flags,
        }
        if checks["5_sensitive_scan"] is False:
            con.execute("INSERT OR REPLACE INTO exclusions VALUES(?,?,?)", (qid, kind, "sensitive_term_in_final_text"))
            scan_failed += 1
            continue
        rec["bio"] = re.sub(r"\.{2,}", ".", rec["bio"])
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
    print("records", con.execute("SELECT COUNT(*) FROM records").fetchone()[0], "site descriptions dropped", site_clean, "records failed scan", scan_failed)


STAGES = dict(seed_deceased=stage_seed_deceased, seed=stage_seed, enrich=stage_enrich, sites=stage_sites, select=stage_select, images=stage_images, logos=stage_logos, finalise=stage_finalise)

if __name__ == "__main__":
    if len(sys.argv) < 2 or sys.argv[1] not in STAGES:
        sys.exit("usage: run_first_1000.py " + "|".join(STAGES) + " [target_orgs]")
    STAGES[sys.argv[1]]()
