<!-- verify-after-round-9 -->

# Demo content

Demo content fills the site so you can see every page working before the real records arrive. You choose which parts to add, and you can remove each part again with one click. Everything the demo adds is marked as sample content, and the site's launch checks remind you to remove it before going live.

## The parts

| Part | What it adds | Needs |
|---|---|---|
| Profiles | About 50 real profiles from the acquisition team's research, with photos only where the licence is open and a credit line under each photo | Nothing |
| Stories | Sample stories of each type | Nothing |
| Comments | Sample comments on the stories, some approved and some waiting for moderation, so you can practise moderating | Stories |
| Events | Upcoming and past events | Nothing |
| Awards | Award categories, nominees and winners | Profiles |
| Home curation | Picks for the Home page blocks | Profiles and Stories |
| Pages | About, FAQ, the legal pages and the other site pages | Nothing |

When you tick a part that needs another, the other is ticked for you.

The demo profiles go through the normal import, so they arrive with their sources and their review states like any real record. Individuals arrive not public until someone approves them, exactly as in [Profile review](/staff/profile-review).

## Add demo content

1. Open **WHOISWHO > Setup** and scroll to **Demo content**.
2. Press **Import demo content**.
3. Tick the parts you want.
4. If the site already has real profiles or stories, a box asks you to type a confirmation word first, so nobody adds demo content to a live site by accident.
5. Press **Yes, import the demo content**.
6. A progress bar shows it working. **Keep the page open** until the bar is full.
7. If it stops, press **Try again**. It carries on from where it stopped and never adds anything twice.
8. Press **Visit the site** to look around.

The demo sets its own Home page as the site's front page. Removing the demo puts your previous setting back.

## Remove demo content

You can remove one part or all of it.

1. Open **WHOISWHO > Setup** and scroll to **Demo content**.
2. Press **Remove** next to a part, or **Remove demo content** for everything.
3. Confirm with **Yes, remove the demo content**.

Only items marked as sample content are deleted. Your own profiles, stories and pages are not touched, and the settings the demo changed are put back. If you remove a part that another part needs (for example Profiles while Awards is still there), the dependent part is removed too.

## Before going live

Remove every part of the demo. The [going-live steps](/start/going-live) check for it.

## If pictures are missing

If the server has no image library, the demo says "Pictures were skipped because this server has no image library (PHP GD)". Ask your host to switch on the PHP **GD** or **Imagick** extension (in cPanel: **Select PHP Version** > Extensions), then remove and add the demo again.
