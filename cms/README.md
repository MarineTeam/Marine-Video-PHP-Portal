# Marine Team CMS — PHP / MySQL edition

A full rebuild of the "Marine-team" content platform: nested categories →
series → videos/files, a WordPress-style installer, a WordPress-style plugin
architecture (every optional feature is a real, toggleable plugin), Auth0
authentication, and bunny.net for both video (Stream) and downloadable files
(Storage).

This is a **separate engine** from the flat video-library app built earlier
(in the sibling `mvp/` folder) — you now have both, as requested. Use this
one if you want the full categories/series/plugins CMS; use `mvp/` if you
just want the simpler single-library video portal.

## Setup (WordPress-style — no manual config editing)

1. Upload this whole folder to your server.
2. Create a MySQL database (empty — the installer creates all tables).
3. Make `config.sample.php` readable and `uploads/` writable by the web server.
4. Visit `https://your-domain/install.php` in a browser. It walks you through:
   - **Database connection** — writes `config.php` from `config.sample.php`
     once your DB credentials verify.
   - **Site setup** — site name, Auth0 (required), bunny.net Stream/Storage,
     Resend, and Web Push VAPID keys (all bunny/Resend/VAPID fields are
     optional — leave blank to disable and configure later by editing
     `config.php` directly).
5. **Delete `install.php`** once setup finishes — the installer page tells
   you this too, but it bears repeating: a working installer left reachable
   on a live server is a standing risk.
6. Sign in with one of your `admin_emails` addresses — you're an authorized
   ADMIN immediately. Everyone else who signs in gets a `users` row but
   stays unauthorized until you approve them under **Admin → Users**.

### Auth0 app settings
Regular Web Application. Allowed Callback URL: `https://your-domain/auth/callback.php`.
Allowed Logout URL: your site URL.

## How the content model works

```
Category (nestable, arbitrary depth)
  └─ Category
       └─ Series (tags, thumbnail, featured/pinned, sequential-unlock toggle)
            ├─ Video (local file, embed URL, or bunny.net Stream)
            └─ File (local upload ≤4.5MB, or bunny.net Storage for bigger ones)
```

Visibility of any series/video is resolved in this order (see
`includes/content.php`):
1. **Admins** always see everything.
2. Must be **published** and within its **publish_at/unpublish_at** window.
3. If any **restricted-viewing grant** exists on the item (Admin → a
   series/video's "Access" link), *only* grantees (by permission-group or by
   exact email) can view it — this **overrides member-only entirely**.
4. Otherwise, **member-only** content requires an authorized login; anything
   else is public.

**Sequential unlock**: if a series has "require watching in order" on, a
video is locked until the previous video in that series is marked completed
in `watch_progress` for that user.

## Permissions

Fixed capabilities (`includes/capabilities.php`): manage_categories,
manage_series, manage_videos, manage_files, publish_content,
moderate_comments, manage_users, manage_permissions, manage_plugins,
view_audit_log, view_analytics.

**Permission groups** bundle these capabilities and get assigned to a user
either site-wide, or scoped to one category (covers everything nested under
it) or one series. ADMIN is a separate, all-capabilities role that can't be
granted through a group — only another ADMIN can promote someone to ADMIN —
to close off a privilege-escalation path via a custom "grant manage_users"
group.

## Plugins — "everything is a plugin," WordPress-style

Every optional feature is a real plugin under `plugins/<slug>/plugin.php`,
registered purely through `add_action()`/`add_filter()` hooks defined in
`includes/hooks.php` — the core has zero plugin-specific code in it. Toggle
each one site-wide, or override it per-category, under **Admin → Plugins**:

| Plugin | What it does |
|---|---|
| Favorites | ★ button on series/videos, `/favorites.php` list |
| Watch Later | Queue button on videos, `/watch-later.php` list |
| Comments | Threaded comments, with moderator delete via `moderate_comments` |
| Related Content | Recommends series sharing a category or tag |
| Ratings | 1–5 star rating with running average |
| View Counts | Simple per-item view counter |
| Social Share | Copy-link + X/Facebook/email share buttons |
| Announcements | Dismissible site-wide banner, own admin page |
| Notifications | Real Web Push (VAPID + aes128gcm), no external library |
| Subscriptions | Subscribe to a series/category; notified on new videos |
| Playlists | User-created video playlists |
| Likes / Dislikes | Thumbs up/down with counts |

Both **Subscriptions** and **Notifications** hook into `content_published`,
fired by the core whenever a video finishes uploading (or finishes
bunny.net encoding) — so subscribers get pushed (if Notifications is active)
or emailed via Resend (fallback) automatically.

### ⚠️ One thing to actually test yourself: Web Push
`includes/webpush.php` implements real elliptic-curve message encryption
(RFC 8291) and VAPID signing (RFC 8292) using only PHP's built-in
`openssl_*`/`hash_hkdf()` functions — no Composer package. It's implemented
carefully to spec, but it's also the single most intricate piece of
cryptography in this whole project, and it could not be exercised against a
real browser push subscription in the sandbox this was built in (no real
push service is reachable from there). Test it against an actual
subscription before relying on it in production. If it misbehaves, the
well-established `minishlink/web-push` Composer package is a solid
drop-in fallback — everything else (the subscription storage, the
subscribe/unsubscribe UI, the trigger-on-publish logic) stays the same
either way.

## Feeds
- `feed.php` — RSS of recently-published series (public content only).
- `podcast.php?series=<slug>` — iTunes-compatible podcast feed with video
  enclosures, for one series at a time. Only available for fully public
  series (no member-only, no restricted-viewing grants) since podcast apps
  can't authenticate — enclosure URLs need to be permanently reachable.

## Security notes
- `config.php` is gitignored; only `config.sample.php` (the template) is
  tracked, so a `git pull` can never overwrite your real secrets.
- `config.php`/`schema.sql` are blocked from direct HTTP access via
  `.htaccess`.
- **`uploads/videos` is deliberately NOT access-blocked** the way the sister
  `mvp/` engine's uploads are — this is a considered tradeoff, not an
  oversight, explained in detail in `.htaccess`'s comments: filenames are
  random 128-bit hex strings never exposed to the client except via the
  token-gated `stream.php` URL, and leaving direct access open is what lets
  the podcast feed publish stable, permanent enclosure URLs. If you don't
  need podcast feeds, you can lock this down further using the same
  `Require all denied` pattern as `mvp/uploads/videos/.htaccess`.
- CSRF tokens are required on every state-changing POST, including plugin
  endpoints.
- Auth0 ID tokens are verified against the tenant's live JWKS (RS256), not
  trusted blindly.

## What I tested directly (not just wrote)
Installer (all 3 steps, including "already installed" detection), category →
series → video creation, video upload + token-gated streaming with Range
requests, homepage/category/series/video page rendering, the full plugin
toggle → button-appears → action → persists → list-page pipeline (using
Favorites as the example), permission groups including scoped grants (found
and fixed two real bugs in the process — see below), RSS + podcast feeds,
and sequential-unlock enforcement.

**Bugs found and fixed during testing:**
1. The "not authorized" page was returning HTTP 200 instead of 403.
2. `admin/videos.php` and `admin/files.php` checked the `manage_videos`/
   `manage_files` capability *before* knowing which series was being
   managed, so scoped permission-group grants (e.g. "this editor can manage
   videos, but only for series #1") were silently ignored no matter what.
   Fixed by extracting `series_id` before the capability check and passing
   it through as the permission scope.
