<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';
require __DIR__ . '/db.php';

if (empty($_SESSION['review_token'])) {
    $_SESSION['review_token'] = bin2hex(random_bytes(32));
}

$query = $db->query(
    "SELECT s.id, s.name, s.description, s.price, s.submitted_at,
            c.name AS category_name, u.name AS owner_name
     FROM product_submissions AS s
     JOIN products AS p ON p.id = s.product_id
     JOIN users AS u ON u.id = p.owner_id
     LEFT JOIN categories AS c ON c.id = s.category_id
     WHERE s.review_status = 'pending'
       AND p.deleted_at IS NULL
       AND s.id = (
           SELECT MAX(newest.id)
           FROM product_submissions AS newest
           WHERE newest.product_id = s.product_id
       )
     ORDER BY s.submitted_at ASC"
);
$submissions = $query->fetchAll();

function showText($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Product reviews | FoodCompass</title>
</head>
<body>
    <h1>Pending product submissions</h1>

    <?php if (!$submissions): ?>
        <p>There are no submissions waiting for review.</p>
    <?php endif; ?>

    <?php foreach ($submissions as $submission): ?>
        <section>
            <h2><?= showText($submission['name']) ?></h2>
            <p>Submitted by: <?= showText($submission['owner_name']) ?></p>
            <p>Category: <?= showText($submission['category_name'] ?? 'None') ?></p>
            <p>Description: <?= showText($submission['description']) ?></p>
            <p>Price: <?= showText($submission['price']) ?></p>
            <p>Submitted: <?= showText($submission['submitted_at']) ?></p>

            <form method="post" action="admin-review-action.php">
                <input type="hidden" name="submission_id"
                       value="<?= (int) $submission['id'] ?>">
                <input type="hidden" name="review_token"
                       value="<?= showText($_SESSION['review_token']) ?>">

                <label>
                    Review note
                    <input type="text" name="review_note" maxlength="500">
                </label>

                <button type="submit" name="decision" value="approved"
        onclick="return confirm('Approve this product submission?')">Approve</button>
                <button type="submit" name="decision" value="rejected">
                    Reject
                </button>
            </form>
            <hr>
        </section>
    <?php endforeach; ?>
</body>
</html>