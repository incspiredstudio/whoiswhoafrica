"""Export staged records as import-contract v1.1 bundles (docs/contracts/import-format.md). Issue #807.

Reads acquisition/data/staging.sqlite READ-ONLY and writes bundles (manifest.json, records.jsonl,
images/) to acquisition/data/exports/, which is git-ignored. Bundles hold personal data: never copy
them into the repository. Python standard library only.

Usage (from the repo root; -I keeps the interpreter isolated):

    python -I acquisition/pipeline/export_bundle.py --dry-run
    python -I acquisition/pipeline/export_bundle.py --batch-size 1000 --zip
    python -I acquisition/pipeline/export_bundle.py --sample 50 --zip

Options:
    --db PATH            staging database (default acquisition/data/staging.sqlite)
    --images-root PATH   folder the photo_path / logo_path / cutout_path columns are relative to
                         (default acquisition/data)
    --out PATH           output folder (default acquisition/data/exports). Must be outside the
                         repository or inside acquisition/data.
    --batch-size N       records per bundle (default 1000)
    --zip                also write each bundle as <bundle>.zip (files at the archive root)
    --sample N           write one balanced bundle of N records named sample-N (a mix of
                         individuals, entrepreneurs, organisations and deceased people; many
                         countries and industries; records with all four narrative sections
                         and an open-licence image first)
    --dry-run            build and check every record, print the counts, write nothing
    --no-ledger          do not read or update the record-version ledger

Full export layout:  <out>/full-<stamp>/export-<run_id>-<batch_id>/   (+ .zip with --zip)
Sample layout:       <out>/sample-<N>/ and <out>/sample-<N>.zip

Mapping (records table -> contract):
    type: organisation -> organisation; person -> individual; deceased -> individual with
    `lifespan`; entrepreneur -> entrepreneur.
    tier: people T3; organisations T2 when any narrative section is AI-drafted, else T1.
    country: names (or QIDs through `countries`) to ISO 3166-1 alpha-2 with COUNTRY_ISO below;
    "Pan-African" -> PAN; historical states map to the modern state that holds their core
    territory; ambiguous ones are skipped and counted.
    Narrative sections bio, achievement, influence, legacy: from `records`, else `ai_text`;
    provenance method `ai` when `text_method` starts with "ai". The staging tables mark AI text
    confidence "low" as a review marker; the contract would refuse every such record
    (LOW_CONFIDENCE), so AI sections are exported with the confidence of the facts they reword
    ("medium"), while `ai.ai_assisted`, the flags and the import state (people never public on
    import) carry the review need.
    Provenance: `provenance` table, else `evidence_url` and `retrieved_at`. source_hash is the
    SHA-256 of what the pipeline stored for that source (Wikidata entity JSON, own-site row, the
    AI input facts, the Commons image file).
    Images: photo_* and logo_* when the licence maps to the contract allowlist and the file is
    JPEG, PNG or WebP of at most 5 MB. An approved cutout (photo_cutouts.review = approved)
    replaces the original photo.
    record_version: a ledger at <out>/record-ledger.json keeps the last hash per record_id and
    bumps the version when it changes (written only by a full export).
"""

from __future__ import annotations

import argparse
import hashlib
import json
import shutil
import sqlite3
import struct
import subprocess
import sys
import time
import unicodedata
import urllib.parse
import urllib.request
import zipfile
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

CONTRACT_VERSION = "1.1"
SCRIPT_VERSION = "export_bundle-1.1"
REPO = Path(__file__).resolve().parents[2]
MAX_IMAGE_BYTES = 5 * 1024 * 1024
NARRATIVE = ("bio", "achievement", "influence", "legacy")
NARRATIVE_MAX = 6000
STRING_MAX = 300
COUNT_KEYS = ("organisation", "individual", "entrepreneur")
TYPE_MAP = {"organisation": "organisation", "person": "individual", "deceased": "individual",
            "entrepreneur": "entrepreneur"}

COUNTRY_ISO = {
    "Algeria": "DZ", "Angola": "AO", "Benin": "BJ", "Botswana": "BW", "Burkina Faso": "BF",
    "Burundi": "BI", "Cameroon": "CM", "Cape Verde": "CV", "Cabo Verde": "CV",
    "Central African Republic": "CF", "Chad": "TD", "Comoros": "KM",
    "Democratic Republic of the Congo": "CD", "Republic of the Congo": "CG", "Djibouti": "DJ",
    "Egypt": "EG", "Equatorial Guinea": "GQ", "Eritrea": "ER", "Eswatini": "SZ", "Swaziland": "SZ",
    "Ethiopia": "ET", "Gabon": "GA", "The Gambia": "GM", "Gambia": "GM", "Ghana": "GH",
    "Guinea": "GN", "Guinea-Bissau": "GW", "Ivory Coast": "CI", "Côte d'Ivoire": "CI",
    "Kenya": "KE", "Lesotho": "LS", "Liberia": "LR", "Libya": "LY", "Madagascar": "MG",
    "Malawi": "MW", "Mali": "ML", "Mauritania": "MR", "Mauritius": "MU", "Morocco": "MA",
    "Mozambique": "MZ", "Namibia": "NA", "Niger": "NE", "Nigeria": "NG", "Rwanda": "RW",
    "São Tomé and Príncipe": "ST", "Sao Tome and Principe": "ST", "Senegal": "SN",
    "Seychelles": "SC", "Sierra Leone": "SL", "Somalia": "SO", "South Africa": "ZA",
    "South Sudan": "SS", "Sudan": "SD", "Tanzania": "TZ", "Togo": "TG", "Tunisia": "TN",
    "Uganda": "UG", "Zambia": "ZM", "Zimbabwe": "ZW", "Western Sahara": "EH",
    "Pan-African": "PAN",
    # Historical states: the modern state holding their core territory.
    "Sultanate of Zanzibar (historical)": "TZ", "Kingdom of Egypt (historical)": "EG",
    "Dahomey (historical)": "BJ", "Beylik of Tunis (historical)": "TN",
    "Zulu Kingdom (historical)": "ZA", "Merina Kingdom (historical)": "MG",
    "Yeke Kingdom (historical)": "CD", "Sultanate of Darfur (historical)": "SD",
    "South African Republic (historical)": "ZA", "Rhodesia (historical)": "ZW",
    "Mamluk Sultanate of Egypt (historical)": "EG", "Khedivate of Egypt (historical)": "EG",
    "Hafsid dynasty (historical)": "TN", "Gaza Empire (historical)": "MZ",
    "Ethiopian Empire (historical)": "ET", "Benin Empire (historical)": "NG",
    "Ashanti Empire (historical)": "GH",
    # Deliberately unmapped (span several modern states): Kingdom of Kongo, Kanem-Bornu Empire,
    # Federation of Rhodesia and Nyasaland.
}

# Staging licence label -> (contract id, licence URL, label shown in the credit line).
LICENCES = {
    "CC0": ("CC0-1.0", "https://creativecommons.org/publicdomain/zero/1.0/", "CC0"),
    "Public domain": ("PDM-1.0", "https://creativecommons.org/publicdomain/mark/1.0/", "Public domain"),
}
for _v in ("2.0", "2.5", "3.0", "4.0"):
    LICENCES[f"CC BY {_v}"] = (f"CC-BY-{_v}", f"https://creativecommons.org/licenses/by/{_v}/", f"CC BY {_v}")
    LICENCES[f"CC BY-SA {_v}"] = (f"CC-BY-SA-{_v}", f"https://creativecommons.org/licenses/by-sa/{_v}/",
                                  f"CC BY-SA {_v}")
NO_CREATOR_OK = {"CC0-1.0", "PDM-1.0"}

# Staging columns that hold profile data but have no key in the contract allowlists.
NOT_EXPORTED = ("born_in", "died_in", "education", "description", "exchange", "site_description")


# --------------------------------------------------------------------------------------------
# Canonical JSON and the content hash (contract section 8: NFC, RFC 8785, SHA-256).

def nfc(value):
    """Return value with every string (and key) normalised to Unicode NFC."""
    if isinstance(value, str):
        return unicodedata.normalize("NFC", value)
    if isinstance(value, list):
        return [nfc(v) for v in value]
    if isinstance(value, dict):
        return {nfc(k): nfc(v) for k, v in value.items()}
    return value


def canonical(value) -> str:
    """RFC 8785 serialisation of JSON data built from dict, list, str, int, bool and None."""
    if value is None:
        return "null"
    if value is True:
        return "true"
    if value is False:
        return "false"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        raise ValueError("floats are not used in the contract")
    if isinstance(value, str):
        return json.dumps(unicodedata.normalize("NFC", value), ensure_ascii=False)
    if isinstance(value, list):
        return "[" + ",".join(canonical(v) for v in value) + "]"
    if isinstance(value, dict):
        items = {unicodedata.normalize("NFC", k): v for k, v in value.items()}
        keys = sorted(items, key=lambda k: k.encode("utf-16-be"))
        return "{" + ",".join(canonical(k) + ":" + canonical(items[k]) for k in keys) + "}"
    raise ValueError(f"unsupported type {type(value).__name__}")


HASHED = ("record_id", "entity_type", "tier", "country", "external_ids", "inclusion", "fields",
          "claims", "passages", "images", "ai")


def content_hash(record: dict) -> str:
    subset = {k: record[k] for k in HASHED if k in record}
    return "sha256:" + hashlib.sha256(canonical(subset).encode("utf-8")).hexdigest()


# --------------------------------------------------------------------------------------------
# Small helpers.

def sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


def iso_utc(text, fallback: str) -> str:
    """ISO 8601 UTC with a Z, from the staging timestamp formats."""
    if not text:
        return fallback
    try:
        dt = datetime.fromisoformat(str(text).replace("Z", "+00:00"))
    except ValueError:
        return fallback
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")


def clean_text(text) -> str:
    if text is None:
        return ""
    text = unicodedata.normalize("NFC", str(text)).replace("\r\n", "\n").replace("\r", "\n")
    return "\n".join(line.rstrip() for line in text.strip().split("\n"))


def ok_string(value, limit=STRING_MAX) -> bool:
    return isinstance(value, str) and value.strip() != "" and len(value.encode("utf-8")) <= limit


def ok_url(value) -> bool:
    if not isinstance(value, str) or len(value) > 2048 or any(c.isspace() for c in value):
        return False
    try:
        parts = urllib.parse.urlsplit(value)
    except ValueError:
        return False
    return parts.scheme in ("http", "https") and "." in (parts.hostname or "")


def json_list(text) -> list:
    try:
        items = json.loads(text) if text else []
    except (ValueError, TypeError):
        return []
    out = []
    for item in items if isinstance(items, list) else []:
        if isinstance(item, str):
            item = clean_text(item)
            if ok_string(item) and item not in out:
                out.append(item)
    return out


def source_for_url(url: str) -> str:
    host = (urllib.parse.urlsplit(url or "").hostname or "").lower()
    if host.endswith("wikidata.org"):
        return "wikidata"
    if host.endswith("commons.wikimedia.org"):
        return "commons"
    return "own_site"


# --------------------------------------------------------------------------------------------
# Image sniffing (type and size from the bytes, stdlib only).

def sniff_image(data: bytes):
    """Return (mime, ext, width, height) or None when the bytes are not JPEG, PNG or WebP."""
    if data[:8] == b"\x89PNG\r\n\x1a\n" and data[12:16] == b"IHDR":
        w, h = struct.unpack(">II", data[16:24])
        return "image/png", "png", w, h
    if data[:2] == b"\xff\xd8":
        i = 2
        while i + 9 < len(data):
            if data[i] != 0xFF:
                i += 1
                continue
            marker = data[i + 1]
            if marker in (0xD8, 0x01) or 0xD0 <= marker <= 0xD7 or marker == 0xFF:
                i += 1 if marker == 0xFF else 2
                continue
            seg = struct.unpack(">H", data[i + 2:i + 4])[0]
            if 0xC0 <= marker <= 0xCF and marker not in (0xC4, 0xC8, 0xCC):
                h, w = struct.unpack(">HH", data[i + 5:i + 9])
                return "image/jpeg", "jpg", w, h
            i += 2 + seg
        return None
    if data[:4] == b"RIFF" and data[8:12] == b"WEBP":
        chunk = data[12:16]
        if chunk == b"VP8 " and len(data) >= 30:
            w, h = struct.unpack("<HH", data[26:30])
            return "image/webp", "webp", w & 0x3FFF, h & 0x3FFF
        if chunk == b"VP8L" and len(data) >= 25:
            b = data[21:25]
            w = 1 + (((b[1] & 0x3F) << 8) | b[0])
            h = 1 + (((b[3] & 0x0F) << 10) | (b[2] << 2) | ((b[1] & 0xC0) >> 6))
            return "image/webp", "webp", w, h
        if chunk == b"VP8X" and len(data) >= 30:
            w = 1 + int.from_bytes(data[24:27], "little")
            h = 1 + int.from_bytes(data[27:30], "little")
            return "image/webp", "webp", w, h
    return None


# --------------------------------------------------------------------------------------------
# The exporter.

class Exporter:
    def __init__(self, db: Path, images_root: Path, stamp: str):
        uri = "file:" + urllib.request.pathname2url(str(db)) + "?mode=ro"
        self.con = sqlite3.connect(uri, uri=True)
        self.con.row_factory = sqlite3.Row
        self.images_root = images_root
        self.stamp = stamp
        self.run_id = f"exp-{stamp}"
        self.now = datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ")
        self.skipped = Counter()
        self.img_in = Counter()
        self.img_refused = Counter()
        self.img_refused_licence = Counter()
        self.dropped_fields = Counter()
        self.notes = Counter()
        self._hash_cache = {}
        self._file_cache = {}
        self.sources = {r["source_id"]: r["name"] for r in self.con.execute("SELECT source_id, name FROM sources")}
        self.qid_country = {r["qid"]: r["name"] for r in self.con.execute("SELECT qid, name FROM countries")}
        self.prov = {}
        for r in self.con.execute("SELECT * FROM provenance"):
            self.prov[(r["record_id"], r["field"])] = dict(r)
        self.ai = {r["record_id"]: dict(r) for r in self.con.execute("SELECT * FROM ai_text WHERE status='ok'")}
        self.cutouts = {r["record_id"]: dict(r) for r in self.con.execute("SELECT * FROM photo_cutouts")
                        if (r["review"] or "").strip().lower() == "approved"}
        self.img_meta = {(r["qid"], r["role"]): dict(r) for r in self.con.execute("SELECT * FROM images")}
        self.org_names = {}
        for r in self.con.execute("SELECT record_id, qid, name FROM records WHERE type='organisation'"):
            self.org_names[r["qid"]] = (r["record_id"], r["name"])
        self.person_orgs = {}
        for table, col in (("founder_links", "person_qid"), ("leader_links", "person_qid"),
                           ("selected_leaders", "qid")):
            for r in self.con.execute(f"SELECT {col} AS p, org_qid FROM {table}"):
                if r["org_qid"]:
                    self.person_orgs.setdefault(r["p"], set()).add(r["org_qid"])

    # -- source hashes ------------------------------------------------------------------------
    def source_hash(self, source_id: str, rec: dict, field: str):
        key = (source_id, rec["qid"], field if source_id in ("ai", "commons") else "")
        if key in self._hash_cache:
            return self._hash_cache[key]
        data = None
        if source_id == "ai":
            a = self.ai.get(rec["record_id"])
            data = (a or {}).get("facts")
        elif source_id == "commons":
            path = rec.get("photo_path") if field == "photo" else rec.get("logo_path")
            info = self.file_info(path) if path else None
            self._hash_cache[key] = "sha256:" + info["sha256"] if info else None
            return self._hash_cache[key]
        elif source_id == "own_site":
            r = self.con.execute("SELECT url, final_url, description, logo_url FROM sites WHERE qid=?",
                                 (rec["qid"],)).fetchone()
            data = json.dumps(dict(r), ensure_ascii=False, sort_keys=True) if r else None
        if data is None:  # wikidata, or the fallback: the stored entity JSON
            r = self.con.execute("SELECT data FROM entities WHERE qid=?", (rec["qid"],)).fetchone() or \
                self.con.execute("SELECT data FROM person_entities WHERE qid=?", (rec["qid"],)).fetchone()
            data = r["data"] if r else None
        value = "sha256:" + sha256_bytes(data.encode("utf-8")) if data else None
        self._hash_cache[key] = value
        return value

    # -- provenance ---------------------------------------------------------------------------
    def provenance(self, rec: dict, staging_field: str, ai_text: bool):
        p = self.prov.get((rec["record_id"], staging_field))
        url = (p or {}).get("source_url") or rec["evidence_url"]
        if not ok_url(url):
            url = f"https://www.wikidata.org/wiki/{rec['qid']}"
        source_id = (p or {}).get("source_id") or source_for_url(url)
        if source_id not in self.sources or source_id == "deepseek":
            source_id = source_for_url(url)
        method = ((p or {}).get("method") or "API")
        if ai_text:
            method_out = "ai"
        elif method.startswith("ai"):
            method_out = "ai"
        elif method == "fetch":
            method_out = "import"
        else:
            method_out = "api"  # API, template (filled from API facts)
        confidence = (p or {}).get("confidence") or "medium"
        if confidence == "low":
            if method_out != "ai":
                return None
            confidence = "medium"
            self.notes["ai_low_confidence_exported_as_medium"] += 1
        if confidence not in ("high", "medium"):
            return None
        retrieved = iso_utc((p or {}).get("retrieved_at") or rec["retrieved_at"], self.now)
        entry = {"source_id": source_id, "source_url": url, "retrieved_at": retrieved,
                 "confidence": confidence, "method": method_out}
        sh = self.source_hash("ai" if method_out == "ai" else source_id, rec, staging_field)
        if sh:
            entry["source_hash"] = sh
        else:
            self.notes["provenance_without_source_hash"] += 1
        if p is None:
            self.notes["provenance_fallback_to_evidence_url"] += 1
        return [entry]

    def add_field(self, fields: dict, rec: dict, key: str, value, staging_field: str, ai_text=False):
        prov = self.provenance(rec, staging_field, ai_text)
        if prov is None:
            self.dropped_fields[f"{key}:low_or_unsourced"] += 1
            return False
        fields[key] = {"value": value, "provenance": prov}
        return True

    # -- images -------------------------------------------------------------------------------
    def file_info(self, rel_path: str):
        if rel_path in self._file_cache:
            return self._file_cache[rel_path]
        path = (self.images_root / rel_path).resolve()
        info = None
        try:
            if path.is_file() and not path.is_symlink():
                size = path.stat().st_size
                if size > MAX_IMAGE_BYTES:
                    info = {"error": "too_large"}
                else:
                    data = path.read_bytes()
                    sniff = sniff_image(data)
                    if sniff is None:
                        info = {"error": "type_not_allowed"}
                    elif sniff[2] <= 0 or sniff[3] <= 0:
                        info = {"error": "no_dimensions"}
                    else:
                        info = {"path": path, "sha256": sha256_bytes(data), "mime": sniff[0], "ext": sniff[1],
                                "width": sniff[2], "height": sniff[3], "bytes": size}
            else:
                info = {"error": "file_missing"}
        except OSError:
            info = {"error": "file_unreadable"}
        self._file_cache[rel_path] = info
        return info

    def image(self, rec: dict, kind: str):
        """Return (image entry, source file path) or None."""
        path = rec[f"{kind}_path"]
        if not path:
            return None
        label = (rec[f"{kind}_licence"] or "").strip()
        lic = LICENCES.get(label)
        if lic is None:
            self.img_refused[f"{kind}:licence"] += 1
            self.img_refused_licence[label or "(none)"] += 1
            return None
        cutout = self.cutouts.get(rec["record_id"]) if kind == "photo" else None
        if cutout and cutout.get("cutout_path") and LICENCES.get((cutout.get("licence") or "").strip()) == lic:
            info = self.file_info(cutout["cutout_path"])
            if "error" in info:
                cutout = None
                info = self.file_info(path)
        else:
            cutout = None
            info = self.file_info(path)
        if "error" in info:
            self.img_refused[f"{kind}:{info['error']}"] += 1
            return None
        creator = clean_text(rec[f"{kind}_credit"])
        if not creator and lic[0] not in NO_CREATOR_OK:
            self.img_refused[f"{kind}:no_creator"] += 1
            return None
        source_url = rec[f"{kind}_source"]
        if not ok_url(source_url):
            self.img_refused[f"{kind}:no_source_url"] += 1
            return None
        meta = self.img_meta.get((rec["qid"], kind)) or {}
        prov = self.prov.get((rec["record_id"], kind)) or {}
        retrieved = iso_utc(meta.get("retrieved_at") or prov.get("retrieved_at") or rec["retrieved_at"], self.now)
        parts = [creator] if creator else []
        parts.append(lic[2])
        if (urllib.parse.urlsplit(source_url).hostname or "").endswith("commons.wikimedia.org"):
            parts.append("via Wikimedia Commons")
        if cutout:
            parts.append("background removed")
        entry = {
            "image_id": f"{kind}-1",
            "file": f"images/{info['sha256']}.{info['ext']}",
            "sha256": info["sha256"],
            "mime_type": info["mime"],
            "width": info["width"],
            "height": info["height"],
            "kind": kind,
            "source_url": source_url,
            "retrieved_at": retrieved,
            "creator": creator,
            "licence": lic[0],
            "licence_url": lic[1],
            "attribution_text": ", ".join(parts),
        }
        self.img_in[f"{kind}{':cutout' if cutout else ''}"] += 1
        return entry, info["path"]

    # -- one record ---------------------------------------------------------------------------
    def country(self, value):
        if not value:
            return None
        value = unicodedata.normalize("NFC", value.strip())
        if value in self.qid_country:
            value = self.qid_country[value]
        return COUNTRY_ISO.get(value)

    def narrative(self, rec: dict, key: str):
        text = clean_text(rec.get(key))
        if not text:
            text = clean_text((self.ai.get(rec["record_id"]) or {}).get(key))
            if text:
                self.notes[f"{key}_from_ai_text"] += 1
        return text

    def build(self, row) -> tuple:
        """Return (record, image files, meta) or (None, reason)."""
        rec = dict(row)
        kind = rec["type"]
        etype = TYPE_MAP.get(kind)
        if etype is None:
            return None, f"unknown_type:{kind}"
        if not rec["record_id"] or len(rec["record_id"]) > 64:
            return None, "bad_record_id"
        country = self.country(rec["country"])
        if country is None:
            return None, "country_unmapped"
        name = clean_text(rec["name"])
        if not ok_string(name):
            return None, "missing_name"
        checks = json.loads(rec["checks"] or "{}")
        verification, failed = self.verification(rec, checks)
        if failed:
            return None, "verification_failed"
        if not rec["criterion"] or not ok_url(rec["evidence_url"]):
            return None, "missing_inclusion"

        fields = {}
        self.add_field(fields, rec, "name", name, "name")
        text_method = rec["text_method"] or ""
        is_ai = text_method.startswith("ai")
        website = rec["website"] if rec["website_status"] in ("ok", "not_checked", "robots_blocked") else None
        if rec["website"] and not website:
            self.dropped_fields[f"website:{rec['website_status']}"] += 1
        if etype == "organisation":
            org_type = clean_text(rec["org_type"] or rec["org_category"])
            if not ok_string(org_type) or not self.add_field(fields, rec, "org_type", org_type, "org_type"):
                return None, "missing_org_type"
            if rec["org_category"] and ok_string(rec["org_category"]):
                self.add_field(fields, rec, "sector", clean_text(rec["org_category"]), "org_type")
            if ok_string(clean_text(rec["industry"])):
                self.add_field(fields, rec, "industry", clean_text(rec["industry"]), "industry")
            if ok_string(clean_text(rec["city"])):
                self.add_field(fields, rec, "hq_city", clean_text(rec["city"]), "city")
            if website and ok_url(website):
                self.add_field(fields, rec, "website", website, "website")
            year = str(rec["founded_year"] or "").strip()
            if year.isdigit() and 0 < int(year) <= datetime.now().year:
                self.add_field(fields, rec, "year_founded", int(year), "founded_year")
        else:
            for key, col in (("current_title", "current_title"), ("current_organisation", "current_org"),
                             ("industry", "industry"), ("field", "person_field")):
                value = clean_text(rec[col])
                if ok_string(value):
                    self.add_field(fields, rec, key, value, col)
            if kind == "deceased" and ok_string(clean_text(rec["lifespan"]), 40):
                self.add_field(fields, rec, "lifespan", clean_text(rec["lifespan"]), "lifespan")
            ref = self.org_ref(rec)
            if ref and "current_organisation" in fields:
                self.add_field(fields, rec, "current_organisation_ref", ref, "current_org")
            for key, col in (("public_record_roles", "roles"), ("published_awards", "awards")):
                items = json_list(rec[col])
                if items:
                    self.add_field(fields, rec, key, items, col)
            if website and ok_url(website):
                self.add_field(fields, rec, "official_website", website, "website")
        sections = 0
        for key in NARRATIVE:
            text = self.narrative(rec, key)
            if not text:
                continue
            if len(text) > NARRATIVE_MAX:
                self.dropped_fields[f"{key}:too_long"] += 1
                continue
            if self.add_field(fields, rec, key, text, key, ai_text=is_ai):
                sections += 1

        images, files = [], []
        kinds = ("logo", "photo") if etype == "organisation" else ("photo",)
        for img_kind in kinds:
            got = self.image(rec, img_kind)
            if got:
                images.append(got[0])
                files.append(got[1])

        model = prompt = None
        if is_ai:
            parts = text_method.split(":")
            model = parts[1] if len(parts) > 1 else "unknown"
            prompt = parts[2] if len(parts) > 2 else "unknown"
        flags_in = json_list(rec["flags"])
        dup = "possible_duplicate" in flags_in or checks.get("7_duplicates_flagged") is True
        external = {"wikidata_qid": rec["qid"]} if rec["qid"] else {}
        if etype == "organisation" and website and ok_url(website):
            host = (urllib.parse.urlsplit(website).hostname or "").lower()
            host = host[4:] if host.startswith("www.") else host
            if host:
                external["official_domain"] = host
        dropped = [c for c in NOT_EXPORTED if rec.get(c)]
        if rec["website"] and not website:
            dropped.append("website")
        record = {
            "contract_version": CONTRACT_VERSION,
            "record_id": rec["record_id"],
            "record_version": 1,
            "run_id": self.run_id,
            "batch_id": "",
            "entity_type": etype,
            "tier": "T3" if etype != "organisation" else ("T2" if is_ai and sections else "T1"),
            "country": country,
            "external_ids": external,
            "inclusion": {"criterion_code": rec["criterion"], "evidence_url": rec["evidence_url"]},
            "fields": fields,
            "claims": [],
            "passages": [],
            "images": images,
            "ai": {"ai_assisted": bool(is_ai and sections), "model": model if sections else None,
                   "prompt_version": prompt if sections else None},
            "flags": {"probable_duplicate": dup, "duplicate_of": [], "flag_reasons": flags_in},
            "verification": dict(verification, fields_dropped=sorted(set(dropped))),
            "content_hash": "",
        }
        record = nfc(record)
        meta = {"kind": kind, "country": country, "sections": sections, "has_image": bool(images),
                "sitelinks": rec["sitelinks"] or 0,
                "topic": rec["industry"] or rec["org_category"] or rec["person_field"] or rec["segment"] or "",
                "sources": {p["source_id"] for f in fields.values() for p in f["provenance"]}}
        return record, files, meta

    def org_ref(self, rec: dict):
        current = clean_text(rec["current_org"]).casefold()
        if not current:
            return None
        for org_qid in sorted(self.person_orgs.get(rec["qid"], ())):
            hit = self.org_names.get(org_qid)
            if hit and clean_text(hit[1]).casefold() == current:
                return hit[0]
        return None

    def verification(self, rec: dict, checks: dict):
        def passed(value):
            if value is True:
                return True
            return isinstance(value, str) and value != "" and not value.lower().startswith(("fail", "not_"))
        mapping = {"approved_source": "1_source_approved", "source_url_ok": "2_source_url_success",
                   "exact_quotes": "3_claim_quotes", "allowlisted_fields": "4_allowlisted_fields_only",
                   "sensitive_scan": "5_sensitive_scan", "suppression": "6_suppression_check"}
        out, failed = {}, False
        for name, key in mapping.items():
            ok = passed(checks.get(key))
            out[name] = "pass" if ok else "fail"
            failed = failed or not ok
        out["duplicates"] = "flagged" if checks.get("7_duplicates_flagged") is True else "clear"
        return {"run_id": rec["run_id"] or self.run_id, "verified_at": iso_utc(rec["retrieved_at"], self.now),
                "checks": out, "claims_kept": 0, "claims_dropped": 0}, failed

    # -- all records --------------------------------------------------------------------------
    def build_all(self):
        order = {"organisation": 0, "entrepreneur": 1, "person": 2, "deceased": 3}
        rows = self.con.execute("SELECT * FROM records").fetchall()

        def sort_key(r):
            qid = r["qid"] or ""
            num = int(qid[1:]) if qid[1:].isdigit() else 0
            return order.get(r["type"], 9), num, r["record_id"]
        built = []
        for row in sorted(rows, key=sort_key):
            result = self.build(row)
            if result[0] is None:
                self.skipped[result[1]] += 1
                continue
            built.append(result)
        return built


# --------------------------------------------------------------------------------------------
# Sample selection, bundle writing and the ledger.

def pick_sample(built: list, n: int) -> list:
    """A balanced sample: kinds in turn; new countries and topics, image and full sections first."""
    by_kind = {}
    for item in built:
        by_kind.setdefault(item[2]["kind"], []).append(item)
    kinds = [k for k in ("person", "entrepreneur", "organisation", "deceased") if by_kind.get(k)]
    chosen, countries, topics, used = [], Counter(), Counter(), set()
    while len(chosen) < n and kinds:
        progressed = False
        for kind in kinds:
            if len(chosen) >= n:
                break
            best, best_score = None, None
            for item in by_kind[kind]:
                rid = item[0]["record_id"]
                if rid in used:
                    continue
                m = item[2]
                score = (4 if m["has_image"] else 0) + (2 if m["sections"] == 4 else 0) \
                    + 3 / (1 + countries[m["country"]]) + 2 / (1 + topics[m["topic"]]) \
                    + min(m["sitelinks"], 100) / 1000
                if best_score is None or score > best_score:
                    best, best_score = item, score
            if best is None:
                continue
            used.add(best[0]["record_id"])
            countries[best[2]["country"]] += 1
            topics[best[2]["topic"]] += 1
            chosen.append(best)
            progressed = True
        if not progressed:
            break
    return chosen


def apply_versions(items: list, ledger: dict):
    for record, _files, _meta in items:
        h = content_hash(record)
        prev = ledger.get(record["record_id"])
        version = 1 if prev is None else (prev[0] if prev[1] == h else prev[0] + 1)
        record["record_version"] = version
        ledger[record["record_id"]] = [version, h]


def write_bundle(directory: Path, items: list, ex: Exporter, batch_id: str, make_zip: bool) -> dict:
    if directory.exists():
        shutil.rmtree(directory)
    (directory / "images").mkdir(parents=True)
    counts = dict.fromkeys(COUNT_KEYS, 0)
    sources, copied, lines = set(), set(), []
    for record, files, meta in items:
        record["batch_id"] = batch_id
        record["content_hash"] = content_hash(record)
        counts[record["entity_type"]] += 1
        sources |= meta["sources"]
        for image, src in zip(record["images"], files):
            if image["file"] not in copied:
                shutil.copyfile(src, directory / image["file"])
                copied.add(image["file"])
        lines.append(json.dumps(record, ensure_ascii=False, separators=(",", ":")))
    body = ("\n".join(lines) + "\n").encode("utf-8")
    (directory / "records.jsonl").write_bytes(body)
    approved = ex.con.execute("SELECT MAX(approved_on) FROM sources").fetchone()[0] or "unknown"
    manifest = {
        "contract_version": CONTRACT_VERSION,
        "run_id": ex.run_id,
        "batch_id": batch_id,
        "generated_at": ex.now,
        "pipeline_version": pipeline_version(),
        "source_list_version": f"sources-{approved}",
        "sources": [{"source_id": s, "name": ex.sources.get(s, s)} for s in sorted(sources)],
        "suppression_snapshot_id": "none-no-list-yet",
        "record_count": len(items),
        "counts": counts,
        "records_sha256": "sha256:" + sha256_bytes(body),
    }
    (directory / "manifest.json").write_text(json.dumps(manifest, ensure_ascii=False, indent=2) + "\n",
                                             encoding="utf-8")
    result = {"dir": directory, "records": len(items), "images": len(copied), "counts": counts,
              "bytes": sum(p.stat().st_size for p in directory.rglob("*") if p.is_file())}
    if make_zip:
        zpath = directory.with_name(directory.name + ".zip")
        with zipfile.ZipFile(zpath, "w") as z:
            for name in ("manifest.json", "records.jsonl"):
                z.write(directory / name, name, compress_type=zipfile.ZIP_DEFLATED)
            for p in sorted((directory / "images").iterdir()):
                z.write(p, f"images/{p.name}", compress_type=zipfile.ZIP_STORED)
        result["zip"] = zpath
        result["zip_bytes"] = zpath.stat().st_size
    return result


_PV = None


def pipeline_version() -> str:
    global _PV
    if _PV is None:
        try:
            sha = subprocess.run(["git", "-C", str(REPO), "rev-parse", "--short", "HEAD"], capture_output=True,
                                 text=True, timeout=10).stdout.strip()
        except (OSError, subprocess.SubprocessError):
            sha = ""
        _PV = f"{SCRIPT_VERSION}+{sha}" if sha else SCRIPT_VERSION
    return _PV


def check_out_path(out: Path):
    out = out.resolve()
    data = (REPO / "acquisition" / "data").resolve()
    try:
        out.relative_to(REPO)
    except ValueError:
        return
    try:
        out.relative_to(data)
    except ValueError:
        sys.exit(f"refusing to write personal data inside the repository outside {data}: {out}")


def mb(n: int) -> str:
    return f"{n / 1048576:.1f} MB"


def main(argv=None) -> int:
    ap = argparse.ArgumentParser(description="Export staged records as contract v1.1 bundles.")
    ap.add_argument("--db", type=Path, default=REPO / "acquisition" / "data" / "staging.sqlite")
    ap.add_argument("--images-root", type=Path, default=REPO / "acquisition" / "data")
    ap.add_argument("--out", type=Path, default=REPO / "acquisition" / "data" / "exports")
    ap.add_argument("--batch-size", type=int, default=1000)
    ap.add_argument("--zip", action="store_true")
    ap.add_argument("--sample", type=int, default=0)
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--no-ledger", action="store_true")
    args = ap.parse_args(argv)
    if args.batch_size < 1:
        ap.error("--batch-size must be at least 1")
    if not args.db.is_file():
        ap.error(f"no database at {args.db}")
    check_out_path(args.out)
    started = time.monotonic()
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    ex = Exporter(args.db.resolve(), args.images_root.resolve(), stamp)
    built = ex.build_all()
    items = pick_sample(built, args.sample) if args.sample else built

    ledger_path = args.out / "record-ledger.json"
    ledger = {}
    if not args.no_ledger and ledger_path.is_file():
        ledger = json.loads(ledger_path.read_text(encoding="utf-8"))
    apply_versions(items, dict(ledger) if args.sample or args.dry_run else ledger)

    by_kind = Counter(m["kind"] for _r, _f, m in items)
    by_type = Counter(r["entity_type"] for r, _f, _m in items)
    print(f"run_id {ex.run_id}  contract {CONTRACT_VERSION}  db {args.db}")
    print(f"records exported: {len(items)} of {len(built) + sum(ex.skipped.values())} staged")
    print("  by entity_type: " + ", ".join(f"{k} {by_type[k]}" for k in COUNT_KEYS))
    print("  by staging kind: " + ", ".join(f"{k} {v}" for k, v in sorted(by_kind.items())))
    print("  by tier: " + ", ".join(f"{k} {v}" for k, v in sorted(Counter(r['tier'] for r, _f, _m in items).items())))
    print(f"  countries: {len({m['country'] for _r, _f, m in items})}; with all four sections: "
          f"{sum(1 for _r, _f, m in items if m['sections'] == 4)}; with an image: "
          f"{sum(1 for _r, _f, m in items if m['has_image'])}")
    print("skipped by reason: " + (", ".join(f"{k} {v}" for k, v in ex.skipped.most_common()) or "none"))
    print("images included (all built records): " + (", ".join(f"{k} {v}" for k, v in sorted(ex.img_in.items())) or "none"))
    print("images refused: " + (", ".join(f"{k} {v}" for k, v in ex.img_refused.most_common()) or "none"))
    print("  refused licences: " + (", ".join(f"{k} {v}" for k, v in ex.img_refused_licence.most_common()) or "none"))
    print("fields not exported: " + (", ".join(f"{k} {v}" for k, v in ex.dropped_fields.most_common()) or "none"))
    print("notes: " + (", ".join(f"{k} {v}" for k, v in sorted(ex.notes.items())) or "none"))

    if args.dry_run:
        print(f"dry run: nothing written ({time.monotonic() - started:.1f} s)")
        return 0

    args.out.mkdir(parents=True, exist_ok=True)
    results = []
    if args.sample:
        results.append(write_bundle(args.out / f"sample-{args.sample}", items, ex, f"{stamp}-sample-{args.sample}",
                                    args.zip))
    else:
        parent = args.out / f"full-{stamp}"
        for i in range(0, len(items), args.batch_size):
            batch_id = f"{stamp}-b{i // args.batch_size + 1:03d}"
            results.append(write_bundle(parent / f"export-{ex.run_id}-{batch_id}", items[i:i + args.batch_size],
                                        ex, batch_id, args.zip))
        if not args.no_ledger:
            ledger_path.write_text(json.dumps(ledger, separators=(",", ":")), encoding="utf-8")
    for r in results:
        line = f"  {r['dir']}  records {r['records']}  images {r['images']}  {mb(r['bytes'])}"
        if "zip" in r:
            line += f"  zip {mb(r['zip_bytes'])}"
        print(line)
    print(f"bundles written: {len(results)}; total {mb(sum(r['bytes'] for r in results))}"
          + (f", zips {mb(sum(r['zip_bytes'] for r in results))}" if args.zip else "")
          + f"; {time.monotonic() - started:.1f} s")
    return 0


if __name__ == "__main__":
    sys.exit(main())
