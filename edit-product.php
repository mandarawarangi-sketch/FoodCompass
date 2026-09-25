<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}

$find = $db->prepare(
    'SELECT s.name, s.description, s.category_id, s.price
     FROM products p
     JOIN product_submissions s ON s.id = (
         SELECT MAX(s2.id)
         FROM product_submissions s2
         WHERE s2.product_id = p.id
     )
     WHERE p.id = ? AND p.owner_id = ? AND p.deleted_at IS NULL'
);
$find->execute([$productId, $ownerId]);
$current = $find->fetch();

if (!$current) {
    http_response_code(404);
    exit('Product not found.');
}

$categories = $db->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

if (!isset($_SESSION['edit_token'])) {
    $_SESSION['edit_token'] = bin2hex(random_bytes(32));
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $price = trim($_POST['price'] ?? '');
    $token = $_POST['edit_token'] ?? '';

    if (!hash_equals($_SESSION['edit_token'], $token)) {
        $message = 'Form expired. Refresh and try again.';
    } elseif (
        $name === '' ||
        strlen($name) > 150 ||
        $description === '' ||
        !$categoryId ||
        !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price)
    ) {
        $message = 'Complete every field and enter a valid price.';
    } else {
        $validCategory = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $validCategory->execute([$categoryId]);

        if (!$validCategory->fetch()) {
            $message = 'Choose a valid category.';
        } else {
            // Check ownership again when saving, not just when opening the form.
            $insert = $db->prepare(
                "INSERT INTO product_submissions
                 (product_id, name, description, category_id, price)
                 SELECT p.id, ?, ?, ?, ?
                 FROM products p
                 WHERE p.id = ? AND p.owner_id = ? AND p.deleted_at IS NULL"
            );
            $insert->execute([
                $name, $description, $categoryId, $price,
                $productId, $ownerId
            ]);

            if ($insert->rowCount() !== 1) {
                http_response_code(404);
                exit('Product not found.');
            }

            $_SESSION['edit_token'] = bin2hex(random_bytes(32));
            header('Location: my-submissions.php');
            exit;
        }
    }

    $current = [
        'name' => $name,
        'description' => $description,
        'category_id' => $categoryId,
        'price' => $price,
    ];
}

function fieldValue(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<style>
    * { box-sizing: border-box; }

    body {
        max-width: 720px;
        margin: 48px auto;
        padding: 0 20px;
        background: #f7fbf9;
        color: #17352b;
        font-family: Arial, sans-serif;
        line-height: 1.5;
    }

    h1 { margin-bottom: 8px; }

    form {
        margin-top: 28px;
        padding: 28px;
        background: white;
        border: 1px solid #d8e8df;
        border-radius: 16px;
        box-shadow: 0 8px 24px #17352b0a;
    }

    form p { margin: 0 0 20px; }

    label {
        display: block;
        font-weight: 600;
    }

    input, textarea, select {
        display: block;
        width: 100%;
        margin-top: 8px;
        padding: 11px 12px;
        border: 1px solid #bdcfc3;
        border-radius: 8px;
        background: white;
        font: inherit;
    }

    textarea {
        min-height: 120px;
        resize: vertical;
    }

    input:focus, textarea:focus, select:focus {
        outline: 2px solid #28754d;
        outline-offset: 1px;
    }

    button {
        padding: 12px 18px;
        border: 0;
        border-radius: 8px;
        background: #28754d;
        color: white;
        font: inherit;
        font-weight: 700;
        cursor: pointer;
    }

    button:hover { background: #1c5a3a; }
    a { color: #206743; }
</style>
<head>
    <meta charset="utf-8">
    <title>Edit product | FoodCompass</title>
</head>
<body>
    <h1>Edit product</h1>
    <p>Saving changes sends the new details for administrator review.</p>
    <p><?= fieldValue($message) ?></p>

    <form method="post">
        <input type="hidden" name="edit_token"
               value="<?= fieldValue($_SESSION['edit_token']) ?>">

        <p><label>Product name<br>
            <input name="name" maxlength="150" required
                   value="<?= fieldValue($current['name']) ?>">
        </label></p>

        <p><label>Description<br>
            <textarea name="description" required><?= fieldValue($current['description']) ?></textarea>
        </label></p>

        <p><label>Category<br>
            <select name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>"
                        <?= (int) $category['id'] === (int) $current['category_id']
                            ? 'selected' : '' ?>>
                        <?= fieldValue($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label></p>

        <p><label>Price<br>
            <input name="price" type="number" min="0" max="99999999.99"
                   step="0.01" required
                   value="<?= fieldValue($current['price']) ?>">
        </label></p>

        <button type="submit">Save and request review</button>
    </form>

    <p><a href="my-submissions.php">Back to my submissions</a></p>
</body>
</html>