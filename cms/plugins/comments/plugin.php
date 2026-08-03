<?php
function plugin_comments_register(): void
{
    add_action('series_below_content', 'plugin_comments_render', 20);
    add_action('video_below_content', 'plugin_comments_render_video', 20);
}

function plugin_comments_render(array $series, ?array $user): void
{
    plugin_comments_render_block('series', $series['id'], $user, $series['category_id']);
}
function plugin_comments_render_video(array $video, array $series, ?array $user): void
{
    plugin_comments_render_block('video', $video['id'], $user, $series['category_id'] ?? null);
}

function plugin_comments_render_block(string $type, int $id, ?array $user, ?int $categoryId): void
{
    $stmt = db()->prepare('SELECT c.*, u.email FROM comments c JOIN users u ON u.id = c.user_id WHERE content_type = ? AND content_id = ? ORDER BY c.created_at ASC');
    $stmt->execute([$type, $id]);
    $comments = $stmt->fetchAll();
    $canModerate = $user && user_can($user, 'moderate_comments');

    echo '<section><h2>Comments (' . count($comments) . ')</h2>';
    foreach ($comments as $c) {
        echo '<div class="comment"><div class="comment-meta">' . h($c['email']) . ' · ' . h(time_ago($c['created_at']));
        if ($canModerate || ($user && $user['id'] == $c['user_id'])) {
            echo ' · <form method="post" action="' . h(SITE_URL) . '/plugins/comments/delete.php" style="display:inline">' . csrf_field()
               . '<input type="hidden" name="id" value="' . (int)$c['id'] . '"><button class="link-btn danger" type="submit">Delete</button></form>';
        }
        echo '</div><p>' . nl2br(h($c['body'])) . '</p></div>';
    }
    if ($user && $user['authorized']) {
        echo '<form method="post" action="' . h(SITE_URL) . '/plugins/comments/post.php" class="card">' . csrf_field()
           . '<input type="hidden" name="content_type" value="' . h($type) . '"><input type="hidden" name="content_id" value="' . (int)$id . '">'
           . '<textarea name="body" rows="2" placeholder="Add a comment…" required></textarea><button class="btn small" type="submit">Post</button></form>';
    }
    echo '</section>';
}
