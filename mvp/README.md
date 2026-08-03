# Marine Video Portal — PHP / MySQL edition

A self-hosted port of the MarineTeam private video portal (originally Next.js
+ Vercel + bunny.net + Upstash Redis) to plain PHP + MySQL, **keeping Auth0
for authentication and Resend for share-link emails**, as requested.

## What's the same as the original apps

- Invite-only: sign in with Auth0, but only **approved** viewers can watch.
- Admin panel: upload/manage videos, collections, custom ordering, homepage
  video-count cap, color palette picker.
- Search + pagination (10 per page) across a capped homepage set.
- Continue-watching strip with resume-from-last-position (including bunny.net
  videos, via a minimal player.js-protocol listener).
- Private per-recipient share links: expire (default 72h, cap 30 days),
  can be revoked, only play for the exact recipient email, optionally
  emailed via Resend, with a "viewed" timestamp and resend/copy in the admin
  Shares tab.
- Activity log of all admin actions.
- Analytics: total views, 30-day chart, total watch time, most-watched list.
- Idle auto sign-out (30 min default).
- PWA (installable, manifest + service worker for the static shell only).
- **bunny.net Stream** as a video source: browser uploads straight to bunny.net
  over resumable TUS (video bytes never touch this server), signed
  time-limited embed URLs for playback, signed CDN thumbnails, and
  encoding-status badges (Processing… / Ready / Failed) in the admin panel.

## What's different (technology swaps)

| Original | This port |
|---|---|
| Vercel/Next.js | Plain PHP 8.x, no framework |
| Upstash Redis | MySQL (`settings`, `share_links`, `watch_progress`, etc.) |
| Auth0 | **Kept** — implemented directly against Auth0's OIDC endpoints with plain cURL (no SDK/Composer needed), including full RS256 JWKS signature verification |
| Resend | **Kept** — called directly via Resend's HTTP API with cURL |
| bunny.net Stream | **Kept** — called directly via bunny's Stream API + TUS with cURL/fetch (no SDK). Local file upload and generic iframe embeds remain available too, as alternatives when bunny.net isn't configured. |

## Requirements

- PHP 8.1+ with `pdo_mysql`, `curl`, `openssl`, `gd` (icons only) extensions
- MySQL 5.7+ / MariaDB 10.3+
- An Auth0 tenant (free tier is fine)
- A Resend account + API key (optional — without it, admins just copy share
  links manually)
- A bunny.net Stream library (optional — without it, admins upload locally
  or paste embed URLs instead)
- Apache with `mod_rewrite`/`.htaccess` support, or equivalent Nginx rules

## Setup

1. **Database**: create a database, then either run `schema.sql` yourself:
   ```
   mysql -u youruser -p yourdb < schema.sql
   ```
   or upload the whole app and visit `install.php?confirm=yes` once (then
   **delete `install.php`**). If you're upgrading an existing install, just
   re-run `schema.sql` — it's all `CREATE TABLE IF NOT EXISTS` plus one
   `ADD COLUMN IF NOT EXISTS` migration for `bunny_video_id`, so it's safe to
   re-import.

2. **Auth0**: create a Regular Web Application.
   - Allowed Callback URL: `https://your-domain/auth0-callback.php`
   - Allowed Logout URL: `https://your-domain/`
   - Under Authentication → Database, **disable Sign Ups** (this app has no
     self-registration; you add approved viewers by email in the admin panel
     — the Auth0 side just needs Users created manually, or a passwordless/
     social connection where anyone can *authenticate* but only *approved*
     emails can actually see the library).

3. **bunny.net (optional)**: create a Stream library, enable **Embed View
   Token Authentication** on its Security tab, and note the Library ID, API
   key, and token-auth key. Note the CDN/pull-zone hostname too if you want
   thumbnails. Leave `BUNNY_LIBRARY_ID` blank in `config.php` to hide this
   option entirely and stick to local upload / embed URLs.

4. **Create your real config file** — `config.sample.php` is the template
   that's tracked in git; `config.php` (your real secrets) is gitignored on
   purpose so a `git pull` can never overwrite it:
   ```
   cp config.sample.php config.php
   ```
   Then edit `config.php` and fill in:
   - `DB_HOST` / `DB_NAME` / `DB_USER` / `DB_PASS`
   - `SITE_URL` (no trailing slash)
   - `AUTH0_DOMAIN` / `AUTH0_CLIENT_ID` / `AUTH0_CLIENT_SECRET`
   - `APP_SECRET` — generate with `php -r "echo bin2hex(random_bytes(32));"`
   - `ADMIN_EMAILS` — comma-separated; these are auto-promoted to admin (and
     auto-approved) on their first login
   - `RESEND_API_KEY` / `MAIL_FROM` (optional — leave the key blank to disable
     automatic emails)
   - `BUNNY_LIBRARY_ID` / `BUNNY_API_KEY` / `BUNNY_TOKEN_AUTH_KEY` /
     `BUNNY_CDN_HOSTNAME` (optional — leave `BUNNY_LIBRARY_ID` blank to disable)

5. Make `uploads/videos` and `uploads/thumbs` writable by the web server.

6. Visit the site, sign in with an `ADMIN_EMAILS` address, and you're an
   approved admin immediately. Add other viewers under **Admin → Viewers**.

## Deploying with git

If you deploy by `git pull`-ing on the server (common on shared hosts like
DreamHost), the important thing is that **`config.php` is never a tracked
file** — only `config.sample.php` (the template) is. That's what `.gitignore`
in this project does, and it's what makes future `git pull`s safe even as
the template gains new settings (like the `BUNNY_*` block above): git only
ever touches `config.sample.php`, and your real `config.php` sits untouched
alongside it.

If you're migrating an *existing* deployment where `config.php` was
previously tracked (and git pull now refuses to overwrite your live file
with local changes), fix it once with:
```bash
cp config.php ~/config.php.production.bak   # back up your real secrets first
git rm --cached config.php                  # stop tracking it (keeps the file on disk)
git checkout -- config.php 2>/dev/null || true
git pull                                    # now succeeds
# re-apply any real values that got reset, by diffing against your backup:
diff ~/config.php.production.bak config.php
```
After that one-time fix, `config.php` is untracked for good and this
conflict won't recur.

## Notes on security

- Video playback never uses a permanent public URL. Every play generates a
  fresh, time-limited token — `stream.php` checks its own token for local
  files, and bunny.net videos get a freshly signed embed URL
  (`bunny_embed_url()`) on every watch request. `uploads/videos/.htaccess`
  also blocks direct access to locally-stored raw files.
- bunny.net uploads never touch this server: the browser gets a short-lived
  signed TUS ticket (`bunny_sign_tus_upload()`) and uploads directly to
  bunny.net; the API key itself never leaves the server.
- Share links are gated on the recipient's authenticated Auth0 email —
  logging in as anyone else shows a generic "wrong email" message that never
  reveals the intended recipient's address.
- CSRF tokens are required on every admin POST (including the bunny.net AJAX
  endpoints); ID token signatures from Auth0 are verified against the
  tenant's live JWKS (RS256) rather than trusted blindly.
- `config.php` and `schema.sql` are blocked from direct HTTP access via
  `.htaccess`; keep `config.php` out of version control in real deployments.

## File map

```
config.sample.php       — config TEMPLATE (tracked in git)
config.php               — your real settings, copied from the template (gitignored, never committed)
schema.sql               — MySQL schema
install.php              — one-time schema installer (delete after use)
index.php                — homepage / library
watch.php, stream.php    — playback + token-gated streaming (local + bunny.net)
share.php                — private share-link watch page
progress.php             — AJAX resume/continue-watching endpoint
login.php, logout.php, auth0-callback.php  — Auth0 flow
includes/                — db.php, auth0.php, mail.php, bunny.php, functions.php, header/footer
admin/                   — videos, viewers, shares, settings, activity, analytics tabs
admin/bunny_create.php, admin/bunny_finalize.php — AJAX: create bunny video + TUS ticket, poll encoding status
assets/                  — CSS, JS (incl. bunny-upload.js TUS client, bunny-player.js resume listener), PWA icons
uploads/                 — video + thumbnail storage (videos/ is access-blocked)
```
