# ADR-007 Media and private files

Status: Proposed v2 for WordPress (Gate 2) · Date: 2026-11-08, amended 2026-11-09 (D-050) · REQs: REQ-042, 080, 127, 162, 203

**Context.** Public images use the WordPress media library. There are no claim documents any more (D-036). Private files are customer intake uploads and evidence attached to removal or correction requests. Imported records carry no images for now (contract section 10, Q34).

**Decision.** Public images are re-encoded server-side (strips EXIF and payloads), stored with licence and attribution, and kept in the WordPress media library on the host. There is no object storage and no offload (Q75 answered). A CDN such as Cloudflare can cache them later if added. SVG uploads are not allowed. Private uploads go to a directory outside the web root, JPG, PNG or PDF only, size-limited, scanned if the host offers a scanner, never served inline, retention-limited, and downloaded only through an authenticated handler. Backups come from cPanel backups plus a backup plugin or export if needed.

**Why.** WordPress already handles images and resizing. No extra account, token or code.

**Rejected.** Object storage with offload (not needed now; REQ-080 amended).

**Consequences.** Images and backups live on the host; keep an off-server copy of backups by downloading them.
