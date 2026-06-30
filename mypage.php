<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /index.php?login=1");
    exit;
}

$userId = (int)$_SESSION['user_id'];

$userStmt = $conn->prepare("
    SELECT id, nickname, email, profile_image, created_at
    FROM users
    WHERE id = ?
");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT
        r.*,
        w.title AS webtoon_title,
        w.id AS webtoon_id,
        COUNT(rl.id) AS like_count
    FROM reviews r
    JOIN webtoons w ON r.webtoon_id = w.id
    LEFT JOIN review_likes rl ON r.id = rl.review_id
    WHERE r.user_id = ?
    GROUP BY r.id
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$myReviews = $stmt->get_result();
?>

<h1>마이페이지</h1>
<p>안녕하세요, <strong><?= h($user['nickname'] ?? $_SESSION['nickname']) ?></strong>님</p>

<?php if (!empty($user['profile_image'])): ?>
    <p><img src="<?= h($user['profile_image']) ?>" alt="프로필 이미지" style="width:72px;height:72px;border-radius:50%;object-fit:cover;"></p>
<?php endif; ?>

<?php if (!empty($user['email'])): ?>
    <p>이메일: <?= h($user['email']) ?></p>
<?php endif; ?>

<section class="section">
    <h2>내가 작성한 리뷰 (<?= $myReviews->num_rows ?>)</h2>
    <div class="review-list">
        <?php if ($myReviews->num_rows > 0): ?>
            <?php while ($r = $myReviews->fetch_assoc()): ?>
                <div class="review-item">
                    <a href="/detail.php?id=<?= (int)$r['webtoon_id'] ?>"><strong><?= h($r['webtoon_title']) ?></strong></a>
                    <span class="rating"><?= render_stars((float)$r['rating']) ?> <?= number_format((float)$r['rating'], 1) ?></span>
                    <p><?= nl2br(h($r['content'])) ?></p>
                    <small><?= h($r['created_at']) ?> · 좋아요 <?= (int)$r['like_count'] ?></small>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="empty">아직 작성한 리뷰가 없습니다.</p>
        <?php endif; ?>
    </div>
</section>

<?php
$stmt->close();
$userStmt->close();
require_once __DIR__ . '/includes/footer.php';
?>
