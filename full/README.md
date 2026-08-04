# Marine Team — unified engine (CMS + private share links)

This is the **third, merged product**: everything from the `cms/` engine
(nested categories → series → videos/files, WordPress-style installer,
WordPress-style plugins, permission groups, Auth0, bunny.net Stream +
Storage) **plus** the one genuinely distinct feature from the `mvp/`
engine that the CMS didn't otherwise have: **private, time-limited share
links**.

## Why this needed a real feature merge, not just a copy

The CMS engine already had a way to restrict content to specific people —
"restricted viewing grants" — but that mechanism assumes the recipient has
(or will create) a standing authorized account and stays scoped to a
specific series/video permanently until an admin removes it. The flat
engine's share links are a different shape entirely: a secret URL, good for
a bounded time (default 72h, cap 30 days), revocable, that only requires the
recipient to sign in with the matching email — well suited to "let me send
my in-laws this one sermon for a week" in a way a permanent access grant
isn't.

Both mechanisms now coexist here, doing different jobs:

| | Restricted viewing grants | Share links |
|---|---|---|
| Where | Admin → a series/video's "Access" link | Admin → a series/video's "Share" action |
| Who | By permission-group, or by exact email | By exact email only |
| Duration | Permanent until removed | Time-limited (1h–720h), auto-expires |
| Overrides member-only? | Yes, once any grant exists | N/A — bypasses publish/member-only entirely for that one recipient |
| Typical use | "This staff group can always see this category" | "Share this one video with my aunt for a week" |

## What's new in this engine specifically

- `share_links` table — generalized to share either a whole series (with a
  browsable video list) or a single video directly.
- `share.php` — the public view page: forces Auth0 login, checks the
  logged-in email against the intended recipient (generic mismatch message,
  never reveals who the link was actually for), stamps a `viewed_at`
  timestamp on first view, and renders either a series' video list or a
  single video's player depending on what was shared.
- **Admin → Shares** tab — review all active links, resend via Resend,
  revoke, or copy the raw URL.
- **"Share" action** on each row of Admin → Series and Admin → Videos —
  this is where links actually get created (recipient email, expiry hours,
  optional "email it" checkbox), right next to the existing "Access" action.

Everything else — the content model, permissions, all 12 plugins, the
installer, bunny.net integration, sequential unlock, feeds — is identical
to the `cms/` engine's own README, which still applies here in full.

## Setup

Same as the `cms/` engine: visit `install.php`, fill in DB then Auth0/
bunny.net/Resend/VAPID, delete `install.php` when done. See the shared
setup steps, Auth0 app configuration, and security notes in this folder's
inherited documentation (identical process — nothing share-link-specific
changes about installation).

## Tested directly (not just written)

Full install (DB step → site step → schema import), category → series →
video creation, share-link creation from the Series admin row, wrong-email
rejection (generic message), correct-email access with the `viewed_at`
timestamp getting stamped, drill-down from a shared series into one of its
videos (with the player actually rendering), a direct video-level share
link (not going through a series), and the Admin → Shares tab correctly
listing both link types together.
