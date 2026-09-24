<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';
$_SESSION['delete_token'] ??= bin2hex(random_bytes(32));

$statement = $db->prepare(
    'SELECT p.id AS product_id,
            s.name, s.description, s.price,
            s.review_status, s.review_note, s.submitted_at,
            c.name AS category_name
     FROM products p
     JOIN product_submissions s ON s.id = (
         SELECT MAX(s2.id)
         FROM product_submissions s2
         WHERE s2.product_id = p.id
     )
     JOIN categories c ON c.id = s.category_id
     WHERE p.owner_id = ? AND p.deleted_at IS NULL
     ORDER BY s.submitted_at DESC, s.id DESC'
);
$statement->execute([$ownerId]);
$products = $statement->fetchAll();

function showText(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<style>
    * { box-sizing: border-box; }

    body {
        max-width: 1200px;
        margin: 48px auto;
        padding: 0 20px;
        background: #f7fbf9;
        color: #17352b;
        font-family: Arial, sans-serif;
        line-height: 1.5;
    }

    h1 { margin-bottom: 8px; }
    a { color: #206743; }

    table {
        width: 100%;
        margin-top: 24px;
        border-collapse: collapse;
        background: white;
        border: 1px solid #d8e8df;
        border-radius: 12px;
        box-shadow: 0 8px 24px #17352b0a;
    }

    th, td {
        padding: 14px;
        border-bottom: 1px solid #d8e8df;
        text-align: left;
        vertical-align: top;
    }

    th {
        background: #edf7f0;
        font-size: 14px;
    }

    td form { margin-top: 8px; }

    button {
        padding: 7px 12px;
        border: 1px solid #c75353;
        border-radius: 8px;
        background: white;
        color: #9e2b2b;
        font: inherit;
        cursor: pointer;
    }

    button:hover { background: #fff0f0; }

    @media (max-width: 800px) {
        table { display: block; overflow-x: auto; }
    }
</style>
<head>
    <meta charset="utf-8">
    <title>My product submissions | FoodCompass</title>
</head>
<body>
    <h1>My product submissions</h1>
    <p><a href="submit-product.php">Submit another product</a></p>

    <?php if (!$products): ?>
        <p>You have not submitted any products yet.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Review status</th>
                    <th>Review note</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= showText($product['name']) ?></td>
                        <td><?= showText($product['category_name']) ?></td>
                        <td><?= showText($product['price']) ?></td>
                        <td><?= showText($product['review_status']) ?></td>
                        <td><?= showText($product['review_note'] ?? '—') ?></td>
                        <td><?= showText($product['submitted_at']) ?></td>
                        <td>
    <a href="edit-product.php?id=<?= (int) $product['product_id'] ?>">Edit</a>
    <a href="submission-history.php?id=<?= (int) $product['product_id'] ?>">History</a>

    <form method="post" action="delete-product.php"
          onsubmit="return confirm('Delete this product?');">
        <input type="hidden" name="product_id"
               value="<?= (int) $product['product_id'] ?>">
        <input type="hidden" name="delete_token"
               value="<?= showText($_SESSION['delete_token']) ?>">
        <button type="submit">Delete</button>
    </form>
</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>