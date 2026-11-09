#!/usr/bin/env python3
"""Write acquisition/docs/data-breakdown.md: how the staging records are grouped.

Counts only. It names countries and categories, never people or organisations, so the file
holds no personal data and can be committed. Run it again after every run to refresh it.
Run from the repo root: python -I acquisition/pipeline/breakdown.py
"""
import pathlib
import sqlite3
import time

ROOT = pathlib.Path(__file__).resolve().parents[2]
DB = ROOT / "acquisition" / "data" / "staging.sqlite"
OUT = ROOT / "acquisition" / "docs" / "data-breakdown.md"

REGIONS = {
    "North Africa": ["Algeria", "Egypt", "Libya", "Morocco", "Sudan", "Tunisia", "Sahrawi Arab Democratic Republic"],
    "West Africa": ["Benin", "Burkina Faso", "Cape Verde", "Ivory Coast", "The Gambia", "Ghana", "Guinea", "Guinea-Bissau",
                    "Liberia", "Mali", "Mauritania", "Niger", "Nigeria", "Senegal", "Sierra Leone", "Togo"],
    "East Africa": ["Burundi", "Comoros", "Djibouti", "Eritrea", "Ethiopia", "Kenya", "Madagascar", "Mauritius", "Rwanda",
                    "Seychelles", "Somalia", "South Sudan", "Tanzania", "Uganda"],
    "Central Africa": ["Cameroon", "Central African Republic", "Chad", "Republic of the Congo", "Democratic Republic of the Congo",
                       "Equatorial Guinea", "Gabon", "São Tomé and Príncipe"],
    "Southern Africa": ["Angola", "Botswana", "Eswatini", "Lesotho", "Malawi", "Mozambique", "Namibia", "South Africa",
                        "Zambia", "Zimbabwe"],
}
REGION_OF = {c: r for r, cs in REGIONS.items() for c in cs}
TYPES = [("organisation", "Organisations"), ("entrepreneur", "Entrepreneurs"), ("deceased", "Notable people (deceased)")]
WEBSITE = {"ok": "Reached", "no_website": "No website listed", "unreachable": "Unreachable", "http_error": "Site returned an error",
           "bad_url": "Domain not found or invalid", "robots_blocked": "Not fetched: blocked by robots.txt", "not_checked": "Not checked"}
CRITERIA = {"O1": "O1: listed on a stock exchange", "O5": "O5: has a Wikipedia article (via Wikidata)",
            "E1": "E1: founder of a listed company", "LEAD": "Lead: E1 or E2 not yet shown",
            "HIST": "Historical figure: outside the plan criteria"}


def region(country):
    if country == "Pan-African":
        return "Pan-African bodies"
    if country.endswith("(historical)"):
        return "Historical African states"
    return REGION_OF.get(country, "Unassigned")


def fmt(n):
    return f"{n:,}"


def table(headers, rows, align=None):
    align = align or ["---"] + ["---:"] * (len(headers) - 1)
    out = ["| " + " | ".join(headers) + " |", "|" + "|".join(align) + "|"]
    out += ["| " + " | ".join(str(c) for c in r) + " |" for r in rows]
    return "\n".join(out)


def main():
    con = sqlite3.connect(DB)
    q = lambda sql, *a: con.execute(sql, a).fetchall()
    one = lambda sql, *a: con.execute(sql, a).fetchone()[0]
    total = one("SELECT COUNT(*) FROM records")
    by_type = dict(q("SELECT type, COUNT(*) FROM records GROUP BY 1"))
    countries = q("SELECT country, type, COUNT(*) FROM records GROUP BY 1, 2")
    L = []
    w = L.append
    w("# Data breakdown: how the staging records are grouped")
    w("")
    w(f"Generated {time.strftime('%Y-%m-%d %H:%M')} from `acquisition/data/staging.sqlite` by `acquisition/pipeline/breakdown.py`. "
      "Run it again after each run to refresh this file: `python -I acquisition/pipeline/breakdown.py`.")
    w("")
    w("**Unreviewed staging data.** Every grouping below is assigned by script from Wikidata. No person has checked it. "
      "This file holds counts only, with no names.")
    w("")
    w("## 1. Totals")
    w("")
    w(table(["Type", "Records"], [[label, fmt(by_type.get(k, 0))] for k, label in TYPES] + [["**Total**", f"**{fmt(total)}**"]]))
    w("")
    runs = q("SELECT run_id, type, COUNT(*) FROM records GROUP BY 1, 2 ORDER BY 1")
    run_rows = {}
    for run, t, n in runs:
        run_rows.setdefault(run, {})[t] = n
    w(table(["Run"] + [l for _, l in TYPES] + ["Total"],
            [[f"`{run}`"] + [fmt(v.get(k, 0)) for k, _ in TYPES] + [fmt(sum(v.values()))] for run, v in run_rows.items()]))
    w("")

    w("## 2. Region")
    w("")
    reg = {}
    reg_countries = {}
    for c, t, n in countries:
        r = region(c)
        reg.setdefault(r, {})[t] = reg.get(r, {}).get(t, 0) + n
        reg_countries.setdefault(r, set()).add(c)
    rows = sorted(reg.items(), key=lambda x: -sum(x[1].values()))
    w(table(["Region"] + [l for _, l in TYPES] + ["Total", "Countries"],
            [[r] + [fmt(v.get(k, 0)) for k, _ in TYPES] + [fmt(sum(v.values())), len(reg_countries[r])] for r, v in rows]))
    w("")
    w("Regions follow the African Union's five regions, simplified: Mauritania sits in West Africa, Sudan in North Africa. "
      "\"Historical African states\" holds deceased people whose only African citizenship is a former state, where the birthplace "
      "does not map to a present-day country.")
    w("")

    w("## 3. Country")
    w("")
    per = {}
    for c, t, n in countries:
        per.setdefault(c, {})[t] = n
    rows = sorted(per.items(), key=lambda x: (-sum(x[1].values()), x[0]))
    w(table(["Country", "Region"] + [l for _, l in TYPES] + ["Total"],
            [[c, region(c)] + [fmt(v.get(k, 0)) for k, _ in TYPES] + [fmt(sum(v.values()))] for c, v in rows],
            ["---", "---"] + ["---:"] * (len(TYPES) + 1)))
    w("")
    bands = {"500 or more": 0, "100 to 499": 0, "50 to 99": 0, "Under 50": 0}
    band_records = dict.fromkeys(bands, 0)
    for c, v in per.items():
        n = sum(v.values())
        b = "500 or more" if n >= 500 else "100 to 499" if n >= 100 else "50 to 99" if n >= 50 else "Under 50"
        bands[b] += 1
        band_records[b] += n
    w(table(["Records per country", "Countries", "Records"], [[b, bands[b], fmt(band_records[b])] for b in bands]))
    w("")

    w("## 4. Organisations by category")
    w("")
    w("The category comes from the organisation's Wikidata type. Rules are checked in this order and the first match wins: "
      "NGO or association, Media, Education, Research, Government, then Business if the type is a kind of business, else Other.")
    w("")
    cats = q("""SELECT org_category, COUNT(*), SUM(website_status='ok'), SUM(logo_path IS NOT NULL), SUM(photo_path IS NOT NULL)
                FROM records WHERE type='organisation' GROUP BY 1 ORDER BY 2 DESC""")
    w(table(["Category", "Records", "Website reached", "With logo", "With photo"], [[c or "(none)", fmt(n), fmt(a), fmt(b), fmt(p)] for c, n, a, b, p in cats]))
    w("")
    w("**Most common Wikidata types in each category:**")
    w("")
    for c, *_ in cats:
        top = q("SELECT org_type, COUNT(*) n FROM records WHERE type='organisation' AND org_category IS ? GROUP BY 1 ORDER BY n DESC LIMIT 8", c)
        w(f"- **{c}:** " + ", ".join(f"{t} {fmt(n)}" for t, n in top))
    w("")

    w("## 5. Entrepreneurs by title")
    w("")
    w("The title is the founder or owner link if Wikidata has one, else a current position, else the business occupation.")
    w("")
    titles = q("SELECT current_title, COUNT(*) n FROM records WHERE type='entrepreneur' GROUP BY 1 ORDER BY n DESC")
    top, rest = titles[:10], titles[10:]
    rows = [[t or "(none)", fmt(n)] for t, n in top]
    if rest:
        rows.append([f"Other titles ({len(rest)})", fmt(sum(n for _, n in rest))])
    w(table(["Title", "Records"], rows))
    w("")

    w("## 6. Notable people (deceased) by field")
    w("")
    w("The field is the one that matches most of the person's Wikidata occupations and positions held. A tie goes to the earlier "
      "field in this order: royalty, politics, activism, military, religion, science, literature, music, film, visual arts, sport, "
      "business, law. If nothing matches, the Wikidata description decides. So a singer and activist with three music "
      "occupations counts under Music, and an archbishop with several church positions under Religion.")
    w("")
    fields = q("SELECT person_field, COUNT(*) n, SUM(photo_path IS NOT NULL) FROM records WHERE type='deceased' GROUP BY 1 ORDER BY n DESC")
    w(table(["Field", "Records", "With photo"], [[f or "(none)", fmt(n), fmt(p)] for f, n, p in fields]) if fields else "No deceased records yet.")
    w("")
    eras = q("""SELECT CASE WHEN CAST(substr(lifespan, instr(lifespan,'–')+1) AS INT) >= 2000 THEN 'Died 2000 or later'
                     WHEN CAST(substr(lifespan, instr(lifespan,'–')+1) AS INT) >= 1950 THEN 'Died 1950 to 1999'
                     WHEN CAST(substr(lifespan, instr(lifespan,'–')+1) AS INT) >= 1900 THEN 'Died 1900 to 1949'
                     WHEN CAST(substr(lifespan, instr(lifespan,'–')+1) AS INT) >= 1500 THEN 'Died 1500 to 1899'
                     ELSE 'Death year unknown' END e, COUNT(*) FROM records WHERE type='deceased' GROUP BY 1 ORDER BY 1 DESC""")
    if eras:
        w(table(["Era", "Records"], [[e, fmt(n)] for e, n in eras]))
        w("")

    w("## 7. Plan criterion")
    w("")
    crit = q("SELECT type, criterion, COUNT(*) FROM records GROUP BY 1, 2 ORDER BY 1, 3 DESC")
    w(table(["Type", "Criterion", "Records"], [[dict(TYPES).get(t, t), CRITERIA.get(c, c), fmt(n)] for t, c, n in crit], ["---", "---", "---:"]))
    w("")

    w("## 8. Organisation website check")
    w("")
    ws = q("SELECT website_status, COUNT(*) FROM records WHERE type='organisation' GROUP BY 1 ORDER BY 2 DESC")
    w(table(["Result", "Organisations"], [[WEBSITE.get(s, s), fmt(n)] for s, n in ws]))
    w("")

    w("## 9. Images")
    w("")
    imgs = q("""SELECT type, SUM(photo_path IS NOT NULL), SUM(logo_path IS NOT NULL),
                       SUM(logo_path IS NOT NULL AND logo_licence LIKE 'Not licensed%'),
                       SUM(photo_path IS NULL AND logo_path IS NULL) FROM records GROUP BY 1""")
    names = dict(TYPES)
    w(table(["Type", "With photo", "With logo", "Logos without a licence", "No image"],
            [[names.get(t, t), fmt(a), fmt(b), fmt(c), fmt(d)] for t, a, b, c, d in imgs]))
    w("")
    w("Photos and logos from Wikimedia Commons carry their licence and credit. Logos from organisations' own sites have no licence "
      "and need permission or removal before any public use.")
    w("")

    w("## 10. Flags")
    w("")
    flag_counts = {}
    for (f,) in q("SELECT flags FROM records"):
        for x in __import__("json").loads(f or "[]"):
            flag_counts[x] = flag_counts.get(x, 0) + 1
    w(table(["Flag", "Records"], [[f"`{k}`", fmt(v)] for k, v in sorted(flag_counts.items(), key=lambda x: -x[1])]))
    w("")
    ex = q("SELECT kind, reason, COUNT(*) FROM exclusions GROUP BY 1, 2 ORDER BY 1, 3 DESC")
    w("## 11. Candidates excluded, by reason")
    w("")
    w(table(["Type", "Reason", "Candidates"], [[names.get(k, k), f"`{r}`", fmt(n)] for k, r, n in ex], ["---", "---", "---:"]))
    w("")
    unassigned = sorted(c for c in per if region(c) == "Unassigned")
    if unassigned:
        w("**Countries with no region yet:** " + ", ".join(unassigned) + ". Add them to `REGIONS` in the script.")
        w("")
    OUT.write_text("\n".join(L), encoding="utf-8")
    print(OUT, "written;", total, "records; unassigned regions:", unassigned)


if __name__ == "__main__":
    main()
