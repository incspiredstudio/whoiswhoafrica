# Getting started

This section helps a new reader find the right starting point and get the prototype and the docs running. Pick the path that matches your role.

## Pick your path

| You are | Start with | Then |
|---|---|---|
| Product owner | [What WHOISWHO is](/overview) | [Features](/features/), [Open questions](/open-questions) |
| Designer | [Theme](/theme/) | [Brand and tokens](/theme/brand-and-tokens), [Components](/theme/components), [Prototype guide](/theme/prototype-guide) |
| Developer | [Working in this repo](/getting-started/working-in-this-repo) | [Plugins](/plugins/), [Engineering](/build/), [Import contract](/contracts/import-format), [Run the checks](/getting-started/run-the-checks) |
| Staff trainer | [Run the prototype](/getting-started/run-the-prototype) | The Flows menu inside the prototype, then [Admin screens](/pages/admin/) |

## What this project is

WHOISWHO is an African discovery, recognition and media platform, being built on WordPress. The repository holds the product documents, a clickable prototype, the data acquisition pipeline and, later, the website code. The [Overview](/overview) explains the product in one page.

## The pages in this section

| Page | Use it to |
|---|---|
| [Run the prototype](/getting-started/run-the-prototype) | Open the prototype, try roles and flows, view it on a phone |
| [Run the checks](/getting-started/run-the-checks) | Run the smoke test and rebuild the inventory |
| [Working in this repo](/getting-started/working-in-this-repo) | Learn who may edit what, and the branch and commit rules |
| [Writing these docs](/getting-started/writing-these-docs) | Add or change a page of this site |
| [Glossary](/getting-started/glossary) | Look up REQ, D, Q, ADR and the product terms |

## Five things to know first

1. **Nothing is public before review.** Imported records are checked by a person before they go live, except organisation profiles that carry an explicit "human review pending" label.
2. **There are no public accounts.** Visitors never sign in. Only staff use the WordPress admin.
3. **Money never buys recognition.** Payment never affects nominations, judging, winners, profile ranking or any label. A sponsor can only pay for a labelled story placement.
4. **Every rule has an ID.** Requirements are REQ numbers, and the pages cite them as plain text. They are never renumbered.
5. **The prototype is not the product.** It shows behaviour with sample data. The WordPress build follows the plugin, theme and engineering pages.

## Where the current state is written down

| Question | Read |
|---|---|
| What is the platform and what are we building? | [What WHOISWHO is](/overview) and [Features](/features/) |
| What is still open? | [Open questions](/open-questions) |
| Where did the last session stop? | `STATUS.md` and `prototype/STATUS.md` in the repository |

## The two sessions

Two kinds of work run side by side. The wireframe session writes only in `prototype/` and owns the shared docs for now. The acquisition session writes only in `acquisition/`. They meet through one file, the import contract. See [Working in this repo](/getting-started/working-in-this-repo).
