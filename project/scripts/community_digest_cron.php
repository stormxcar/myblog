<?php
// Usage:
// php scripts/community_digest_cron.php --frequency=daily
// php scripts/community_digest_cron.php --frequency=weekly

include __DIR__ . '/../components/connect.php';
include __DIR__ . '/../components/community_engine.php';
include __DIR__ . '/../components/feature_engine.php';

community_ensure_tables($conn);
blog_ensure_feature_tables($conn);

$frequency = 'daily';
foreach ($argv as $arg) {
    if (strpos((string)$arg, '--frequency=') === 0) {
        $frequency = trim((string)substr((string)$arg, strlen('--frequency=')));
    }
}
if (!in_array($frequency, ['daily', 'weekly'], true)) {
    $frequency = 'daily';
}

$intervalHours = $frequency === 'weekly' ? 24 * 7 : 24;

$usersStmt = $conn->prepare("SELECT user_id, COALESCE(last_sent_at, '1970-01-01 00:00:00') AS last_sent_at
    FROM notification_digest_preferences
    WHERE enabled = 1 AND frequency = ?");
$usersStmt->execute([$frequency]);
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($users)) {
    echo "No users to digest for frequency={$frequency}.\n";
    exit(0);
}

$updateSentStmt = $conn->prepare('UPDATE notification_digest_preferences SET last_sent_at = NOW(), updated_at = CURRENT_TIMESTAMP WHERE user_id = ?');

$reactionCountStmt = $conn->prepare("SELECT COUNT(*)
    FROM community_posts p
    INNER JOIN community_post_reactions r ON r.post_id = p.id
    WHERE p.user_id = ?
      AND r.created_at > ?");

$commentCountStmt = $conn->prepare("SELECT COUNT(*)
    FROM community_posts p
    INNER JOIN community_post_comments c ON c.post_id = p.id
    WHERE p.user_id = ?
      AND c.status = 'active'
      AND c.created_at > ?");

$sent = 0;
foreach ($users as $row) {
    $uid = (int)($row['user_id'] ?? 0);
    $lastSentAt = (string)($row['last_sent_at'] ?? '1970-01-01 00:00:00');
    if ($uid <= 0) {
        continue;
    }

    $hoursSinceLast = (time() - strtotime($lastSentAt)) / 3600;
    if ($hoursSinceLast < $intervalHours) {
        continue;
    }

    $reactionCountStmt->execute([$uid, $lastSentAt]);
    $reactionCount = (int)$reactionCountStmt->fetchColumn();

    $commentCountStmt->execute([$uid, $lastSentAt]);
    $commentCount = (int)$commentCountStmt->fetchColumn();

    if ($reactionCount <= 0 && $commentCount <= 0) {
        $updateSentStmt->execute([$uid]);
        continue;
    }

    $title = $frequency === 'weekly' ? 'Digest tuan cong dong' : 'Digest ngay cong dong';
    $message = 'Ban co ' . $reactionCount . ' luot react va ' . $commentCount . ' binh luan moi tren bai viet cong dong.';
    blog_push_notification($conn, $uid, 'community_digest', $title, $message, site_url('static/community_feed.php'));

    $updateSentStmt->execute([$uid]);
    $sent++;
}

echo "Digest complete. frequency={$frequency}, sent={$sent}\n";
