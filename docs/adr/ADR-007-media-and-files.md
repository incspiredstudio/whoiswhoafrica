# ADR-007 Media and private files

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08 · REQs: REQ-042, 080, 127, 162, 203

**Context.** Public images need a CDN. There are no claim documents any more (D-036). Private files are customer intake uploads and evidence attached to removal or correction requests. Imported records carry no images for now (contract section 10, Q34). Backups need an off-host copy.

**Decision.** Public images are re-encoded server-side (strips EXIF and payloads), stored with licence and attribution, and served from object storage behind the CDN (Cloudflare R2; the offload method, plugin or custom code, is decided after spike S8, Q75). SVG uploads are not allowed. Private uploads go to a directory outside the web root, JPG, PNG or PDF only, size-limited, scanned if a scanner exists (spike S4), never served inline, retention-limited, and downloaded only through an authenticated handler. Backups are encrypted and sent to a separate bucket with a separate token.

**Why.** Cheap, S3-compatible, no egress fee, aligned with Cloudflare.

**Rejected.** S3 (egress cost). Local uploads only (no CDN or off-host copy; would need a REQ-080 change).

**Consequences.** Two buckets and two tokens to manage; one extra plugin or module to verify.
