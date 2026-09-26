<?php
declare(strict_types=1);

require __DIR__ . '/owner-auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/product-labels.php';

$productId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1) {
    http_response_code(404);
    exit('Product not found.');
}

$find = $db->prepare(
    'SELECT s.id, s.name, s.description, s.category_id, s.price,
            s.allergen_status, s.allergens_json, s.vegetarian_claim, s.vegan_claim,
            s.ingredients_photo, s.allergen_photo, s.nutrition_photo
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
        try {
            [$allergenStatus, $allergensJson, $vegetarianClaim, $veganClaim] = parseLabelDetails($_POST);
            $uploads = validateLabelUploads($_FILES);
        } catch (InvalidArgumentException $error) {
            $message = $error->getMessage();
        }
    }
    if ($message === '') {
        $validCategory = $db->prepare('SELECT id FROM categories WHERE id = ?');
        $validCategory->execute([$categoryId]);

        if (!$validCategory->fetch()) {
            $message = 'Choose a valid category.';
        } else {
            $created = [];
            try {
                $db->beginTransaction();
                // Lock the product and read its latest version again when saving.
                $lock = $db->prepare('SELECT id FROM products WHERE id = ? AND owner_id = ? AND deleted_at IS NULL FOR UPDATE');
                $lock->execute([$productId, $ownerId]);
                if (!$lock->fetch()) { $db->rollBack(); http_response_code(404); exit('Product not found.'); }
                $latest = $db->prepare('SELECT ingredients_photo, allergen_photo, nutrition_photo FROM product_submissions WHERE product_id = ? ORDER BY id DESC LIMIT 1');
                $latest->execute([$productId]);
                [$photos, $created] = saveLabelUploads($uploads, $latest->fetch() ?: []);
                $insert = $db->prepare(
                    'INSERT INTO product_submissions
                     (product_id, name, description, category_id, price, allergen_status, allergens_json, vegetarian_claim, vegan_claim, ingredients_photo, allergen_photo, nutrition_photo)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $insert->execute([$productId, $name, $description, $categoryId, $price,
                    $allergenStatus, $allergensJson, $vegetarianClaim, $veganClaim,
                    $photos['ingredients_photo'], $photos['allergen_photo'], $photos['nutrition_photo']]);
                $db->commit();
            } catch (Throwable $error) {
                if ($db->inTransaction()) $db->rollBack();
                foreach ($created as $path) @unlink($path);
                $message = 'Could not save the submission. Please try again.';
            }
            if ($message === '') {
            $_SESSION['edit_token'] = bin2hex(random_bytes(32));
            header('Location: my-submissions.php');
            exit;
            }
        }
    }

    $current = [
        'name' => $name,
        'description' => $description,
        'category_id' => $categoryId,
        'price' => $price,
        'allergen_status' => $_POST['allergen_status'] ?? 'unavailable',
        'allergens_json' => json_encode($_POST['allergens'] ?? []),
        'vegetarian_claim' => $_POST['vegetarian_claim'] ?? 'unknown',
        'vegan_claim' => $_POST['vegan_claim'] ?? 'unknown',
        'ingredients_photo' => $current['ingredients_photo'],
        'allergen_photo' => $current['allergen_photo'],
        'nutrition_photo' => $current['nutrition_photo'],
        'id' => $current['id'],
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
    fieldset { border: 1px solid #d8e8df; border-radius: 10px; margin: 20px 0; padding: 16px; }
    .choice { display: inline-flex; align-items: center; gap: 6px; margin: 6px 14px 6px 0; }
    .choice input { width: auto; margin: 0; }
    .choices { margin-top: 12px; }
    small { display: block; color: #536c5b; }

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

    <form method="post" enctype="multipart/form-data">
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

        <?php labelForm($current); ?>
        <button type="submit">Save and request review</button>
    </form>

    <p><a href="my-submissions.php">Back to my submissions</a></p>
</body>
</html>