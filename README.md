# Three products, as requested

This delivery contains **three separate PHP/MySQL applications**, all using
Auth0 for auth and bunny.net for video:

### `mvp/` — the flat video-portal engine
A single video library: approved viewers, admin panel, collections,
search, private share links, continue-watching, analytics. The simplest
of the three — built from Marine-Video-Portal-1 / MarineTeamVideos /
fable-video / fable-video2.

### `cms/` — the full Marine-team CMS engine
Nested categories → series → videos/files, a WordPress-style web installer,
a WordPress-style plugin architecture (12 real plugins: Favorites, Watch
Later, Comments, Ratings, View Counts, Social Share, Announcements,
Notifications/Web-Push, Subscriptions, Playlists, Likes/Dislikes, Related
Content), permission groups, restricted-viewing grants, sequential unlock,
scheduled publish/unpublish, audit log, RSS/podcast feeds, analytics.

### `full/` — the unified engine
The `cms/` engine as its base (it already covers everything `mvp/` does,
and more), with the one genuinely distinct feature from `mvp/` merged in:
private, time-limited share links — a different mechanism from the CMS's
permanent restricted-viewing grants, better suited to "share this one
thing with someone for a week" than a standing access grant is. See
`full/README.md` for exactly what changed and why.

Each has its own `README.md` with full setup instructions — start there.
They're independent: separate databases, separate `config.php`/
`config.sample.php`, separate installers. Run any one, any two, or all
three side by side on different subdomains/paths.
