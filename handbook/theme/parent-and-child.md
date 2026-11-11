# Parent and child theme

The public site uses two themes. The parent theme `whoiswho` holds the reusable structure. The child theme `whoiswho-child` is the active theme and holds the brand values and site-specific overrides. This page explains which goes where and how to make a change safely.

## What each theme holds

| Theme | Path | Holds | Active? |
|---|---|---|---|
| Parent `whoiswho` | `site/wp-content/themes/whoiswho` | Templates, block patterns, components, motion, accessibility rules and the structure of the design tokens | No. A parent is never activated on its own. |
| Child `whoiswho-child` | `site/wp-content/themes/whoiswho-child` | The brand token values (colours, fonts, logo), site-specific template overrides, and anything that must survive a parent update | Yes |

## Rules

1. The child is the active theme.
2. Brand values are never edited in the parent. They live in the child's tokens file, which overrides the parent's defaults.
3. To change a template for this site, copy the template into the child and edit the copy. The parent stays reusable and can be updated without losing the change.
4. Tests run against the child as the active theme, so what visitors see is what is tested.
5. A release carries both themes, and the deploy checks that the child is active and the parent is present.

## Make a site-specific template change

1. Find the template in the parent theme.
2. Copy it to the same path in the child theme.
3. Edit the copy.
4. Build the assets and check the page at phone, tablet and desktop widths.
5. Ship through the normal release. Do not edit theme files in wp-admin. File editing is switched off on the live site.

## Templates the theme provides

| Area | Templates |
|---|---|
| Home and trust pages | Home, About, FAQ, complaints, data rights, legal pages |
| Directory | Lists, industries, profile pages and their states |
| Stories | Stories list and single story |
| Events | Events list and event page |
| Awards | Overview, rules, categories, nominees, nominee page, winners, Nominate |
| Forms and links | Claim, correct, remove, report, contact, Get listed, invitation, consent, unsubscribe |
| System | Search, not found, gone and server error pages |

Public templates read profiles and stories only through the plugin's public read models, never directly from the database. That is how a hidden or unreviewed profile cannot appear by accident.
