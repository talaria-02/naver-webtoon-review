<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/header.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$errorMsg = '';

if ($DEMO_MODE) {
    $webtoon = null;
    foreach ($demoWebtoons as $w) {
        if ($w['id'] === $id) { $webtoon = $w; break; }
    }
    if (!$webtoon) {
        echo "<p class='error-msg'>존재하지 않는 웹툰입니다. (데모 모드: id 1~10만 존재)</p>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }
    $reviewsList = array_values(array_filter($demoReviews, fn($r) => $r['webtoon_id'] === $id));
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errorMsg = "데모 모드에서는 리뷰 작성이 저장되지 않습니다. DB 연결이 필요합니다.";
    }
} else {
    $stmt = $conn->prepare("
        SELECT
            w.*,
            COALESCE(rs.avg_rating, 0) AS avg_rating,
            COALESCE(rs.review_count, 0) AS review_count,
            COALESCE(ls.like_count, 0) AS like_count
        FROM webtoons w
        LEFT JOIN (
            SELECT webtoon_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
            FROM reviews
            GROUP BY webtoon_id
        ) rs ON rs.webtoon_id = w.id
        LEFT JOIN (
            SELECT r.webtoon_id, COUNT(rl.id) AS like_count
            FROM reviews r
            LEFT JOIN review_likes rl ON rl.review_id = r.id
            GROUP BY r.webtoon_id
        ) ls ON ls.webtoon_id = w.id
        WHERE w.id = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $webtoon = $stmt->get_result()->fetch_assoc();

    if (!$webtoon) {
        echo "<p class='error-msg'>존재하지 않는 웹툰입니다.</p>";
        require_once __DIR__ . '/includes/footer.php';
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($currentUserId === 0) {
            $errorMsg = "로그인 후 이용할 수 있습니다.";
        } elseif ($action === 'add_review') {
            $ratingRaw = $_POST['rating'] ?? '';
            $rating = is_numeric($ratingRaw) ? (float)$ratingRaw : -1;
            $content = trim($_POST['content'] ?? '');
            $isHalfStep = abs(($rating * 2) - round($rating * 2)) < 0.001;

            if ($content === '' || $rating < 0 || $rating > 5 || !$isHalfStep) {
                $errorMsg = "0점부터 5점까지 0.5점 단위로 별점과 리뷰 내용을 입력해주세요.";
            } else {
                $ins = $conn->prepare("
                    INSERT INTO reviews (webtoon_id, user_id, rating, content)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        rating = VALUES(rating),
                        content = VALUES(content),
                        updated_at = CURRENT_TIMESTAMP
                ");
                $ins->bind_param("iids", $id, $currentUserId, $rating, $content);
                $ins->execute();
                $ins->close();

                header("Location: /detail.php?id=$id");
                exit;
            }
        } elseif ($action === 'toggle_like') {
            $reviewId = (int)($_POST['review_id'] ?? 0);

            $check = $conn->prepare("SELECT id FROM review_likes WHERE user_id = ? AND review_id = ?");
            $check->bind_param("ii", $currentUserId, $reviewId);
            $check->execute();
            $alreadyLiked = $check->get_result()->fetch_assoc();
            $check->close();

            if ($alreadyLiked) {
                $del = $conn->prepare("DELETE FROM review_likes WHERE user_id = ? AND review_id = ?");
                $del->bind_param("ii", $currentUserId, $reviewId);
                $del->execute();
                $del->close();
            } else {
                $like = $conn->prepare("INSERT IGNORE INTO review_likes (user_id, review_id) VALUES (?, ?)");
                $like->bind_param("ii", $currentUserId, $reviewId);
                $like->execute();
                $like->close();
            }

            header("Location: /detail.php?id=$id");
            exit;
        }
    }

    $reviewStmt = $conn->prepare("
        SELECT
            r.*,
            u.nickname,
            COUNT(rl.id) AS like_count,
            MAX(CASE WHEN rl.user_id = ? THEN 1 ELSE 0 END) AS is_liked
        FROM reviews r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN review_likes rl ON r.id = rl.review_id
        WHERE r.webtoon_id = ?
        GROUP BY r.id
        ORDER BY r.created_at DESC
    ");
    $reviewStmt->bind_param("ii", $currentUserId, $id);
    $reviewStmt->execute();
    $reviewsList = $reviewStmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$reviewCountTop = count($reviewsList);
$totalLikeCount = isset($webtoon['like_count'])
    ? (int)$webtoon['like_count']
    : array_sum(array_map(fn($r) => (int)($r['like_count'] ?? 0), $reviewsList));
?>

<?php if ($DEMO_MODE): ?>
<div class="demo-banner">DB 미연결 상태 - 디자인 확인용 더미 데이터가 표시되고 있습니다. 리뷰 작성은 저장되지 않습니다.</div>
<?php endif; ?>

<div class="detail-top">
    <div class="detail-thumb-wrap">
        <img src="<?= h($webtoon['thumbnail_url']) ?>" alt="<?= h($webtoon['title']) ?>" class="detail-thumb" onerror="this.src='/images/placeholder.svg'">
    </div>
    <div class="detail-info">
        <p class="genre-badge"><?= h(day_code_to_label($webtoon['update_days'])) ?>요일 연재 · <?= h($webtoon['provider'] ?? 'NAVER') ?></p>
        <h1><?= h($webtoon['title']) ?></h1>
        <p class="author">작가 <?= h($webtoon['authors']) ?></p>

        <?php if (!empty($webtoon['source_url'])): ?>
            <p><a href="<?= h($webtoon['source_url']) ?>" target="_blank" rel="noopener">네이버 원본 보기</a></p>
        <?php endif; ?>

        <div class="rating-panel">
            <div class="rating-score">
                <span class="rating-big"><?= number_format((float)$webtoon['avg_rating'], 1) ?></span>
                <?= render_stars((float)$webtoon['avg_rating']) ?>
            </div>
            <div class="detail-stats">
                <span>리뷰 <?= (int)($webtoon['review_count'] ?? $reviewCountTop) ?></span>
                <span class="heart-count">♥ <?= $totalLikeCount ?></span>
            </div>
        </div>
    </div>
</div>

<section class="section">
    <h2>리뷰 작성</h2>
    <?php if ($errorMsg): ?>
        <p class="error-msg"><?= h($errorMsg) ?></p>
    <?php endif; ?>

    <?php if ($currentUserId > 0 || $DEMO_MODE): ?>
        <form action="/detail.php?id=<?= $id ?>" method="post" class="review-form">
            <input type="hidden" name="action" value="add_review">
            <div class="rating-input" aria-label="별점 선택">
                <?php for ($i = 0; $i <= 10; $i++):
                    $value = $i / 2;
                    $valueLabel = number_format($value, 1);
                    $valueAttr = rtrim(rtrim($valueLabel, '0'), '.');
                ?>
                    <label>
                        <input type="radio" name="rating" value="<?= h($valueAttr) ?>" required>
                        <span>
                            <?= render_stars($value) ?>
                            <em><?= h($valueLabel) ?></em>
                        </span>
                    </label>
                <?php endfor; ?>
            </div>
            <textarea name="content" placeholder="리뷰를 작성해주세요. 이미 작성한 리뷰가 있으면 수정됩니다." required></textarea>
            <button type="submit">리뷰 저장</button>
        </form>
    <?php else: ?>
        <p><button type="button" class="inline-login-button" data-login-open>로그인</button> 후 리뷰를 작성할 수 있습니다.</p>
    <?php endif; ?>
</section>

<section class="section">
    <h2>리뷰 (<?= $reviewCountTop ?>)</h2>
    <div class="review-list">
        <?php if ($reviewCountTop > 0): ?>
            <?php foreach ($reviewsList as $r): ?>
                <div class="review-item">
                    <div class="review-head">
                        <div>
                            <strong><?= h($r['nickname']) ?></strong>
                            <div class="review-rating">
                                <?= render_stars((float)$r['rating']) ?>
                                <span><?= number_format((float)$r['rating'], 1) ?></span>
                            </div>
                        </div>
                        <?php if (!$DEMO_MODE): ?>
                            <?php if ($currentUserId > 0): ?>
                                <form action="/detail.php?id=<?= $id ?>" method="post" class="like-form">
                                    <input type="hidden" name="action" value="toggle_like">
                                    <input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="heart-button <?= !empty($r['is_liked']) ? 'is-liked' : '' ?>" aria-label="리뷰 좋아요">
                                        <span><?= !empty($r['is_liked']) ? '♥' : '♡' ?></span>
                                        <?= (int)$r['like_count'] ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="heart-button" data-login-open aria-label="로그인 후 좋아요">
                                    <span>♡</span>
                                    <?= (int)$r['like_count'] ?>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <p><?= nl2br(h($r['content'])) ?></p>
                    <small><?= h($r['created_at']) ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="empty">아직 리뷰가 없습니다. 첫 리뷰를 남겨보세요!</p>
        <?php endif; ?>
    </div>
</section>

<?php
if (!$DEMO_MODE) {
    $stmt->close();
    $reviewStmt->close();
}
require_once __DIR__ . '/includes/footer.php';
?>
