# Photos and image credits

WHOISWHO only shows a photo or logo when we have the right to use it and can say who made it. In practice that means an **open licence**, a **credit line** and the **source address**, every time. When in doubt, leave the profile without a picture: a profile without a photo is fine, a photo we may not use is not.

## Allowed licences

| Licence | In the Licence list on Add profile (code in import bundles) | Credit needed? |
|---|---|---|
| Creative Commons Zero | CC0 1.0 (public domain dedication) (`CC0-1.0`) | Not required, but we still show the creator when known |
| Public domain | Public domain (Public Domain Mark 1.0) (`PDM-1.0`) | Not required, but we still show the source |
| Creative Commons Attribution 2.0, 2.5, 3.0, 4.0 | CC BY 2.0 to CC BY 4.0 (`CC-BY-2.0` to `CC-BY-4.0`) | Yes |
| Creative Commons Attribution-ShareAlike 2.0, 2.5, 3.0, 4.0 | CC BY-SA 2.0 to CC BY-SA 4.0 (`CC-BY-SA-2.0` to `CC-BY-SA-4.0`) | Yes |

**Anything else is refused**: "all rights reserved", NonCommercial (NC), NoDerivatives (ND), "free to use" without a named licence, photos copied from news sites or social media, and photos sent by email without a written licence.

Where to find openly licensed photos: Wikimedia Commons is the main source. Each file page there shows the licence, the author and the attribution text to copy.

## What to record for every image

| Field on Add profile | Example |
|---|---|
| Licence | CC BY-SA 4.0 (the site links it to the licence's page on creativecommons.org) |
| Credit (who made it, as the licence asks) | "A. Mensah", or the organisation that owns the logo |
| Image source URL | The page you took the image from |

Imported images carry the same details, plus the creator and the licence address, from the acquisition team.

The public profile shows the credit under the photo, for example "Photo: A. Mensah · CC BY-SA 4.0" ("Logo:" for a logo), with the credit linked to the source page and the licence linked to its page.

## Files

- JPEG, PNG or WebP only, up to 5 MB.
- A portrait photo for people, the official logo for organisations.
- Do not crop out a watermark or alter a logo.

## Add or change a photo by hand

**On a new profile:**

1. Upload the file in **Media > Add New Media File**, or choose an existing one.
2. Open **Directory > Add profile** for a new profile.
3. Under **Photo (optional)** or **Logo (optional)**, press **Choose from media library** and pick the image.
4. Fill in **Licence**, **Credit** and **Image source URL**. They are required when an image is set.
5. Press **Save profile**.

**On an existing profile (Edit profile, photo panel):** Editors and Administrators (anyone who may edit profiles).

1. Open **Directory > Profiles**, find the profile and press **Edit fields**.
2. In the **Photo** panel (**Logo** for an organisation), press **Choose from media library** and pick a JPEG, PNG or WebP.
3. Choose the **Licence**, and fill in **Creator or credit (as the licence asks)** and **Image source URL**. All three are required.
4. Fill in **Reason or request reference (required)**.
5. Press **Save photo** (or **Save logo**). The message "Saved. A version and an audit row were written." confirms it. To take the picture off the profile, give a reason and press **Remove photo** (or **Remove logo**); the file stays in the media library.

The change is written to the audit log as `profile.image_change`, and the previous picture is kept in the profile's history. A profile's assigned user can propose a new photo from **My profiles**, which a reviewer approves (see [Claims](/staff/claims)).

## Imported photos

Photos and logos that come with an [import](/staff/importing) are checked on the way in: the file must match its fingerprint, be a JPEG, PNG or WebP under 5 MB, and carry an allowed licence. An image with any other licence is refused with the code `IMAGE_NOT_ALLOWED` and the rest of the record still imports. Accepted images go into the media library with their licence, creator, credit and source saved, and undoing the import batch removes them again.

## If someone asks us to remove a photo

Remove it straight away: an Administrator opens **Media > Library**, finds the image and deletes it permanently. The profile then shows without a picture. Then handle the request as in [Requests and reports](/staff/requests-and-reports).
