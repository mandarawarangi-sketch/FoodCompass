<?php
require_once __DIR__ . '/../config/db.php';
$retailer_id = require_retailer_login();
require_retail_post();
$id = (int) ($_GET['branch_id'] ?? 0);
$branch = branch_owned_by($conn, $id, $retailer_id);
if (!$branch) { http_response_code(404); exit('Branch not found.'); }
$error = ''; $ok = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['branch_name'] ?? ''));
    $area = trim((string) ($_POST['area'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $status = (string) ($_POST['status'] ?? '');
    if ($name === '' || $area === '' || strlen($name) > 120 || strlen($area) > 120 || strlen($address) > 255 || !in_array($status, ['active','suspended'], true)) {
        $error = 'Enter valid branch details.';
    } else {
        $stmt = $conn->prepare('UPDATE branches SET branch_name = ?, area = ?, address = ?, status = ? WHERE id = ? AND retailer_id = ?');
        $stmt->bind_param('ssssii', $name, $area, $address, $status, $id, $retailer_id);
        $stmt->execute();
        $branch = branch_owned_by($conn, $id, $retailer_id);
        $ok = 'Branch updated.';
    }
}
function out($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit branch | FoodCompass</title><link rel="stylesheet" href="assets/retailer.css"></head><body>
<nav><div class="logo">FoodCompass</div><a href="retailer_dashboard.php">← My branches</a></nav>
<main class="container"><section class="section"><h1>Edit branch</h1>
<?php if ($error): ?><p class="err"><?= out($error) ?></p><?php endif; ?>
<?php if ($ok): ?><p class="ok"><?= out($ok) ?></p><?php endif; ?>
<form class="inline" method="post"><input type="hidden" name="retail_token" value="<?= retail_form_token() ?>">
<div class="f"><label>Name<input name="branch_name" maxlength="120" value="<?= out($branch['branch_name']) ?>" required></label></div>
<div class="f"><label>Area<input name="area" maxlength="120" value="<?= out($branch['area']) ?>" required></label></div>
<div class="f"><label>Address<input name="address" maxlength="255" value="<?= out($branch['address']) ?>"></label></div>
<div class="f"><label>Status<select name="status"><option value="active" <?= $branch['status']==='active'?'selected':'' ?>>Active</option><option value="suspended" <?= $branch['status']==='suspended'?'selected':'' ?>>Suspended</option></select></label></div>
<button class="btn">Save branch</button></form>
<p class="muted" style="margin-top:14px">Suspending removes this branch from customer discovery. Its request history is kept.</p>
</section></main></body></html>
