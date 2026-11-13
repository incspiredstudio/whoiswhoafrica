import type { APIRequestContext } from "@playwright/test";

// REQ-041. Finds a real profile or story to measure: an env override first (WIW_PERF_PROFILE_PATH, WIW_PERF_STORY_PATH),
// else the first matching link on the list page. Returns null on an empty site, so the content tests skip instead of guessing.
export function firstMatch(html: string, pattern: RegExp): string | null {
  const hrefs = [...html.matchAll(/href="([^"#?]+)"/g)].map((m) => m[1]);
  for (const href of hrefs) {
    const path = href.replace(/^https?:\/\/[^/]+/, "");
    if (pattern.test(path)) return path;
  }
  return null;
}

export const profilePattern = /^\/(?:profile|profiles|directory)\/[^/]+(?:\/[^/]+)?\/$/;
export const storyPattern = /^\/stories\/(?!type\/|page\/)[^/]+\/$/;

export async function discover(request: APIRequestContext, override: string | undefined, listPath: string, pattern: RegExp): Promise<string | null> {
  if (override) return override;
  const res = await request.get(listPath);
  if (!res.ok()) return null;
  const found = firstMatch(await res.text(), pattern);
  return found && found !== listPath ? found : null;
}
