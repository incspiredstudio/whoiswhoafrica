// Every public page the "Every page" checklist in docs/theme/theme-acceptance.md covers. Add a page to the list with one line when
// its theme issue lands (the M1 and M2 pages: profile, story, event, nominate, claim, correct, remove, report, get listed). Search results (/?s=) is added by M2-SRC-05: until its template exists the URL shows an empty "Archives" page.
// A route that does not exist on the site under test is skipped with a note, not failed, so the list can run ahead of the pages;
// a route listed with `status` must answer exactly that.

export type Route = {
  /** Path on the site. */
  path: string;
  /** Short name for the test title. */
  name: string;
  /** Expected HTTP status; 200 when omitted. */
  status?: number;
  /** Skip with a note when the page answers 404 (the page is not built yet). Default true; set false once the page must exist. */
  optional?: boolean;
};

export const routes: Route[] = [
  { path: "/", name: "home", optional: false },
  { path: "/directory/", name: "directory", optional: false },
  { path: "/industries/", name: "industries", optional: false },
  { path: "/events/", name: "events", optional: false },
  { path: "/stories/", name: "stories", optional: false },
  { path: "/contact/", name: "contact", optional: false },
  { path: "/privacy/", name: "privacy", optional: false },
  { path: "/terms/", name: "terms", optional: false },
  { path: "/cookies/", name: "cookies", optional: false },
  { path: "/get-listed/", name: "get listed" },
  { path: "/nominate/", name: "nominate" },
  { path: "/newsletter/", name: "newsletter" },
  { path: "/this-page-does-not-exist-wiw/", name: "404 page", status: 404, optional: false },
];
