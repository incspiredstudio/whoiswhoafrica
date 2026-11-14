# Demo content

Demo content fills the site so you can see every page working before the real records arrive. You choose which parts to add, and you can remove each part again with one click. Everything the demo adds is marked as sample content, and the site's launch checks remind you to remove it before going live.

## The parts

| Part | What it adds | Needs |
|---|---|---|
| Profiles | About 50 sample profiles of real people and organisations from the acquisition team's research, with their sources, photos only where the licence is open and a credit line under each photo. If this copy of the plugin has no sample profiles, invented ones are added instead. | Nothing |
| Stories | Invented stories with cover pictures | Nothing |
| Comments | A few invented reader comments on each story, some approved and some waiting for moderation, so you can practise moderating | Stories |
| Events | Invented upcoming events | Nothing |
| Awards | Invented award categories, nominees and winners | Profiles |
| Home page curation | Chooses which profiles and stories the Home page shows | Profiles and Stories |
| Pages (About, FAQ, legal and so on) | The Home, About, FAQ, Awards and draft legal pages. The demo Home page becomes your front page. | Nothing |
| Settings | Switches on Nominations so the Nominate links show | Nothing |

When you tick a part that needs another, the other is ticked for you. If you untick a part that others need, those are unticked too.

The demo awards and Home page picks never name the real people in the sample profiles: they use a few invented stand-in profiles instead.

The sample profiles go through the normal import, so they arrive with their sources like any real record. The demo then publishes each one as reviewed, with you as the reviewer, and marks it as sample content.

## Add demo content

1. Open **WHOISWHO > Setup** and scroll to **Demo content (optional)**.
2. Under **Choose the parts to import**, tick the parts you want.
3. Press **Import demo content**. A box asks **Import the chosen parts?** and says what will be added.
4. On a live site that already has real profiles or stories, the box asks you to type `IMPORT DEMO` first, so nobody adds demo content to a live site by accident.
5. Press **Yes, import the demo content**.
6. A progress bar shows it working. **Keep the page open** until the bar is full.
7. If it stops, press **Try again**. It carries on from where it stopped and never adds anything twice.
8. Press **Visit the site** to look around.

The demo sets its own Home page as the site's front page. Removing the demo puts your previous setting back.

## Remove demo content

You can remove one part or all of it.

1. Open **WHOISWHO > Setup** and scroll to **Demo content (optional)**.
2. Press the **Remove** button next to a part (for example **Remove Stories**), or **Remove demo content** for everything.
3. Confirm with **Yes, remove it** (one part) or **Yes, remove the demo content** (everything).

Only items marked as sample content are deleted. Your own profiles, stories and pages are not touched, and the settings the demo changed are put back. A demo item that someone changed after the import is kept and listed, and from then on counts as your own content. If you remove a part that another part needs (for example Profiles while Awards is still there), the dependent part is removed too.

## Before going live

Remove every part of the demo. The [going-live steps](/start/going-live) check for it.

## If pictures are missing

If the server has no image library, the demo says "Pictures were skipped because this server has no image library (PHP GD)". Ask your host to switch on the PHP **GD** or **Imagick** extension (in cPanel: **Select PHP Version** > Extensions), then remove and add the demo again.
