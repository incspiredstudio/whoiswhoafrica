# Edit a profile

Editors and Administrators change a profile on the **Edit profile** screen. Every save needs a reason, keeps the previous version and writes an audit row.

## Open the screen

1. Open **Directory > Profiles** and find the profile (search by name, or use the filters).
2. Press **Edit fields** in its row.

## What is on the screen

From top to bottom:

| Part | What it is for |
|---|---|
| The fields | The facts of the profile, with plain names such as **Current title** or **Website**. Long texts (summary, mandate, Bio, Achievement, Influence, Legacy) are paragraph boxes with a character counter. See [Profile sections](/staff/profile-sections). |
| **Classification** | Where the profile sits in the directory: **Industry**, **Other industries**, **Organisation type** (organisations and institutions), **Country** and **City**. See [Classification](/staff/classification). People also have a **Professions** list here. |
| **Reason or request reference (required)** | Why you are changing it, for example "Owner asked for the new title" or a correction reference. |
| **This resolves the open item** | Shown when the profile has an open correction. Choose it to close the correction with this save. |
| **Photo** or **Logo** | The image, its open licence, credit and source. See [Photos and credits](/staff/photos-and-credits). |
| **Status and marks** | State, origin, source, import batch and whether it is claimed. |
| **Contact details**, **Enquiries**, **Public URL** | Private contact details and their public switches, the enquiry switch, and the profile's address. |
| **Where each fact comes from** | For each fact: its source, when it was retrieved, the confidence and the method. Read-only. A fact you edit shows the source "Staff edit" and the method "Entered by hand". |
| **Changes from the source since review** | Only shown when a later import found different values at the source. Those values are not public until a reviewer accepts them. |
| **Revision history** | Every change: when, who, how and why. |
| **Internal notes** | Staff-only notes, never public. |

Industry, sector, organisation type, head office city and a person's field are no longer typed in as text: they are chosen in **Classification**. The import still reads those values from a bundle and turns them into the classification.

## Save a change

1. Change the fields or the classification.
2. Fill in **Reason or request reference (required)**.
3. Press **Save changes**. The message "Saved. A version and an audit row were written." confirms it.

If the profile is published, the public page changes at once. A fact you edit keeps showing on the public page: the save records you as its source ("Staff edit", method manual, medium confidence).

If the profile is claimed and you are its assigned profile manager, your change waits for a Reviewer or Administrator. See [Claims](/staff/claims#the-assigned-user).

## Who can do it

| Work | Capability | Default roles |
|---|---|---|
| Edit fields | `edit_wiw_profiles` | Editor, Administrator |
| Change the classification | `edit_wiw_profiles` and `assign_terms` | Editor, Administrator |
| See where each fact comes from | `wiw_view_sources` | Reviewer, Editor, Administrator |
