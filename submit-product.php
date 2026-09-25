<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';

if (!isset($_SESSION['form_token'])) {
    $_SESSION['form_token'] = bin2hex(random_bytes(32));
}

$categories = $db->query(
    'SELECT id, name FROM categories ORDER BY name'
)->fetchAll();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = filter_var($_POST['category_id'] ?? null, FILTER_VALIDATE_INT);
    $price = trim($_POST['price'] ?? '');
    $token = $_POST['form_token'] ?? '';

    if (!hash_equals($_SESSION['form_token'], $token)) {
        $message = 'Form expired. Refresh the page and try again.';
    } elseif (
        $name === '' ||
        strlen($name) > 150 ||
        $description === '' ||
        !$categoryId ||
        !preg_match('/^\d{1,8}(\.\d{1,2})?$/', $price)
    ) {
        $message = 'Complete every field and enter a valid price.';
    } else {
        $check = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $check->execute([$categoryId]);

        if (!$check->fetch()) {
            $message = 'Choose a valid category.';
        } else {
            try {
                $db->beginTransaction();

                $product = $db->prepare(
                    'INSERT INTO products (owner_id) VALUES (?)'
                );
                $product->execute([$ownerId]);
                $productId = (int) $db->lastInsertId();

                $submission = $db->prepare(
                    "INSERT INTO product_submissions
                     (product_id, name, description, category_id, price)
                     VALUES (?, ?, ?, ?, ?)"
                );
                $submission->execute([
                    $productId, $name, $description, $categoryId, $price
                ]);

                $db->commit();
                $_SESSION['form_token'] = bin2hex(random_bytes(32));
                $message = 'Product submitted. Review status: pending.';
            } catch (Throwable $error) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $message = 'Could not save the submission. Please try again.';
            }
        }
    }
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
    <title>Submit product | FoodCompass</title>
</head>
<body>
    <h1>Submit a product</h1>
    <p><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>

    <form method="post">
        <input type="hidden" name="form_token"
               value="<?= htmlspecialchars($_SESSION['form_token'], ENT_QUOTES, 'UTF-8') ?>">

        <p><label>Product name<br>
            <input name="name" maxlength="150" required>
        </label></p>

        <p><label>Description<br>
            <textarea name="description" required></textarea>
        </label></p>

        <p><label>Category<br>
            <select name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>">
                        <?= htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label></p>

        <p><label>Price<br>
            <input name="price" type="number" min="0" max="99999999.99"
                   step="0.01" required>
        </label></p>

        <button type="submit">Submit for review</button>
    </form>
</body>
</html>