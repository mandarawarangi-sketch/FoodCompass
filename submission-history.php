<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}

$query = $db->prepare(
    'SELECT s.name, s.description, s.price, s.review_status,
            s.review_note, s.submitted_at, s.reviewed_at,
            c.name AS category_name
     FROM products AS p
     JOIN product_submissions AS s ON s.product_id = p.id
     LEFT JOIN categories AS c ON c.id = s.category_id
     WHERE p.id = ? AND p.owner_id = ? AND p.deleted_at IS NULL
     ORDER BY s.id DESC'
);
$query->execute([$productId, $ownerId]);
$submissions = $query->fetchAll();

if (!$submissions) {
    http_response_code(404);
    exit('Product not found.');
}

function showHistoryText(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<style>
    * { box-sizing: border-box; }

    body {
        max-width: 900px;
        margin: 48px auto;
        padding: 0 20px;
        background: #f7fbf9;
        color: #17352b;
        font-family: Arial, sans-serif;
        line-height: 1.5;
    }

    h1 { margin-bottom: 8px; }
    a { color: #206743; }

    section {
        margin: 24px 0;
        padding: 24px;
        background: white;
        border: 1px solid #d8e8df;
        border-radius: 12px;
        box-shadow: 0 8px 24px #17352b0a;
    }

    section h2 { margin-top: 0; }
    section p { margin: 10px 0; }
    section hr { display: none; }
</style>
<head>
    <meta charset="utf-8">
    <title>Submission history | FoodCompass</title>
</head>
<body>
    <h1>Submission history: <?= showHistoryText($submissions[0]['name']) ?></h1>
    <p><a href="my-submissions.php">Back to my submissions</a></p>

    <?php foreach ($submissions as $submission): ?>
        <section>
            <h2><?= showHistoryText($submission['name']) ?></h2>
            <p>Status: <?= showHistoryText(ucfirst($submission['review_status'])) ?></p>
            <p>Submitted: <?= showHistoryText($submission['submitted_at']) ?></p>
            <p>Category: <?= showHistoryText($submission['category_name'] ?? 'None') ?></p>
            <p>Description: <?= showHistoryText($submission['description']) ?></p>
            <p>Price: <?= showHistoryText($submission['price']) ?></p>
            <p>Review note: <?= showHistoryText($submission['review_note'] ?: '—') ?></p>
            <hr>
        </section>
    <?php endforeach; ?>
</body>
</html>