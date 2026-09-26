<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'customer') {
    header('Location: customer-account.php'); exit;
}
require __DIR__ . '/db.php';
require __DIR__ . '/product-labels.php';
$customerId = (int)$_SESSION['user_id'];
$_SESSION['preferences_token'] ??= bin2hex(random_bytes(32));
$conditionsAllowed = ['diabetes'=>'Diabetes', 'hypertension'=>'High blood pressure', 'other'=>'Other condition'];
function prefText(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function prefArray(mixed $json): array {
    $items = json_decode((string)$json, true);
    return is_array($items) ? $items : [];
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['preferences_token'], (string)($_POST['token'] ?? ''))) {
        http_response_code(403); exit('Invalid form. Refresh and try again.');
    }
    $allergens = $_POST['avoid_allergens'] ?? [];
    $diet = (string)($_POST['diet'] ?? '');
    $conditions = $_POST['health_conditions'] ?? [];
    if (!is_array($allergens) || !is_array($conditions)
        || count($allergens) > count(DECLARED_ALLERGENS) || count($conditions) > count($conditionsAllowed)) {
        $error = 'Choose valid preferences.';
    } else {
        $allergens = array_values(array_unique($allergens));
        $conditions = array_values(array_unique($conditions));
        if (array_diff($allergens, DECLARED_ALLERGENS)
            || array_diff($conditions, array_keys($conditionsAllowed))
            || !in_array($diet, ['none','vegetarian','vegan'], true)) {
            $error = 'Choose valid preferences.';
        } else {
            $stmt = $db->prepare('INSERT INTO customer_preferences
                (customer_id, avoided_allergens_json, dietary_preference, health_conditions_json) VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE avoided_allergens_json = VALUES(avoided_allergens_json),
                dietary_preference = VALUES(dietary_preference), health_conditions_json = VALUES(health_conditions_json)');
            $stmt->execute([$customerId, json_encode($allergens, JSON_THROW_ON_ERROR), $diet,
                json_encode($conditions, JSON_THROW_ON_ERROR)]);
            header('Location: preferences.php?saved=1'); exit;
        }
    }
}
$stmt = $db->prepare('SELECT avoided_allergens_json, dietary_preference, health_conditions_json FROM customer_preferences WHERE customer_id = ?');
$stmt->execute([$customerId]);
$preferences = $stmt->fetch() ?: ['avoided_allergens_json'=>'[]', 'dietary_preference'=>'none', 'health_conditions_json'=>'[]'];
$avoided = prefArray($preferences['avoided_allergens_json']);
$conditions = prefArray($preferences['health_conditions_json']);
$products = $db->query("SELECT p.id, s.id AS submission_id, s.name, s.description, s.price,
    s.allergen_status, s.allergens_json, s.vegetarian_claim, s.vegan_claim,
    s.ingredients_photo, s.allergen_photo, s.nutrition_photo
    FROM products p JOIN product_submissions s ON s.id =
    (SELECT MAX(v.id) FROM product_submissions v WHERE v.product_id = p.id AND v.review_status = 'approved')
    WHERE p.deleted_at IS NULL ORDER BY s.name")->fetchAll();
$matches = [];
$unverifiedCount = 0;
foreach ($products as $p) {
    if ($preferences['dietary_preference'] === 'vegan' && $p['vegan_claim'] !== 'yes') continue;
    if ($preferences['dietary_preference'] === 'vegetarian'
        && $p['vegetarian_claim'] !== 'yes' && $p['vegan_claim'] !== 'yes') continue;
    if ($avoided) {
        // Unknown declarations do not become matches; a declared overlap excludes the product.
        if ($p['allergen_status'] === 'unavailable') { $unverifiedCount++; continue; }
        if (array_intersect($avoided, declaredAllergens($p))) continue;
    }
    $matches[] = $p;
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>My food preferences | FoodCompass</title>
<style>*{box-sizing:border-box}body{margin:0;background:#f7faf8;color:#26352d;font:16px/1.5 system-ui,Arial,sans-serif}header{background:#fff;border-bottom:1px solid #dce8df;padding:18px max(20px,calc((100vw - 1060px)/2))}header a{color:#2f7d4a;font-weight:700;margin-right:20px}main{max-width:1060px;margin:35px auto;padding:0 20px}h1,h2,h3{color:#183225}.card{background:white;border:1px solid #dce8df;border-radius:16px;padding:22px;margin:18px 0;box-shadow:0 6px 20px #1832250a}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(265px,1fr));gap:14px}fieldset{border:1px solid #dce8df;border-radius:10px;margin:16px 0;padding:16px}legend{font-weight:700}label{display:block;margin:8px 0}select{display:block;margin:8px 0;padding:10px;border:1px solid #b9d1c0;border-radius:8px;font:inherit}input[type=checkbox]{margin-right:8px}button,.button{display:inline-block;background:#28754d;color:white;border:0;border-radius:8px;padding:10px 15px;font:inherit;text-decoration:none;cursor:pointer}.muted{color:#62756b}.error{color:#b3372b}.hint{background:#f0f7f2;border:1px solid #cde3d1;padding:13px;border-radius:10px}</style><link rel="stylesheet" href="assets/customer-nav.css"></head><body>
<header><a href="index.html">FoodCompass</a><a href="products.php">Approved products</a><a href="lists.php">My lists</a><a href="preferences.php" aria-current="page">Recommended</a><a href="customer-account.php">My account</a></header>
<main><h1>My food preferences</h1><p class="muted">Save preferences to see product suggestions based on the latest administrator-approved Product Owner declarations.</p>
<?php if ($error): ?><p class="error" role="alert"><?= prefText($error) ?></p><?php endif; ?>
<?php if (isset($_GET['saved'])): ?><p role="status">Preferences saved.</p><?php endif; ?>
<section class="card"><h2>Edit preferences</h2><form method="post"><input type="hidden" name="token" value="<?= prefText($_SESSION['preferences_token']) ?>">
<fieldset><legend>Allergens I want to avoid</legend><p class="muted">Select all that apply. These choices only compare declared allergens; they cannot guarantee a food is safe for an allergy.</p>
<div class="grid"><?php foreach (DECLARED_ALLERGENS as $a): ?><label><input type="checkbox" name="avoid_allergens[]" value="<?= prefText($a) ?>" <?= in_array($a,$avoided,true)?'checked':'' ?>><?= prefText($a) ?></label><?php endforeach; ?></div></fieldset>
<label>Dietary preference<select name="diet"><option value="none">No preference</option><option value="vegetarian" <?= $preferences['dietary_preference']==='vegetarian'?'selected':'' ?>>Vegetarian</option><option value="vegan" <?= $preferences['dietary_preference']==='vegan'?'selected':'' ?>>Vegan</option></select></label>
<fieldset><legend>Health conditions (optional)</legend><p class="muted">Saved to your account for future features. These choices do not determine product suitability in the current suggestions.</p>
<?php foreach ($conditionsAllowed as $code=>$label): ?><label><input type="checkbox" name="health_conditions[]" value="<?= prefText($code) ?>" <?= in_array($code,$conditions,true)?'checked':'' ?>><?= prefText($label) ?></label><?php endforeach; ?></fieldset>
<button>Save preferences</button></form></section>
<section class="card"><h2>Suggested products</h2><p class="hint">These are matches to the approved declarations above, not guarantees of allergen safety or medical suitability. Always check the current package label and seek personal medical advice for health conditions. Nutrition photos are review evidence, not numeric filtering data.</p>
<?php if (!$matches): ?><p>No products match the selected declarations. You can still browse all <a href="products.php">approved products</a> and inspect their labels.</p><?php endif; ?>
<?php if ($avoided && $unverifiedCount): ?><p class="muted"><?= $unverifiedCount ?> product(s) with unavailable allergen information were withheld from suggestions.</p><?php endif; ?>
<div class="grid"><?php foreach ($matches as $p): ?><article class="card"><h3><?= prefText($p['name']) ?></h3><p><?= prefText($p['description']) ?></p><p><b>Declared allergens:</b> <?= prefText(allergenSummary($p)) ?></p><p><b>Vegetarian:</b> <?= prefText(claimSummary($p['vegetarian_claim'])) ?> · <b>Vegan:</b> <?= prefText(claimSummary($p['vegan_claim'])) ?></p><p>Rs. <?= prefText($p['price']) ?></p><a class="button" href="products.php">View approved catalog</a></article><?php endforeach; ?></div>
</section></main></body></html>
