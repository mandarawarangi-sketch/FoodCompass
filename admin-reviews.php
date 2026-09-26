<?php
declare(strict_types=1);

require __DIR__ . '/admin-auth.php';
require __DIR__ . '/db.php';
require __DIR__ . '/product-labels.php';

if (empty($_SESSION['review_token'])) {
    $_SESSION['review_token'] = bin2hex(random_bytes(32));
}

$query = $db->query(
    "SELECT s.id, s.name, s.description, s.price, s.submitted_at,
            s.allergen_status, s.allergens_json, s.vegetarian_claim, s.vegan_claim,
            s.ingredients_photo, s.allergen_photo, s.nutrition_photo,
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
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Product reviews | FoodCompass</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f7faf8;color:#26352d;font:15px/1.55 Inter,system-ui,Arial,sans-serif}
.wrap{max-width:980px;margin:0 auto;padding:30px 20px 60px}header{display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:26px}
h1{color:#183225;font-size:34px;line-height:1.2;margin:0}header p{margin:7px 0 0;color:#6d7b73}a{color:#2f7d4a}nav a{display:inline-block;padding:10px 14px;background:#eaf7ef;border-radius:9px;text-decoration:none;font-weight:700}
.card{background:#fff;border:1px solid #dce8df;border-radius:14px;padding:22px;margin:16px 0;box-shadow:0 5px 18px rgba(24,50,37,.04)}h2{font-size:22px;margin:0 0 10px;color:#183225}.meta{color:#6d7b73;margin:4px 0}.details{margin:16px 0}label{display:block;font-weight:700;margin:15px 0 6px}input{width:min(460px,100%);padding:11px;font:inherit;border:1px solid #dce8df;border-radius:9px}
.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}button{padding:11px 16px;font:inherit;font-weight:700;border-radius:9px;cursor:pointer}.approve{background:#2f7d4a;color:#fff;border:1px solid #2f7d4a}.reject{background:#fff1f1;color:#b3372b;border:1px solid #f1cccc}input:focus,button:focus-visible{outline:2px solid #2f7d4a;outline-offset:2px}
.empty{padding:26px;background:#fff;border:1px solid #dce8df;border-radius:14px;color:#6d7b73}
</style></head><body><main class="wrap">
<header><div><h1>Pending product submissions</h1><p>Review new and updated Product Owner information before publication.</p></div>
<nav><a href="admin-categories.php">Manage categories</a></nav></header>
<?php if (!$submissions): ?><p class="empty">There are no submissions waiting for review.</p><?php endif; ?>
<?php foreach ($submissions as $submission): ?>
<article class="card"><h2><?= showText($submission['name']) ?></h2>
<p class="meta">Submitted by <?= showText($submission['owner_name']) ?> · <?= showText($submission['submitted_at']) ?></p>
<div class="details"><p><strong>Category:</strong> <?= showText($submission['category_name'] ?? 'None') ?></p>
<p><strong>Description:</strong> <?= showText($submission['description']) ?></p>
<p><strong>Price:</strong> <?= showText($submission['price']) ?></p>
<p><strong>Declared allergens:</strong> <?= showText(allergenSummary($submission)) ?></p>
<p><strong>Vegetarian claim:</strong> <?= showText(claimSummary($submission['vegetarian_claim'])) ?></p>
<p><strong>Vegan claim:</strong> <?= showText(claimSummary($submission['vegan_claim'])) ?></p>
<strong>Label photos for review:</strong> <?= labelPhotoLinks($submission) ?></div>
<form method="post" action="admin-review-action.php">
<input type="hidden" name="submission_id" value="<?= (int) $submission['id'] ?>">
<input type="hidden" name="review_token" value="<?= showText($_SESSION['review_token']) ?>">
<label for="note-<?= (int) $submission['id'] ?>">Review note</label>
<input id="note-<?= (int) $submission['id'] ?>" type="text" name="review_note" maxlength="500" placeholder="Required when rejecting">
<div class="actions"><button class="approve" type="submit" name="decision" value="approved" onclick="return confirm('Approve this product submission?')">Approve</button>
<button class="reject" type="submit" name="decision" value="rejected">Reject</button></div></form></article>
<?php endforeach; ?></main></body></html>
