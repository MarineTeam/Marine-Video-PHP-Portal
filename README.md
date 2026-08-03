# Two engines, as requested

This delivery contains **two separate PHP/MySQL applications**, both using
Auth0 for auth and bunny.net for video:

### `mvp/` — the flat video-portal engine
A single video library: approved viewers, admin panel, collections,
search, private share links, continue-watching, analytics. This is the
simpler of the two — built from Marine-Video-Portal-1 / MarineTeamVideos /
fable-video / fable-video2.

### `cms/` — the full Marine-team CMS engine
Nested categories → series → videos/files, a WordPress-style web installer,
a WordPress-style plugin architecture (12 real plugins: Favorites, Watch
Later, Comments, Ratings, View Counts, Social Share, Announcements,
Notifications/Web-Push, Subscriptions, Playlists, Likes/Dislikes, Related
Content), permission groups, restricted-viewing grants, sequential unlock,
scheduled publish/unpublish, audit log, RSS/podcast feeds, analytics.

Each has its own `README.md` with full setup instructions — start there.
They're independent: separate databases, separate `config.php`/
`config.sample.php`, separate installers. You can run one, the other, or
both side by side on different subdomains/paths.
