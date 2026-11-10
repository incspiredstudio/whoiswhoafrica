# Editable pages and copy

Staff edit page text in a page editor, not in code, while brand colours, fonts and the logo stay fixed in the theme.

## What it does

- Lets staff change the copy of About, FAQ, Awards rules, Nominate guidance, legal pages and the home page text.
- Provides a repeatable question-and-answer block, with groups, for the FAQ.
- Keeps every brand value in one place so the identity can change without rebuilding pages.

## Who uses it and how

- As an Editor, I can edit the About page copy and publish it.
- As an Editor, I can add, reorder and group FAQ questions without a developer.
- As an Administrator, I can change the wording of the pending-review label in one setting.
- As the brand owner, I can change a colour or font once and see it everywhere.

## The pieces

### Theme

- Page templates that render editable content (About, FAQ, rules, legal, Nominate guidance, home text) inside the design system.
- Block patterns for the repeating layouts: FAQ group with questions, the three pillars (Influence, Achievement, Legacy), the "ways in" list, the "built to be checked" list, figures, call to action.
- One tokens file holding colours, fonts, spacing, logo and the brand and edition names. A check fails the build if a brand value is hard-coded elsewhere (REQ-190).

### Plugin

- Configuration values for strings that Legal may need to change, such as the "Compiled automatically from public sources. Human review pending." label (REQ-216).
- Capability checks so only users with page-editing rights can change pages.

### Pages and screens

- Public: About (`/about`), FAQ (`/faq`), Awards rules (`/awards/rules`), Nominate guidance (below the form on `/nominate`), Privacy, Terms, Cookies, Complaints and takedown.
- Staff: the page editor in WordPress (Pages), the settings screen for configuration strings.

### Third-party plugins

The page editor is the native block editor with theme block patterns (D-060). No page builder is installed.

### Data

- Pages in the WordPress database. Tokens in the theme file, not in the database.

## Business rules

- Page copy is edited by staff in the page editor, not in code (REQ-260). The FAQ is a repeatable question-and-answer block with groups.
- Brand values (name, long-form name, edition name, legal trading name, colours, type, logo, tagline "Influence. Achievement. Legacy.") live only in the theme tokens file (REQ-052, 190).
- The tagline and mission copy are supplied by Brand; until then About and similar pages carry a "Draft copy for review" note.
- Awards rules, Terms, Privacy and Cookie policy are published (REQ-120); there is no public commercial terms page.
- Legal copy is supplied by Legal; pages say so until it is.
- The nominate guidance adds no data collection (REQ-250).
- Images on pages follow the image rules: open-licence or supplied images only, with licence and attribution stored and shown (REQ-203).

## Acceptance criteria

- [ ] A user with page-editing capability can edit and publish About, FAQ, rules and legal pages; a Reviewer without it cannot.
- [ ] A new FAQ question added in the editor appears on `/faq` under its group, and groups can be reordered.
- [ ] Changing a colour or font token changes it on every page without editing a template.
- [ ] A build check fails when a colour, font or brand name is hard-coded outside the tokens file.
- [ ] Changing the pending-review label setting changes the label on every pending profile page, card and share preview.
- [ ] Draft copy pages show the "Draft copy for review" note until it is removed in the editor.
- [ ] Editing a page does not change its URL, and a changed slug redirects the old URL.
- [ ] Edited pages keep revision history with author and time.

## Depends on

- Home and curation (home page text).
- Awards and nominations (rules and Nominate guidance).
- Privacy and data rights (legal pages).
- Roles, audit and admin (editing capability).

## Out of scope or later

- A visual page builder with free-form layouts outside the design system.
- Multiple languages (the site is English only).
- A live preview for each block.

## Requirement ids covered

REQ-052, 120, 190, 203, 216, 250, 260.
