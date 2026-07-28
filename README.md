# Full Portal - Ported from fable-video & fable-video2

Complete PHP port, no composer needed.

## Import Flow
1. Upload videos to Bunny.net Stream library (via Bunny dashboard)
2. /admin?tab=videos -> Sync from Bunny.net -> pulls all via API https://api.bunny.net/library/{id}/videos
3. Videos appear on homepage grid with thumbnails from CDN

## Features from originals ported
- Homepage grid, search, collection filter, pagination (HOMEPAGE_COUNT), continue watching from watch_progress
- Watch /watch/{guid} signed token URL (hash tokenKey+guid+expires), watermark email overlay resolved via WatermarkService layers
- Share /s/{token} private email-gated magic link, expiring, revocable, view_count, last_viewed, resend via MailManager (Resend default switchable)
- Bundle /b/{id} per recipient
- Admin Videos: sync import, edit title/order/watermark, delete (both DB and Bunny), share form per video with notify checkbox
- Private lists table private_lists, viewer_groups
- Viewers approve list
- Settings via .env
- Audit log, watch_progress

Upload this zip over your existing install - keep .env
